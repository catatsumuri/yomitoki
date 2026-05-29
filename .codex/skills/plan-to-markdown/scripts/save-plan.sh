#!/bin/bash
set -euo pipefail

PLAN_SLUG="${1:?plan slug is required}"
PROJECT_DIR="${3:-$(pwd)}"
PLAN_FILE="${2:-$PROJECT_DIR/storage/app/plans-tmp/plan-$PLAN_SLUG.md}"
DESCRIPTION="${4:-}"
PROJECT_NAME="$(basename "$PROJECT_DIR")"
CONTAINER_ROOT="${CONTAINER_ROOT:-/var/www/html}"
ARTISAN_BIN="${ARTISAN_BIN:-}"

if [ ! -f "$PLAN_FILE" ]; then
  echo "Error: plan file not found: $PLAN_FILE" >&2
  echo "Write the plan markdown to that path first." >&2
  exit 1
fi

TMP_DIR="$PROJECT_DIR/storage/app/plans-tmp"
mkdir -p "$TMP_DIR"

cd "$PROJECT_DIR"

PLAN_TITLE="$(sed -n 's/^# //p' "$PLAN_FILE" | head -n 1)"

if [ -z "$PLAN_TITLE" ]; then
  PLAN_TITLE="$PLAN_SLUG"
fi

if [ -z "$ARTISAN_BIN" ]; then
  if [ -x "vendor/bin/sail" ]; then
    ARTISAN_BIN="vendor/bin/sail artisan"
  else
    ARTISAN_BIN="php artisan"
  fi
fi

DESCRIPTION_ARGS=()
if [ -n "$DESCRIPTION" ]; then
  DESCRIPTION_ARGS=(--description="$DESCRIPTION")
fi

if [ "$ARTISAN_BIN" = "vendor/bin/sail artisan" ]; then
  TMP_BASENAME="$(date +%s)-$(basename "$PLAN_FILE")"
  TMP_HOST="$TMP_DIR/$TMP_BASENAME"
  cp "$PLAN_FILE" "$TMP_HOST"
  CONTAINER_FILE="$CONTAINER_ROOT/storage/app/plans-tmp/$TMP_BASENAME"

  $ARTISAN_BIN plans:save \
    --title="$PLAN_TITLE" \
    --slug="$PLAN_SLUG" \
    --file="$CONTAINER_FILE" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR" \
    --created-from="codex-plan-to-markdown-skill" \
    "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"

  rm -f "$TMP_HOST"
else
  $ARTISAN_BIN plans:save \
    --title="$PLAN_TITLE" \
    --slug="$PLAN_SLUG" \
    --file="$PLAN_FILE" \
    --project="$PROJECT_NAME" \
    --directory="$PROJECT_DIR" \
    --created-from="codex-plan-to-markdown-skill" \
    "${DESCRIPTION_ARGS[@]+"${DESCRIPTION_ARGS[@]}"}"
fi
