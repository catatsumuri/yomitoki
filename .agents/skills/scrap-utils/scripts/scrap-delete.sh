#!/bin/bash
set -euo pipefail

# scrap-delete.sh — slug を指定して Scrap を削除する（子 Scrap も含む）
#
# Usage: scrap-delete.sh "{slug}" [project-dir]
#   slug        : 削除対象のスラッグ（必須）
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)
#
# Note: 確認プロンプトなし（--force 固定）

SLUG="${1:-}"
PROJECT_DIR="${2:-$(pwd)}"

if [ -z "$SLUG" ]; then
    echo "Error: slug argument is required" >&2
    exit 1
fi

ARTISAN_BIN=""
cd "$PROJECT_DIR"

if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
else
    ARTISAN_BIN="php artisan"
fi

$ARTISAN_BIN scraps:delete --slug="$SLUG" --force
