# APX

以「人与人的社交关系」为核心的现代社交平台。取消公共聊天室，关系驱动内容流。PHP 8.1+ 自研 MVC + 适度 Composer 依赖（Predis / Monolog / Ratchet，均提供缺失时回退实现），原生 ES Module 前端，可内网离线运行（vendor 随包或安装期 composer install）。

## 技术栈

- **后端**：PHP 8.1+ 自研 MVC（无框架），PDO 预处理；引入必要 Composer 依赖（`predis/predis` 纯 PHP Redis 客户端、`monolog/monolog`、`cboden/ratchet` WebSocket），均缺省自动回退到自研实现（无 Redis 时用文件缓存、SSE 文件队列，不依赖任何扩展）；SMTP 用 `fsockopen` 自实现
- **数据库**：MySQL 5.7 兼容（兼容 8.0 / MariaDB 10.x），InnoDB + `utf8mb4_unicode_ci`
- **前端**：原生 ES Module，零构建零 CDN；PHP SSR 首屏 + AJAX 局部增强
- **实时**：WebSocket（Ratchet 常驻进程 + Redis pub/sub，连接鉴权与心跳）为主通道，SSE 推送 + 轮询回退；前端 `realtime.js` 优先 WS 并自动降级
- **队列**：Redis list + `blpop` 的异步队列（`bin/worker.php` 常驻消费），将邮件发送、缩略图生成等重活移出请求链路；无 Redis 时同步回退
- **基础设施**：Redis 统一承载缓存 / 实时 / 队列三大场景（`config/app.php` 的 `cache_driver` / `realtime_driver` 可切换，缺 Redis 自动回退）
- **安全**：评分制 WAF（观察 / 防御模式可在线切换）、外链二次确认守护、GD 图形验证码、CSRF 双通道、登录限流、TOTP 两步验证、设备信任与会话纪元失效

## 目录结构

```
composer.json        Composer 依赖与 PSR-4 自动加载（App\ → app/）
vendor/              Composer 产物（随包或安装期 composer install 生成；缺失则自动回退自研实现）
bin/                 ws-server.php（WebSocket 常驻进程）/ worker.php（队列 worker 常驻进程）
public/            前端控制器、安装向导、静态资源（assets/css、assets/js、assets/uploads）
app/Core/          Router / Request / View / Database / Session / Csrf / Validator / Cache / Logger /
                   LinkRenderer / I18n / Theme / WafService / Redis（Predis 封装，可切换缓存驱动）
app/Middleware/    SecureHeaders / Waf / Locale / Theme / RateLimit / Auth / Guest / Admin / Permission / Csrf
app/Controllers/   Auth / Home / Post / Topic / Favorite / Follow / Friends / Message / Notifications /
                   Discover / Search / Profile / Settings / Group / Report / Upload / Captcha / Events / Admin
app/Models/        一表一模型
app/Interfaces/    RealtimeInterface / NotifierInterface / StorageInterface（推送与存储契约，便于替换实现）
app/Services/      Auth / Waf / Captcha / LinkGuard / Upload / Mail / Totp / Relation / Message / Post / Group /
                   Favorite / Topic / Notification / Search / Profile / Report / Settings / SiteSettings /
                   Device / Achievement / Admin / Migration / Update / Permission / Realtime /
                   Queue（异步队列）/ RedisRealtime（WS+Redis 实现）/ RealtimeTicket（WS 一次性票据）
app/Views/         layouts / partials / pages
config/            app / database.example / security / mail / upload / redis.example
database/          schema.sql（57 表）/ seed.sql / waf_rules.sql / migrations/（增量迁移，v2 含 FULLTEXT 索引）
lang/              zh-CN / zh-TW / en（三语 key 完全对齐）
scripts/           install.sh / setup.sh / release.sh / update.sh / migrate-v1-v2.php（v1→v2 迁移向导）
systemd/           apx-ws.service / apx-worker.service（WebSocket 与队列常驻进程单元）
storage/           uploads / logs / cache（更新保护，禁止执行 PHP）
```

## 安装

### 一行脚本部署（推荐）

`scripts/setup.sh` 会在**全新 Linux 服务器**上自动完成：安装 Nginx + PHP-FPM(8.x) + MariaDB、建库建表并导入种子、
写数据库配置、生成 Nginx 站点、创建后台管理员、设置目录权限。无需 Docker，无需手工配置。

```bash
# 1) 拉取代码
git clone https://github.com/OWNER/REPO.git apx && cd apx

# 2) 一行部署（root 执行）
sudo bash scripts/setup.sh
```

完成后访问 `http://你的域名/` 即可，后台 `/admin`。可用环境变量免交互：

```bash
WEB_DOMAIN=apx.example.com DB_NAME=apx DB_USER=apx DB_PASS='S3cret!' \
ADMIN_USER=admin ADMIN_PASS='admin123' sudo -E bash scripts/setup.sh
```

> 支持 Debian / Ubuntu（apt）与 CentOS / Rocky / Alma（dnf）。默认 `root` 指向 `public/`（方式 A，仅暴露入口与静态资源）；
> 如需根目录部署，加 `WEB_ROOT_MODE=root`。脚本会自动选择对应 Nginx 配置与 PHP-FPM 单元名。
> 若服务器已存在 Nginx + PHP + MySQL（只想建库建表），用 `scripts/install.sh`。

## 部署与路由

APX 默认使用**查询串路由**（`index.php?r=/路径`），因此**无需任何服务器 URL 重写**即可运行；
同时提供**实体 `.php` 入口文件**（`/login.php`、`/home.php`、`/groups.php`、`/settings.php` …，项目根与 `public/` 各一份），
由 `app.file_urls = true` 控制，`.php` 天然由 PHP 处理，同样零服务器配置。

静态资源（CSS/JS/上传）由 `asset()` 依据**当前入口脚本所在目录**自动探测真实路径：

- web 根指向 `public/` → `/assets/...`
- web 根指向项目根 → `/public/assets/...`

> 可选增强：若要 `/login` 这类干净地址，先让服务器把未知路径转发到入口
> （Nginx：`try_files $uri $uri/ /index.php?$query_string;`），再把 `config/app.php` 的
> `'pretty_urls' => true`。**服务器未配置转发时不要开启**，否则整站 404（改回 `false` 即恢复）。

## 管理后台

登录后访问 `/admin`（或 `/admin.php`）。入口需具备 `admin.access` 权限，超级管理员（`role_level >= 100`）自动拥有全部权限。

| 模块 | 路径 | 权限点 |
| --- | --- | --- |
| 概览 / 统计 | `/admin`、`/admin/stats` | `admin.access` |
| 用户管理（封禁、超管、角色分配） | `/admin/users` | `user.view` / `user.ban` |
| 内容管理（动态、评论） | `/admin/posts` | `post.view` / `post.delete` / `comment.delete` |
| 群组管理（解散、恢复） | `/admin/groups` | `group.view` / `group.delete` |
| 黑名单（用户拉黑关系，可解除） | `/admin/blocked` | `user.view` / `user.ban` |
| 举报处理（警告/删除/封禁/驳回） | `/admin/reports` | `report.view` / `report.handle` |
| 角色与权限矩阵 | `/admin/roles` | `role.manage` / `permission.manage` |
| 站点设置（名称/标语/公告/注册策略/默认外观/WAF 模式） | `/admin/settings` | `setting.manage` |
| 主题与语言包开关、设默认 | `/admin/appearance` | `theme.manage` / `language.manage` |
| 日志（管理员操作日志 + 登录日志） | `/admin/logs` | `admin.access` |
| WAF（规则开关、日志筛选、封禁/解封、模式切换） | `/admin/waf` | `waf.manage` |
| 系统更新（检测、下载、数据库迁移、备份） | `/admin/update` | `update.manage` |

内置角色：`super_admin`（全部权限）、`admin`（除角色/权限/备份外）、`moderator`（内容审核与举报）。
可在 `/admin/roles` 新建自定义角色并勾选权限点，再到 `/admin/users` 为用户分配。

用户侧举报入口：动态详情、个人主页、群组页均有「举报」，提交至 `/api/report`，由后台举报处理页统一下发处置并留痕（`report_actions`）。

## 数据库迁移

增量变更放在 `database/migrations/`（命名与约束见该目录 `README.md`），后台 **系统更新 → 数据库迁移** 一键执行，
已执行文件记录在 `schema_migrations`，不会重复运行。全新安装只用 `schema.sql`。

## 一键安装（脚本）

`scripts/install.sh`（Linux / WSL / Git Bash）会建库建表、写 `config/database.php`、创建后台管理员、设置 `storage/` 权限：

```bash
# 交互模式
bash scripts/install.sh

# 免交互（CI / 容器）
DB_HOST=127.0.0.1 DB_USER=apx DB_PASS=secret \
ROOT_DB_USER=root ROOT_DB_PASS=rootpass \
ADMIN_USER=admin ADMIN_PASS=admin123 \
bash scripts/install.sh
```

Windows 本地开发可直接用：`php -S 0.0.0.0:8080 -t public`（或任意入口文件）。

## 发布与更新

版本号单一来源为仓库根目录 `VERSION`：

```bash
bash scripts/release.sh 1.1.0 "修复若干体验问题"
```

脚本会写入 `VERSION`、打包干净的 zip（排除 `.git`、`storage/*`、`config/database.php`）、生成 `manifest.json`、提交并打 `v1.1.0` 标签。

**更新流程**

1. 管理员登录后台 → **系统更新**：对比本地 `VERSION` 与远端 tag/manifest，显示新版本、更新说明与下载入口。
2. 点击「下载到服务器」可把更新包落盘到 `storage/cache/updates/`（**仅下载，不自动覆盖站点**，避免更新中站点不可用）。
3. 应用更新的两种方式：
   - **脚本（推荐，自动备份）**：
     ```bash
     bash scripts/update.sh https://github.com/owner/repo/releases/download/v1.1.0/apx-1.1.0.zip
     ```
     会先备份 `config/database.php` 与 `storage/` 到 `.apx-backup-<时间戳>/`，覆盖后自动还原受保护目录。
   - **手动**：解压覆盖，保留 `config/` 与 `storage/`。
4. 若该版本包含 `database/migrations/*.sql`，回到后台 **系统更新 → 执行待应用迁移**。
5. **回滚**：从对应 `.apx-backup-*` 目录还原 `config/` 与 `storage/` 即可（备份列表在更新页可见）。

更新检测依赖 `config/app.php` 的 `app.update_repo`（`owner/repo`）、`app.update_channel`（`tags` / `releases` / `manifest`）、`app.update_branch`。

## 功能进度

- **P1 地基与安全**：安装向导、57 表 schema、评分制 WAF、GD 验证码、外链守护、三层设计令牌与 12 主题三档外观、三语包、认证全流程（注册/登录/2FA/找回/邮箱验证）
- **P2 关系与通讯**：关注 / 好友 / 密友三层关系；私聊全功能——会话列表与未读数、文本/表情/图片/文件/位置消息、撤回（2 分钟窗口）、引用回复、复制、会话内消息搜索与跳转、置顶、免打扰、删除会话、正在输入、表情回应、已读回执与已读人数、阅后即焚与定时销毁；统一通知中心；实时事件通道（SSE 主通道 + 5 秒轮询回退）
- **P3 内容与发现**：动态发布与互动（多图/视频、四级可见性、多级评论、转发、收藏夹分类）、#话题# 与话题页、发现页、全局搜索（动态/用户/话题/群组四类、实时建议下拉、热门搜索、搜索历史、无限滚动、关键词高亮）、时间线无限滚动
- **P4 群组与设置**：群组双形态（群聊 + 群动态，含公告、禁言、三级角色、申请审批、邀请、退出解散）、个人主页等级/徽章/成就、设置中心（TOTP + 备份码、设备信任与远程下线、屏蔽词、屏蔽用户、黑名单、免打扰、账号注销冷静期）
- **P5 后台与治理**：RBAC 角色权限矩阵、用户/内容/群组/黑名单管理、举报全链路、数据统计与趋势、管理员操作日志与登录日志、WAF 面板（模式切换/筛选/封禁）、主题语言管理、站点设置（含全站公告与维护模式在线开关）、基于 GitHub tag 的更新系统（下载 + 迁移 + 备份回滚）、Nginx / 脚本部署配套

## v2 升级说明

v2 在保持「零框架自研 MVC、关系驱动社交」内核的前提下，补齐实时性与工程化短板：用 Redis 统一缓存 / 实时 / 队列，用 WebSocket 实现真正双向实时，用异步队列剥离重活，并对 UI 做苹果化与 View Transitions 局部视图交换。

- **破坏性变更可接受**，但提供 **v1 → v2 迁移向导**，且保留全部存量数据。
- 全新安装：按上文「一行脚本部署」即可；`scripts/setup.sh` 会自动安装 Redis、运行 `composer install` 并启用 `apx-ws` / `apx-worker` systemd 单元（含 Nginx `/ws` 反代）。
- 已有 v1 站点升级（两种方式，均幂等且执行前自动备份）：
  - 浏览器访问 `install.php`，在「升级到 v2」面板点击升级；或
  - 服务器执行 `php scripts/migrate-v1-v2.php`（推荐，含交互确认）。
- 缺 Redis / 未运行 `composer install` 时，缓存自动回退文件实现、实时回退 SSE、队列同步执行——**整站不依赖 Redis 亦可运行**，只是失去 WS 双向实时与异步队列的收益。

## 实时通道与队列

`app/Interfaces/RealtimeInterface.php` 定义推送契约。`app/Services/Realtime.php` 按 `config/app.php` 的 `realtime_driver`（`ws` / `sse`）选择实现：

- **WebSocket（默认 ws）**：`bin/ws-server.php`（Ratchet 常驻进程）校验一次性票据后绑定用户连接，订阅 Redis 频道转发事件；含 30 秒心跳、连接级在线态查询。由 `systemd/apx-ws.service` 托管。
- **SSE 回退**：`/api/events`（SSE）持续下发队列事件，闲置时心跳，前端自动重连；`/api/events/poll` 轮询返回增量与未读计数。
- 业务侧只需 `Realtime::push($userId, $event, $payload)`（`NotificationService` 与 `MessageService` 已接入），无需关心底层是 WS 还是 SSE；Redis 不可用时自动回退文件队列。

异步队列：`app/Services/QueueService.php` 以 Redis list + `blpop` 派发任务，`bin/worker.php` 常驻消费（邮件发送、缩略图生成等）。无 Redis 时 `queue()` 同步回退。

前端 `public/assets/js/realtime.js` 优先建立 WebSocket，失败自动降级 SSE/轮询，统一渲染徽标与提示，并广播 `window` 事件 `apx:realtime`；`public/assets/js/nav.js` 用 View Transitions 做局部视图交换，减少整页跳转动画。

## 开发约定

- 文件首行必须为 `declare(strict_types=1)`；禁用 MySQL 5.7 不支持的语法（CTE / 窗口函数 / `JSON` 列类型 / `ALTER ... RENAME COLUMN`）。PHP 8.1+ 语法（`enum` / `readonly` / `never`）已放开。
- 引入第三方依赖（Composer）必须保证**缺省可回退**：任何 `class_exists` / `is_file(vendor/autoload.php)` 未命中时，自动降级到自研实现，绝不因缺 Redis / 缺 vendor 导致整站 500。
- 所有输出走 `e()` 转义，URL 走 `route()`，文案走 `__()` / `trans_choice()`；新增文案必须同步 `zh-CN` / `zh-TW` / `en` 三份。
- 业务规则、权限三重校验、事务、通知与日志下沉到 Service；Controller 只取参与组织响应。
- 上传必须 MIME + 扩展名双校验、GD 重建、随机文件名、realpath 前缀比对（WS 鉴权与队列任务同样遵循既有安全清单）。
- 管理员治理动作必须写 `admin_logs`；日志禁止记录密码、令牌与完整请求体（Monolog 替换简单 Logger 后依旧遵循）。

## 常见问题排查

| 现象 | 排查方向 |
| --- | --- |
| 页面无样式、按钮点不动 | 静态资源 404：确认 `public/assets/` 已上传，且 web 根与 `asset()` 探测一致（F12 Network 看 CSS 状态码）。 |
| 访问 `/login` 报 404 | 未开启干净 URL：服务器缺 `try_files`。改用 `/login.php`，或把 `config/app.php` 的 `pretty_urls` 改回 `false`。 |
| 访问 `/login.php` 也 404 | 实体入口文件未上传到位（项目根与 `public/` 各需一份）。 |
| 登录后立刻过期 | 服务器时间与时区不一致导致会话/令牌校验失败；确认 `date.timezone` 且系统时间准确。 |
| 上传失败 | `storage/`（及 `public/assets/uploads/`）不可写，或超过 `upload_max_filesize` / `client_max_body_size`。 |
| 后台某个菜单看不到 | 当前账号缺少对应权限点：用超级管理员在 `/admin/roles` 勾选，或在 `/admin/users` 直接授予超管。 |
| 邮件收不到 | 默认 `smtp_dev_mode = 1` 只记录不发送；在 `/admin/settings` 关闭调试模式并配置 `config/mail.php`。 |
| 数据库连不上 | 检查 `config/database.php` 是否存在且参数正确；未安装时访问 `/install.php`。 |
| 更新后 500 | 查看 `storage/logs/YYYY-MM-DD.log`；确认已执行 `database/migrations/` 中待应用迁移；必要时从 `.apx-backup-*` 回滚。 |
