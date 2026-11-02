#!/usr/bin/env bash
# journey.sh <steps.json> <out folder>: runs journey.cjs with Playwright's Chromium, installed once in a cache folder
# (~/.cache/caligrafy-journey, outside the project). Needs Node 18+.
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
cache="${CALIGRAFY_JOURNEY_CACHE:-$HOME/.cache/caligrafy-journey}"
command -v node >/dev/null || { echo "FAIL Node 18+ is needed (brew install node)"; exit 2; }
if [ ! -d "$cache/node_modules/playwright" ]; then
  mkdir -p "$cache"
  (cd "$cache" && npm init -y >/dev/null && npm install --silent playwright@1.49.1 && npx --yes playwright install chromium >/dev/null)
fi
NODE_PATH="$cache/node_modules" node "$here/journey.cjs" "$1" "$2"
