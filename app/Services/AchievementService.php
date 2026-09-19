<?php
declare(strict_types=1);
namespace App\Services;

use App\Core\Database;

/**
 * 等级 / 徽章 / 成就服务。
 * 等级由经验值（users.experience）按递增曲线换算，徽章与成就按用户真实统计数据实时判定，
 * 不依赖额外数据表，任意历史账号升级后都能立刻正确展示。
 */
class AchievementService
{
    /** 达到 $level 所需累计经验（三角形曲线：50 递增）。 */
    public static function expForLevel(int $level): int
    {
        if ($level <= 1) {
            return 0;
        }
        return (int) (25 * $level * ($level - 1));
    }

    /** 由经验值反推等级。 */
    public static function levelFromExp(int $exp): int
    {
        $level = 1;
        while ($level < 99 && $exp >= self::expForLevel($level + 1)) {
            $level++;
        }
        return $level;
    }

    /** 参与计算的原始统计（单次聚合查询组）。 */
    public static function stats(int $userId): array
    {
        $db = Database::instance();
        $row = $db->fetch(
            "SELECT
                u.id, u.experience, u.level, u.created_at, u.two_factor_enabled, u.email_verified_at,
                (SELECT COUNT(*) FROM posts p WHERE p.user_id = u.id AND p.deleted_at IS NULL AND p.is_review = 0) AS posts,
                (SELECT COALESCE(SUM(p.like_count), 0) FROM posts p WHERE p.user_id = u.id AND p.deleted_at IS NULL) AS likes_received,
                (SELECT COUNT(*) FROM post_comments c WHERE c.user_id = u.id AND c.deleted_at IS NULL) AS comments,
                (SELECT COUNT(*) FROM friendships f WHERE f.user_id = u.id) AS friends,
                (SELECT COUNT(*) FROM follows f WHERE f.following_id = u.id) AS followers,
                (SELECT COUNT(*) FROM follows f WHERE f.follower_id = u.id) AS following,
                (SELECT COUNT(*) FROM group_members gm JOIN groups g ON g.id = gm.group_id WHERE gm.user_id = u.id AND g.status = 'active') AS groups_joined,
                (SELECT COUNT(*) FROM groups g WHERE g.owner_id = u.id AND g.status = 'active') AS groups_owned,
                (SELECT COUNT(*) FROM favorites f WHERE f.user_id = u.id) AS favorites,
                (SELECT COUNT(*) FROM topic_follows tf WHERE tf.user_id = u.id) AS topics_followed,
                (SELECT COUNT(*) FROM group_posts gp WHERE gp.user_id = u.id AND gp.deleted_at IS NULL) AS group_posts
             FROM users u WHERE u.id = ? LIMIT 1",
            [$userId]
        );
        if (!$row) {
            return [];
        }
        foreach (['experience', 'level', 'posts', 'likes_received', 'comments', 'friends', 'followers', 'following',
                  'groups_joined', 'groups_owned', 'favorites', 'topics_followed', 'group_posts', 'two_factor_enabled'] as $k) {
            $row[$k] = (int) $row[$k];
        }
        return $row;
    }

    /** 等级信息：当前等级、经验、距下一级进度。 */
    public static function level(int $experience): array
    {
        $level = self::levelFromExp($experience);
        $currentFloor = self::expForLevel($level);
        $nextFloor = self::expForLevel($level + 1);
        $span = max(1, $nextFloor - $currentFloor);
        $progress = (int) min(100, max(0, (int) round(($experience - $currentFloor) / $span * 100)));
        // 等级称号（按区间）
        if ($level >= 30) {
            $titleKey = 'level.title.legend';
        } elseif ($level >= 20) {
            $titleKey = 'level.title.master';
        } elseif ($level >= 12) {
            $titleKey = 'level.title.expert';
        } elseif ($level >= 6) {
            $titleKey = 'level.title.member';
        } else {
            $titleKey = 'level.title.newbie';
        }
        return [
            'level'      => $level,
            'experience' => $experience,
            'current'    => $experience - $currentFloor,
            'needed'     => $nextFloor - $currentFloor,
            'next_total' => $nextFloor,
            'progress'   => $progress,
            'title_key'  => $titleKey,
        ];
    }

    /** 徽章（已获得的，按稀有度排序）。 */
    public static function badges(array $s, mixed $profile = null): array
    {
        $profile = is_array($profile) ? $profile : [];
        $defs = [
            ['key' => 'founder',    'icon' => '★', 'tone' => 'gold',    'name' => 'badge.founder',    'desc' => 'badge.founder_desc',    'got' => (int) ($s['id'] ?? 0) <= 10],
            ['key' => 'verified',   'icon' => '✓', 'tone' => 'info',    'name' => 'badge.verified',   'desc' => 'badge.verified_desc',   'got' => ($profile['verified_type'] ?? 'none') !== 'none'],
            ['key' => 'creator',    'icon' => '✎', 'tone' => 'primary', 'name' => 'badge.creator',    'desc' => 'badge.creator_desc',    'got' => ($s['posts'] ?? 0) >= 10],
            ['key' => 'writer',     'icon' => '✒', 'tone' => 'primary', 'name' => 'badge.writer',     'desc' => 'badge.writer_desc',     'got' => ($s['posts'] ?? 0) >= 100],
            ['key' => 'popular',    'icon' => '♥', 'tone' => 'danger',  'name' => 'badge.popular',    'desc' => 'badge.popular_desc',    'got' => ($s['likes_received'] ?? 0) >= 500],
            ['key' => 'socialite',  'icon' => '✦', 'tone' => 'success', 'name' => 'badge.socialite',  'desc' => 'badge.socialite_desc',  'got' => ($s['friends'] ?? 0) >= 20],
            ['key' => 'influencer', 'icon' => '◈', 'tone' => 'success', 'name' => 'badge.influencer', 'desc' => 'badge.influencer_desc', 'got' => ($s['followers'] ?? 0) >= 100],
            ['key' => 'leader',     'icon' => '⛨', 'tone' => 'warning', 'name' => 'badge.leader',     'desc' => 'badge.leader_desc',     'got' => ($s['groups_owned'] ?? 0) >= 1],
            ['key' => 'guardian',   'icon' => '⛨', 'tone' => 'warning', 'name' => 'badge.guardian',   'desc' => 'badge.guardian_desc',   'got' => ($s['two_factor_enabled'] ?? 0) === 1],
            ['key' => 'veteran',    'icon' => '⏳', 'tone' => 'info',    'name' => 'badge.veteran',    'desc' => 'badge.veteran_desc',    'got' => !empty($s['created_at']) && (time() - strtotime((string) $s['created_at'])) >= 31536000],
        ];
        $out = [];
        foreach ($defs as $d) {
            if ($d['got']) {
                $out[] = $d;
            }
        }
        return $out;
    }

    /** 成就（含进度，未解锁也返回用于展示进度条）。 */
    public static function achievements(array $s): array
    {
        $defs = [
            ['key' => 'first_post',   'icon' => '📝', 'name' => 'achv.first_post',   'desc' => 'achv.first_post_desc',   'have' => $s['posts'] ?? 0,          'need' => 1],
            ['key' => 'creator_10',   'icon' => '🖋', 'name' => 'achv.creator_10',   'desc' => 'achv.creator_10_desc',   'have' => $s['posts'] ?? 0,          'need' => 10],
            ['key' => 'creator_50',   'icon' => '📚', 'name' => 'achv.creator_50',   'desc' => 'achv.creator_50_desc',   'have' => $s['posts'] ?? 0,          'need' => 50],
            ['key' => 'first_friend', 'icon' => '🤝', 'name' => 'achv.first_friend', 'desc' => 'achv.first_friend_desc', 'have' => $s['friends'] ?? 0,        'need' => 1],
            ['key' => 'friends_10',   'icon' => '👥', 'name' => 'achv.friends_10',   'desc' => 'achv.friends_10_desc',   'have' => $s['friends'] ?? 0,        'need' => 10],
            ['key' => 'followers_50', 'icon' => '🌟', 'name' => 'achv.followers_50', 'desc' => 'achv.followers_50_desc', 'have' => $s['followers'] ?? 0,      'need' => 50],
            ['key' => 'liked_100',    'icon' => '❤️', 'name' => 'achv.liked_100',    'desc' => 'achv.liked_100_desc',    'have' => $s['likes_received'] ?? 0, 'need' => 100],
            ['key' => 'commenter_50', 'icon' => '💬', 'name' => 'achv.commenter_50', 'desc' => 'achv.commenter_50_desc', 'have' => $s['comments'] ?? 0,       'need' => 50],
            ['key' => 'group_owner',  'icon' => '🏛', 'name' => 'achv.group_owner',  'desc' => 'achv.group_owner_desc',  'have' => $s['groups_owned'] ?? 0,   'need' => 1],
            ['key' => 'group_member_3','icon' => '🧩', 'name' => 'achv.group_member_3','desc' => 'achv.group_member_3_desc','have' => $s['groups_joined'] ?? 0, 'need' => 3],
            ['key' => 'collector_20', 'icon' => '🔖', 'name' => 'achv.collector_20', 'desc' => 'achv.collector_20_desc', 'have' => $s['favorites'] ?? 0,      'need' => 20],
            ['key' => 'topics_5',     'icon' => '🏷', 'name' => 'achv.topics_5',     'desc' => 'achv.topics_5_desc',     'have' => $s['topics_followed'] ?? 0, 'need' => 5],
            ['key' => 'secure',       'icon' => '🔐', 'name' => 'achv.secure',       'desc' => 'achv.secure_desc',       'have' => $s['two_factor_enabled'] ?? 0, 'need' => 1],
            ['key' => 'verified_email','icon' => '📧', 'name' => 'achv.verified_email','desc' => 'achv.verified_email_desc','have' => empty($s['email_verified_at']) ? 0 : 1, 'need' => 1],
        ];
        $out = [];
        foreach ($defs as $d) {
            $have = (int) $d['have'];
            $need = max(1, (int) $d['need']);
            $d['unlocked'] = $have >= $need;
            $d['percent'] = (int) min(100, (int) round($have / $need * 100));
            $d['have'] = min($have, $need);
            $d['need'] = $need;
            $out[] = $d;
        }
        return $out;
    }

    /** 组装个人主页所需的等级 / 徽章 / 成就数据。 */
    public static function summary(int $userId, mixed $profile = null): array
    {
        $s = self::stats($userId);
        if (!$s) {
            return ['level' => self::level(0), 'badges' => [], 'achievements' => [], 'stats' => []];
        }
        // 等级与经验保持同步（历史账号首次访问即回填）
        $computed = self::levelFromExp($s['experience']);
        if ((int) $s['level'] !== $computed) {
            Database::instance()->update('users', ['level' => $computed], 'id = ?', [$userId]);
            $s['level'] = $computed;
        }
        $achv = self::achievements($s);
        $unlocked = 0;
        foreach ($achv as $a) {
            if ($a['unlocked']) {
                $unlocked++;
            }
        }
        return [
            'level'        => self::level($s['experience']),
            'badges'       => self::badges($s, $profile),
            'achievements' => $achv,
            'unlocked'     => $unlocked,
            'total'        => count($achv),
            'stats'        => $s,
        ];
    }

    /**
     * 发放经验。等级随经验自动提升；单次上限防止刷分。
     */
    public static function award(int $userId, int $amount, string $reason = ''): void
    {
        if ($userId <= 0 || $amount <= 0) {
            return;
        }
        $amount = min($amount, 100);
        $db = Database::instance();
        $db->statement('UPDATE `users` SET `experience` = `experience` + ? WHERE `id` = ?', [$amount, $userId]);
        $exp = (int) $db->column('SELECT experience FROM users WHERE id = ?', [$userId]);
        $level = self::levelFromExp($exp);
        $db->update('users', ['level' => $level], 'id = ?', [$userId]);
    }
}
