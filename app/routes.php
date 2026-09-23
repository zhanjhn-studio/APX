<?php
declare(strict_types=1);
/**
 * 路由表。middleware 支持字符串或 ['permission', 'xxx']。
 */
return [
    ['GET',  '/login',            'AuthController@showLogin',    ['guest']],
    ['POST', '/login',            'AuthController@login',        ['guest', 'csrf']],
    ['POST', '/login/2fa',        'AuthController@login2fa',      ['guest', 'csrf']],
    ['GET',  '/register',         'AuthController@showRegister', ['guest']],
    ['POST', '/register',         'AuthController@register',     ['guest', 'csrf']],
    ['GET',  '/forgot-password',  'AuthController@showForgot',   ['guest']],
    ['POST', '/forgot-password',  'AuthController@forgot',       ['guest', 'csrf']],
    ['GET',  '/reset-password/{token}', 'AuthController@showReset', ['guest']],
    ['POST', '/reset-password',   'AuthController@reset',        ['guest', 'csrf']],
    ['GET',  '/verify-email/{token}', 'AuthController@verifyEmail', ['guest']],
    ['GET',  '/logout',           'AuthController@logout',       ['auth']],
    ['GET',  '/captcha',          'CaptchaController@show',      []],

    ['GET',  '/',                 'HomeController@index',        ['auth']],
    ['GET',  '/home',             'HomeController@index',        ['auth']],
    ['GET',  '/publish',          'HomeController@compose',      ['auth']],

    // 动态 API
    ['GET',  '/api/feed',         'PostController@feed',         ['auth']],
    ['POST', '/api/post',         'PostController@store',        ['auth', 'csrf', 'rate']],
    ['POST', '/api/upload',        'UploadController@upload',      ['auth', 'csrf', 'rate']],
    ['POST', '/api/post/like',    'PostController@like',         ['auth', 'csrf', 'rate']],
    ['POST', '/api/post/comment', 'PostController@comment',      ['auth', 'csrf', 'rate']],
    ['GET',  '/api/post/comments', 'PostController@comments',     ['auth']],
    ['POST', '/api/post/share',   'PostController@share',        ['auth', 'csrf', 'rate']],
    ['GET',  '/post/{id}',        'PostController@show',         ['auth']],
    ['POST', '/api/post/favorite','PostController@favorite',      ['auth', 'csrf']],
    ['POST', '/api/post/update',  'PostController@update',        ['auth', 'csrf', 'rate']],
    ['POST', '/api/post/delete',  'PostController@delete',        ['auth', 'csrf', 'rate']],
    ['POST', '/api/post/comment/like',   'PostController@commentLike',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/post/comment/delete', 'PostController@commentDelete', ['auth', 'csrf', 'rate']],
    ['POST', '/api/topic/unfollow','TopicController@unfollow',    ['auth', 'csrf', 'rate']],

    // 实时通道
    ['GET',  '/api/events',        'EventsController@stream',      ['auth']],
    ['GET',  '/api/events/poll',   'EventsController@poll',        ['auth']],
    ['GET',  '/api/realtime/ticket','EventsController@ticket',      ['auth']],

    // 关注
    ['POST', '/api/follow',        'FollowController@toggle',     ['auth', 'csrf', 'rate']],

    // 话题
    ['GET',  '/topic/{slug}',      'TopicController@show',        ['auth']],
    ['GET',  '/api/topic/posts',   'TopicController@posts',       ['auth']],
    ['POST', '/api/topic/follow',  'TopicController@follow',      ['auth', 'csrf', 'rate']],

    // 收藏夹
    ['GET',  '/favorites',                 'FavoriteController@index',        ['auth']],
    ['GET',  '/api/favorites',             'FavoriteController@list',         ['auth']],
    ['GET',  '/api/favorites/folders',     'FavoriteController@folders',      ['auth']],
    ['POST', '/api/favorites/folder',      'FavoriteController@folderCreate', ['auth', 'csrf', 'rate']],
    ['POST', '/api/favorites/folder/rename','FavoriteController@folderRename',['auth', 'csrf', 'rate']],
    ['POST', '/api/favorites/folder/delete','FavoriteController@folderDelete',['auth', 'csrf', 'rate']],
    ['POST', '/api/favorites/toggle',      'FavoriteController@toggle',       ['auth', 'csrf', 'rate']],

    // 发现
    ['GET',  '/discover',          'DiscoverController@index',     ['auth']],
    ['GET',  '/api/discover/feed', 'DiscoverController@feed',       ['auth']],

    // 搜索
    ['GET',  '/search',            'SearchController@index',        ['auth']],
    ['GET',  '/api/search',        'SearchController@results',      ['auth']],
    ['GET',  '/api/search/suggest','SearchController@suggest',      ['auth']],
    ['POST', '/api/search/history/clear', 'SearchController@clearHistory', ['auth', 'csrf']],

    // 个人主页
    ['GET',  '/profile',           'ProfileController@show',       ['auth']],
    ['GET',  '/profile/{username}','ProfileController@show',       ['auth']],
    ['GET',  '/api/profile/posts', 'ProfileController@posts',      ['auth']],

    // 群组（群聊 + 群动态双形态）
    ['GET',  '/groups',                    'GroupController@index',            ['auth']],
    ['GET',  '/group/{slug}',              'GroupController@show',             ['auth']],
    ['POST', '/api/group/create',          'GroupController@create',           ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/update',          'GroupController@update',           ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/join',            'GroupController@join',             ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/leave',           'GroupController@leave',            ['auth', 'csrf']],
    ['POST', '/api/group/disband',         'GroupController@disband',          ['auth', 'csrf']],
    ['POST', '/api/group/transfer',        'GroupController@transfer',         ['auth', 'csrf']],
    ['POST', '/api/group/request/respond', 'GroupController@respondRequest',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/invite',          'GroupController@invite',           ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/member/kick',     'GroupController@kick',             ['auth', 'csrf']],
    ['POST', '/api/group/member/role',     'GroupController@role',             ['auth', 'csrf']],
    ['POST', '/api/group/member/mute',     'GroupController@mute',             ['auth', 'csrf']],
    ['POST', '/api/group/announcement',    'GroupController@announcement',     ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/announcement/delete', 'GroupController@announcementDelete', ['auth', 'csrf']],
    ['POST', '/api/group/announcement/pin', 'GroupController@announcementPin', ['auth', 'csrf']],
    ['GET',  '/api/group/posts',           'GroupController@feed',             ['auth']],
    ['POST', '/api/group/post',            'GroupController@postCreate',       ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/post/delete',     'GroupController@postDelete',       ['auth', 'csrf']],
    ['POST', '/api/group/post/like',       'GroupController@postLike',         ['auth', 'csrf', 'rate']],
    ['GET',  '/api/group/chat',            'GroupController@chat',             ['auth']],
    ['POST', '/api/group/chat/send',       'GroupController@chatSend',         ['auth', 'csrf', 'rate']],
    ['POST', '/api/group/chat/recall',     'GroupController@chatRecall',       ['auth', 'csrf', 'rate']],

    // 设置
    ['GET',  '/settings',                'SettingsController@index',      ['auth']],
    ['POST', '/api/settings/appearance', 'SettingsController@appearance', ['auth', 'csrf']],
    ['POST', '/api/settings/totp/setup',   'SettingsController@totpSetup',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/totp/enable',  'SettingsController@totpEnable',  ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/totp/disable', 'SettingsController@totpDisable', ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/totp/backup',  'SettingsController@backupCodes', ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/word',         'SettingsController@wordAdd',     ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/word/remove',  'SettingsController@wordRemove',  ['auth', 'csrf']],
    ['POST', '/api/settings/mute',         'SettingsController@muteUser',    ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/mute/remove',  'SettingsController@unmuteUser',  ['auth', 'csrf']],
    ['POST', '/api/settings/block',        'SettingsController@blockUser',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/block/remove', 'SettingsController@unblockUser', ['auth', 'csrf']],
    ['POST', '/api/settings/device/trust', 'SettingsController@deviceTrust', ['auth', 'csrf']],
    ['POST', '/api/settings/device/revoke','SettingsController@deviceRevoke',['auth', 'csrf']],
    ['POST', '/api/settings/device/revoke-others', 'SettingsController@deviceRevokeOthers', ['auth', 'csrf']],
    ['POST', '/api/settings/dnd',          'SettingsController@dndSave',     ['auth', 'csrf']],
    ['POST', '/api/settings/account/delete', 'SettingsController@accountDelete', ['auth', 'csrf', 'rate']],
    ['POST', '/api/settings/account/cancel-delete', 'SettingsController@accountCancelDelete', ['auth', 'csrf']],

    // 后台（RBAC）：auth + admin + 细粒度权限点三重校验
    ['GET',  '/admin',                 'AdminController@index',    ['auth', 'admin', ['permission', 'admin.access']]],
    ['GET',  '/admin/stats',           'AdminController@stats',    ['auth', 'admin', ['permission', 'admin.access']]],
    ['GET',  '/admin/users',           'AdminController@users',    ['auth', 'admin', ['permission', 'user.view']]],
    ['POST', '/admin/users/action',    'AdminController@userAction', ['auth', 'admin', ['permission', 'user.ban'], 'csrf', 'rate']],
    ['GET',  '/admin/posts',           'AdminController@posts',    ['auth', 'admin', ['permission', 'post.view']]],
    ['POST', '/admin/posts/action',    'AdminController@postAction', ['auth', 'admin', ['permission', 'post.delete'], 'csrf', 'rate']],
    ['POST', '/admin/comments/action', 'AdminController@commentAction', ['auth', 'admin', ['permission', 'comment.delete'], 'csrf', 'rate']],
    ['GET',  '/admin/groups',          'AdminController@groups',   ['auth', 'admin', ['permission', 'group.view']]],
    ['POST', '/admin/groups/action',   'AdminController@groupAction', ['auth', 'admin', ['permission', 'group.delete'], 'csrf', 'rate']],
    ['GET',  '/admin/blocked',         'AdminController@blocked',  ['auth', 'admin', ['permission', 'user.view']]],
    ['POST', '/admin/blocked/action',  'AdminController@blockAction', ['auth', 'admin', ['permission', 'user.ban'], 'csrf', 'rate']],
    ['GET',  '/admin/reports',         'AdminController@reports',  ['auth', 'admin', ['permission', 'report.view']]],
    ['POST', '/admin/reports/action',  'AdminController@reportAction', ['auth', 'admin', ['permission', 'report.handle'], 'csrf', 'rate']],
    ['GET',  '/admin/roles',           'AdminController@roles',    ['auth', 'admin', ['permission', 'role.manage']]],
    ['POST', '/admin/roles/save',      'AdminController@roleSave', ['auth', 'admin', ['permission', 'permission.manage'], 'csrf']],
    ['POST', '/admin/roles/create',    'AdminController@roleCreate', ['auth', 'admin', ['permission', 'role.manage'], 'csrf', 'rate']],
    ['POST', '/admin/roles/delete',    'AdminController@roleDelete', ['auth', 'admin', ['permission', 'role.manage'], 'csrf']],
    ['GET',  '/admin/settings',        'AdminController@settings', ['auth', 'admin', ['permission', 'setting.manage']]],
    ['POST', '/admin/settings/save',   'AdminController@settingsSave', ['auth', 'admin', ['permission', 'setting.manage'], 'csrf']],
    ['GET',  '/admin/appearance',      'AdminController@appearance', ['auth', 'admin', ['permission', 'theme.manage']]],
    ['POST', '/admin/appearance/save', 'AdminController@appearanceSave', ['auth', 'admin', ['permission', 'theme.manage'], 'csrf']],
    ['GET',  '/admin/logs',            'AdminController@logs',     ['auth', 'admin', ['permission', 'admin.access']]],
    ['GET',  '/admin/waf',             'AdminController@waf',      ['auth', 'admin', ['permission', 'waf.manage']]],
    ['POST', '/admin/waf/rule/toggle', 'AdminController@wafRuleToggle', ['auth', 'admin', ['permission', 'waf.manage'], 'csrf']],
    ['POST', '/admin/waf/mode',        'AdminController@wafMode',  ['auth', 'admin', ['permission', 'waf.manage'], 'csrf']],
    ['POST', '/admin/waf/ban',         'AdminController@banIp',    ['auth', 'admin', ['permission', 'waf.manage'], 'csrf']],
    ['POST', '/admin/waf/unban',       'AdminController@unbanIp',  ['auth', 'admin', ['permission', 'waf.manage'], 'csrf']],
    ['GET',  '/admin/update',          'AdminController@update',   ['auth', 'admin', ['permission', 'update.manage']]],
    ['POST', '/admin/update/download', 'AdminController@updateDownload', ['auth', 'admin', ['permission', 'update.manage'], 'csrf', 'rate']],
    ['POST', '/admin/update/migrate',  'AdminController@updateMigrate', ['auth', 'admin', ['permission', 'update.manage'], 'csrf', 'rate']],

    // 举报（用户侧）
    ['POST', '/api/report',            'ReportController@submit',  ['auth', 'csrf', 'rate']],

    // 关系：好友（双向）+ 特别关注
    ['GET',  '/friends',           'FriendsController@index',     ['auth']],
    ['POST', '/api/friend/request','FriendsController@send',      ['auth', 'csrf', 'rate']],
    ['POST', '/api/friend/respond','FriendsController@respond',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/friend/special','FriendsController@special',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/friend/remove', 'FriendsController@remove',    ['auth', 'csrf', 'rate']],

    // 通知中心
    ['GET',  '/notifications',     'NotificationsController@index', ['auth']],
    ['POST', '/api/notifications/read-all', 'NotificationsController@readAll', ['auth', 'csrf']],

    // 私聊 / 群聊
    ['GET',  '/messages',          'MessageController@index',     ['auth']],
    ['GET',  '/api/messages',      'MessageController@view',       ['auth']],
    ['POST', '/api/message/send',  'MessageController@send',      ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/recall','MessageController@recall',    ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/react', 'MessageController@react',     ['auth', 'csrf', 'rate']],
    ['GET',  '/api/message/receipts','MessageController@receipts',['auth']],
    ['GET',  '/api/message/search',  'MessageController@search',   ['auth']],
    ['POST', '/api/message/pin',     'MessageController@pin',      ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/mute',    'MessageController@mute',     ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/delete',  'MessageController@deleteConv', ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/typing',  'MessageController@typing',   ['auth', 'csrf']],
    ['POST', '/api/message/read',  'MessageController@markRead',   ['auth', 'csrf', 'rate']],
    ['POST', '/api/message/start', 'MessageController@start',     ['auth', 'csrf', 'rate']],
];
