#!/usr/bin/env bash
#
# bin/serve.sh — starts WordPress's built-in dev server (via WP-CLI, which
# already knows the right docroot/path from wp-cli.yml) bound to whatever
# port .env's WP_HOME actually declares, rather than a hardcoded port that
# could silently drift out of sync with it.
#
# Used by `composer dev` (build the theme bundle, then serve — see
# composer.json). Safe to run standalone too: `./bin/serve.sh`.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

WP_CLI="vendor/bin/wp"

info() { printf '\033[1;34m==>\033[0m %s\n' "$1"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$1"; }

if [ ! -f .env ]; then
    warn "No .env found — run ./bin/setup.sh first."
    exit 1
fi

# WP_HOME looks like WP_HOME='http://localhost:8000' — pull the port back
# out so this server always matches whatever bin/setup.sh (or a manual .env
# edit) actually configured, instead of a second hardcoded port to remember
# and keep in sync by hand.
WP_HOME_LINE="$(grep -E '^WP_HOME=' .env | head -1)"
PORT="$(printf '%s' "$WP_HOME_LINE" | grep -oE ':[0-9]+' | head -1 | tr -d ':')"
PORT="${PORT:-8000}"

info "Serving at http://localhost:${PORT} (Ctrl-C to stop)"
"$WP_CLI" server --host=localhost --port="$PORT"
