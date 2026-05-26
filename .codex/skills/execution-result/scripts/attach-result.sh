#!/bin/bash
set -euo pipefail

PLAN_SLUG="${1:?plan slug is required}"
PROJECT_DIR="${3:-$(pwd)}"
RESULT_FILE="${2:-$PROJECT_DIR/storage/app/plans-tmp/result-$PLAN_SLUG.md}"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"
ARTISAN_BIN="${ARTISAN_BIN:-}"

if [ ! -f "$RESULT_FILE" ]; then
  echo "Error: result file not found: $RESULT_FILE" >&2
  echo "Write the execution result markdown to that path first." >&2
  exit 1
fi

TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"
mkdir -p "$TMP_DIR"

cd "$PROJECT_DIR"

RESULT_TITLE="$(sed -n 's/^# //p' "$RESULT_FILE" | head -n 1)"

if [ -z "$ARTISAN_BIN" ]; then
  if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
  else
    ARTISAN_BIN="php artisan"
  fi
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
  TMP_BASENAME="$(date +%s)-$(basename "$RESULT_FILE")"
  TMP_HOST="$TMP_DIR/$TMP_BASENAME"
  cp "$RESULT_FILE" "$TMP_HOST"
  CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

  $ARTISAN_BIN plans:result \
    --plan="$PLAN_SLUG" \
    --file="$CONTAINER_FILE" \
    --title="${RESULT_TITLE:-}" \
    --directory="$PROJECT_DIR" \
    --created-from="codex-execution-result-skill"

  rm -f "$TMP_HOST"
else
  $ARTISAN_BIN plans:result \
    --plan="$PLAN_SLUG" \
    --file="$RESULT_FILE" \
    --title="${RESULT_TITLE:-}" \
    --directory="$PROJECT_DIR" \
    --created-from="codex-execution-result-skill"
fi
