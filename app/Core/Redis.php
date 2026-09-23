<?php
declare(strict_types=1);
namespace App\Core;

/**
 * Redis 封装（Predis 纯 PHP 客户端，无需 php-redis 扩展）。
 * 单例 + 连接懒加载；Redis 不可用时 available() 返回 false，
 * 上层（Cache / Realtime / Queue）据此自动回退到自研文件实现。
 */
class Redis
{
    private static ?self $instance = null;
    private ?\Predis\Client $client = null;
    private bool $available = false;
    private array $config;

    private function __construct(array $config)
    {
        $this->config = $config;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            $cfg = Config::get('redis', []);
            self::$instance = new self(is_array($cfg) ? $cfg : []);
        }
        return self::$instance;
    }

    private function connect(): void
    {
        if ($this->client !== null) {
            return;
        }
        if (!class_exists(\Predis\Client::class)) {
            $this->available = false;
            return;
        }
        try {
            $params = [
                'scheme' => $this->config['scheme'] ?? 'tcp',
                'host'   => $this->config['host'] ?? '127.0.0.1',
                'port'   => (int) ($this->config['port'] ?? 6379),
            ];
            if (!empty($this->config['password'])) {
                $params['password'] = $this->config['password'];
            }
            if (!empty($this->config['db'])) {
                $params['database'] = (int) $this->config['db'];
            }
            $client = new \Predis\Client($params);
            $client->connect();
            $client->ping();
            $this->client = $client;
            $this->available = true;
        } catch (\Throwable $e) {
            $this->available = false;
            $this->client = null;
        }
    }

    public function available(): bool
    {
        if ($this->client === null) {
            $this->connect();
        }
        return $this->available;
    }

    public function client(): ?\Predis\Client
    {
        if ($this->client === null) {
            $this->connect();
        }
        return $this->client;
    }

    /** 重置单例（重连 / 测试用）。 */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
