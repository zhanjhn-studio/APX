<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Logger;
use App\Core\Redis;

/**
 * 异步队列（Redis list + blpop）。
 * - push：rpush 任务到默认队列；Redis 不可用时自动降级为同步执行，保证不丢任务、不报 500。
 * - worker：常驻阻塞消费，按 job 名分发到对应处理逻辑（mail / thumbnail）。
 */
class QueueService
{
    private const QUEUE = 'apx:queue:default';

    /** 投递任务；Redis 不可用时同步执行。 */
    public static function push(string $job, array $payload): void
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            self::run($job, $payload);
            return;
        }
        try {
            $c->rpush(self::QUEUE, [json_encode(
                ['job' => $job, 'payload' => $payload],
                JSON_UNESCAPED_UNICODE
            )]);
        } catch (\Throwable $e) {
            Logger::error('队列投递失败，降级同步执行', ['job' => $job, 'err' => $e->getMessage()]);
            self::run($job, $payload);
        }
    }

    /** worker 主循环：阻塞消费，直到被信号终止。 */
    public static function worker(int $timeout = 5): void
    {
        $c = Redis::instance()->client();
        if ($c === null) {
            fwrite(STDERR, "Redis 不可用，worker 无法启动。\n");
            exit(1);
        }
        fwrite(STDOUT, "APX queue worker started (timeout={$timeout}s)\n");
        while (true) {
            try {
                $item = $c->blpop([self::QUEUE], $timeout);
            } catch (\Throwable $e) {
                Logger::error('队列消费异常', ['err' => $e->getMessage()]);
                sleep(1);
                continue;
            }
            if (empty($item)) {
                continue; // 超时，继续
            }
            $raw = is_array($item) ? end($item) : $item;
            $data = json_decode((string) $raw, true);
            if (!is_array($data) || empty($data['job'])) {
                continue;
            }
            try {
                self::run($data['job'], $data['payload'] ?? []);
            } catch (\Throwable $e) {
                Logger::error('队列任务执行失败', ['job' => $data['job'], 'err' => $e->getMessage()]);
            }
        }
    }

    /** 任务分发。 */
    public static function run(string $job, array $payload): void
    {
        switch ($job) {
            case 'mail':
                MailService::send(
                    (string) ($payload['to'] ?? ''),
                    (string) ($payload['subject'] ?? ''),
                    (string) ($payload['html'] ?? ''),
                    (string) ($payload['text'] ?? '')
                );
                break;
            case 'thumbnail':
                try {
                    UploadService::generateThumbnail((string) ($payload['path'] ?? ''));
                } catch (\Throwable $e) {
                    Logger::error('缩略图异步生成失败', ['path' => $payload['path'] ?? '', 'err' => $e->getMessage()]);
                }
                break;
            default:
                Logger::error('未知队列任务', ['job' => $job]);
        }
    }
}
