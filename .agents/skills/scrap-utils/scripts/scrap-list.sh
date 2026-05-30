#!/bin/bash
set -euo pipefail

# scrap-list.sh — Scrap の slug 一覧を 1 行ずつ出力する
#
# Usage: scrap-list.sh [source-type] [project-dir]
#   source-type : 絞り込むソースタイプ (plan, note, execution など)
#   project-dir : プロジェクトルートの絶対パス (default: 現在のディレクトリ)

SOURCE_TYPE="${1:-}"
PROJECT_DIR="${2:-$(pwd)}"

ARTISAN_BIN=""
cd "$PROJECT_DIR"

if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
else
    ARTISAN_BIN="php artisan"
fi

SOURCE_TYPE_ARG=()
if [ -n "$SOURCE_TYPE" ]; then
    SOURCE_TYPE_ARG=(--source-type="$SOURCE_TYPE")
fi

$ARTISAN_BIN scraps:list --slugs-only --limit=1000 "${SOURCE_TYPE_ARG[@]+"${SOURCE_TYPE_ARG[@]}"}"
