<?php
declare(strict_types=1);
/**
 * APX - 全局助手函数
 * 所有输出走 e()，所有 URL 走 route()，所有文案走 __()。
 */

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    function __(string $key, array $params = []): string
    {
        return App\Core\I18n::translate($key, $params);
    }
}

if (!function_exists('trans_choice')) {
    function trans_choice(string $key, int $count, array $params = []): string
    {
        return App\Core\I18n::choice($key, $count, $params);
    }
}

if (!function_exists('route')) {
    /**
     * 生成 URL。由 app.pretty_urls 决定形态：
     *   false（默认）→ 查询串路由 index.php?r=/login，任意服务器零配置可用。
     *   true          → 干净地址 /login，需服务器把未知路径转发到 index.php：
     *                   Nginx: try_files $uri $uri/ /index.php?$query_string;
     *                   Apache: 见根目录 .htaccess
     */
    function route(string $path, array $params = []): string
    {
        $base = rtrim((string) (App\Core\Config::get('app.base_url') ?: ''), '/');
        $rel = ltrim($path, '/');

        // 实体入口文件映射：逻辑路径 → /xxx.php（文件置于项目根与 public/）。
        static $fileMap = [
            '' => 'home.php', 'home' => 'home.php',
            'login' => 'login.php', 'register' => 'register.php',
            'forgot-password' => 'forgot-password.php',
            'reset-password' => 'reset-password.php', 'verify-email' => 'verify-email.php',
            'logout' => 'logout.php', 'captcha' => 'captcha.php',
            'discover' => 'discover.php', 'search' => 'search.php',
            'messages' => 'messages.php', 'friends' => 'friends.php',
            'notifications' => 'notifications.php', 'profile' => 'profile.php',
            'favorites' => 'favorites.php', 'settings' => 'settings.php',
            'publish' => 'publish.php',
            'groups' => 'groups.php', 'group' => 'group.php',
            'post' => 'post.php', 'topic' => 'topic.php',
            'admin' => 'admin.php', 'admin/waf' => 'admin-waf.php', 'admin/update' => 'admin-update.php',
        ];
        static $paramMap = [
            'profile' => 'username', 'post' => 'id', 'topic' => 'slug', 'group' => 'slug',
            'reset-password' => 'token', 'verify-email' => 'token',
        ];

        if ((bool) App\Core\Config::get('app.file_urls', false)) {
            // 允许路径内联查询串，如 '/messages?start=tom'
            $qpos = strpos($rel, '?');
            $inlineQuery = '';
            if ($qpos !== false) {
                $inlineQuery = substr($rel, $qpos + 1);
                $rel = substr($rel, 0, $qpos);
            }

            $segments = $rel === '' ? [''] : explode('/', $rel);
            $key1 = $segments[0];
            $key2 = count($segments) >= 2 ? $segments[0] . '/' . $segments[1] : '';

            if ($key2 !== '' && isset($fileMap[$key2])) {
                $url = $base . '/' . $fileMap[$key2];
            } elseif (isset($fileMap[$key1])) {
                $rest = array_slice($segments, 1);
                if (!$rest) {
                    $url = $base . '/' . $fileMap[$key1];
                } elseif (isset($paramMap[$key1])) {
                    $url = $base . '/' . $fileMap[$key1] . '?' . $paramMap[$key1] . '=' . rawurlencode(implode('/', $rest));
                } else {
                    // 有额外路径段但无参数映射（如 /login/2fa）→ 回落查询串，保证不丢段
                    $url = $base . '/index.php?r=' . urlencode($rel);
                }
            } else {
                // 未映射（含 /api/*）→ 查询串路由
                $url = $rel === '' ? $base . '/index.php' : $base . '/index.php?r=' . urlencode($rel);
            }

            if ($inlineQuery !== '') {
                $url .= (strpos($url, '?') === false ? '?' : '&') . $inlineQuery;
            }
        } elseif ((bool) App\Core\Config::get('app.pretty_urls', false)) {
            $url = ($rel === '') ? ($base . '/') : ($base . '/' . rtrim($rel, '/'));
        } elseif ($rel === '') {
            $url = $base . '/index.php';
        } else {
            $url = $base . '/index.php?r=' . urlencode($rel);
        }

        if ($params) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
        }
        return $url;
    }
}

if (!function_exists('asset')) {
    /**
     * 生成静态资源 URL。真实资源恒位于 public/assets/。
     * 依据「当前入口脚本所在目录」判断 web 根，直接给出可被服务器命中的真实路径：
     *   入口在项目根   → /public/assets/...（web 根=项目根）
     *   入口在 public/ → /assets/...       （web 根=public）
     * 无需服务器 rewrite 或别名即可加载。
     */
    function asset(string $path): string
    {
        $base = rtrim((string) (App\Core\Config::get('app.base_url') ?: ''), '/');

        // 只缓存「目录前缀」（/assets 或 /public/assets），绝不能把文件名一起缓存。
        static $dir = null;
        if ($dir === null) {
            $dir = '/assets'; // 兜底：web 根=public/
            $roots = [];
            $script = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
            if ($script !== '') {
                $roots[] = dirname($script);
            }
            $docroot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
            if ($docroot !== '') {
                $roots[] = $docroot;
            }
            $roots[] = APP_ROOT;
            foreach ($roots as $root) {
                $root = rtrim(str_replace('\\', '/', (string) $root), '/');
                if ($root === '') {
                    continue;
                }
                if (is_dir($root . '/assets')) {
                    $dir = '/assets';
                    break;
                }
                if (is_dir($root . '/public/assets')) {
                    $dir = '/public/assets';
                    break;
                }
            }
        }

        return $base . $dir . '/' . ltrim($path, '/');
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = App\Core\Csrf::token();
        $name = App\Core\Config::get('security.csrf.token_name', 'csrf_token');
        return '<input type="hidden" name="' . e($name) . '" value="' . e($token) . '">';
    }
}

if (!function_exists('csrf_meta')) {
    function csrf_meta(): string
    {
        $token = App\Core\Csrf::token();
        return '<meta name="csrf-token" content="' . e($token) . '">';
    }
}

if (!function_exists('old')) {
    function old(string $key, $default = '')
    {
        return App\Core\Session::old($key) ?? $default;
    }
}

if (!function_exists('auth')) {
    function auth()
    {
        return App\Services\AuthService::user();
    }
}

if (!function_exists('db')) {
    function db(): App\Core\Database
    {
        return App\Core\Database::instance();
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $code = 302): void
    {
        header('Location: ' . route($path), true, $code);
        exit;
    }
}

if (!function_exists('abort')) {
    function abort(int $code, string $message = ''): void
    {
        App\Core\ErrorHandler::abort($code, $message);
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return APP_ROOT . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return rtrim(App\Core\Config::get('app.storage_dir'), '/') . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('now_utc')) {
    function now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('is_ajax')) {
    function is_ajax(): bool
    {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);
    }
}

if (!function_exists('icon')) {
    /**
     * 渲染统一图标（引用 icons.php 注入的 SVG sprite）。
     * @param string $name  图标名（对应 #i-<name>）
     * @param int    $size  像素尺寸，默认 20
     * @param string $class 附加 class（如 'apx-ico--fill'）
     */
    function icon(string $name, int $size = 20, string $class = ''): string
    {
        $cls = 'apx-ico' . ($class !== '' ? ' ' . $class : '');
        return '<svg class="' . e($cls) . '" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><use href="#i-' . e($name) . '"></use></svg>';
    }
}
