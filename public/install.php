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

$error = '';
$success = false;
$dbFile = APP_ROOT . '/config/database.php';
$installed = is_file($dbFile) && empty($_GET['force']);

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
 * 环境检测：返回 [名称, 是否通过, 说明] 列表。
 */
function envCheck(): array
{
    $checks = [];
    $checks[] = ['PHP 版本 >= 8.0', PHP_VERSION_ID >= 80000, '当前 ' . PHP_VERSION];

    $exts = ['pdo', 'pdo_mysql', 'gd', 'mbstring', 'json', 'openssl', 'fileinfo', 'ctype'];
    foreach ($exts as $e) {
        $loaded = extension_loaded($e);
        $checks[] = ['PHP 扩展 ' . $e, $loaded, $loaded ? '已加载' : '缺失'];
    }

    $writables = [
        APP_ROOT . '/config'  => is_writable(APP_ROOT . '/config'),
        APP_ROOT . '/storage' => is_writable(APP_ROOT . '/storage'),
    ];
    foreach ($writables as $path => $ok) {
        $checks[] = ['目录可写 ' . basename($path), $ok, $ok ? '可写' : '不可写（请检查权限）'];
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
                // MySQL 字符串内反斜杠转义（\' 转义单引号、\\ 转义反斜杠等），
                // 必须连同下一个字符一起吞掉，否则会误判字符串边界。
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

$env = envCheck();

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
        $error = '请填写所有必填项。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{1,64}$/', $dbName)) {
        $error = '数据库名仅允许字母数字下划线（1-64 位）。';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,32}$/', $adminUser)) {
        $error = '管理员用户名格式不正确（3-32 位字母数字下划线）。';
    } elseif (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $error = '管理员邮箱格式不正确。';
    } elseif (mb_strlen($adminPass) < 8) {
        $error = '管理员密码至少 8 位。';
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
            $error = '安装失败：' . $e->getMessage();
        }
    }
}

if ($installed && !$success) {
    echo '<!doctype html><meta charset="utf-8"><title>APX</title><body style="font-family:sans-serif;background:#0E1116;color:#E8ECF2;display:flex;align-items:center;justify-content:center;height:100vh"><div>APX 已安装。如要重新安装，请删除 <code>config/database.php</code> 或访问 <a href="?force=1" style="color:#8B5CF6">install.php?force=1</a>。<br><a href="/login" style="color:#22D3EE">前往登录</a></div></body>';
    exit;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>APX 安装向导</title>
<style>
  :root{--bg:#0E1116;--surface:#151A21;--text:#E8ECF2;--muted:#9AA5B4;--brand:#6366F1;--brand2:#8B5CF6;--ok:#34D399;--bad:#F87171;}
  *{box-sizing:border-box}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,PingFang SC,sans-serif;background:radial-gradient(80% 60% at 30% 0%,rgba(99,102,241,.25),transparent),var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:32px}
  .card{background:rgba(21,26,33,.7);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,.08);border-radius:22px;padding:40px;width:100%;max-width:620px;box-shadow:0 24px 60px rgba(0,0,0,.45)}
  h1{margin:0 0 6px;font-size:28px;background:linear-gradient(135deg,var(--brand),var(--brand2),#22D3EE);-webkit-background-clip:text;background-clip:text;color:transparent}
  p.sub{margin:0 0 20px;color:var(--muted);font-size:14px}
  .steps{display:flex;gap:8px;margin-bottom:18px}
  .step{flex:1;text-align:center;font-size:12px;color:var(--muted);padding:8px;border-radius:10px;background:rgba(255,255,255,.04)}
  .step.on{color:#fff;background:linear-gradient(135deg,var(--brand),var(--brand2))}
  .env{background:rgba(255,255,255,.04);border-radius:12px;padding:12px 14px;margin-bottom:20px;font-size:13px}
  .env table{width:100%;border-collapse:collapse}
  .env td{padding:4px 6px}
  .env .s-ok{color:var(--ok)}
  .env .s-bad{color:var(--bad)}
  label{display:block;font-size:13px;color:var(--muted);margin:14px 0 6px}
  input[type=text],input[type=password]{width:100%;padding:11px 14px;border-radius:12px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:var(--text);font-size:14px;outline:none}
  input:focus{border-color:var(--brand)}
  .row{display:flex;gap:12px}.row>div{flex:1}
  .check{display:flex;align-items:center;gap:8px;margin-top:16px;font-size:13px;color:var(--muted)}
  button{margin-top:24px;width:100%;padding:13px;border:0;border-radius:12px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;font-size:15px;font-weight:600;cursor:pointer}
  button:disabled{opacity:.5;cursor:not-allowed}
  .err{background:rgba(248,113,113,.12);border:1px solid rgba(248,113,113,.4);color:#FCA5A5;padding:12px;border-radius:12px;font-size:13px;margin-bottom:8px}
  .ok{background:rgba(52,211,153,.12);border:1px solid rgba(52,211,153,.4);color:#6EE7B7;padding:16px;border-radius:12px;font-size:14px;line-height:1.7}
  .ok a{color:#22D3EE;font-weight:600}
  .hint{font-size:12px;color:var(--muted);margin-top:4px}
  hr{border-color:rgba(255,255,255,.08);margin:20px 0}
  code{background:rgba(255,255,255,.08);padding:1px 6px;border-radius:6px}
</style>
</head>
<body>
<div class="card">
  <h1>APX 安装向导</h1>
  <p class="sub">环境检测通过后，填写数据库与管理员信息，一键完成建库、建表与初始账号。</p>

  <div class="steps">
    <div class="step on">1. 环境检测</div>
    <div class="step on">2. 数据库 / 账号</div>
    <div class="step on">3. 完成</div>
  </div>

  <div class="env">
    <table>
      <?php foreach ($env as $c): ?>
      <tr>
        <td style="width:60%"><?php echo e($c[0]); ?></td>
        <td class="<?php echo $c[1] ? 's-ok' : 's-bad'; ?>"><?php echo $c[1] ? '✓' : '✗'; ?></td>
        <td class="s-<?php echo $c[1] ? 'ok' : 'bad'; ?>"><?php echo e($c[2]); ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>

  <?php if ($error): ?><div class="err"><?php echo nl2br(e($error)); ?></div><?php endif; ?>

  <?php if ($success): ?>
    <div class="ok">
      🎉 安装成功！<br>
      管理员账号：<code><?php echo e($adminUser); ?></code><br>
      管理员密码：<code><?php echo e($adminPass); ?></code><br>
      <?php if (!empty($importDemo)): ?>
        <br>已导入演示数据，可用以下账号登录体验：<br>
        <code>alice / bob / carol</code>，密码均为 <code>demo1234</code>。<br>
      <?php endif; ?>
      <br><a href="/login">前往登录 →</a><br>
      <span class="hint">出于安全，请删除 <code>install.php</code>（项目根与 public/ 下各一份）。</span>
    </div>
  <?php else: ?>
  <form method="post" autocomplete="off">
    <label>数据库主机</label>
    <input type="text" name="db_host" value="<?php echo e($_POST['db_host'] ?? '127.0.0.1'); ?>" required>
    <div class="row">
      <div><label>端口</label><input type="text" name="db_port" value="<?php echo e($_POST['db_port'] ?? '3306'); ?>" required></div>
      <div><label>数据库名</label><input type="text" name="db_name" value="<?php echo e($_POST['db_name'] ?? 'apx'); ?>" required></div>
    </div>
    <label>数据库用户名</label>
    <input type="text" name="db_user" value="<?php echo e($_POST['db_user'] ?? ''); ?>" required>
    <label>数据库密码</label>
    <input type="password" name="db_pass" autocomplete="new-password">

    <hr>
    <label>站点名称</label>
    <input type="text" name="site_name" value="<?php echo e($_POST['site_name'] ?? 'APX'); ?>">
    <label>管理员用户名</label>
    <input type="text" name="admin_username" value="<?php echo e($_POST['admin_username'] ?? ''); ?>" required>
    <label>管理员邮箱</label>
    <input type="text" name="admin_email" value="<?php echo e($_POST['admin_email'] ?? ''); ?>" required>
    <label>管理员密码</label>
    <input type="password" name="admin_password" autocomplete="new-password" required>
    <div class="hint">密码至少 8 位，将使用 Argon2id / bcrypt 加密。</div>

    <label class="check"><input type="checkbox" name="import_demo" value="1" checked> 导入演示数据（几个示例账号与动态，便于立即体验）</label>

    <button type="submit">开始安装</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
