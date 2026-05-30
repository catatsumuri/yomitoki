#!/bin/bash
set -euo pipefail

# scrap-update.sh — slug を指定して Scrap を更新する
#
# Usage: scrap-update.sh "{slug}" [--title="..."] [--file="..."] [--status="..."] [--new-slug="..."]

SLUG="${1:?slug is required}"
shift

if [ -z "${YOMITOKI_URL:-}" ] || [ -z "${YOMITOKI_TOKEN:-}" ]; then
    CONFIG="$HOME/.config/yomitoki/config"
    [ -f "$CONFIG" ] && source "$CONFIG"
fi
: "${YOMITOKI_URL:?YOMITOKI_URL is not set.}"
: "${YOMITOKI_TOKEN:?YOMITOKI_TOKEN is not set.}"
command -v jq >/dev/null 2>&1 || { echo "Error: jq is required. Install with: brew install jq" >&2; exit 1; }

TITLE="" FILE="" STATUS="" NEW_SLUG=""

for arg in "$@"; do
    case "$arg" in
        --title=*)    TITLE="${arg#--title=}" ;;
        --file=*)     FILE="${arg#--file=}" ;;
        --status=*)   STATUS="${arg#--status=}" ;;
        --new-slug=*) NEW_SLUG="${arg#--new-slug=}" ;;
    esac
done

PAYLOAD='{}'

if [ -n "$TITLE" ]; then
    PAYLOAD=$(echo "$PAYLOAD" | jq --arg v "$TITLE" '. + {title: $v}')
fi

if [ -n "$FILE" ]; then
    if [ ! -f "$FILE" ]; then
        echo "Error: file not found: $FILE" >&2
        exit 1
    fi
    PAYLOAD=$(echo "$PAYLOAD" | jq --rawfile v "$FILE" '. + {content_markdown: $v}')
fi

if [ -n "$STATUS" ]; then
    PAYLOAD=$(echo "$PAYLOAD" | jq --arg v "$STATUS" '. + {status: $v}')
fi

if [ -n "$NEW_SLUG" ]; then
    PAYLOAD=$(echo "$PAYLOAD" | jq --arg v "$NEW_SLUG" '. + {slug: $v}')
fi

if [ "$PAYLOAD" = '{}' ]; then
    echo "Warning: no fields to update" >&2
    exit 0
fi

curl -sf -X PATCH \
    -H "Authorization: Bearer $YOMITOKI_TOKEN" \
    -H "Content-Type: application/json" \
    "$YOMITOKI_URL/api/scraps/$SLUG" \
    -d "$PAYLOAD"
