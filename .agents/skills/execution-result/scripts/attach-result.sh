#!/bin/bash
set -euo pipefail

# attach-result.sh — 実行結果を DB (scraps テーブル) に保存し、親プランに紐付ける
#
# Usage: attach-result.sh <plan-slug> [project-dir]
#   plan-slug   : 対象プランのスラッグ (plans:save 実行時に出力された slug)
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#
# 事前に storage/app/plans-tmp/result-{plan-slug}.md を書き込んでおくこと。

PLAN_SLUG="${1:?plan-slug is required}"
PROJECT_DIR="${2:-$(pwd)}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"
ARTISAN_BIN="${ARTISAN_BIN:-}"

RESULT_FILE="$PROJECT_DIR/storage/app/plans-tmp/result-${PLAN_SLUG}.md"

if [ ! -f "$RESULT_FILE" ]; then
  echo "Error: result file not found: $RESULT_FILE" >&2
  echo "Write the execution result markdown to that path first." >&2
  exit 1
fi

TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"

cd "$PROJECT_DIR"

if [ -z "$ARTISAN_BIN" ]; then
  if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
  else
    ARTISAN_BIN="php artisan"
  fi
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
  TMP_BASENAME="$(date +%s)-result-${PLAN_SLUG}.md"
  TMP_HOST="$TMP_DIR/$TMP_BASENAME"
  cp "$RESULT_FILE" "$TMP_HOST"
  CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

  $ARTISAN_BIN plans:result \
    --plan="$PLAN_SLUG" \
    --file="$CONTAINER_FILE" \
    --directory="$PROJECT_DIR"

  rm -f "$TMP_HOST"
else
  $ARTISAN_BIN plans:result \
    --plan="$PLAN_SLUG" \
    --file="$RESULT_FILE" \
    --directory="$PROJECT_DIR"
fi
