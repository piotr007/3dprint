#!/usr/bin/env bash
set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PARENT_DIR="$(dirname "$PROJECT_ROOT")"
TIMESTAMP="$(date +%Y%m%d_%H%M)"
ARCHIVE_PATH="$PARENT_DIR/3dprint_${TIMESTAMP}.zip"

cd "$PROJECT_ROOT"

zip -r "$ARCHIVE_PATH" . \
  -x "storage/*" \
  -x "storage/**" \
  -x "bootstrap/cache/*" \
  -x "bootstrap/cache/**" \
  -x "vendor/*" \
  -x "vendor/**" \
  -x "node_modules/*" \
  -x "node_modules/**"

echo "$ARCHIVE_PATH"
