<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\ReportService;

/**
 * 用户侧举报入口。目标类型：user / post / comment / group / message。
 */
class ReportController
{
    public function submit(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $result = ReportService::submit(
            $uid,
            (string) $req->post('target_type', ''),
            (int) $req->post('target_id', 0),
            (string) $req->post('reason', ''),
            (string) $req->post('detail', '')
        );
        if (!$result['ok']) {
            JsonResponse::fail(422, (string) $result['error']);
            return;
        }
        JsonResponse::created([], 'report.submitted');
    }
}
