-- APX 种子数据：站点配置、主题、语言、角色与权限点
SET NAMES utf8mb4;

-- 站点配置
INSERT INTO `settings` (`key`, `value`, `group`) VALUES
('site_name', 'APX', 'general'),
('site_slogan', '连接真实的社交关系', 'general'),
('register_mode', 'open', 'auth'),
('allow_registration', '1', 'auth'),
('default_theme', 'graphite', 'appearance'),
('default_mode', 'dark', 'appearance'),
('default_language', 'zh-CN', 'i18n'),
('smtp_dev_mode', '1', 'mail'),
('maintenance', '0', 'general'),
('waf_mode', 'observe', 'security');

-- 12 套主题
INSERT INTO `themes` (`slug`, `name`, `is_default`, `is_enabled`) VALUES
('graphite', 'Graphite', 1, 1),
('minimal',  'Minimal',  0, 1),
('light',    'Light',    0, 1),
('dark',     'Dark',     0, 1),
('blue',     'Blue',     0, 1),
('purple',   'Purple',   0, 1),
('pink',     'Pink',     0, 1),
('green',    'Green',    0, 1),
('gold',     'Gold',     0, 1),
('cyber',    'Cyber',    0, 1),
('mint',     'Mint',     0, 1),
('graphite_pro', 'Graphite Pro', 0, 1);

-- 三语
INSERT INTO `language_packs` (`code`, `name`, `is_default`, `is_enabled`) VALUES
('zh-CN', '简体中文', 1, 1),
('zh-TW', '繁體中文', 0, 1),
('en',    'English',  0, 1);

-- 角色
INSERT INTO `roles` (`name`, `slug`, `description`, `is_system`) VALUES
('超级管理员', 'super_admin', '拥有全部权限', 1),
('管理员',   'admin',       '用户与内容管理', 1),
('审核员',   'moderator',   '内容审核与举报处理', 1);

-- 权限点
INSERT INTO `permissions` (`code`, `name`, `group`) VALUES
('user.view',     '查看用户',     'user'),
('user.ban',      '封禁用户',     'user'),
('user.delete',   '删除用户',     'user'),
('user.impersonate', '登录为其他用户', 'user'),
('post.view',     '查看动态',     'post'),
('post.delete',   '删除动态',     'post'),
('comment.delete','删除评论',     'post'),
('group.view',    '查看群组',     'group'),
('group.delete',  '删除群组',     'group'),
('report.view',   '查看举报',     'report'),
('report.handle', '处理举报',     'report'),
('role.manage',   '角色管理',     'admin'),
('permission.manage', '权限管理',  'admin'),
('setting.manage','站点设置',     'admin'),
('theme.manage',  '主题管理',     'admin'),
('language.manage','语言管理',    'admin'),
('waf.manage',    'WAF 管理',    'security'),
('backup.manage', '备份管理',     'system'),
('update.manage', '更新管理',     'system'),
('admin.access',  '后台访问',     'admin');

-- 超级管理员：全部权限
INSERT INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.slug = 'super_admin';

-- 管理员：除角色/权限/备份管理外的全部
INSERT INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'admin' AND p.code NOT IN ('role.manage', 'permission.manage', 'backup.manage');

-- 审核员：内容审核与举报
INSERT INTO `role_permissions` (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.slug = 'moderator' AND p.code IN ('post.view','post.delete','comment.delete','group.view','group.delete','report.view','report.handle','user.view','admin.access');
