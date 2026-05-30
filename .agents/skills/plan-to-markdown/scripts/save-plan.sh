#!/bin/bash
set -euo pipefail

# save-plan.sh — 指定したプランファイルを DB (scraps テーブル) に保存し、Embedding と AI 要約をキューに積む
#
# Usage: save-plan.sh <plan-file> [slug] [project-dir] [description]
#   plan-file   : プランの Markdown ファイルの絶対パス (必須)
#   slug        : 英数字・ハイフンのみのスラッグ (default: "plan")
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#   description : 短い説明文。指定すると summary に保存され AI 要約をスキップする (default: "")
#
# Title はマークダウンファイルの最初の # H1 見出しから自動抽出する。
# H1 が見つからない場合はスラッグをタイトルとして使用する。

PLAN_FILE="${1:?plan-file is required}"
SLUG="${2:-plan}"
PROJECT_DIR="${3:-$(pwd)}"
DESCRIPTION="${4:-}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"
ARTISAN_BIN="${ARTISAN_BIN:-}"

if [ ! -f "$PLAN_FILE" ]; then
  echo "Error: plan file not found: $PLAN_FILE" >&2
  exit 1
fi

# マークダウンの最初の # H1 行からタイトルを抽出
TITLE=$(grep -m 1 '^# ' "$PLAN_FILE" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
  TITLE="$SLUG"
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

DESCRIPTION_ARGS=()
if [ -n "$DESCRIPTION" ]; then
  DESCRIPTION_ARGS=(--description="$DESCRIPTION")
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
  TMP_BASENAME="$(date +%s)-$(basename "$PLAN_FILE")"
  TMP_HOST="$TMP_DIR/$TMP_BASENAME"
  cp "$PLAN_FILE" "$TMP_HOST"
  CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

  $ARTISAN_BIN plans:save \
    --title="$TITLE" \
    --slug="$SLUG" \
    --file="$CONTAINER_FILE" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR" \
    "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"

  rm -f "$TMP_HOST"
else
  $ARTISAN_BIN plans:save \
    --title="$TITLE" \
    --slug="$SLUG" \
    --file="$PLAN_FILE" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR" \
    "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"
fi
