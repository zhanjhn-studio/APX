#!/usr/bin/env bash
# =====================================================================
# APX · 在服务器上应用更新包（安全：自动备份并保留配置与用户数据）
#
# 用法（在项目根目录执行）：
#   bash scripts/update.sh https://github.com/owner/repo/releases/download/v1.1.0/apx-1.1.0.zip
#
# 行为：
#   - 备份 config/database.php 与 storage/ 到 .apx-backup-<时间戳>/
#   - 下载并解包到临时目录
#   - 覆盖项目文件，但排除 config/database.php 与 storage/
#   - 还原数据库配置与用户数据
#   - （如有 migrations，可在此执行，目前为空）
# =====================================================================
set -uo pipefail

URL="${1:-}"
[ -z "$URL" ] && { echo "用法: bash scripts/update.sh <更新包URL>"; exit 1; }

command -v unzip >/dev/null 2>&1 || { echo "✗ 需要 unzip"; exit 1; }

TS="$(date +%Y%m%d%H%M%S)"
BAK=".apx-backup-$TS"
TMPD="$(mktemp -d)"
TMPZ="$(mktemp)"

echo "• 备份当前配置与数据到 $BAK ..."
mkdir -p "$BAK"
[ -f config/database.php ] && cp config/database.php "$BAK/"
[ -d storage ] && cp -r storage "$BAK/storage"

echo "• 下载更新包 ..."
if command -v curl >/dev/null 2>&1; then
  curl -fSL "$URL" -o "$TMPZ" || { echo "✗ 下载失败"; exit 1; }
elif command -v wget >/dev/null 2>&1; then
  wget -O "$TMPZ" "$URL" || { echo "✗ 下载失败"; exit 1; }
else
  echo "✗ 需要 curl 或 wget"; exit 1
fi

echo "• 解包并覆盖（保留 config/database.php 与 storage/）..."
unzip -o "$TMPZ" -d "$TMPD" >/dev/null
if command -v rsync >/dev/null 2>&1; then
  rsync -a --exclude='config/database.php' --exclude='storage' "$TMPD/" ./
else
  # 无 rsync：先拷全部，再还原受保护目录
  cp -r "$TMPD/." ./
  [ -f "$BAK/database.php" ] && cp "$BAK/database.php" config/database.php
  [ -d "$BAK/storage" ] && rm -rf storage && cp -r "$BAK/storage" storage
fi
[ -f "$BAK/database.php" ] && cp "$BAK/database.php" config/database.php

rm -rf "$TMPD" "$TMPZ"
echo "✅ 更新完成。已保留数据库配置与 storage/，备份位于 $BAK"
echo "   若需要回滚：从 $BAK 还原文件即可。"
