<?php
declare(strict_types=1);
namespace App\Core;

/**
 * 请求封装。所有输入经此访问，不直接使用 $_GET/$_POST/$_COOKIE。
 */
class Request
{
    private array $get;
    private array $post;
    private array $cookie;
    private array $server;
    private ?array $jsonBody = null;

    public function __construct()
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->cookie = $_COOKIE;
        $this->server = $_SERVER;
    }

    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    /** 优先 POST 再 GET */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        return $this->get[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->input($k);
        }
        return $out;
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function hasFile(string $key): bool
    {
        return isset($_FILES[$key]) && is_array($_FILES[$key]) && ($_FILES[$key]['error'] ?? 4) !== 4 && ($_FILES[$key]['size'] ?? 0) > 0;
    }

    public function header(string $key): ?string
    {
        $k = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$k] ?? $this->server[strtoupper($key)] ?? null;
    }

    public function method(): string
    {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function isGet(): bool { return $this->method() === 'GET'; }
    public function isPost(): bool { return $this->method() === 'POST'; }

    public function isJson(): bool
    {
        $ct = $this->server['CONTENT_TYPE'] ?? '';
        return stripos($ct, 'application/json') !== false;
    }

    public function json(): ?array
    {
        if ($this->jsonBody === null) {
            $raw = file_get_contents('php://input');
            $this->jsonBody = $raw ? json_decode($raw, true) : [];
        }
        return $this->jsonBody;
    }

    public function ip(): string
    {
        foreach (['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $k) {
            if (!empty($this->server[$k])) {
                $parts = explode(',', $this->server[$k]);
                return trim($parts[0]);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->server['HTTP_USER_AGENT'] ?? '';
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        $uri = parse_url($this->uri(), PHP_URL_PATH) ?? '/';
        return $uri === '' ? '/' : $uri;
    }

    /**
     * 解析当前请求对应的路由路径，兼容多种部署方式（无需服务器重写）：
     *   1) 显式查询串 ?r=/foo（零配置兼容，默认方式）
     *   2) PATH_INFO（如 /index.php/foo）
     *   3) 干净 URL 路径（需服务器 try_files 重写，并剥离可能的 /index.php 前缀）
     */
    public function routePath(): string
    {
        $r = $this->get['r'] ?? null;
        if (is_string($r) && $r !== '') {
            $path = parse_url($r, PHP_URL_PATH) ?? '/';
            return $path === '' ? '/' : '/' . ltrim($path, '/');
        }
        $pi = $this->server['PATH_INFO'] ?? '';
        if (is_string($pi) && $pi !== '') {
            return $pi;
        }
        $p = $this->path();
        if (strpos($p, '/index.php') === 0) {
            $p = substr($p, strlen('/index.php')) ?: '/';
        }
        return $p === '' ? '/' : $p;
    }
}
