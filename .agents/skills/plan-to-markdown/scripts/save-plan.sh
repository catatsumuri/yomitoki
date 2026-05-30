#!/bin/bash
set -euo pipefail

# save-plan.sh — 指定したプランファイルを Yomitoki API に保存する
#
# Usage: save-plan.sh <plan-file> [slug] [project-dir] [description]
#   plan-file   : プランの Markdown ファイルの絶対パス（必須）
#   slug        : 英数字・ハイフンのみのスラッグ (default: "plan")
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#   description : 短い説明文。指定すると AI 要約をスキップして summary に直接保存される

PLAN_FILE="${1:?plan-file is required}"
SLUG="${2:-plan}"
PROJECT_DIR="${3:-$(pwd)}"
DESCRIPTION="${4:-}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

if [ ! -f "$PLAN_FILE" ]; then
    echo "Error: plan file not found: $PLAN_FILE" >&2
    exit 1
fi

TITLE=$(grep -m 1 '^# ' "$PLAN_FILE" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
    TITLE="$SLUG"
fi

PAYLOAD=$(jq -n \
    --arg title "$TITLE" \
    --arg slug "$SLUG" \
    --rawfile content "$PLAN_FILE" \
    --arg project "$PROJECT_NAME" \
    --arg directory "$PROJECT_DIR" \
    '{title: $title, slug: $slug, content_markdown: $content, source_type: "plan", project: $project, directory: $directory}')

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
echo "✓ Plan saved: scrap #$SCRAP_ID \"$SCRAP_SLUG\""
