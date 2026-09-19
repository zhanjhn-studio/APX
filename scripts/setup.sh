#!/usr/bin/env bash
# =====================================================================
# APX · 一行部署脚本（不依赖 Docker）
#
# 在「全新 Linux 服务器」上一条命令完成：
#   Nginx + PHP-FPM(8.x) + MariaDB/MySQL 安装 → 建库建表导入种子
#   → 写数据库配置 → 配置 Nginx 站点 → 创建后台管理员 → 设置目录权限
#
# 用法（在项目根目录执行）：
#   sudo bash scripts/setup.sh
#
# 免交互（CI / 自动化）：
#   WEB_DOMAIN=apx.example.com DB_NAME=apx DB_USER=apx DB_PASS='S3cret!' \
#   ADMIN_USER=admin ADMIN_PASS='admin123' sudo -E bash scripts/setup.sh
#
# 可选环境变量：
#   WEB_ROOT_MODE  public(默认,方式A) | root(方式B,根目录部署)
#   INSTALL_DIR    项目绝对路径（默认脚本所在目录的上一级）
#   PHP_VER        指定 PHP 大版本（如 8.1 / 8.2），未指定则取发行版默认
# 支持的系统：Debian / Ubuntu（apt）、CentOS / Rocky / Alma（dnf/yum）
# =====================================================================
set -uo pipefail

# ---------- 定位项目根 ----------
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
APX_DIR="${INSTALL_DIR:-$SCRIPT_DIR/..}"
APX_DIR="$(cd "$APX_DIR" && pwd)"
cd "$APX_DIR" || { echo "✗ 无法进入项目目录 $APX_DIR"; exit 1; }
[ -f database/schema.sql ] || { echo "✗ 未在项目根目录运行（找不到 database/schema.sql）"; exit 1; }

echo "=== APX 一行部署脚本 ==="
echo "• 项目目录: $APX_DIR"

# ---------- 权限检查 ----------
if [ "$(id -u)" -ne 0 ]; then
  echo "✗ 请使用 root 运行： sudo bash scripts/setup.sh"
  exit 1
fi

# ---------- 发行版探测 ----------
if [ -f /etc/os-release ]; then
  . /etc/os-release
  OS_ID="$ID"; OS_LIKE="${ID_LIKE:-}"
else
  echo "✗ 无法识别发行版"; exit 1
fi
echo "• 系统: $PRETTY_NAME"

PKG_UPDATE=""; PKG_INSTALL=""
if command -v apt-get >/dev/null 2>&1; then
  PKG_UPDATE="apt-get update -y"
  PKG_INSTALL="DEBIAN_FRONTEND=noninteractive apt-get install -y"
  FAMILY="debian"
elif command -v dnf >/dev/null 2>&1; then
  PKG_INSTALL="dnf install -y"; FAMILY="rhel"
elif command -v yum >/dev/null 2>&1; then
  PKG_INSTALL="yum install -y"; FAMILY="rhel"
else
  echo "✗ 不支持的包管理器，请手动安装 Nginx/PHP-FPM/MySQL 后改用 scripts/install.sh"; exit 1
fi

# ---------- 参数 ----------
WEB_DOMAIN="${WEB_DOMAIN:-_}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-apx}"
DB_USER="${DB_USER:-apx}"
DB_PASS="${DB_PASS:-$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 16)}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_PASS="${ADMIN_PASS:-$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 12)}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@apx.local}"
WEB_ROOT_MODE="${WEB_ROOT_MODE:-public}"

echo "• PHP 版本: ${PHP_VER:-发行版默认}"
echo "• Web 根模式: $WEB_ROOT_MODE"

# ---------- 1) 系统包 ----------
echo "• [1/7] 安装系统依赖（Nginx / PHP / MariaDB）..."
$PKG_UPDATE >/dev/null 2>&1 || true

PHP_BASE="php"
PHP_FPM="php-fpm"
if [ "$FAMILY" = "debian" ]; then
  # 优先用发行版自带 PHP；低于 8.0 时启用 sury 源
  $PKG_INSTALL nginx mariadb-server \
    php php-fpm php-mysql php-gd php-mbstring php-xml php-zip php-curl php-intl php-bcmath php-opcache >/dev/null 2>&1 || true
  PHP_VER_NOW="$(php -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)"
  if [ "${PHP_VER_NOW:-0}" -lt 80000 ]; then
    echo "   PHP < 8.0，启用 sury 源..."
    $PKG_INSTALL ca-certificates apt-transport-https lsb-release >/dev/null 2>&1 || true
    curl -fsSL https://packages.sury.org/php/README.txt -o /tmp/sury 2>/dev/null || true
    echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury.list
    curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury.gpg 2>/dev/null || true
    $PKG_UPDATE >/dev/null 2>&1 || true
    VER="${PHP_VER:-8.2}"
    $PKG_INSTALL "php$VER" "php$VER-fpm" "php$VER-mysql" "php$VER-gd" "php$VER-mbstring" \
      "php$VER-xml" "php$VER-zip" "php$VER-curl" "php$VER-intl" "php$VER-bcmath" "php$VER-opcache" >/dev/null 2>&1 || true
    PHP_BASE="php$VER"; PHP_FPM="php$VER-fpm"
  fi
elif [ "$FAMILY" = "rhel" ]; then
  $PKG_INSTALL epel-release >/dev/null 2>&1 || true
  VER="${PHP_VER:-8.2}"
  dnf module enable -y "php:$VER" >/dev/null 2>&1 || true
  $PKG_INSTALL nginx mariadb-server php php-fpm php-mysqlnd php-gd php-mbstring \
    php-xml php-zip php-curl php-intl php-bcmath php-opcache >/dev/null 2>&1 || true
  PHP_BASE="php"; PHP_FPM="php-fpm"
fi

# 定位 php / php-fpm 可执行文件
PHP_BIN="$(command -v "$PHP_BASE" || command -v php)"
[ -z "$PHP_BIN" ] && { echo "✗ PHP 安装失败"; exit 1; }
"$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' || { echo "✗ 需要 PHP 8.0+，当前 $("$PHP_BIN" -r 'echo PHP_VERSION;')"; exit 1; }
echo "   PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')"

# 启动 PHP-FPM（systemd 单元名随发行版不同，兼容探测）
PHP_UNIT="$PHP_FPM"
PV="$("$PHP_BIN" -r 'echo PHP_MAJOR . "." . PHP_MINOR;' 2>/dev/null)"
if systemctl list-unit-files 2>/dev/null | grep -q "php${PV}-fpm.service"; then
  PHP_UNIT="php${PV}-fpm"
fi
systemctl enable "$PHP_UNIT" 2>/dev/null || true
systemctl start "$PHP_UNIT" 2>/dev/null || systemctl start php-fpm 2>/dev/null || true
sleep 2

# ---------- 2) 启动数据库 ----------
echo "• [2/7] 启动数据库服务..."
if command -v mysqld_safe >/dev/null 2>&1 || [ -x /etc/init.d/mysql ]; then
  systemctl enable mariadb mysqld nginx 2>/dev/null || true
  systemctl start mariadb mysqld 2>/dev/null || systemctl start mysql 2>/dev/null || true
elif command -v mariadbd >/dev/null 2>&1; then
  systemctl enable mariadb 2>/dev/null || true; systemctl start mariadb 2>/dev/null || true
fi
sleep 3

MYSQL_BIN="$(command -v mysql || true)"
[ -z "$MYSQL_BIN" ] && { echo "✗ 未找到 mysql 客户端"; exit 1; }

# 探测 root 连接方式（新装 MariaDB 多为 unix_socket 免密）
ROOT_OK=0
if $MYSQL_BIN -u root -e "SELECT 1" >/dev/null 2>&1; then
  ROOT_OK=1; ROOT_PREFIX="$MYSQL_BIN -u root"
elif [ -n "${ROOT_DB_PASS:-}" ] && $MYSQL_BIN -u root -p"$ROOT_DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
  ROOT_OK=1; ROOT_PREFIX="$MYSQL_BIN -u root -p$ROOT_DB_PASS"
else
  # 尝试初始化 root 密码（MariaDB 首次安全初始化）
  echo "• 初始化数据库 root 密码..."
  ROOT_DB_PASS="${ROOT_DB_PASS:-$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 16)}"
  mysqladmin -u root password "$ROOT_DB_PASS" >/dev/null 2>&1 || true
  if $MYSQL_BIN -u root -p"$ROOT_DB_PASS" -e "SELECT 1" >/dev/null 2>&1; then
    ROOT_OK=1; ROOT_PREFIX="$MYSQL_BIN -u root -p$ROOT_DB_PASS"
  fi
fi
[ "$ROOT_OK" -eq 0 ] && { echo "✗ 无法以 root 连接数据库（请手动设置 root 密码后重试）"; exit 1; }

# ---------- 3) 建库 / 导入 ----------
echo "• [3/7] 创建数据库 $DB_NAME 并导入结构..."
$ROOT_PREFIX -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
  || { echo "✗ 创建数据库失败"; exit 1; }
$ROOT_PREFIX "$DB_NAME" < database/schema.sql || { echo "✗ schema.sql 导入失败"; exit 1; }
for f in seed.sql waf_rules.sql; do
  [ -f "database/$f" ] && { echo "   ↳ database/$f"; $ROOT_PREFIX "$DB_NAME" < "database/$f" || echo "     (告警：database/$f 导入失败，可忽略)"; }
done
$ROOT_PREFIX -e "CREATE USER IF NOT EXISTS '$DB_USER'@'127.0.0.1' IDENTIFIED BY '$DB_PASS';" 2>/dev/null \
  || $ROOT_PREFIX -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
$ROOT_PREFIX -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'127.0.0.1';" 2>/dev/null \
  || $ROOT_PREFIX -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
$ROOT_PREFIX -e "FLUSH PRIVILEGES;" || true

# ---------- 4) 写数据库配置 ----------
echo "• [4/7] 写入 config/database.php ..."
cat > config/database.php <<EOF
<?php
declare(strict_types=1);
return [
    'host'     => '$DB_HOST',
    'port'     => (int)'$DB_PORT',
    'dbname'   => '$DB_NAME',
    'username' => '$DB_USER',
    'password' => '$DB_PASS',
    'charset'  => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options'  => [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES  => false,
        PDO::ATTR_PERSISTENT        => false,
    ],
];
EOF
chmod 640 config/database.php

# ---------- 5) 创建管理员 ----------
echo "• [5/7] 创建后台管理员 ($ADMIN_USER) ..."
TMP_PHP="$(mktemp /tmp/apx_setup.XXXXXX.php)"
cat > "$TMP_PHP" <<PHP
<?php
\$pdo = new PDO("mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4", '$DB_USER', '$DB_PASS',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
\$hash = password_hash('$ADMIN_PASS', PASSWORD_BCRYPT);
\$pdo->prepare('INSERT INTO users (username,email,password_hash,nickname,status,role_level,created_at) VALUES (?,?,?,?,?,?,?)')
    ->execute(['$ADMIN_USER','$ADMIN_EMAIL',\$hash,'$ADMIN_USER','active',100, gmdate('Y-m-d H:i:s')]);
\$uid = \$pdo->lastInsertId();
\$rid = \$pdo->query("SELECT id FROM roles WHERE slug='super_admin' LIMIT 1")->fetchColumn();
if (\$rid) \$pdo->prepare('INSERT IGNORE INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([\$uid, \$rid]);
echo "管理员已创建\n";
PHP
"$PHP_BIN" "$TMP_PHP" || echo "  (管理员创建失败，可稍后用 /install.php 向导创建)"
rm -f "$TMP_PHP"

# ---------- 6) Nginx 站点 ----------
echo "• [6/7] 生成 Nginx 站点配置 ..."
NGINX_CONF="/etc/nginx/sites-available/apx.conf"
[ -d /etc/nginx/sites-enabled ] || NGINX_CONF="/etc/nginx/conf.d/apx.conf"
PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)"
[ -z "$PHP_SOCK" ] && PHP_SOCK="/run/php/php-fpm.sock"
WEB_ROOT="$APX_DIR"
ASSETS_DIR="$APX_DIR/public/assets"
if [ "$WEB_ROOT_MODE" = "public" ]; then
  WEB_ROOT="$APX_DIR/public"
  ASSETS_LINE=""
else
  ASSETS_DIR="$APX_DIR/public/assets"
  ASSETS_LINE="    location ^~ /assets/ { alias $ASSETS_DIR/; location ~* \\\.php\$ { return 403; } try_files \$uri =404; }"
fi

cat > "$NGINX_CONF" <<NGINX
server {
    listen 80;
    server_name $WEB_DOMAIN;

    root $WEB_ROOT;
    index index.php;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()" always;
    add_header Content-Security-Policy "default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none'" always;

    client_max_body_size 20m;
$ASSETS_LINE
    location ^~ /assets/uploads/ { location ~* \\\.php\$ { return 403; } try_files \$uri =404; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \\.php\$ {
        fastcgi_buffering off;
        fastcgi_read_timeout 300s;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ ^/(config|storage|database)(/|\$) { deny all; }
}
NGINX
if [ -d /etc/nginx/sites-enabled ] && [ "$NGINX_CONF" != "/etc/nginx/conf.d/apx.conf" ]; then
  ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/apx.conf
  rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
fi
nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || echo "  (nginx -t 未通过，请检查配置)"
systemctl enable nginx 2>/dev/null || true

# ---------- 7) 目录权限 ----------
echo "• [7/7] 设置 storage 目录权限 ..."
WEB_USER="www-data"
command -v nginx >/dev/null 2>&1 && WEB_USER="$(ps axo user,comm | awk '$2=="nginx"{print $1; exit}')"
[ "$WEB_USER" = "root" ] && WEB_USER="www-data"
mkdir -p storage/uploads storage/logs storage/cache
chown -R "$WEB_USER" storage 2>/dev/null || true
chmod -R 755 storage
chmod -R 775 storage/uploads storage/logs storage/cache

# ---------- 完成 ----------
echo ""
echo "✅ 部署完成！"
echo "   访问: http://$WEB_DOMAIN/"
echo "   后台: http://$WEB_DOMAIN/admin  (或 /admin.php)"
echo "   账号: $ADMIN_USER / $ADMIN_PASS （请尽快在后台修改）"
echo "   数据库用户 $DB_USER 密码已写入 config/database.php"
echo ""
echo "提示：已用查询串/实体入口路由，无需额外重写；如需 /login 干净地址，"
echo "      当前 Nginx 已配置 try_files，把 config/app.php 的 'pretty_urls' => true 即可。"
