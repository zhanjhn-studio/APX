-- =====================================================================
-- APX 社交平台 · 数据库初始化脚本
-- 兼容 MySQL 5.7 / 8.0 / MariaDB 10.x（utf8mb4_unicode_ci，无 JSON / CTE / 窗口函数）
-- 所有时间字段统一存 UTC（DATETIME）。所有外键显式声明 ON DELETE。
-- 表总数：59
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- 账号域
-- =====================================================================

CREATE TABLE `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(32) NOT NULL COMMENT '登录用户名，唯一',
  `email` VARCHAR(191) NOT NULL COMMENT '邮箱，唯一',
  `password_hash` VARCHAR(255) NOT NULL COMMENT 'Argon2id 或 bcrypt',
  `nickname` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '显示昵称',
  `status` ENUM('active','pending','banned','deleting') NOT NULL DEFAULT 'pending' COMMENT '账号状态',
  `role_level` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 普通用户',
  `last_active_at` DATETIME DEFAULT NULL COMMENT '最近活跃，用于在线判定',
  `email_verified_at` DATETIME DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `experience` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '等级经验值',
  `level` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `deleted_at` DATETIME DEFAULT NULL COMMENT '注销冷静期到期时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_status` (`status`),
  KEY `idx_users_last_active` (`last_active_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户主表';

CREATE TABLE `user_profiles` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `avatar` VARCHAR(255) DEFAULT '' COMMENT '头像相对路径',
  `cover` VARCHAR(255) DEFAULT '' COMMENT '背景图相对路径',
  `bio` VARCHAR(255) DEFAULT '' COMMENT '个性签名',
  `gender` ENUM('unknown','male','female','other') NOT NULL DEFAULT 'unknown',
  `birthday` DATE DEFAULT NULL,
  `location` VARCHAR(64) DEFAULT '',
  `website` VARCHAR(255) DEFAULT '',
  `verified_type` ENUM('none','personal','organization','official') NOT NULL DEFAULT 'none',
  `verified_note` VARCHAR(128) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_profile_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户公开资料';

CREATE TABLE `user_settings` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `key` VARCHAR(64) NOT NULL,
  `value` TEXT,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`key`),
  CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  KEY `idx_settings_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户偏好键值对（免打扰、隐私项等）';

CREATE TABLE `user_totp` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `secret` VARCHAR(64) NOT NULL COMMENT 'Base32 密钥',
  `label` VARCHAR(128) DEFAULT '',
  `algorithm` VARCHAR(16) NOT NULL DEFAULT 'sha1',
  `digits` TINYINT UNSIGNED NOT NULL DEFAULT 6,
  `period` SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  `confirmed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_totp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TOTP 两步验证';

CREATE TABLE `backup_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_backup_user` (`user_id`),
  CONSTRAINT `fk_backup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='TOTP 备份码（一次性）';

CREATE TABLE `sessions` (
  `id` VARCHAR(128) NOT NULL COMMENT 'session_id',
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) DEFAULT '',
  `device_fingerprint` VARCHAR(64) DEFAULT '',
  `trusted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '设备信任',
  `last_active_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sessions_user` (`user_id`),
  KEY `idx_sessions_active` (`last_active_at`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录会话（含设备管理）';

CREATE TABLE `login_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) DEFAULT '',
  `result` ENUM('success','fail','locked','totp_required','totp_fail') NOT NULL,
  `reason` VARCHAR(128) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`user_id`),
  KEY `idx_login_ip` (`ip`),
  KEY `idx_login_created` (`created_at`),
  CONSTRAINT `fk_login_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录日志';

CREATE TABLE `email_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `token` VARCHAR(64) NOT NULL,
  `type` ENUM('verify','reset','change_email') NOT NULL,
  `new_email` VARCHAR(191) DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email_token` (`token`),
  KEY `idx_email_user` (`user_id`),
  CONSTRAINT `fk_email_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮箱验证 / 密码重置令牌';

CREATE TABLE `invite_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(32) NOT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `used_by` BIGINT UNSIGNED DEFAULT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite_code` (`code`),
  KEY `idx_invite_created` (`created_by`),
  CONSTRAINT `fk_invite_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邀请码（注册准入）';

-- =====================================================================
-- 关系域
-- =====================================================================

CREATE TABLE `follows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `follower_id` BIGINT UNSIGNED NOT NULL,
  `following_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_follow` (`follower_id`,`following_id`),
  KEY `idx_follow_following` (`following_id`),
  CONSTRAINT `fk_follow_follower` FOREIGN KEY (`follower_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_follow_following` FOREIGN KEY (`following_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='单向关注';

CREATE TABLE `friendships` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `friend_id` BIGINT UNSIGNED NOT NULL,
  `remark` VARCHAR(32) DEFAULT '' COMMENT '备注',
  `group_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '所属好友分组',
  `is_special` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '特别关注（密友分众）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friend` (`user_id`,`friend_id`),
  KEY `idx_friend_friend` (`friend_id`),
  KEY `idx_friend_group` (`group_id`),
  CONSTRAINT `fk_friend_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_friend` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_friend_group` FOREIGN KEY (`group_id`) REFERENCES `friend_groups` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='双向好友（每行单向，两条互为好友）';

CREATE TABLE `friend_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `from_user_id` BIGINT UNSIGNED NOT NULL,
  `to_user_id` BIGINT UNSIGNED NOT NULL,
  `message` VARCHAR(128) DEFAULT '',
  `status` ENUM('pending','accepted','declined','blocked') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_friend_req` (`from_user_id`,`to_user_id`),
  KEY `idx_friend_req_to` (`to_user_id`,`status`),
  CONSTRAINT `fk_req_from` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_req_to` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友申请（仅一条 pending）';

CREATE TABLE `friend_groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(32) NOT NULL DEFAULT '默认分组',
  `sort` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_friendgroup_user` (`user_id`),
  CONSTRAINT `fk_friendgroup_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='好友分组';

CREATE TABLE `friend_group_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `friend_id` BIGINT UNSIGNED NOT NULL COMMENT 'friendships.id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fgm` (`group_id`,`friend_id`),
  CONSTRAINT `fk_fgm_group` FOREIGN KEY (`group_id`) REFERENCES `friend_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fgm_friend` FOREIGN KEY (`friend_id`) REFERENCES `friendships` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分组与好友关联';

CREATE TABLE `close_friends` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `friend_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_close` (`user_id`,`friend_id`),
  CONSTRAINT `fk_close_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_close_friend` FOREIGN KEY (`friend_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='特别关注（密友）';

CREATE TABLE `blocks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '拉黑者',
  `target_id` BIGINT UNSIGNED NOT NULL COMMENT '被拉黑者',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_block` (`user_id`,`target_id`),
  KEY `idx_block_target` (`target_id`),
  CONSTRAINT `fk_block_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_block_target` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='黑名单';

CREATE TABLE `user_muted_users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mute_user` (`user_id`,`target_id`),
  CONSTRAINT `fk_mute_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mute_target` FOREIGN KEY (`target_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='屏蔽某人动态（不拉黑）';

CREATE TABLE `user_muted_words` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `word` VARCHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mute_word` (`user_id`,`word`),
  CONSTRAINT `fk_muteword_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='自定义屏蔽关键词';

-- =====================================================================
-- 通讯域（私聊）
-- =====================================================================

CREATE TABLE `conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `type` ENUM('direct','group') NOT NULL DEFAULT 'direct',
  `title` VARCHAR(128) DEFAULT '' COMMENT '群聊名（direct 为空）',
  `last_message_id` BIGINT UNSIGNED DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_conv_last` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话';

CREATE TABLE `conversation_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '置顶',
  `is_muted` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '免打扰',
  `unread_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_read_message_id` BIGINT UNSIGNED DEFAULT NULL,
  `last_read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conv_member` (`conversation_id`,`user_id`),
  KEY `idx_conv_member_user` (`user_id`),
  CONSTRAINT `fk_cm_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='会话成员状态';

CREATE TABLE `messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '系统消息为 NULL',
  `type` ENUM('text','image','file','emoji','location','system','recall') NOT NULL DEFAULT 'text',
  `body` MEDIUMTEXT COMMENT '文本内容或 JSON 元数据',
  `reply_to_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '引用回复',
  `recalled_at` DATETIME DEFAULT NULL COMMENT '撤回时间',
  `burn_mode` ENUM('none','after_view','after_time') NOT NULL DEFAULT 'none' COMMENT '阅后即焚',
  `burn_value` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '秒数或 0',
  `expires_at` DATETIME DEFAULT NULL COMMENT '定时销毁时间',
  `destroyed_at` DATETIME DEFAULT NULL COMMENT '已销毁',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_msg_conv` (`conversation_id`,`id`),
  KEY `idx_msg_sender` (`sender_id`),
  KEY `idx_msg_created` (`created_at`),
  KEY `idx_msg_expires` (`expires_at`),
  CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_msg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='私聊/群聊消息';

CREATE TABLE `message_reads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_msg_read` (`message_id`,`user_id`),
  KEY `idx_msgread_user` (`user_id`),
  CONSTRAINT `fk_msgread_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_msgread_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='已读回执';

CREATE TABLE `message_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) NOT NULL DEFAULT '',
  `mime` VARCHAR(128) NOT NULL DEFAULT '',
  `size` INT UNSIGNED NOT NULL DEFAULT 0,
  `width` SMALLINT UNSIGNED DEFAULT NULL,
  `height` SMALLINT UNSIGNED DEFAULT NULL,
  `thumb_path` VARCHAR(255) DEFAULT NULL,
  `duration` INT UNSIGNED DEFAULT NULL COMMENT '视频秒数',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_att_msg` (`message_id`),
  CONSTRAINT `fk_att_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息附件';

CREATE TABLE `message_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `emoji` VARCHAR(8) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_react` (`message_id`,`user_id`,`emoji`),
  KEY `idx_react_msg` (`message_id`),
  CONSTRAINT `fk_react_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_react_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息表情回应';

CREATE TABLE `message_locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `lat` DECIMAL(10,8) NOT NULL,
  `lng` DECIMAL(11,8) NOT NULL,
  `label` VARCHAR(128) DEFAULT '',
  `address` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_loc_msg` (`message_id`),
  CONSTRAINT `fk_loc_msg` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='单次位置分享';

CREATE TABLE `message_drafts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `body` TEXT,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_draft` (`user_id`,`conversation_id`),
  CONSTRAINT `fk_draft_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_draft_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='消息草稿';

-- =====================================================================
-- 动态域
-- =====================================================================

CREATE TABLE `posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` MEDIUMTEXT COMMENT '正文（存原文，输出转义）',
  `visibility` ENUM('public','friends','close_friends','private') NOT NULL DEFAULT 'public',
  `origin_post_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '转发源动态',
  `like_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `comment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `share_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `favorite_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_review` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '命中敏感词进审核',
  `deleted_at` DATETIME DEFAULT NULL COMMENT '软删除（7 天可恢复）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_post_user` (`user_id`),
  KEY `idx_post_visibility_created` (`visibility`,`created_at`),
  KEY `idx_post_created` (`created_at`),
  KEY `idx_post_origin` (`origin_post_id`),
  FULLTEXT KEY `ft_post` (`body`),
  CONSTRAINT `fk_post_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_post_origin` FOREIGN KEY (`origin_post_id`) REFERENCES `posts` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态';

CREATE TABLE `post_media` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('image','video') NOT NULL,
  `path` VARCHAR(255) NOT NULL,
  `thumb_path` VARCHAR(255) DEFAULT NULL,
  `width` SMALLINT UNSIGNED DEFAULT NULL,
  `height` SMALLINT UNSIGNED DEFAULT NULL,
  `duration` INT UNSIGNED DEFAULT NULL,
  `sort` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pmedia_post` (`post_id`),
  CONSTRAINT `fk_pmedia_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态媒体';

CREATE TABLE `post_likes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_like` (`post_id`,`user_id`),
  KEY `idx_like_user` (`user_id`),
  CONSTRAINT `fk_like_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_like_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='点赞';

CREATE TABLE `post_comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '自引用承载多级回复',
  `reply_to_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `body` MEDIUMTEXT,
  `like_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_comment_post` (`post_id`,`created_at`),
  KEY `idx_comment_parent` (`parent_id`),
  KEY `idx_comment_user` (`user_id`),
  CONSTRAINT `fk_comment_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_parent` FOREIGN KEY (`parent_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comment_replyuser` FOREIGN KEY (`reply_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论与多级回复';

CREATE TABLE `post_shares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `origin_post_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '被转发原始动态',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share` (`post_id`,`user_id`),
  KEY `idx_share_user` (`user_id`),
  CONSTRAINT `fk_share_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_share_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='转发';

CREATE TABLE `comment_likes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `comment_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comment_like` (`comment_id`,`user_id`),
  KEY `idx_cl_user` (`user_id`),
  CONSTRAINT `fk_cl_comment` FOREIGN KEY (`comment_id`) REFERENCES `post_comments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cl_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='评论点赞';

CREATE TABLE `favorites` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `target_type` ENUM('post','link','message') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `folder_id` BIGINT UNSIGNED DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fav` (`user_id`,`target_type`,`target_id`),
  KEY `idx_fav_folder` (`folder_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_folder` FOREIGN KEY (`folder_id`) REFERENCES `favorite_folders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收藏';

CREATE TABLE `favorite_folders` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(32) NOT NULL DEFAULT '默认收藏夹',
  `sort` SMALLINT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_favfolder_user` (`user_id`),
  CONSTRAINT `fk_favfolder_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收藏夹分类';

-- =====================================================================
-- 话题域
-- =====================================================================

CREATE TABLE `topics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL COMMENT '不含 #',
  `slug` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT '',
  `post_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `follower_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `hot_score` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_topic_name` (`name`),
  UNIQUE KEY `uq_topic_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='话题';

CREATE TABLE `post_topics` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `post_id` BIGINT UNSIGNED NOT NULL,
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_post_topic` (`post_id`,`topic_id`),
  KEY `idx_pt_topic` (`topic_id`),
  CONSTRAINT `fk_pt_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pt_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态与话题关联';

CREATE TABLE `topic_follows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `topic_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_topic_follow` (`user_id`,`topic_id`),
  CONSTRAINT `fk_tf_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tf_topic` FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='关注话题';

-- =====================================================================
-- 群组域
-- =====================================================================

CREATE TABLE `groups` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `owner_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(64) NOT NULL,
  `slug` VARCHAR(64) NOT NULL,
  `description` VARCHAR(255) DEFAULT '',
  `avatar` VARCHAR(255) DEFAULT '',
  `visibility` ENUM('public','private','hidden') NOT NULL DEFAULT 'public',
  `member_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `post_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `status` ENUM('active','disbanded') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_slug` (`slug`),
  KEY `idx_group_owner` (`owner_id`),
  FULLTEXT KEY `ft_group` (`name`,`description`),
  CONSTRAINT `fk_group_owner` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群组';

CREATE TABLE `group_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role` ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
  `muted_until` DATETIME DEFAULT NULL COMMENT '禁言到期',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_member` (`group_id`,`user_id`),
  KEY `idx_gm_user` (`user_id`),
  CONSTRAINT `fk_gm_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群成员';

CREATE TABLE `group_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `message` VARCHAR(128) DEFAULT '',
  `status` ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_group_req` (`group_id`,`user_id`),
  KEY `idx_greq_group` (`group_id`,`status`),
  CONSTRAINT `fk_greq_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_greq_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入群申请';

CREATE TABLE `group_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `sender_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('text','image','file','emoji','location','system','recall') NOT NULL DEFAULT 'text',
  `body` MEDIUMTEXT,
  `reply_to_id` BIGINT UNSIGNED DEFAULT NULL,
  `recalled_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gmsg_group` (`group_id`,`id`),
  KEY `idx_gmsg_created` (`created_at`),
  CONSTRAINT `fk_gmsg_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gmsg_reply` FOREIGN KEY (`reply_to_id`) REFERENCES `group_messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群聊消息';

CREATE TABLE `group_posts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` MEDIUMTEXT,
  `visibility` ENUM('public','members') NOT NULL DEFAULT 'members',
  `like_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `comment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gpost_group` (`group_id`,`created_at`),
  KEY `idx_gpost_user` (`user_id`),
  CONSTRAINT `fk_gpost_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gpost_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群动态';

CREATE TABLE `group_announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `group_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` MEDIUMTEXT,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gann_group` (`group_id`,`is_pinned`),
  CONSTRAINT `fk_gann_group` FOREIGN KEY (`group_id`) REFERENCES `groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gann_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='群公告';

-- =====================================================================
-- 通知 / 治理 / 权限 / 安全 / 系统
-- =====================================================================

CREATE TABLE `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL COMMENT '接收者',
  `type` VARCHAR(32) NOT NULL COMMENT 'friend_request/like/comment/reply/message/group_invite/system',
  `actor_id` BIGINT UNSIGNED DEFAULT NULL COMMENT '触发者',
  `target_type` VARCHAR(32) DEFAULT '',
  `target_id` BIGINT UNSIGNED DEFAULT NULL,
  `body` VARCHAR(255) DEFAULT '',
  `read_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`,`read_at`,`created_at`),
  KEY `idx_notif_type` (`user_id`,`type`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_actor` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='统一通知中心';

CREATE TABLE `reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_id` BIGINT UNSIGNED NOT NULL,
  `target_type` ENUM('user','post','comment','group','message') NOT NULL,
  `target_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `reason` VARCHAR(32) NOT NULL,
  `detail` VARCHAR(512) DEFAULT '',
  `status` ENUM('pending','processing','resolved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_report_status` (`status`,`created_at`),
  KEY `idx_report_target` (`target_type`,`target_id`),
  CONSTRAINT `fk_report_user` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报';

CREATE TABLE `report_actions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_id` BIGINT UNSIGNED NOT NULL,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(32) NOT NULL COMMENT 'warn/delete/ban/dismiss',
  `note` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ra_report` (`report_id`),
  CONSTRAINT `fk_ra_report` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ra_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报处理记录';

CREATE TABLE `admin_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `admin_id` BIGINT UNSIGNED NOT NULL,
  `action` VARCHAR(64) NOT NULL,
  `target_type` VARCHAR(32) DEFAULT '',
  `target_id` BIGINT UNSIGNED DEFAULT NULL,
  `detail` VARCHAR(512) DEFAULT '',
  `ip` VARCHAR(45) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alog_admin` (`admin_id`),
  KEY `idx_alog_created` (`created_at`),
  CONSTRAINT `fk_alog_admin` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理员操作日志';

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(32) NOT NULL,
  `slug` VARCHAR(32) NOT NULL,
  `description` VARCHAR(128) DEFAULT '',
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色';

CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(64) NOT NULL COMMENT '权限点，域.操作，如 user.ban',
  `name` VARCHAR(64) NOT NULL,
  `group` VARCHAR(32) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='权限点';

CREATE TABLE `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rp` (`role_id`,`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_perm` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色权限';

CREATE TABLE `user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_role` (`user_id`,`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户角色';

CREATE TABLE `waf_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL,
  `category` VARCHAR(32) NOT NULL COMMENT 'sqli/xss/traversal/lfi/cmdi/phpinj/ssrf/xxe/crlf/redirect/scanner/protocol',
  `pattern` VARCHAR(512) NOT NULL COMMENT 'PCRE 正则（不含分隔符）',
  `score` SMALLINT NOT NULL DEFAULT 8,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `hit_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_waf_cat` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WAF 规则';

CREATE TABLE `waf_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `uri` VARCHAR(512) DEFAULT '',
  `rule_id` BIGINT UNSIGNED DEFAULT NULL,
  `rule_name` VARCHAR(64) DEFAULT '',
  `score` SMALLINT NOT NULL DEFAULT 0,
  `action` ENUM('log','challenge','block','ban') NOT NULL DEFAULT 'log',
  `payload_snapshot` VARCHAR(512) DEFAULT '' COMMENT '截断存储',
  `user_agent` VARCHAR(255) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_waflog_ip` (`ip`),
  KEY `idx_waflog_created` (`created_at`),
  KEY `idx_waflog_action` (`action`),
  CONSTRAINT `fk_waflog_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_waflog_rule` FOREIGN KEY (`rule_id`) REFERENCES `waf_rules` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='WAF 拦截日志';

CREATE TABLE `ip_bans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `reason` VARCHAR(128) DEFAULT '',
  `expires_at` DATETIME DEFAULT NULL COMMENT 'NULL 为永久',
  `lifted_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ipban_ip` (`ip`),
  KEY `idx_ipban_user` (`user_id`),
  KEY `idx_ipban_expires` (`expires_at`),
  CONSTRAINT `fk_ipban_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ipban_lifter` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='封禁名单';

CREATE TABLE `link_clicks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `url` VARCHAR(1024) NOT NULL,
  `domain` VARCHAR(255) DEFAULT '',
  `risk_level` ENUM('safe','caution','danger') NOT NULL DEFAULT 'safe',
  `source` VARCHAR(32) DEFAULT '' COMMENT 'post/comment/message/group/profile',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_link_user` (`user_id`),
  KEY `idx_link_created` (`created_at`),
  CONSTRAINT `fk_link_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='外链点击日志';

CREATE TABLE `link_domains` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `domain` VARCHAR(255) NOT NULL,
  `type` ENUM('white','black') NOT NULL,
  `origin` ENUM('builtin','manual') NOT NULL DEFAULT 'manual',
  `note` VARCHAR(128) DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_link_domain` (`domain`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='域名黑白名单';

CREATE TABLE `settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key` VARCHAR(64) NOT NULL,
  `value` MEDIUMTEXT,
  `group` VARCHAR(32) NOT NULL DEFAULT 'general',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点配置键值对';

CREATE TABLE `themes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(32) NOT NULL,
  `name` VARCHAR(32) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_theme_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='主题';

CREATE TABLE `language_packs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(8) NOT NULL COMMENT 'zh-CN/zh-TW/en',
  `name` VARCHAR(32) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lang_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='语言包';

CREATE TABLE `search_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `keyword` VARCHAR(128) NOT NULL,
  `result_count` INT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_search_user` (`user_id`),
  KEY `idx_search_created` (`created_at`),
  CONSTRAINT `fk_search_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='搜索历史';

CREATE TABLE `schema_migrations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(32) NOT NULL COMMENT '对应 GitHub tag，如 v1.0.0',
  `filename` VARCHAR(128) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migration` (`version`,`filename`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库迁移记录（更新系统用）';

SET FOREIGN_KEY_CHECKS = 1;
