<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\FavoriteService;

/**
 * 收藏夹：列表页、收藏夹增删改、收藏开关与分页列表。
 */
class FavoriteController
{
    public function index(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $folderRaw = $req->get('folder');
        $folderId = ($folderRaw === null || $folderRaw === '') ? null : (int) $folderRaw;
        $folders = FavoriteService::folders($uid);
        $defaultCount = FavoriteService::defaultCount($uid);
        $items = FavoriteService::list($uid, $folderId, 0, 20);
        echo View::render('favorites/index', [
            'folders'      => $folders,
            'defaultCount' => $defaultCount,
            'activeFolder' => $folderId,
            'items'        => $items,
            'css'          => ['css/pages/favorites.css', 'css/pages/post.css'],
        ], 'app');
    }

    public function folders(Request $req): void
    {
        $uid = (int) AuthService::userId();
        JsonResponse::ok([
            'folders'      => FavoriteService::folders($uid),
            'defaultCount' => FavoriteService::defaultCount($uid),
        ], 'ok');
    }

    public function folderCreate(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $name = trim((string) $req->post('name', ''));
        if ($name === '') {
            JsonResponse::fail(422, 'validation.required');
            return;
        }
        $id = FavoriteService::createFolder($uid, $name);
        JsonResponse::created(['id' => $id, 'name' => mb_substr($name, 0, 32)], 'favorite.folder_created');
    }

    public function folderRename(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $folderId = (int) $req->post('folder_id', 0);
        $name = trim((string) $req->post('name', ''));
        if ($folderId <= 0 || $name === '') {
            JsonResponse::fail(422, 'validation.required');
            return;
        }
        FavoriteService::renameFolder($uid, $folderId, $name);
        JsonResponse::ok([], 'favorite.folder_renamed');
    }

    public function folderDelete(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $folderId = (int) $req->post('folder_id', 0);
        if ($folderId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        FavoriteService::deleteFolder($uid, $folderId);
        JsonResponse::ok([], 'favorite.folder_deleted');
    }

    public function toggle(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $targetId = (int) $req->post('target_id', 0);
        $folderRaw = $req->post('folder_id');
        $folderId = ($folderRaw === null || $folderRaw === '') ? null : (int) $folderRaw;
        if ($targetId <= 0) {
            JsonResponse::fail(422, 'validation.invalid');
            return;
        }
        $fav = FavoriteService::toggle($uid, $targetId, $folderId);
        $count = FavoriteService::favoriteCount($targetId);
        JsonResponse::ok(['favorited' => $fav, 'count' => $count], $fav ? 'favorite.added' : 'favorite.remove');
    }

    public function list(Request $req): void
    {
        $uid = (int) AuthService::userId();
        $folderRaw = $req->get('folder');
        $folderId = ($folderRaw === null || $folderRaw === '') ? null : (int) $folderRaw;
        $before = (int) $req->get('before', 0);
        $items = FavoriteService::list($uid, $folderId, $before, 20);
        JsonResponse::ok(['items' => $items, 'has_more' => count($items) === 20], 'ok');
    }
}
