<?php
declare(strict_types=1);
namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * 白名单 HTML 净化器（零依赖，基于 DOMDocument）。
 *
 * 用途：后台把「外部 AI 生成的自定义前端 HTML」交给我们渲染前，必须先净化，
 * 防止存储型 XSS（<script>、on* 事件、javascript: 协议、危险标签等）。
 *
 * 设计原则：
 *  - 仅放行白名单标签；未知标签「解包」保留其子节点，危险标签整体删除。
 *  - 仅放行白名单属性；去除所有 on* 事件属性与危险协议值。
 *  - 这是纵深防御的一环；线上还有 WAF 与 SecureHeadersMiddleware 的 CSP 兜底。
 */
class HtmlSanitizer
{
    /** 允许保留的标签。 */
    private const ALLOWED_TAGS = [
        'a', 'p', 'br', 'span', 'div', 'section', 'article', 'header', 'footer', 'nav',
        'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'strong', 'em', 'b', 'i', 'u', 's', 'small',
        'img', 'button', 'table', 'thead', 'tbody', 'tr', 'td', 'th', 'blockquote', 'hr', 'style', 'time',
    ];

    /** 允许保留的属性（data-* 通配）。 */
    private const ALLOWED_ATTRS = [
        'class', 'id', 'style', 'title', 'role', 'aria-label', 'aria-hidden',
        'href', 'src', 'alt', 'width', 'height', 'target', 'rel', 'type',
        'value', 'placeholder', 'datetime', 'loading', 'controls', 'poster',
    ];

    /** 整体删除（不解包）的危险标签。 */
    private const DROP_TAGS = [
        'script', 'iframe', 'object', 'embed', 'link', 'meta', 'base', 'form',
        'input', 'textarea', 'select', 'option', 'button', 'frame', 'frameset', 'svg', 'math', 'portal',
    ];

    public static function clean(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        $prev = libxml_use_internal_errors(true);
        $root = '<div id="__apx_sanitize_root__">' . $html . '</div>';
        // 前置 XML 编码声明，避免 libxml 把 UTF-8 当 Latin-1 处理导致中文乱码。
        $doc->loadHTML('<?xml encoding="UTF-8"?>' . $root, LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $rootNode = $doc->getElementById('__apx_sanitize_root__');
        if (!$rootNode) {
            $rootNode = $doc->documentElement;
        }
        if (!$rootNode) {
            return '';
        }

        self::walk($rootNode);

        $out = '';
        $children = [];
        foreach ($rootNode->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            $out .= $doc->saveHTML($child);
        }
        return $out;
    }

    private static function walk(DOMNode $node): void
    {
        if ($node instanceof DOMElement) {
            $tag = strtolower($node->tagName);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                $parent = $node->parentNode;
                if (!$parent) {
                    return;
                }
                if (in_array($tag, self::DROP_TAGS, true)) {
                    $parent->removeChild($node);
                    return;
                }
                // 未知标签：解包，保留子节点
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                return;
            }

            // 处理属性
            $toRemove = [];
            foreach ($node->attributes as $attr) {
                $name = strtolower($attr->nodeName);
                $value = $attr->nodeValue ?? '';
                if (!self::attrAllowed($name) || !self::attrSafe($name, $value)) {
                    $toRemove[] = $attr->nodeName;
                } elseif ($name === 'style') {
                    $node->setAttribute('style', self::sanitizeStyle($value));
                }
            }
            foreach ($toRemove as $r) {
                $node->removeAttribute($r);
            }

            // a 标签强制安全 rel
            if ($tag === 'a' && $node->hasAttribute('target') && $node->getAttribute('target') === '_blank') {
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }

        $children = [];
        foreach ($node->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::walk($child);
        }
    }

    private static function attrAllowed(string $name): bool
    {
        if (str_starts_with($name, 'data-')) {
            return true;
        }
        return in_array($name, self::ALLOWED_ATTRS, true);
    }

    private static function attrSafe(string $name, string $value): bool
    {
        $v = trim($value);
        if ($v === '') {
            return true;
        }
        // 任何属性值都禁止脚本协议
        if (preg_match('/^\s*(javascript|vbscript):/i', $v)) {
            return false;
        }
        if ($name === 'href' || $name === 'src') {
            // 允许：http(s) / mailto / tel / 锚点 / 绝对路径 / 相对路径（无协议）
            if (preg_match('#^(https?:|mailto:|tel:|/|#)#i', $v)) {
                // ok
            } elseif (strpos($v, ':') === false) {
                // 相对路径（如 foo/bar.html、./x.png）
            } else {
                return false;
            }
            // img 的 src 额外允许 data:image/
            if ($name === 'src' && !preg_match('#^(https?:|/|data:image/)#i', $v)) {
                return false;
            }
        }
        return true;
    }

    /** 清理 style 值中的危险表达式。 */
    private static function sanitizeStyle(string $value): string
    {
        $v = preg_replace('/expression\s*\(/i', '', $value);
        $v = preg_replace('/javascript\s*:/i', '', $v);
        $v = preg_replace('/@import/i', '', $v);
        return trim((string) $v);
    }
}
