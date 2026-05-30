#!/bin/bash
set -euo pipefail

# scrap-list.sh — Scrap の slug 一覧を 1 行ずつ出力する
#
# Usage: scrap-list.sh [source-type]
#   source-type : 絞り込むソースタイプ (plan, note, execution など)

SOURCE_TYPE="${1:-}"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set. Run the install script or set the env var.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set. Run the install script or set the env var.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

URL="$YOMITOKI_URL/api/scraps?limit=1000"
if [ -n "$SOURCE_TYPE" ]; then
    URL="$URL&source_type=$SOURCE_TYPE"
fi

curl -sf \
    -H "Authorization: Bearer $YOMITOKI_TOKEN" \
    "$URL" \
    | jq -r '.data[].slug'
