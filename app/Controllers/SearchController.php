<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\View;
use App\Services\AuthService;
use App\Services\SearchService;

/**
 * 全局搜索：搜索页、分类结果（动态/用户/话题/群组）、实时建议、热门搜索、历史记录。
 */
class SearchController
{
    public function index(Request $req): void
    {
        $q = trim((string) $req->get('q', ''));
        $uid = (int) (AuthService::userId() ?? 0);
        $overview = $q !== ''
            ? SearchService::overview($q, $uid)
            : ['posts' => [], 'users' => [], 'topics' => [], 'groups' => [], 'count' => 0];
        echo View::render('search/index', [
            'q'        => $q,
            'overview' => $overview,
            'history'  => SearchService::recentHistory($uid, 12),
            'hot'      => SearchService::hotKeywords(8),
            'css'      => ['css/pages/search.css'],
        ], 'app');
    }

    public function results(Request $req): void
    {
        $q = trim((string) $req->get('q', ''));
        $tab = (string) $req->get('tab', 'posts');
        $before = (int) $req->get('before', 0);
        $uid = (int) (AuthService::userId() ?? 0);

        if ($q === '') {
            JsonResponse::ok(['items' => [], 'has_more' => false, 'tab' => $tab, 'q' => $q], 'ok');
            return;
        }

        if ($tab === 'users') {
            JsonResponse::ok(['items' => SearchService::searchUsers($q, 30), 'has_more' => false, 'tab' => $tab, 'q' => $q], 'ok');
            return;
        }
        if ($tab === 'topics') {
            JsonResponse::ok(['items' => SearchService::searchTopics($q, 30), 'has_more' => false, 'tab' => $tab, 'q' => $q], 'ok');
            return;
        }
        if ($tab === 'groups') {
            JsonResponse::ok(['items' => SearchService::searchGroups($q, $uid, 30), 'has_more' => false, 'tab' => $tab, 'q' => $q], 'ok');
            return;
        }

        if ($before === 0) {
            SearchService::recordHistory($uid, $q, 0);
        }
        $items = SearchService::searchPosts($q, $uid, $before, 20);
        JsonResponse::ok(['items' => $items, 'has_more' => count($items) === 20, 'tab' => $tab, 'q' => $q], 'ok');
    }

    /** 实时搜索建议（输入框下拉）。 */
    public function suggest(Request $req): void
    {
        $q = trim((string) $req->get('q', ''));
        $uid = (int) (AuthService::userId() ?? 0);
        JsonResponse::ok(SearchService::suggest($q, $uid), 'ok');
    }

    public function clearHistory(Request $req): void
    {
        $uid = (int) AuthService::userId();
        SearchService::clearHistory($uid);
        JsonResponse::ok([], 'ok');
    }
}
