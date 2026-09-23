<?php
declare(strict_types=1);
/**
 * APX 安装向导（仿 Discuz 一键安装）。
 * 流程：环境检测 → 填写数据库与管理员信息 → 自动建库、建表、导入种子与 WAF 规则、
 *       创建超级管理员、可选演示数据 → 写入配置并锁定 → 跳转登录。
 * 完成后请删除本文件以及项目根的 install.php。
 */
define('APX_INSTALLING', true);
require_once __DIR__ . '/../app/bootstrap.php';

// ---------- 语言 ----------
$LANGS = ['zh-CN' => '简体中文', 'zh-TW' => '繁體中文', 'en' => 'English'];
$lang = $_POST['install_lang'] ?? $_GET['lang'] ?? 'zh-CN';
if (!is_string($lang) || !array_key_exists($lang, $LANGS)) {
    $lang = 'zh-CN';
}

$TXT = [
    'zh-CN' => [
        'html_lang' => 'zh-CN',
        'title' => 'APX 安装向导',
        'subtitle' => '环境检测通过后，填写数据库与管理员信息，一键完成建库、建表与初始账号。',
        'step1' => '环境检测',
        'step2' => '数据库 / 账号',
        'step3' => '完成',
        'env_title' => '环境检测',
        'env_php_version' => 'PHP 版本 >= 8.1',
        'env_php_current' => '当前 %s',
        'env_ext' => 'PHP 扩展 %s',
        'env_writable' => '目录可写 %s',
        'load_ok' => '已加载',
        'load_missing' => '缺失',
        'write_ok' => '可写',
        'write_bad' => '不可写（请检查权限）',
        'label_db_host' => '数据库主机',
        'label_port' => '端口',
        'label_db_name' => '数据库名',
        'label_db_user' => '数据库用户名',
        'label_db_pass' => '数据库密码',
        'label_site_name' => '站点名称',
        'label_admin_user' => '管理员用户名',
        'label_admin_email' => '管理员邮箱',
        'label_admin_pass' => '管理员密码',
        'hint_admin_pass' => '密码至少 8 位，将使用 Argon2id / bcrypt 加密。',
        'label_demo' => '导入演示数据（几个示例账号与动态，便于立即体验）',
        'btn_install' => '开始安装',
        'err_required' => '请填写所有必填项。',
        'err_dbname' => '数据库名仅允许字母数字下划线（1-64 位）。',
        'err_admin_user' => '管理员用户名格式不正确（3-32 位字母数字下划线）。',
        'err_admin_email' => '管理员邮箱格式不正确。',
        'err_admin_pass' => '管理员密码至少 8 位。',
        'install_fail' => '安装失败：',
        'ok_title' => '🎉 安装成功！',
        'ok_admin_user' => '管理员账号：',
        'ok_admin_pass' => '管理员密码：',
        'ok_demo_intro' => '已导入演示数据，可用以下账号登录体验：',
        'ok_demo_accounts' => 'alice / bob / carol，密码均为 demo1234',
        'ok_to_login' => '前往登录 →',
        'ok_delete_hint' => '出于安全，请删除 install.php（项目根与 public/ 下各一份）。',
        'upgrade_title' => 'APX 升级到 v2',
        'upgrade_sub' => '检测到已有安装且存在待应用的结构迁移。点击升级将执行增量迁移并完整保留存量数据。',
        'upgrade_note' => '提示：升级前系统会自动备份 config/database.php 与 storage/；也可用命令行 php scripts/migrate-v1-v2.php 执行（更稳妥）。',
        'upgrade_start' => '开始升级',
        'upgrade_applied' => '已应用 %d 个迁移：',
        'upgrade_fail_list' => '部分迁移失败：',
        'upgrade_fail_note' => '请修复后重新运行升级（已成功的迁移不会重复执行）。',
        'upgrade_err_read' => '读取迁移状态时出错：',
        'upgrade_err' => '升级失败：',
        'already_latest' => 'APX 已安装且数据库为最新。如要重新安装，请删除 config/database.php 或访问 install.php?force=1。',
        'to_login' => '前往登录',
    ],
    'zh-TW' => [
        'html_lang' => 'zh-TW',
        'title' => 'APX 安裝精靈',
        'subtitle' => '環境檢測通過後，填寫資料庫與管理員資訊，一鍵完成建庫、建表與初始帳號。',
        'step1' => '環境檢測',
        'step2' => '資料庫 / 帳號',
        'step3' => '完成',
        'env_title' => '環境檢測',
        'env_php_version' => 'PHP 版本 >= 8.1',
        'env_php_current' => '目前 %s',
        'env_ext' => 'PHP 擴充 %s',
        'env_writable' => '目錄可寫 %s',
        'load_ok' => '已載入',
        'load_missing' => '缺失',
        'write_ok' => '可寫',
        'write_bad' => '不可寫（請檢查權限）',
        'label_db_host' => '資料庫主機',
        'label_port' => '連接埠',
        'label_db_name' => '資料庫名稱',
        'label_db_user' => '資料庫使用者名稱',
        'label_db_pass' => '資料庫密碼',
        'label_site_name' => '站點名稱',
        'label_admin_user' => '管理員使用者名稱',
        'label_admin_email' => '管理員信箱',
        'label_admin_pass' => '管理員密碼',
        'hint_admin_pass' => '密碼至少 8 位，將使用 Argon2id / bcrypt 加密。',
        'label_demo' => '匯入示範資料（幾個範例帳號與動態，便於立即體驗）',
        'btn_install' => '開始安裝',
        'err_required' => '請填寫所有必填項。',
        'err_dbname' => '資料庫名稱僅允許字母數字底線（1-64 位）。',
        'err_admin_user' => '管理員使用者名稱格式不正確（3-32 位字母數字底線）。',
        'err_admin_email' => '管理員信箱格式不正確。',
        'err_admin_pass' => '管理員密碼至少 8 位。',
        'install_fail' => '安裝失敗：',
        'ok_title' => '🎉 安裝成功！',
        'ok_admin_user' => '管理員帳號：',
        'ok_admin_pass' => '管理員密碼：',
        'ok_demo_intro' => '已匯入示範資料，可用以下帳號登入體驗：',
        'ok_demo_accounts' => 'alice / bob / carol，密碼皆為 demo1234',
        'ok_to_login' => '前往登入 →',
        'ok_delete_hint' => '基於安全，請刪除 install.php（專案根與 public/ 下各一份）。',
        'upgrade_title' => 'APX 升級到 v2',
        'upgrade_sub' => '偵測到已有安裝且存在待套用的結構遷移。點擊升級將執行增量遷移並完整保留存量資料。',
        'upgrade_note' => '提示：升級前系統會自動備份 config/database.php 與 storage/；也可使用指令列 php scripts/migrate-v1-v2.php 執行（更穩妥）。',
        'upgrade_start' => '開始升級',
        'upgrade_applied' => '已套用 %d 個遷移：',
        'upgrade_fail_list' => '部分遷移失敗：',
        'upgrade_fail_note' => '請修復後重新執行升級（已成功的遷移不會重複執行）。',
        'upgrade_err_read' => '讀取遷移狀態時出錯：',
        'upgrade_err' => '升級失敗：',
        'already_latest' => 'APX 已安裝且資料庫為最新。如需重新安裝，請刪除 config/database.php 或存取 install.php?force=1。',
        'to_login' => '前往登入',
    ],
    'en' => [
        'html_lang' => 'en',
        'title' => 'APX Installer',
        'subtitle' => 'Once the environment check passes, fill in your database and admin info to create the schema and first account in one click.',
        'step1' => 'Environment',
        'step2' => 'Database / Admin',
        'step3' => 'Done',
        'env_title' => 'Environment Check',
        'env_php_version' => 'PHP version >= 8.1',
        'env_php_current' => 'current %s',
        'env_ext' => 'PHP extension %s',
        'env_writable' => 'Directory writable: %s',
        'load_ok' => 'loaded',
        'load_missing' => 'missing',
        'write_ok' => 'writable',
        'write_bad' => 'not writable (check permissions)',
        'label_db_host' => 'Database host',
        'label_port' => 'Port',
        'label_db_name' => 'Database name',
        'label_db_user' => 'Database user',
        'label_db_pass' => 'Database password',
        'label_site_name' => 'Site name',
        'label_admin_user' => 'Admin username',
        'label_admin_email' => 'Admin email',
        'label_admin_pass' => 'Admin password',
        'hint_admin_pass' => 'At least 8 characters; hashed with Argon2id / bcrypt.',
        'label_demo' => 'Import demo data (sample accounts and posts to explore immediately)',
        'btn_install' => 'Install',
        'err_required' => 'Please fill in all required fields.',
        'err_dbname' => 'Database name may only contain letters, numbers and underscores (1-64 chars).',
        'err_admin_user' => 'Admin username format is invalid (3-32 chars: letters, numbers, underscores).',
        'err_admin_email' => 'Admin email format is invalid.',
        'err_admin_pass' => 'Admin password must be at least 8 characters.',
        'install_fail' => 'Installation failed: ',
        'ok_title' => '🎉 Installation complete!',
        'ok_admin_user' => 'Admin account: ',
        'ok_admin_pass' => 'Admin password: ',
        'ok_demo_intro' => 'Demo data imported. You can sign in with:',
        'ok_demo_accounts' => 'alice / bob / carol, password: demo1234',
        'ok_to_login' => 'Go to login →',
        'ok_delete_hint' => 'For security, delete install.php (one copy at project root and one under public/).',
        'upgrade_title' => 'APX Upgrade to v2',
        'upgrade_sub' => 'An existing installation with pending structural migrations was detected. Upgrading runs incremental migrations while keeping all existing data.',
        'upgrade_note' => 'Tip: a backup of config/database.php and storage/ is taken automatically before upgrading; or run php scripts/migrate-v1-v2.php from the CLI (recommended).',
        'upgrade_start' => 'Start upgrade',
        'upgrade_applied' => 'Applied %d migration(s):',
        'upgrade_fail_list' => 'Some migrations failed:',
        'upgrade_fail_note' => 'Fix the issues and run the upgrade again (already-applied migrations will not repeat).',
        'upgrade_err_read' => 'Error reading migration status: ',
        'upgrade_err' => 'Upgrade failed: ',
        'already_latest' => 'APX is installed and the database is up to date. To reinstall, delete config/database.php or visit install.php?force=1.',
        'to_login' => 'Go to login',
    ],
];
$t = $TXT[$lang];

$error = '';
$success = false;
$adminUser = '';
$adminPass = '';
$dbFile = APP_ROOT . '/config/database.php';
$installed = is_file($dbFile) && empty($_GET['force']);
$forceQ = isset($_GET['force']) ? '&force=1' : '';

/**
 * 建立 PDO 连接。$db 为空时仅连到 MySQL 服务器（用于建库前的探测）。
 */
function apxConnect(string $host, int $port, string $db, string $user, string $pass): \PDO
{
    $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
    if ($db !== '') {
        $dsn .= ";dbname={$db}";
    }
    return new \PDO($dsn, $user, $pass, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
}

/**
 * 环境检测：返回 [名称, 是否通过, 说明] 列表（文案随安装语言切换）。
 */
function envCheck(array $t): array
{
    $checks = [];
    $checks[] = [$t['env_php_version'], PHP_VERSION_ID >= 80100, sprintf($t['env_php_current'], PHP_VERSION)];

    $exts = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'json', 'openssl', 'fileinfo', 'ctype'];
    foreach ($exts as $e) {
        $loaded = extension_loaded($e);
        $checks[] = [sprintf($t['env_ext'], $e), $loaded, $loaded ? $t['load_ok'] : $t['load_missing']];
    }

    $writables = [
        APP_ROOT . '/config'  => is_writable(APP_ROOT . '/config'),
        APP_ROOT . '/storage' => is_writable(APP_ROOT . '/storage'),
    ];
    foreach ($writables as $path => $ok) {
        $checks[] = [sprintf($t['env_writable'], basename($path)), $ok, $ok ? $t['write_ok'] : $t['write_bad']];
    }
    return $checks;
}

/**
 * 按语句执行 SQL 文件。使用字符串感知的分号切分，避免把 WAF 正则等
 * 字符串字面量内的分号误判为语句结束（例如 ';\s*(drop|truncate|alter)'）。
 */
function runSqlFile(\PDO $pdo, string $path): void
{
    $sql = @file_get_contents($path);
    if ($sql === false || $sql === '') {
        return;
    }
    foreach (splitSqlStatements($sql) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '' || $stmt === ';') {
            continue;
        }
        $pdo->exec($stmt);
    }
}

/**
 * 按分号切分 SQL，但忽略被单引号包裹字符串内的分号；MySQL 原生处理 -- 与 /* *\/ 注释。
 */
function splitSqlStatements(string $sql): array
{
    $out = [];
    $buf = '';
    $inString = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($inString) {
            $buf .= $c;
            if ($c === '\\') {
                if ($i + 1 < $len) {
                    $buf .= $sql[$i + 1];
                    $i++;
                }
                continue;
            }
            if ($c === "'") {
                if ($i + 1 < $len && $sql[$i + 1] === "'") {
                    $buf .= $sql[$i + 1];
                    $i++;
                    continue;
                }
                $inString = false;
            }
            continue;
        }
        if ($c === "'") {
            $inString = true;
            $buf .= $c;
            continue;
        }
        if ($c === ';') {
            $out[] = $buf;
            $buf = '';
            continue;
        }
        $buf .= $c;
    }
    if ($buf !== '') {
        $out[] = $buf;
    }
    return $out;
}

/**
 * 可选演示数据：几个示例账号 + 话题 + 动态 + 互相关注，便于安装后立即体验。
 */
function seedDemo(\PDO $pdo): void
{
    $hash = password_hash('demo1234', PASSWORD_BCRYPT);
    $ids = [];
    $users = [
        ['alice', 'alice@example.com', '爱丽丝', '生活记录者，喜欢摄影与咖啡 ☕'],
        ['bob',   'bob@example.com',   'Bob',   '开源爱好者，PHP 后端开发'],
        ['carol', 'carol@example.com', '卡罗尔', '每天分享一点小确幸'],
    ];
    foreach ($users as [$u, $e, $n, $bio]) {
        $pdo->prepare('INSERT INTO users (username,email,password_hash,nickname,status,role_level,created_at) VALUES (?,?,?,?,?,?,?)')
            ->execute([$u, $e, $hash, $n, 'active', 0, gmdate('Y-m-d H:i:s', time() - mt_rand(0, 86400 * 30))]);
        $id = (int) $pdo->lastInsertId();
        $ids[] = $id;
        $pdo->prepare('INSERT INTO user_profiles (user_id,bio) VALUES (?,?)')->execute([$id, $bio]);
    }

    $topics = [['PHP', 'php', 'PHP 开发与最佳实践'], ['开源', 'opensource', '开源项目与协作'], ['生活', 'life', '记录生活点滴']];
    $tids = [];
    foreach ($topics as [$name, $slug, $desc]) {
        $pdo->prepare('INSERT INTO topics (name,slug,description,post_count,follower_count) VALUES (?,?,?,?,?)')
            ->execute([$name, $slug, $desc, 0, 0]);
        $tids[$slug] = (int) $pdo->lastInsertId();
    }

    $posts = [
        [$ids[1], '今天用 PHP 8.0 重写了路由层，类型声明真香！ #PHP #开源', ['php', 'opensource']],
        [$ids[0], '周末去拍了点城市夜景，光影真的很迷人。 #生活', ['life']],
        [$ids[2], '一杯手冲，一本书，一下午。 #生活', ['life']],
        [$ids[1], '欢迎来 APX 分享你的开源故事～ #开源', ['opensource']],
    ];
    foreach ($posts as [$uid, $body, $ts]) {
        $pdo->prepare('INSERT INTO posts (user_id,body,visibility,created_at) VALUES (?,?,?,?)')
            ->execute([$uid, $body, 'public', gmdate('Y-m-d H:i:s', time() - mt_rand(0, 86400 * 10))]);
        $pid = (int) $pdo->lastInsertId();
        foreach ($ts as $slug) {
            if (isset($tids[$slug])) {
                $pdo->prepare('INSERT IGNORE INTO post_topics (post_id,topic_id) VALUES (?,?)')->execute([$pid, $tids[$slug]]);
                $pdo->prepare('UPDATE topics SET post_count = post_count + 1 WHERE id = ?')->execute([$tids[$slug]]);
            }
        }
    }

    $pdo->prepare('INSERT IGNORE INTO follows (follower_id,following_id,created_at) VALUES (?,?,?)')
        ->execute([$ids[0], $ids[1], gmdate('Y-m-d H:i:s')]);
    $pdo->prepare('INSERT IGNORE INTO follows (follower_id,following_id,created_at) VALUES (?,?,?)')
        ->execute([$ids[1], $ids[0], gmdate('Y-m-d H:i:s')]);
}

$env = envCheck($t);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $dbHost = trim($_POST['db_host'] ?? '127.0.0.1');
    $dbPort = (int) ($_POST['db_port'] ?? 3306);
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = $_POST['db_pass'] ?? '';
    $adminUser = trim($_POST['admin_username'] ?? '');
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass = $_POST['admin_password'] ?? '';
    $siteName = trim($_POST['site_name'] ?? 'APX');
    $importDemo = !empty($_POST['import_demo']);

    if ($dbName === '' || $dbUser === '' || $adminUser === '' || $adminEmail === '' || $adminPass === '') {
        $error = $t['err_required'];
    } elseif (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $dbName)) {
        $error = $t['err_dbname'];
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $adminUser)) {
        $error = $t['err_admin_user'];
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = $t['err_admin_email'];
    } elseif (mb_strlen($adminPass) < 8) {
        $error = $t['err_admin_pass'];
    } else {
        try {
            // 优先直连目标库：覆盖“库已存在且用户已授权”的场景（含虚拟主机预建库）
            try {
                $pdo = apxConnect($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'Unknown database') !== false) {
                    // 库不存在：尝试建库（需 CREATE 权限）
                    $tmp = apxConnect($dbHost, $dbPort, '', $dbUser, $dbPass);
                    $tmp->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = apxConnect($dbHost, $dbPort, $dbName, $dbUser, $dbPass);
                } elseif (stripos($msg, 'Access denied') !== false && stripos($msg, 'to database') !== false) {
                    // 库存在但用户无权限：给出可执行方案
                    throw new \Exception(
                        "MySQL 用户 `{$dbUser}`@`{$dbHost}` 已成功登录，但对数据库 `{$dbName}` 没有权限（无权 USE 该库）。\n" .
                        "方案 A：以管理员/root 执行授权后重试 ——\n" .
                        "  GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$dbHost}'; FLUSH PRIVILEGES;\n" .
                        "方案 B：在表单里填写你确实拥有权限的数据库名（部分虚拟主机的库名带用户名前缀，例如 cptro_apx）。"
                    );
                } elseif (stripos($msg, 'Access denied') !== false) {
                    // 1045 登录被拒：账号/密码错误或 localhost 与 127.0.0.1 在 MySQL 中被视为不同主机
                    throw new \Exception(
                        "MySQL 登录被拒绝（1045）：账号 `{$dbUser}`@`{$dbHost}` 认证失败。\n" .
                        "常见原因：\n" .
                        "1. 用户名或密码填写错误；\n" .
                        "2. MySQL 里 `{$dbUser}`@`{$dbHost}`（账号 + 主机）这个组合不存在——注意 localhost 与 127.0.0.1 在 MySQL 中是两个不同主机，需分别授权；\n" .
                        "3. 若你此前用 127.0.0.1 能连上（报 1044 无库权限），请保持主机填 127.0.0.1，先授权后再装。\n" .
                        "请核对凭据；或以管理员执行：\n" .
                        "  CREATE USER IF NOT EXISTS '{$dbUser}'@'{$dbHost}' IDENTIFIED BY '你的密码';\n" .
                        "  GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'{$dbHost}'; FLUSH PRIVILEGES;"
                    );
                } else {
                    throw new \Exception('连接数据库失败：' . $msg);
                }
            }

            // 写数据库配置（真实凭据，不提交版本库）
            $cfgContent = "<?php\nreturn " . var_export([
                'host'     => $dbHost,
                'port'     => $dbPort,
                'dbname'   => $dbName,
                'username' => $dbUser,
                'password' => $dbPass,
                'charset'  => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'options'  => [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ],
            ], true) . ";\n";
            file_put_contents($dbFile, $cfgContent);
            @chmod($dbFile, 0600);

            // 导入 schema / seed / waf 规则
            foreach (['database/schema.sql', 'database/seed.sql', 'database/waf_rules.sql'] as $f) {
                $path = APP_ROOT . '/' . $f;
                if (is_file($path)) {
                    runSqlFile($pdo, $path);
                }
            }

            // 创建超级管理员
            $hash = password_hash($adminPass, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
            $pdo->prepare('INSERT INTO users (username,email,password_hash,nickname,status,role_level,created_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$adminUser, $adminEmail, $hash, $adminUser, 'active', 100, gmdate('Y-m-d H:i:s')]);
            $uid = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_profiles (user_id) VALUES (?)')->execute([$uid]);
            $roleId = $pdo->query("SELECT id FROM roles WHERE slug='super_admin' LIMIT 1")->fetchColumn();
            if ($roleId) {
                $pdo->prepare('INSERT INTO user_roles (user_id,role_id) VALUES (?,?)')->execute([$uid, $roleId]);
            }
            $pdo->prepare("UPDATE settings SET value=? WHERE `key`='site_name'")->execute([$siteName]);

            // 可选演示数据
            if ($importDemo) {
                seedDemo($pdo);
            }

            // 确保存储与上传目录存在
            foreach (['uploads', 'logs', 'cache'] as $d) {
                $dir = APP_ROOT . '/storage/' . $d;
                if (!is_dir($dir)) {
                    @mkdir($dir, 0755, true);
                }
            }
            $upDir = APP_ROOT . '/public/assets/uploads';
            if (!is_dir($upDir)) {
                @mkdir($upDir, 0755, true);
            }

            file_put_contents(APP_ROOT . '/storage/installed.lock', gmdate('c'));
            $success = true;
        } catch (\Throwable $e) {
            $error = $t['install_fail'] . $e->getMessage();
        }
    }
}

if ($installed && !$success) {
    // v1 → v2 升级向导：已安装但存在待应用迁移时，提供浏览器内一键升级入口（存量数据保留）。
    $pendingUpg = [];
    $appliedUpg = [];
    $failUpg    = [];
    $upgradeMsg = '';
    try {
        $dbCfg = require $dbFile;
        if (is_array($dbCfg)) {
            $pdo = apxConnect(
                (string) ($dbCfg['host']     ?? '127.0.0.1'),
                (int)    ($dbCfg['port']     ?? 3306),
                (string) ($dbCfg['dbname']   ?? ''),
                (string) ($dbCfg['username'] ?? ''),
                (string) ($dbCfg['password'] ?? '')
            );
            $pdo->exec("CREATE TABLE IF NOT EXISTS `schema_migrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `version` VARCHAR(32) NOT NULL,
                `filename` VARCHAR(128) NOT NULL,
                `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_migration` (`version`,`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $pendingUpg = \App\Services\MigrationService::status()['pending'];
        }
    } catch (\Throwable $e) {
        $upgradeMsg = $t['upgrade_err_read'] . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upgrade' && !empty($pendingUpg)) {
        try {
            $res = \App\Services\MigrationService::applyPending();
            $pendingUpg = $res['pending'] ?? [];
            $appliedUpg = $res['applied'];
            $failUpg    = $res['failed'];
        } catch (\Throwable $e) {
            $upgradeMsg = $t['upgrade_err'] . $e->getMessage();
        }
    }

    if (empty($pendingUpg)) {
        echo '<!doctype html><meta charset="utf-8"><title>APX</title><body style="font-family:sans-serif;background:#0E1116;color:#E8ECF2;display:flex;align-items:center;justify-content:center;height:100vh"><div>'
            . e($t['already_latest'])
            . ' <br><a href="login.php" style="color:#22D3EE">' . e($t['to_login']) . '</a></div></body>';
        exit;
    }
    ?>
<!doctype html>
<html lang="<?= $t['html_lang'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>APX · <?= e($t['upgrade_title']) ?></title>
<style>
  :root{--bg:#0E1116;--surface:#151A21;--text:#E8ECF2;--muted:#9AA5B4;--brand:#6366F1;--brand2:#8B5CF6;--ok:#34D399;--bad:#F87171;}
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,PingFang SC,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;overflow:hidden}
  body::before,body::after{content:"";position:fixed;border-radius:50%;filter:blur(80px);opacity:.5;z-index:0}
  body::before{width:520px;height:520px;background:radial-gradient(circle,#6366F1,transparent 70%);top:-160px;left:-120px;animation:float1 14s ease-in-out infinite}
  body::after{width:460px;height:460px;background:radial-gradient(circle,#8B5CF6,transparent 70%);bottom:-160px;right:-100px;animation:float2 16s ease-in-out infinite}
  @keyframes float1{50%{transform:translate(60px,40px)}}
  @keyframes float2{50%{transform:translate(-50px,-30px)}}
  .card{position:relative;z-index:1;background:rgba(21,26,33,.72);backdrop-filter:blur(22px);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:40px;width:100%;max-width:620px;box-shadow:0 24px 60px rgba(0,0,0,.45)}
  h1{margin:0 0 6px;font-size:28px;background:linear-gradient(135deg,var(--brand),var(--brand2),#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent}
  p.sub{margin:0 0 20px;color:var(--muted);font-size:14px;line-height:1.6}
  .env{background:rgba(255,255,255,.04);border-radius:12px;padding:12px 14px;margin:16px 0;font-size:13px;line-height:1.9}
  .env code{display:block;padding:4px 0;color:#A5B4FC}
  .err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:#FCA5A5;padding:12px;border-radius:12px;font-size:13px;margin-bottom:8px;white-space:pre-wrap}
  .ok{background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.4);color:#6EE7B7;padding:16px;border-radius:12px;font-size:14px;line-height:1.7}
  .ok a{color:#22D3EE;font-weight:600}
  .fail{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:#FCA5A5;padding:12px;border-radius:12px;font-size:13px;margin-bottom:8px}
  button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;font-size:15px;font-weight:600;cursor:pointer}
  hr{border-color:rgba(255,255,255,.08);margin:20px 0}
  code{background:rgba(255,255,255,.08);padding:1px 6px;border-radius:6px}
</style>
</head>
<body>
<div class="card">
  <h1><?= e($t['upgrade_title']) ?></h1>
  <p class="sub"><?= e($t['upgrade_sub']) ?></p>

  <?php if ($upgradeMsg): ?><div class="err"><?php echo e($upgradeMsg); ?></div><?php endif; ?>

  <?php if (!empty($failUpg)): ?>
    <div class="fail"><?= e($t['upgrade_fail_list']) ?>
      <?php foreach ($failUpg as $f => $msg): ?>
        <div><code><?php echo e($f); ?></code> — <?php echo e($msg); ?></div>
      <?php endforeach; ?>
    </div>
    <p class="sub"><?= e($t['upgrade_fail_note']) ?></p>
  <?php endif; ?>

  <?php if (!empty($appliedUpg)): ?>
    <div class="ok">✓ <?php printf(e($t['upgrade_applied']), count($appliedUpg)); ?>
      <?php foreach ($appliedUpg as $f): ?>
        <div><code><?php echo e($f); ?></code></div>
      <?php endforeach; ?>
      <br><a href="login.php"><?= e($t['to_login']) ?> →</a>
    </div>
  <?php else: ?>
    <div class="env">
      <?= count($pendingUpg) ?>：
      <?php foreach ($pendingUpg as $f): ?><code><?php echo e($f); ?></code><?php endforeach; ?>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="upgrade">
      <button type="submit"><?= e($t['upgrade_start']) ?></button>
    </form>
    <p class="sub" style="margin-top:14px;"><?= e($t['upgrade_note']) ?></p>
  <?php endif; ?>
</div>
</body>
</html>
    <?php
    exit;
}
?>
<!doctype html>
<html lang="<?= $t['html_lang'] ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>APX · <?= e($t['title']) ?></title>
<style>
  :root{--bg:#0E1116;--surface:#151A21;--text:#E8ECF2;--muted:#9AA5B4;--brand:#6366F1;--brand2:#8B5CF6;--accent:#22D3EE;--ok:#34D399;--bad:#F87171;}
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,PingFang SC,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px;overflow:hidden}
  body::before,body::after{content:"";position:fixed;border-radius:50%;filter:blur(90px);opacity:.45;z-index:0}
  body::before{width:560px;height:560px;background:radial-gradient(circle,#6366F1,transparent 70%);top:-180px;left:-140px;animation:float1 14s ease-in-out infinite}
  body::after{width:500px;height:500px;background:radial-gradient(circle,#8B5CF6,transparent 70%);bottom:-180px;right:-120px;animation:float2 16s ease-in-out infinite}
  @keyframes float1{50%{transform:translate(70px,50px)}}
  @keyframes float2{50%{transform:translate(-60px,-40px)}}
  .card{position:relative;z-index:1;background:rgba(21,26,33,.72);backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);border:1px solid rgba(255,255,255,.08);border-radius:24px;padding:40px;width:100%;max-width:660px;box-shadow:0 24px 60px rgba(0,0,0,.5)}
  .topbar{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:22px}
  .brand{display:flex;align-items:center;gap:12px}
  .brand__mark{width:42px;height:42px;border-radius:13px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;font-weight:800;font-size:20px;color:#fff;box-shadow:0 6px 18px rgba(99,102,241,.45)}
  h1{margin:0;font-size:26px;line-height:1.2;background:linear-gradient(135deg,var(--brand),var(--brand2),var(--accent));-webkit-background-clip:text;background-clip:text;color:transparent}
  p.sub{margin:4px 0 0;color:var(--muted);font-size:13px;line-height:1.6;max-width:420px}
  .lang{display:inline-flex;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.08);border-radius:999px;padding:3px;flex:none}
  .lang a{display:block;padding:6px 12px;border-radius:999px;font-size:12px;color:var(--muted);text-decoration:none;transition:.2s}
  .lang a.is-active{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;font-weight:600}
  .steps{display:flex;gap:8px;margin:8px 0 20px}
  .step{flex:1;text-align:center;font-size:12px;color:var(--muted);padding:9px;border-radius:10px;background:rgba(255,255,255,.04);border:1px solid transparent}
  .step.on{color:#fff;background:linear-gradient(135deg,rgba(99,102,241,.25),rgba(139,92,246,.25));border-color:rgba(139,92,246,.5)}
  .env{margin-bottom:22px}
  .env__title{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:10px}
  .env__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
  .env__item{display:flex;flex-direction:column;gap:4px;padding:12px;border-radius:14px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06)}
  .env__item.is-ok{border-color:rgba(52,211,153,.4)}
  .env__item.is-bad{border-color:rgba(248,113,113,.5)}
  .env__row{display:flex;align-items:center;gap:8px}
  .env__icon{width:20px;height:20px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex:none}
  .env__item.is-ok .env__icon{background:rgba(52,211,153,.18);color:var(--ok)}
  .env__item.is-bad .env__icon{background:rgba(248,113,113,.18);color:var(--bad)}
  .env__name{font-size:12.5px;font-weight:600}
  .env__detail{font-size:11.5px;color:var(--muted);padding-left:28px}
  .sec{margin:18px 0;padding-top:18px;border-top:1px solid rgba(255,255,255,.06)}
  .sec__h{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:12px}
  label{display:block;font-size:12.5px;color:var(--muted);margin:12px 0 6px}
  input[type=text],input[type=password]{width:100%;padding:11px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:var(--text);font-size:14px;outline:none;transition:.18s}
  input:focus{border-color:var(--brand);box-shadow:0 0 0 3px rgba(99,102,241,.2)}
  .row{display:flex;gap:12px}.row>div{flex:1}
  .check{display:flex;align-items:center;gap:9px;margin-top:16px;font-size:13px;color:var(--muted);cursor:pointer}
  .check input{width:16px;height:16px;accent-color:var(--brand)}
  button.submit{margin-top:24px;width:100%;padding:13px;border:0;border-radius:13px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;font-size:15px;font-weight:600;cursor:pointer;transition:.18s}
  button.submit:hover{filter:brightness(1.08);transform:translateY(-1px)}
  .err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:#FCA5A5;padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:14px;white-space:pre-wrap;line-height:1.6}
  .ok{background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.4);color:#6EE7B7;padding:18px;border-radius:14px;font-size:14px;line-height:1.8}
  .ok a{color:var(--accent);font-weight:600;text-decoration:none}
  .ok .code{display:inline-block;background:rgba(255,255,255,.08);padding:2px 8px;border-radius:6px;font-family:ui-monospace,monospace;color:#E8ECF2}
  .ok__hint{margin-top:10px;font-size:12.5px;color:var(--muted)}
  .hint{font-size:12px;color:var(--muted);margin-top:5px;line-height:1.5}
  @media(max-width:560px){.env__grid{grid-template-columns:repeat(2,1fr)}.topbar{flex-direction:column}.lang{align-self:flex-start}}
</style>
</head>
<body>
<div class="card">
  <div class="topbar">
    <div class="brand">
      <div class="brand__mark">A</div>
      <div>
        <h1><?= e($t['title']) ?></h1>
        <p class="sub"><?= e($t['subtitle']) ?></p>
      </div>
    </div>
    <div class="lang">
      <?php foreach ($LANGS as $code => $name): ?>
        <a href="?lang=<?= $code ?><?= $forceQ ?>" class="<?= $code === $lang ? 'is-active' : '' ?>"><?= e($name) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="steps">
    <div class="step on"><?= e($t['step1']) ?></div>
    <div class="step on"><?= e($t['step2']) ?></div>
    <div class="step on"><?= e($t['step3']) ?></div>
  </div>

  <div class="env">
    <div class="env__title"><?= e($t['env_title']) ?></div>
    <div class="env__grid">
      <?php foreach ($env as $c): ?>
        <div class="env__item <?= $c[1] ? 'is-ok' : 'is-bad' ?>">
          <div class="env__row">
            <span class="env__icon"><?= $c[1] ? '✓' : '✗' ?></span>
            <span class="env__name"><?= e($c[0]) ?></span>
          </div>
          <div class="env__detail"><?= e($c[2]) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php if ($error): ?><div class="err"><?php echo nl2br(e($error)); ?></div><?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">
      <?= e($t['ok_title']) ?><br>
      <?= e($t['ok_admin_user']) ?> <span class="code"><?php echo e($adminUser); ?></span><br>
      <?= e($t['ok_admin_pass']) ?> <span class="code"><?php echo e($adminPass); ?></span><br>
      <?php if (!empty($importDemo)): ?>
        <br><?= e($t['ok_demo_intro']) ?><br>
        <span class="code"><?php echo e($t['ok_demo_accounts']); ?></span><br>
      <?php endif; ?>
      <br><a href="login.php"><?= e($t['ok_to_login']) ?></a><br>
      <div class="ok__hint"><?= e($t['ok_delete_hint']) ?></div>
    </div>
  <?php else: ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="install_lang" value="<?= e($lang) ?>">

    <div class="sec">
      <div class="sec__h">MySQL</div>
      <label><?= e($t['label_db_host']) ?></label>
      <input type="text" name="db_host" value="<?php echo e($_POST['db_host'] ?? '127.0.0.1'); ?>" required>
      <div class="row">
        <div><label><?= e($t['label_port']) ?></label><input type="text" name="db_port" value="<?php echo e($_POST['db_port'] ?? '3306'); ?>" required></div>
        <div><label><?= e($t['label_db_name']) ?></label><input type="text" name="db_name" value="<?php echo e($_POST['db_name'] ?? 'apx'); ?>" required></div>
      </div>
      <label><?= e($t['label_db_user']) ?></label>
      <input type="text" name="db_user" value="<?php echo e($_POST['db_user'] ?? ''); ?>" required>
      <label><?= e($t['label_db_pass']) ?></label>
      <input type="password" name="db_pass" autocomplete="new-password">
    </div>

    <div class="sec">
      <div class="sec__h"><?= e($t['label_admin_user']) ?> / <?= e($t['label_admin_email']) ?></div>
      <label><?= e($t['label_site_name']) ?></label>
      <input type="text" name="site_name" value="<?php echo e($_POST['site_name'] ?? 'APX'); ?>">
      <label><?= e($t['label_admin_user']) ?></label>
      <input type="text" name="admin_username" value="<?php echo e($_POST['admin_username'] ?? ''); ?>" required>
      <label><?= e($t['label_admin_email']) ?></label>
      <input type="text" name="admin_email" value="<?php echo e($_POST['admin_email'] ?? ''); ?>" required>
      <label><?= e($t['label_admin_pass']) ?></label>
      <input type="password" name="admin_password" autocomplete="new-password" required>
      <div class="hint"><?= e($t['hint_admin_pass']) ?></div>
    </div>

    <label class="check"><input type="checkbox" name="import_demo" value="1" checked> <?= e($t['label_demo']) ?></label>

    <button class="submit" type="submit"><?= e($t['btn_install']) ?></button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
