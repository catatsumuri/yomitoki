#!/bin/bash
set -euo pipefail

# scrap-add.sh — Markdown ファイルから Scrap を作成する
#
# Usage: scrap-add.sh "{slug}" "{file}" [project-dir] [description]
#   slug        : 希望するスラッグ（重複時は自動でサフィックス付与）
#   file        : コンテンツの Markdown ファイルパス
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#   description : 短い説明文（指定すると AI 要約をスキップして summary に保存）

SLUG="${1:-scrap}"
FILE="${2:-}"
PROJECT_DIR="${3:-$(pwd)}"
DESCRIPTION="${4:-}"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"

if [ -z "$FILE" ]; then
    echo "Error: file argument is required" >&2
    exit 1
fi

if [ ! -f "$FILE" ]; then
    echo "Error: file not found: $FILE" >&2
    exit 1
fi

# マークダウンの最初の # H1 行からタイトルを抽出
TITLE=$(grep -m 1 '^# ' "$FILE" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
    TITLE="$SLUG"
fi

TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"
mkdir -p "$TMP_DIR"

ARTISAN_BIN=""
cd "$PROJECT_DIR"

if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
else
    ARTISAN_BIN="php artisan"
fi

DESCRIPTION_ARGS=()
if [ -n "$DESCRIPTION" ]; then
    DESCRIPTION_ARGS=(--description="$DESCRIPTION")
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
    TMP_BASENAME="$(date +%s)-$(basename "$FILE")"
    TMP_HOST="$TMP_DIR/$TMP_BASENAME"
    cp "$FILE" "$TMP_HOST"
    CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

    $ARTISAN_BIN scraps:add \
        --title="$TITLE" \
        --slug="$SLUG" \
        --file="$CONTAINER_FILE" \
        "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"

    rm -f "$TMP_HOST"
else
    $ARTISAN_BIN scraps:add \
        --title="$TITLE" \
        --slug="$SLUG" \
        --file="$FILE" \
        "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"
fi
