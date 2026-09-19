#!/usr/bin/env bash
# =====================================================================
# APX · 一键引导部署脚本（只上传本文件即可）
#
# 用途：把「这一个文件」传到一台（全新或已有的）Linux 服务器，运行后自动：
#   1) 安装 Nginx + PHP-FPM(8.x) + MariaDB/MySQL（若缺失）
#   2) 从 GitHub 下载完整项目到安装目录
#   3) 生成 Nginx 站点并指向项目
#   4) 设置目录权限
#   5) 输出 install.php 安装向导链接（网页完成建库 / 建管理员）
#
# 用法（仅上传本文件后执行）：
#   sudo bash install.sh
#
# 指定你的仓库（二选一，推荐用环境变量，避免改动脚本）：
#   APX_REPO=https://github.com/你的组织/apx.git \
#   WEB_DOMAIN=apx.example.com \
#   sudo -E bash install.sh
#
# 可选环境变量：
#   APX_REPO       GitHub 仓库地址（git 或 ssh 均可），默认见下方占位符
#   APX_BRANCH     分支/标签，默认 main
#   APX_TARBALL    直接指定 tar.gz 下载地址（优先级高于 APX_REPO）
#   WEB_DOMAIN     Nginx server_name，默认 _
#   INSTALL_DIR    安装目录，默认 /var/www/apx
#   WEB_ROOT_MODE  public(默认,方式A,root 指向 public/) | root(方式B,根目录)
#   SKIP_DEPS=1    服务器已装好 LEMP 时跳过系统包安装
# 支持的系统：Debian / Ubuntu（apt）、CentOS / Rocky / Alma（dnf/yum）
# =====================================================================
set -uo pipefail

# ↓↓↓ 改成你的仓库地址（或用环境变量 APX_REPO=... 覆盖） ↓↓↓
APX_REPO="${APX_REPO:-https://github.com/zhanjhn-studio/APX.git}"
APX_BRANCH="${APX_BRANCH:-main}"
APX_TARBALL="${APX_TARBALL:-}"
WEB_DOMAIN="${WEB_DOMAIN:-_}"
INSTALL_DIR="${INSTALL_DIR:-/var/www/apx}"
WEB_ROOT_MODE="${WEB_ROOT_MODE:-public}"
SKIP_DEPS="${SKIP_DEPS:-0}"

# ---------- 权限检查 ----------
if [ "$(id -u)" -ne 0 ]; then
  echo "✗ 请使用 root 运行： sudo bash install.sh"; exit 1
fi

echo "=== APX 一键引导部署 ==="
echo "• 安装目录: $INSTALL_DIR"
echo "• Web 根模式: $WEB_ROOT_MODE"

# 占位符未改时提示
case "$APX_REPO" in
  *YOUR-ORG*|*YOUR-REPO*)
    echo "✗ 请先设置你的 GitHub 仓库地址："
    echo "    APX_REPO=https://github.com/你的组织/apx.git sudo -E bash install.sh"
    echo "  或编辑本脚本顶部的 APX_REPO 变量。"
    exit 1 ;;
esac

# ---------- 发行版探测 ----------
if [ -f /etc/os-release ]; then . /etc/os-release; OS_ID="$ID"; else echo "✗ 无法识别发行版"; exit 1; fi
echo "• 系统: ${PRETTY_NAME:-unknown}"

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
  echo "✗ 不支持的包管理器，请手动安装 LEMP 后加 SKIP_DEPS=1 重试"; exit 1
fi

# ---------- 1) 系统依赖 ----------
install_lemp() {
  echo "• [1/5] 安装 Nginx + PHP-FPM + MariaDB ..."
  $PKG_UPDATE >/dev/null 2>&1 || true
  if [ "$FAMILY" = "debian" ]; then
    $PKG_INSTALL nginx mariadb-server \
      php php-fpm php-mysql php-gd php-mbstring php-xml php-zip php-curl php-intl php-bcmath php-opcache >/dev/null 2>&1 || true
    if [ "$(php -r 'echo PHP_VERSION_ID;' 2>/dev/null || echo 0)" -lt 80000 ]; then
      echo "   PHP < 8.0，启用 sury 源..."
      $PKG_INSTALL ca-certificates apt-transport-https lsb-release >/dev/null 2>&1 || true
      echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/sury.list
      curl -fsSL https://packages.sury.org/php/apt.gpg | gpg --dearmor -o /etc/apt/trusted.gpg.d/sury.gpg 2>/dev/null || true
      $PKG_UPDATE >/dev/null 2>&1 || true
      VER="8.2"
      $PKG_INSTALL "php$VER" "php$VER-fpm" "php$VER-mysql" "php$VER-gd" "php$VER-mbstring" \
        "php$VER-xml" "php$VER-zip" "php$VER-curl" "php$VER-intl" "php$VER-bcmath" "php$VER-opcache" >/dev/null 2>&1 || true
    fi
  else
    $PKG_INSTALL epel-release >/dev/null 2>&1 || true
    dnf module enable -y "php:8.2" >/dev/null 2>&1 || true
    $PKG_INSTALL nginx mariadb-server php php-fpm php-mysqlnd php-gd php-mbstring \
      php-xml php-zip php-curl php-intl php-bcmath php-opcache >/dev/null 2>&1 || true
  fi

  PHP_BIN="$(command -v php || true)"
  [ -z "$PHP_BIN" ] && { echo "✗ PHP 安装失败"; exit 1; }
  "$PHP_BIN" -r 'exit(PHP_VERSION_ID >= 80000 ? 0 : 1);' || { echo "✗ 需要 PHP 8.0+"; exit 1; }
  echo "   PHP: $("$PHP_BIN" -r 'echo PHP_VERSION;')"

  # 启动 PHP-FPM（systemd 单元名随发行版不同）
  PHP_FPM="php-fpm"; PV="$("$PHP_BIN" -r 'echo PHP_MAJOR . "." . PHP_MINOR;' 2>/dev/null)"
  if systemctl list-unit-files 2>/dev/null | grep -q "php${PV}-fpm.service"; then PHP_FPM="php${PV}-fpm"; fi
  systemctl enable "$PHP_FPM" 2>/dev/null || true
  systemctl start "$PHP_FPM" 2>/dev/null || systemctl start php-fpm 2>/dev/null || true

  # 启动数据库
  systemctl enable mariadb mysqld nginx 2>/dev/null || true
  systemctl start mariadb mysqld 2>/dev/null || systemctl start mysql 2>/dev/null || true
  sleep 3
  MYSQL_BIN="$(command -v mysql || true)"
  if [ -n "$MYSQL_BIN" ] && ! $MYSQL_BIN -u root -e "SELECT 1" >/dev/null 2>&1; then
    echo "• 初始化数据库 root 密码..."
    RP="${ROOT_DB_PASS:-$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 16)}"
    mysqladmin -u root password "$RP" >/dev/null 2>&1 || true
  fi
}

if [ "$SKIP_DEPS" != "1" ]; then
  install_lemp
else
  echo "• 跳过系统依赖安装（SKIP_DEPS=1）"
  PHP_BIN="$(command -v php || true)"
  [ -z "$PHP_BIN" ] && { echo "✗ 未找到 php，请先安装 LEMP"; exit 1; }
fi

# ---------- 2) 从 GitHub 下载项目 ----------
echo "• [2/5] 从 GitHub 下载 APX 项目 ..."
TMPD="$(mktemp -d)"
repo_to_tarball() {
  local r="$1"; r="${r%.git}"
  r="${r#https://github.com/}"; r="${r#http://github.com/}"; r="${r#git@github.com:}"
  echo "https://codeload.github.com/${r}/tarball/${APX_BRANCH}"
}
download_tar() {
  local url="$1"; local out="$TMPD/apx.tar.gz"
  echo "   下载: $url"
  if command -v curl >/dev/null 2>&1; then curl -fSL "$url" -o "$out"
  elif command -v wget >/dev/null 2>&1; then wget -O "$out" "$url"
  else echo "✗ 需要 curl 或 wget"; exit 1; fi
  tar -xzf "$out" -C "$TMPD" || { echo "✗ 解包失败"; exit 1; }
  # GitHub tarball 顶层为 owner-repo-branch，移动到安装目录
  local src; src="$(find "$TMPD" -maxdepth 1 -type d -name '*-*-*' | head -1)"
  [ -z "$src" ] && src="$(find "$TMPD" -maxdepth 1 -type d ! -name "$TMPD" | head -1)"
  mkdir -p "$INSTALL_DIR"
  cp -a "$src/." "$INSTALL_DIR/"
}

if [ -n "$APX_TARBALL" ]; then
  download_tar "$APX_TARBALL"
elif command -v git >/dev/null 2>&1 && [ "$SKIP_GIT" != "1" ]; then
  echo "   git clone $APX_REPO ($APX_BRANCH)"
  mkdir -p "$INSTALL_DIR"
  rm -rf "$INSTALL_DIR/.git"
  git clone --depth 1 -b "$APX_BRANCH" "$APX_REPO" "$TMPD/repo" || { echo "✗ git clone 失败，尝试 tarball 方式"; download_tar "$(repo_to_tarball "$APX_REPO")"; }
  cp -a "$TMPD/repo/." "$INSTALL_DIR/" 2>/dev/null || true
  if [ ! -f "$INSTALL_DIR/install.php" ]; then
    echo "• git clone 未取到文件，改用 tarball 方式"
    rm -rf "$INSTALL_DIR"/* 2>/dev/null || true
    download_tar "$(repo_to_tarball "$APX_REPO")"
  fi
else
  download_tar "$(repo_to_tarball "$APX_REPO")"
fi

[ -f "$INSTALL_DIR/install.php" ] || { echo "✗ 下载后未找到 install.php，仓库地址/分支是否正确？"; exit 1; }
echo "   项目已就位: $INSTALL_DIR"

# ---------- 3) Nginx 站点 ----------
echo "• [3/5] 生成 Nginx 站点配置 ..."
NGINX_CONF="/etc/nginx/sites-available/apx.conf"
[ -d /etc/nginx/sites-enabled ] || NGINX_CONF="/etc/nginx/conf.d/apx.conf"
PHP_SOCK="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -1)"
[ -z "$PHP_SOCK" ] && PHP_SOCK="/run/php/php-fpm.sock"

WEB_ROOT="$INSTALL_DIR"
ASSETS_BLOCK=""
if [ "$WEB_ROOT_MODE" = "root" ]; then
  WEB_ROOT="$INSTALL_DIR"
  ASSETS_BLOCK="    location ^~ /assets/ { alias $INSTALL_DIR/public/assets/; location ~* \\\.php\$ { return 403; } try_files \$uri =404; }"
else
  WEB_ROOT="$INSTALL_DIR/public"
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
$ASSETS_BLOCK
    location ^~ /assets/uploads/ { location ~* \\\.php\$ { return 403; } try_files \$uri =404; }

    location / { try_files \$uri \$uri/ /index.php?\$query_string; }

    location ~ \\.php\$ {
        fastcgi_buffering off;
        fastcgi_read_timeout 300s;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_pass unix:$PHP_SOCK;
    }

    location ~ /\. { deny all; }
    location ~ ^/(config|storage|database)(/|\$) { deny all; }
}
NGINX
if [ -d /etc/nginx/sites-enabled ] && [ "$NGINX_CONF" != "/etc/nginx/conf.d/apx.conf" ]; then
  ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/apx.conf
  rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
fi
nginx -t >/dev/null 2>&1 && systemctl reload nginx 2>/dev/null || { echo "  (nginx -t 未通过，请检查配置)"; }
systemctl enable nginx 2>/dev/null || true

# ---------- 4) 目录权限 ----------
echo "• [4/5] 设置目录权限（www-data 可读写，供安装向导写配置）..."
WEB_USER="www-data"
command -v nginx >/dev/null 2>&1 && WEB_USER="$(ps axo user,comm | awk '$2=="nginx"{print $1; exit}')"
[ "$WEB_USER" = "root" ] && WEB_USER="www-data"
chown -R "$WEB_USER":"$WEB_USER" "$INSTALL_DIR" 2>/dev/null || true
mkdir -p "$INSTALL_DIR/config" "$INSTALL_DIR/storage/uploads" "$INSTALL_DIR/storage/logs" "$INSTALL_DIR/storage/cache"
chmod -R 755 "$INSTALL_DIR/storage"
chmod -R 777 "$INSTALL_DIR/config" "$INSTALL_DIR/storage/uploads" "$INSTALL_DIR/storage/logs" "$INSTALL_DIR/storage/cache" 2>/dev/null || true

# ---------- 5) 完成 ----------
echo ""
echo "✅ 环境已就绪，项目已下载到 $INSTALL_DIR"
echo ""
echo "👉 请打开浏览器访问安装向导，完成数据库与管理员配置："
echo "   http://${WEB_DOMAIN}/install.php"
echo ""
echo "   安装完成后请删除 install.php（项目根与 public/ 下各一份）以提升安全性。"
echo "   若改用干净地址（/login），可在 Nginx 加 try_files 并开启 config/app.php 的 pretty_urls。"
