#!/bin/bash
set -euo pipefail

# save-plan.sh — 最新プランを DB (scraps テーブル) に保存し、Embedding と AI 要約をキューに積む
#
# Usage: save-plan.sh [title-slug] [project-dir]
#   title-slug  : 英数字・ハイフンのみのスラッグ (default: "plan")
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#
# Note: artisan は Sail コンテナ内で動くため、ホスト側の ~/.claude/plans/ は
#       見えない。storage/app/ 経由で一時コピーしてからパスを渡す。

TITLE="${1:-plan}"
PROJECT_DIR="${2:-$(pwd)}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"
ARTISAN_BIN="${ARTISAN_BIN:-}"

# ~/.claude/plans/ の最新ファイルを取得（更新時刻で降順ソート）
LATEST=$(find "$HOME/.claude/plans" -name "*.md" -printf '%T@ %p\n' 2>/dev/null \
  | sort -rn \
  | head -1 \
  | cut -d' ' -f2-)

if [ -z "$LATEST" ]; then
  echo "Error: no plan files found in ~/.claude/plans/" >&2
  exit 1
fi

TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"
mkdir -p "$TMP_DIR"

cd "$PROJECT_DIR"

if [ -z "$ARTISAN_BIN" ]; then
  if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
  else
    ARTISAN_BIN="php artisan"
  fi
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
  TMP_BASENAME="$(date +%s)-$(basename "$LATEST")"
  TMP_HOST="$TMP_DIR/$TMP_BASENAME"
  cp "$LATEST" "$TMP_HOST"
  CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

  $ARTISAN_BIN plans:save \
    --title="$TITLE" \
    --file="$CONTAINER_FILE" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR"

  rm -f "$TMP_HOST"
else
  $ARTISAN_BIN plans:save \
    --title="$TITLE" \
    --file="$LATEST" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR"
fi
