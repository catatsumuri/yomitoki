#!/bin/bash
set -euo pipefail

# save-plan.sh — 最新プランを Yomitoki API に保存する
#
# Usage: save-plan.sh [slug] [project-dir] [description]
#   slug        : 英数字・ハイフンのみのスラッグ (default: "plan")
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#   description : 短い説明文。指定すると AI 要約をスキップして summary に直接保存される

SLUG="${1:-plan}"
PROJECT_DIR="${2:-$(pwd)}"
DESCRIPTION="${3:-}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

LATEST=$(find "$HOME/.claude/plans" -name "*.md" -printf '%T@ %p\n' 2>/dev/null \
    | sort -rn \
    | head -1 \
    | cut -d' ' -f2-)

if [ -z "$LATEST" ]; then
    echo "Error: no plan files found in ~/.claude/plans/" >&2
    exit 1
fi

TITLE=$(grep -m 1 '^# ' "$LATEST" | sed 's/^# //' | tr -d '\r')
if [ -z "$TITLE" ]; then
    TITLE="$SLUG"
fi

PAYLOAD=$(jq -n \
    --arg title "$TITLE" \
    --arg slug "$SLUG" \
    --rawfile content "$LATEST" \
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
