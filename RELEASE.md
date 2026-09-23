# APX v2.0.0 发布说明

> v2 在保持「零框架自研 MVC、关系驱动社交」内核的前提下，补齐实时性与工程化短板：Redis 统一基础设施、WebSocket 双向实时、异步队列，以及苹果化 UI 与 View Transitions 局部视图交换。破坏性变更可接受，但提供 v1 → v2 幂等迁移向导并保留全部存量数据。

## 概述

- **版本**：v2.0.0
- **发布日期**：2026-09-19
- **运行环境**：PHP 8.1+ / MySQL 5.7+ 或 MariaDB / Nginx（推荐）或 Apache；可选 Redis（缺省自动回退）
- **代码规模**：约 57 张数据表、250+ 源文件；引入必要 Composer 依赖（predis/monolog/ratchet），均提供缺省回退

## 核心特性

- **社交主链路**：问答 / 动态 / 小组 / 关注 / 私信 / 通知，覆盖内容生产与关系连接。
- **后台管理**：独立后台（基于 `admin/` 入口）完成内容审核、用户管理、站点配置。
- **安装向导**：浏览器访问 `install.php` 完成环境检测 → 建库建表 → 创建管理员；已安装实例访问 `install.php` 即出现「升级到 v2」入口。
- **一键脚本部署**：`scripts/setup.sh` 在全新 Linux 服务器自动安装 Nginx + PHP-FPM(8.x) + MariaDB + Redis，建库建表导入种子，运行 `composer install` 并启用 `apx-ws` / `apx-worker` systemd 单元（含 Nginx `/ws` 反代）。
- **Redis 基础设施**：可切换缓存驱动（Redis 优先、文件回退）、Redis pub/sub 实时通道，统一承载缓存 / 实时 / 队列。
- **WebSocket 实时**：`bin/ws-server.php` 常驻进程，连接票据鉴权 + 心跳；前端 `realtime.js` 优先 WS、SSE/轮询回退。
- **异步队列**：Redis list + `blpop` 的 `QueueService` 与 `bin/worker.php`，将邮件发送、缩略图生成等重活移出请求链路；无 Redis 时同步回退。
- **搜索与缓存优化**：MySQL FULLTEXT（ngram 中文分词）增强 + Redis 缓存热点搜索与联想建议，兼容 5.7。
- **UI 现代化**：设计令牌与动效时长重写（苹果类、低跳动），`nav.js` 用 View Transitions 做局部视图交换，减少整页跳转动画；兼容 12 主题三档外观。
- **自动更新系统**：`manifest.json` 记录最新版本与下载地址，后台「系统更新」或 `scripts/update.sh` 可一键检测并升级。
- **定时清理**：`cron/cleanup.php` 处理过期缓存与临时数据。

## 快速部署（三种方式）

### 方式一：仅上传 install.sh（最省事）
把 `scripts/install.sh` 上传到服务器任意目录，执行：

```bash
sudo bash install.sh
```

脚本会从 GitHub 仓库拉取完整项目并解包，完成后提示访问 `http://你的域名/install.php` 走安装向导。

### 方式二：全新 Linux 服务器一键装（含环境）
把整个仓库上传后执行：

```bash
sudo bash scripts/setup.sh
# 免交互示例：
WEB_DOMAIN=apx.example.com DB_NAME=apx DB_USER=apx DB_PASS='S3cret!' \
ADMIN_USER=admin ADMIN_PASS='admin123' sudo -E bash scripts/setup.sh
```

`setup.sh` 自动安装 Nginx + PHP-FPM(8.x) + MariaDB，建库建表导入种子，写 `config/database.php`，生成 Nginx 站点并配置 `storage/` 权限。支持 Debian/Ubuntu（apt）与 CentOS/Rocky/Alma（dnf），PHP 低于 8.0 时 Debian 自动启用 sury 源。

### 方式三：已有 Web 环境
1. Web 根目录指向 `public/`（或根目录 + `/assets` 别名，参考 `nginx.conf.example`）。
2. 按 `nginx.conf.example` 配置 rewrite 与上传限制。
3. 访问 `/install.php` 完成初始化。
4. 给 `storage/` 写入权限。

## 下载

- **分发包**：`apx-1.0.0.zip`（本 Release 附件，已剔除 `.git`、运行数据、`.codebuddy` 与敏感配置）。
- **更新源**：`manifest.json`（随 tag 入库，指向本 Release 的 zip）。

## 升级

### v1 → v2（保留存量数据，幂等迁移）
- 浏览器访问 `install.php`，在「升级到 v2」面板点击升级（执行前自动备份 `config/database.php` 与 `storage/`）；或
- 服务器执行：`php scripts/migrate-v1-v2.php`（推荐，含交互确认与自动备份）。
- 迁移仅做结构增量（如新增 FULLTEXT 索引），不改动或删除任何业务数据；已应用过的迁移不会重复执行。

### 常规版本更新
- 后台「系统更新」按 `manifest.json` 检测新版并下载。
- 或服务器执行：`sudo bash scripts/update.sh`。

## 目录结构（精简）

```
apx/
├── public/              入口目录（生产建议 root 指向此处）
├── admin/               后台入口
├── api/                 接口层
├── core/                核心框架（路由/DB/视图/辅助）
├── model/               数据模型（57 表映射）
├── modules/             业务模块（问答/动态/小组/私信…）
├── view/                视图模板
├── storage/             运行数据（uploads/logs/cache，gitignore）
├── config/              配置（database.php 不入库）
├── cron/                定时任务
├── scripts/             install.sh / setup.sh / update.sh / release.sh
├── nginx.conf.example   Nginx 参考配置（方式 A/B）
├── manifest.json        更新清单（指向本 Release zip）
└── install.php          安装向导
```

## 安全说明

- 上传目录禁止执行 PHP；`config/`、`storage/`、`database` 拒绝 Web 访问。
- `config/database.php` 含数据库凭据，已加入 `.gitignore`，不会进入仓库或分发包。
- 安装完成后建议删除 `install.php`。

## 已知事项

- `scripts/setup.sh` 编写环境为 Windows，未做完整 Linux 实测，首次在 Linux 上执行如遇问题请反馈。
- 分发包已随附 `vendor/`（Composer 依赖）以支持离线 / 内网部署；不含 `.git`、`.codebuddy`、`config/database.php` 与 `storage/` 运行数据。若 `vendor/` 缺失，部署期运行 `composer install` 亦可。
- 未安装 Redis 或未运行 `composer install` 时，缓存 / 实时 / 队列自动回退自研实现，整站仍可正常运行（仅失去 WebSocket 双向实时与异步队列收益）。

## 许可证

详见仓库 LICENSE 文件。
