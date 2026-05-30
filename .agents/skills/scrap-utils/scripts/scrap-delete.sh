#!/bin/bash
set -euo pipefail

# scrap-delete.sh — slug を指定して Scrap を削除する（子 Scrap も含む）
#
# Usage: scrap-delete.sh "{slug}"

SLUG="${1:?slug is required}"

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"

curl -sf -X DELETE \
    -H "Authorization: Bearer $YOMITOKI_TOKEN" \
    "$YOMITOKI_URL/api/scraps/$SLUG"

echo "✓ Deleted: $SLUG"
