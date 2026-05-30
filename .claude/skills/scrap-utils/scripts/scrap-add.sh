#!/bin/bash
set -euo pipefail

# scrap-add.sh — Markdown ファイルから Scrap を作成する
#
# Usage: scrap-add.sh "{slug}" "{file}" [description]
#   slug        : 希望するスラッグ（重複時は自動でサフィックス付与）
#   file        : コンテンツの Markdown ファイルパス
#   description : 短い説明文（指定すると AI 要約をスキップして summary に保存）

SLUG="${1:?slug is required}"
FILE="${2:?file is required}"
DESCRIPTION="${3:-}"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

if [ ! -f "$FILE" ]; then
    echo "Error: file not found: $FILE" >&2
    exit 1
fi

TITLE=$(grep -m 1 '^# ' "$FILE" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
    TITLE="$SLUG"
fi

PAYLOAD=$(jq -n \
    --arg title "$TITLE" \
    --arg slug "$SLUG" \
    --rawfile content "$FILE" \
    '{title: $title, slug: $slug, content_markdown: $content, source_type: "note"}')

if [ -n "$DESCRIPTION" ]; then
    PAYLOAD=$(echo "$PAYLOAD" | jq --arg d "$DESCRIPTION" '. + {description: $d}')
fi

RESPONSE=$(curl -sf -X POST \
    -H "Authorization: Bearer $YOMITOKI_TOKEN" \
    -H "Content-Type: application/json" \
    "$YOMITOKI_URL/api/scraps" \
    -d "$PAYLOAD")

SCRAP_SLUG=$(echo "$RESPONSE" | jq -r '.slug')
SCRAP_ID=$(echo "$RESPONSE" | jq -r '.id')
echo "✓ Scrap created: #$SCRAP_ID \"$SCRAP_SLUG\""
