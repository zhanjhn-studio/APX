<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\UploadService;

/**
 * 媒体上传端点。支持批量（字段 files），返回已落库的相对路径列表。
 */
class UploadController
{
    public function upload(Request $req): void
    {
        AuthService::userId();
        $raw = $req->file('files');
        if (!$raw) {
            JsonResponse::fail(400, 'upload.empty');
            return;
        }
        $files = self::normalize($raw);
        $items = [];
        foreach ($files as $f) {
            try {
                $items[] = UploadService::store($f, 'post');
            } catch (\Throwable $e) {
                // 单个失败不影响其余
            }
        }
        if (empty($items)) {
            JsonResponse::fail(422, 'upload.failed');
            return;
        }
        JsonResponse::ok(['items' => $items], 'ok');
    }

    private static function normalize(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [$files];
        }
        $out = [];
        $n = count($files['name']);
        for ($i = 0; $i < $n; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $out[] = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];
        }
        return $out;
    }
}
