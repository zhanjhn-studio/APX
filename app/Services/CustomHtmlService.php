<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * 自定义前端 HTML 服务（v3）。
 *
 * 设计：后台不调用任何 AI。管理员在后台填写「平台接口说明」+「诉求指令」，
 * 由本服务拼出一段可直接粘贴给任意外部 AI（ChatGPT/Claude/本地模型…）的提示词；
 * AI 返回 HTML 后，管理员粘回后台，本站净化 + 占位符替换后全站前台渲染。
 *
 * 因此零外部依赖、可完全离线，也避免把站点密钥暴露给第三方 AI。
 */
class CustomHtmlService
{
    public const DEFAULT_POSITION = 'before_content';

    /** APX 接口说明（默认 manifest）：告诉外部 AI 本站的数据/页面/设计令牌。 */
    public const INTERFACE_MANIFEST = <<<'MD'
APX 是一个现代化社交平台（PHP，类朋友圈动态 + 群组 + 私聊）。
页面区块：信息流（动态）、发现、群组、私信、好友、通知、个人主页、设置。
设计语言：玻璃拟态（glassmorphism），深色为主，支持 12 套主题 + 深/浅/跟随三档外观。
可用 CSS 变量（设计令牌）：--brand-1 ~ --brand-3（品牌渐变）、--bg、--surface、--text、--text-muted、--border、--radius、--shadow 等。
现有组件风格类（可直接复用以保持一致）：.apx-card（玻璃卡片）、.apx-btn .apx-btn--primary（按钮）、.apx-input（输入框）、.apx-badge 等。
可引用的站点数据（由下方占位符注入）：站点名、标语、当前登录用户昵称/头像/用户名、各功能页链接、最新公开动态列表、最热门公开小组列表。
注意：生成的 HTML 会被注入到站点的每个前台页面（登录/注册等未登录页与后台除外），请保持轻量、响应式、不遮挡核心操作、不依赖外部资源（CDN/外链脚本）。
MD;

    /** 可用占位符清单（用于提示词与后台展示）。 */
    public static function placeholders(): array
    {
        return [
            '{{site.name}} - 站点名称',
            '{{site.slogan}} - 站点标语',
            '{{site.url}} - 站点根地址',
            '{{url.home}} / {{url.login}} / {{url.register}} / {{url.discover}} / {{url.messages}} / {{url.groups}} / {{url.profile}} / {{url.settings}} - 对应页面链接',
            '{{user.nickname}} / {{user.username}} / {{user.avatar}} - 当前登录用户（未登录时为空）',
            '{{year}} - 当前年份',
            '{{latest_posts:N}} - 最新 N 条公开动态列表（N=1~20）',
            '{{hot_groups:N}} - 最热门 N 个公开小组列表（N=1~20）',
            '{{page.type}} / {{page.username}} - 当前页面类型 / 浏览的用户名（如有）',
        ];
    }

    public static function isEnabled(): bool
    {
        return SiteSettingsService::bool('custom_html_enabled');
    }

    /**
     * 拼装给外部 AI 的完整提示词。
     * @param string $instruction 管理员的诉求
     * @param string|null $interface 管理员自定义的接口说明（留空则用默认 manifest）
     */
    public static function buildPrompt(string $instruction, ?string $interface = null): string
    {
        $iface = trim((string) ($interface ?? ''));
        if ($iface === '') {
            $iface = self::INTERFACE_MANIFEST;
        }
        $ph = implode("\n", array_map(static fn(string $p) => ' - ' . $p, self::placeholders()));
        $instruction = trim($instruction) === '' ? '（未填写，请根据平台能力自由发挥）' : trim($instruction);

        return "【角色】你是一名前端工程师，为 APX 社交平台生成一段自定义前端 HTML 片段，"
            . "用于注入到站点的前台页面（信息流/发现/群组/私信/个人主页等）。\n\n"
            . "【平台接口说明】\n" . $iface . "\n\n"
            . "【可用占位符（渲染时由站点替换，务必只用这些）】\n" . $ph . "\n\n"
            . "【生成规则】\n"
            . "1. 仅输出纯 HTML 片段，不要 <html>/<head>/<body>，不要 <script>，不要外链脚本/CDN。\n"
            . "2. 只使用上面列出的占位符，不要臆造未知占位符；链接用 {{url.*}}，用户数据用 {{user.*}}。\n"
            . "3. 样式可用内联 style 或复用 .apx-card/.apx-btn 等类，保持响应式与玻璃拟态风格。\n"
            . "4. 保持轻量，不要遮挡核心操作；不要包含任何外部网络请求。\n\n"
            . "【管理员的诉求】\n" . $instruction . "\n";
    }

    public static function sanitize(string $html): string
    {
        return HtmlSanitizer::clean($html);
    }

    /**
     * 渲染指定插槽的自定义 HTML。
     * @param string $slot 'before' | 'after'
     */
    public static function render(string $slot): string
    {
        if (!self::isEnabled()) {
            return '';
        }
        $position = SiteSettingsService::get('custom_html_position', self::DEFAULT_POSITION);
        if ($slot === 'before' && !in_array($position, ['before_content', 'both'], true)) {
            return '';
        }
        if ($slot === 'after' && !in_array($position, ['after_content', 'both'], true)) {
            return '';
        }
        $content = SiteSettingsService::get('custom_html_content', '');
        if (trim($content) === '') {
            return '';
        }
        return self::replacePlaceholders(self::sanitize($content));
    }

    public static function replacePlaceholders(string $html): string
    {
        // 列表占位符：{{latest_posts:N}} / {{hot_groups:N}}
        $html = preg_replace_callback(
            '/\{\{\s*(latest_posts|hot_groups)\s*:\s*(\d+)\s*\}\}/',
            static function (array $m): string {
                $n = max(1, min(20, (int) $m[2]));
                return $m[1] === 'latest_posts' ? self::latestPosts($n) : self::hotGroups($n);
            },
            $html
        );

        // 简单占位符：{{key}}
        $html = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/',
            static function (array $m): string {
                return self::resolvePlaceholder($m[1]);
            },
            $html
        );

        return $html;
    }

    private static function resolvePlaceholder(string $key): string
    {
        switch ($key) {
            case 'site.name':
                return e(SiteSettingsService::get('site_name', 'APX'));
            case 'site.slogan':
                return e(SiteSettingsService::get('site_slogan', ''));
            case 'site.url':
                return e(rtrim((string) Config::get('app.base_url', ''), '/'));
            case 'url.home':
                return e(route('/home'));
            case 'url.login':
                return e(route('/login'));
            case 'url.register':
                return e(route('/register'));
            case 'url.discover':
                return e(route('/discover'));
            case 'url.messages':
                return e(route('/messages'));
            case 'url.groups':
                return e(route('/groups'));
            case 'url.profile':
                return e(route('/profile'));
            case 'url.settings':
                return e(route('/settings'));
            case 'user.nickname':
            case 'user.username':
            case 'user.avatar':
                $u = AuthService::user();
                if (empty($u)) {
                    return '';
                }
                return e((string) ($u[$key === 'user.nickname' ? 'nickname' : ($key === 'user.username' ? 'username' : 'avatar')] ?? ''));
            case 'year':
                return (string) date('Y');
            case 'page.type':
                return e(self::pageType());
            case 'page.username':
                return e(self::pageUsername());
            default:
                return '';
        }
    }

    private static function pageType(): string
    {
        $route = trim((string) ($_GET['r'] ?? ''), '/');
        return $route === '' ? 'home' : $route;
    }

    private static function pageUsername(): string
    {
        $route = trim((string) ($_GET['r'] ?? ''), '/');
        if (preg_match('#^profile/(.+)$#', $route, $m)) {
            return urldecode($m[1]);
        }
        return '';
    }

    private static function latestPosts(int $n): string
    {
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT p.id, p.body, u.nickname FROM posts p '
                . 'JOIN users u ON u.id = p.user_id '
                . 'WHERE p.visibility = ? AND p.deleted_at IS NULL '
                . 'ORDER BY p.created_at DESC LIMIT ' . $n,
                ['public']
            );
        } catch (\Throwable $e) {
            return '';
        }
        if (empty($rows)) {
            return '';
        }
        $html = '<ul class="apx-custom-list">';
        foreach ($rows as $r) {
            $text = mb_substr(strip_tags((string) ($r['body'] ?? '')), 0, 80);
            $html .= '<li><a href="' . e(route('/post/' . $r['id'])) . '">'
                . '<span class="apx-custom-list__author">' . e($r['nickname'] ?? '') . '</span>'
                . '<span class="apx-custom-list__text">' . e($text) . '</span></a></li>';
        }
        $html .= '</ul>';
        return $html;
    }

    private static function hotGroups(int $n): string
    {
        try {
            $rows = Database::instance()->fetchAll(
                'SELECT name, slug, member_count FROM groups '
                . 'WHERE status = ? AND visibility = ? '
                . 'ORDER BY member_count DESC LIMIT ' . $n,
                ['active', 'public']
            );
        } catch (\Throwable $e) {
            return '';
        }
        if (empty($rows)) {
            return '';
        }
        $html = '<ul class="apx-custom-list">';
        foreach ($rows as $r) {
            $html .= '<li><a href="' . e(route('/group/' . $r['slug'])) . '">'
                . '<span class="apx-custom-list__author">' . e($r['name'] ?? '') . '</span>'
                . '<span class="apx-custom-list__text">' . e((int) ($r['member_count'] ?? 0)) . ' 成员</span></a></li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
