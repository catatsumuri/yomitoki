#!/bin/bash
set -euo pipefail

# scrap-update.sh — slug を指定して Scrap を更新する
#
# Usage: scrap-update.sh "{slug}" [project-dir] [--title="..."] [--file="..."] [--status="..."] [--new-slug="..."]
#   slug        : 更新対象のスラッグ（必須）
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#   その後のオプションはそのまま scraps:update に渡される

SLUG="${1:-}"
PROJECT_DIR="${2:-$(pwd)}"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"

if [ -z "$SLUG" ]; then
    echo "Error: slug argument is required" >&2
    exit 1
fi

# 残りの引数を収集
shift 2 2>/dev/null || shift "$#"
EXTRA_ARGS=("$@")

ARTISAN_BIN=""
cd "$PROJECT_DIR"

if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
else
    ARTISAN_BIN="php artisan"
fi

# --file= オプションがあれば Sail 経由でコピーする
FILE_ARG=""
OTHER_ARGS=()
for arg in "${EXTRA_ARGS[@]+"${EXTRA_ARGS[@]}"}"; do
    if [[ "$arg" == --file=* ]]; then
        FILE_ARG="${arg#--file=}"
    else
        OTHER_ARGS+=("$arg")
    fi
done

if [ -n "$FILE_ARG" ] && [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
    TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"
    mkdir -p "$TMP_DIR"
    TMP_BASENAME="$(date +%s)-$(basename "$FILE_ARG")"
    TMP_HOST="$TMP_DIR/$TMP_BASENAME"
    cp "$FILE_ARG" "$TMP_HOST"
    CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

    $ARTISAN_BIN scraps:update \
        --slug="$SLUG" \
        --file="$CONTAINER_FILE" \
        "${OTHER_ARGS[@]+"${OTHER_ARGS[@]}"}"

    rm -f "$TMP_HOST"
else
    FILE_OPT=()
    if [ -n "$FILE_ARG" ]; then
        FILE_OPT=(--file="$FILE_ARG")
    fi

    $ARTISAN_BIN scraps:update \
        --slug="$SLUG" \
        "${FILE_OPT[@]+"${FILE_OPT[@]}"}" \
        "${OTHER_ARGS[@]+"${OTHER_ARGS[@]}"}"
fi
