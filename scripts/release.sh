#!/usr/bin/env bash
# =====================================================================
# APX · 发布到 GitHub 的辅助脚本
#
# 用法：
#   bash scripts/release.sh <版本号> [更新说明]
#   bash scripts/release.sh 1.1.0 "修复若干体验问题"
#
# 做了什么：
#   1. 校验版本号 x.y.z，并写入 VERSION（config/app.php 会自动读取）
#   2. 打包一个「干净分发包」apx-<ver>.zip
#      （排除 .git / storage 上传与日志缓存 / config/database.php / node_modules）
#   3. 生成仓库根目录 manifest.json（供后台「系统更新」检查）
#   4. 提交并打 git tag v<ver>
#   5. 提示用 gh 创建 GitHub Release（把 zip 作为附件）
#
# 之后在 GitHub 创建 Release 时，把 apx-<ver>.zip 作为附件上传，
# 并确保仓库根目录的 manifest.json 已随 tag 提交，后台即可检测更新。
# =====================================================================
set -uo pipefail

NEW_VER="${1:-}"
NOTES="${2:-自动发布}"
[ -z "$NEW_VER" ] && { echo "用法: bash scripts/release.sh 1.1.0 \"说明\""; exit 1; }
echo "$NEW_VER" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' || { echo "✗ 版本格式应为 x.y.z"; exit 1; }

# 当前版本（避免降级）
CUR="$(cat VERSION 2>/dev/null || echo '0.0.0')"
php -r "exit(version_compare('$NEW_VER','$CUR','>')?0:1);" || { echo "✗ 新版本必须高于当前 VERSION ($CUR)"; exit 1; }

echo "$NEW_VER" > VERSION

# 推导仓库 owner/name（优先 git remote，其次 config）
REPO=""
if command -v git >/dev/null 2>&1; then
  REMOTE="$(git config --get remote.origin.url 2>/dev/null || true)"
  REPO="$(echo "$REMOTE" | sed -E 's#.*[:/]([^/]+)/([^/]+?)(\.git)?$#\1/\2#')"
fi
[ -z "$REPO" ] && REPO="$(php -r 'require "config/app.php"; echo $config["update_repo"] ?? "";' 2>/dev/null || true)"
[ -z "$REPO" ] && REPO="owner/repo"

DIST="apx-$NEW_VER"
TMP="$(mktemp -d)"
echo "• 构建分发包 $DIST ..."

# 安装依赖，随包发布 vendor/ 以支持离线 / 内网部署（缺 composer 则跳过，安装期亦可 composer install）
if command -v composer >/dev/null 2>&1; then
  echo "• composer install --no-dev ..."
  COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction 2>&1 | tail -3 || echo "  (composer install 失败，分发包将不含 vendor/)"
else
  echo "• 未找到 composer，分发包不含 vendor/（部署时运行 composer install 亦可）"
fi

if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude='.git' --exclude='storage/uploads/*' --exclude='storage/logs/*' \
    --exclude='storage/cache/*' --exclude='config/database.php' --exclude='node_modules' \
    ./ "$TMP/"
else
  tar -cf - --exclude='.git' --exclude='storage/uploads/*' --exclude='storage/logs/*' \
    --exclude='storage/cache/*' --exclude='config/database.php' --exclude='node_modules' . \
    | (mkdir -p "$TMP" && tar -xf - -C "$TMP")
fi

# 写 manifest.json
printf '%s' "$NOTES" | php -r '$n=file_get_contents("php://stdin"); $m=["version"=>"' "$NEW_VER" '","released_at"=>gmdate("Y-m-d\TH:i:s\Z"),"notes"=>$n,"download"=>"https://github.com/'"$REPO"'/releases/download/v'"$NEW_VER"'/'"$DIST"'.zip","migrations"=>[]]; echo json_encode($m, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);' > manifest.json

# 打包
if command -v zip >/dev/null 2>&1; then
  ( cd "$TMP" && zip -r "$OLDPWD/$DIST.zip" . >/dev/null )
else
  tar -czf "$DIST.tar.gz" -C "$TMP" .
fi
rm -rf "$TMP"

if command -v git >/dev/null 2>&1; then
  git add VERSION manifest.json
  git commit -m "release: v$NEW_VER" || true
  git tag "v$NEW_VER"
  echo "• 已提交并打标签 v$NEW_VER"
fi

echo ""
echo "✅ 发布包: $DIST.zip （或 $DIST.tar.gz）"
echo "   仓库:   $REPO"
echo ""
echo "下一步（在 GitHub 创建 Release）："
if command -v gh >/dev/null 2>&1; then
  echo "   gh release create v$NEW_VER $DIST.zip --title \"v$NEW_VER\" --notes \"$NOTES\""
else
  echo "   1) 打开 https://github.com/$REPO/releases/new"
  echo "   2) Tag 填 v$NEW_VER，把 $DIST.zip 作为附件上传"
  echo "   3) 确保仓库根目录的 manifest.json 已随本次提交推送"
fi
