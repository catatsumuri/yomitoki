#!/bin/bash
set -euo pipefail

# attach-result.sh — 実行結果を Yomitoki API に保存し、親プランに紐付ける
#
# Usage: attach-result.sh <plan-slug> [project-dir]
#   plan-slug   : 対象プランのスラッグ
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#
# 事前に storage/app/plans-tmp/result-{plan-slug}.md を書き込んでおくこと。

PLAN_SLUG="${1:?plan-slug is required}"
PROJECT_DIR="${2:-$(pwd)}"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

RESULT_FILE="$PROJECT_DIR/storage/app/plans-tmp/result-${PLAN_SLUG}.md"

if [ ! -f "$RESULT_FILE" ]; then
    echo "Error: result file not found: $RESULT_FILE" >&2
    echo "Write the execution result markdown to that path first." >&2
    exit 1
fi

TITLE=$(grep -m 1 '^# ' "$RESULT_FILE" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
    TITLE="Execution result: $PLAN_SLUG"
fi

PROJECT_NAME="$(basename "$PROJECT_DIR")"

PAYLOAD=$(jq -n \
    --arg title "$TITLE" \
    --rawfile content "$RESULT_FILE" \
    --arg parent_slug "$PLAN_SLUG" \
    --arg project "$PROJECT_NAME" \
    --arg directory "$PROJECT_DIR" \
    '{title: $title, content_markdown: $content, source_type: "execution", parent_slug: $parent_slug, project: $project, directory: $directory}')

RESPONSE=$(curl -sf -X POST \
    -H "Authorization: Bearer $YOMITOKI_TOKEN" \
    -H "Content-Type: application/json" \
    "$YOMITOKI_URL/api/scraps" \
    -d "$PAYLOAD")

SCRAP_ID=$(echo "$RESPONSE" | jq -r '.id')
echo "✓ Result attached: scrap #$SCRAP_ID → plan \"$PLAN_SLUG\""
