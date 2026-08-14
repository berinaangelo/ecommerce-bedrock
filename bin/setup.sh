#!/usr/bin/env bash
#
# bin/setup.sh — clone-to-running-site setup for this Bedrock project.
#
# Creates the MySQL database + app user, writes .env, and runs the
# WordPress install (wp core install) via WP-CLI so there's no manual
# browser install step left at the end. Safe to re-run.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

WP_CLI="vendor/bin/wp"

# ---------- helpers ----------

info()  { printf '\033[1;34m==>\033[0m %s\n' "$1"; }
warn()  { printf '\033[1;33m!!\033[0m %s\n' "$1"; }
fail()  { printf '\033[1;31mError:\033[0m %s\n' "$1" >&2; exit 1; }

require_cmd() {
    command -v "$1" >/dev/null 2>&1 || fail "'$1' is required but not found on \$PATH."
}

# Escape a value for safe use inside a single-quoted SQL string.
sql_escape() {
    printf '%s' "$1" | sed "s/\\\\/\\\\\\\\/g; s/'/\\\\'/g"
}

prompt() {
    # prompt <var_name> <question> <default>
    local __var="$1" __question="$2" __default="${3:-}"
    local __answer
    if [ -n "$__default" ]; then
        read -r -p "$__question [$__default]: " __answer
        __answer="${__answer:-$__default}"
    else
        read -r -p "$__question: " __answer
    fi
    printf -v "$__var" '%s' "$__answer"
}

prompt_secret() {
    # prompt_secret <var_name> <question>
    local __var="$1" __question="$2"
    local __answer
    read -r -s -p "$__question: " __answer
    echo
    printf -v "$__var" '%s' "$__answer"
}

confirm() {
    local __question="$1" __answer
    read -r -p "$__question [y/N]: " __answer
    [[ "$__answer" =~ ^[Yy]$ ]]
}

# ---------- 1. preflight ----------

info "Checking prerequisites..."
require_cmd php
require_cmd composer
require_cmd pnpm
require_cmd mysql
require_cmd mysqladmin
require_cmd openssl

# ---------- 2. ensure WP-CLI is available ----------

if [ ! -x "$WP_CLI" ]; then
    info "WP-CLI not found in vendor/bin — running composer install..."
    composer install
fi
[ -x "$WP_CLI" ] || fail "composer install finished but $WP_CLI still isn't executable."

# ---------- 3. guard existing .env ----------

if [ -f .env ]; then
    warn ".env already exists."
    confirm "Overwrite it and continue?" || { info "Aborted — leaving .env untouched."; exit 0; }
fi

# ---------- 4. MySQL admin access ----------

info "MySQL admin credentials (used only to create the app database/user — not saved anywhere):"
prompt DB_ADMIN_HOST "  Host" "127.0.0.1"
prompt DB_ADMIN_PORT "  Port" "3306"
prompt DB_ADMIN_USER "  Admin username" "root"
prompt_secret DB_ADMIN_PASSWORD "  Admin password"

# ---------- 5. app database ----------

echo
info "App database:"
prompt DB_NAME "  Database name" "wordpress"
prompt DB_USER "  App DB username" "wordpress"
prompt_secret DB_PASSWORD "  App DB password (leave blank to auto-generate)"
if [ -z "$DB_PASSWORD" ]; then
    DB_PASSWORD="$(openssl rand -hex 24)"
    GENERATED_DB_PASSWORD=1
else
    GENERATED_DB_PASSWORD=0
fi

DB_HOST="$DB_ADMIN_HOST"
if [ "$DB_ADMIN_PORT" != "3306" ]; then
    DB_HOST="${DB_ADMIN_HOST}:${DB_ADMIN_PORT}"
fi

# ---------- 6. create DB + scoped user ----------

info "Creating database '$DB_NAME' and user '$DB_USER'@'$DB_ADMIN_HOST'..."

DB_NAME_ESC="$(sql_escape "$DB_NAME")"
DB_USER_ESC="$(sql_escape "$DB_USER")"
DB_PASSWORD_ESC="$(sql_escape "$DB_PASSWORD")"
DB_ADMIN_HOST_ESC="$(sql_escape "$DB_ADMIN_HOST")"

export MYSQL_PWD="$DB_ADMIN_PASSWORD"
trap 'unset MYSQL_PWD' EXIT

mysql --host="$DB_ADMIN_HOST" --port="$DB_ADMIN_PORT" --user="$DB_ADMIN_USER" <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME_ESC}\` CHARACTER SET utf8mb4;
CREATE USER IF NOT EXISTS '${DB_USER_ESC}'@'${DB_ADMIN_HOST_ESC}' IDENTIFIED BY '${DB_PASSWORD_ESC}';
GRANT ALL PRIVILEGES ON \`${DB_NAME_ESC}\`.* TO '${DB_USER_ESC}'@'${DB_ADMIN_HOST_ESC}';
FLUSH PRIVILEGES;
SQL

unset MYSQL_PWD
trap - EXIT

# ---------- 7. site basics ----------

echo
info "Site setup:"
prompt WP_HOME "  Site URL (WP_HOME)" "http://localhost:8000"
prompt SITE_TITLE "  Site title" "My Store"
prompt ADMIN_USER "  Admin username" "admin"
prompt ADMIN_EMAIL "  Admin email" ""
[ -n "$ADMIN_EMAIL" ] || fail "Admin email is required."

while true; do
    prompt_secret ADMIN_PASSWORD "  Admin password"
    prompt_secret ADMIN_PASSWORD_CONFIRM "  Confirm admin password"
    if [ "$ADMIN_PASSWORD" = "$ADMIN_PASSWORD_CONFIRM" ] && [ -n "$ADMIN_PASSWORD" ]; then
        break
    fi
    warn "Passwords didn't match (or were empty) — try again."
done

# ---------- 8. generate .env ----------

info "Writing .env..."

salt() { openssl rand -hex 32; }

cat > .env <<ENV
DB_NAME='${DB_NAME}'
DB_USER='${DB_USER}'
DB_PASSWORD='${DB_PASSWORD}'
DB_HOST='${DB_HOST}'

WP_ENV='development'
WP_HOME='${WP_HOME}'
WP_SITEURL="\${WP_HOME}/wp"

AUTH_KEY='$(salt)'
SECURE_AUTH_KEY='$(salt)'
LOGGED_IN_KEY='$(salt)'
NONCE_KEY='$(salt)'
AUTH_SALT='$(salt)'
SECURE_AUTH_SALT='$(salt)'
LOGGED_IN_SALT='$(salt)'
NONCE_SALT='$(salt)'
ENV

# ---------- 9. run the WordPress install ----------

if "$WP_CLI" core is-installed --quiet 2>/dev/null; then
    info "WordPress is already installed — skipping install step."
else
    info "Running the WordPress install (wp core install)..."
    "$WP_CLI" core install \
        --url="$WP_HOME" \
        --title="$SITE_TITLE" \
        --admin_user="$ADMIN_USER" \
        --admin_password="$ADMIN_PASSWORD" \
        --admin_email="$ADMIN_EMAIL" \
        --skip-email
fi

# WooCommerce requires "pretty" permalinks (plain `?p=` URLs break the Store
# API and the page-{slug}.php routing modules 1-5 rely on) — idempotent, so
# safe to re-run.
info "Setting permalink structure to /%postname%/..."
"$WP_CLI" rewrite structure '/%postname%/' --hard
"$WP_CLI" rewrite flush --hard

# ---------- 10. build & activate theme, activate plugins ----------

if [ -f web/app/themes/tailpress/composer.json ]; then
    info "Installing TailPress theme dependencies..."
    composer install --working-dir=web/app/themes/tailpress
    pnpm install --dir web/app/themes/tailpress
    pnpm run --dir web/app/themes/tailpress build
fi

info "Activating TailPress theme..."
"$WP_CLI" theme activate tailpress

info "Activating WooCommerce..."
"$WP_CLI" plugin activate woocommerce
"$WP_CLI" transient delete _wc_activation_redirect >/dev/null 2>&1 || true

# A fresh WooCommerce install defaults the store to "Coming soon" mode, which
# intercepts every store page (Shop/Cart/Checkout/product) with a placeholder
# regardless of theme template — not what a demo/dev site wants. Idempotent.
"$WP_CLI" option update woocommerce_coming_soon no >/dev/null

# ---------- 11. seed catalog demo products (Module 1 prerequisite) ----------

EXISTING_PRODUCT_COUNT="$("$WP_CLI" post list --post_type=product --format=count)"
if [ "$EXISTING_PRODUCT_COUNT" -gt 0 ]; then
    info "Products already exist ($EXISTING_PRODUCT_COUNT) — skipping demo product seeding."
else
    info "Seeding demo products for the catalog..."
    "$WP_CLI" wc product create --user="$ADMIN_USER" \
        --name="Pulse Wireless Earbuds" \
        --regular_price="119.00" --sale_price="89.00" \
        --images='[{"src":"https://placehold.co/800x800.jpg?text=Pulse+Earbuds"}]' \
        >/dev/null
    "$WP_CLI" wc product create --user="$ADMIN_USER" \
        --name="Nomad Backpack 22L" \
        --regular_price="128.00" \
        --images='[{"src":"https://placehold.co/800x800.jpg?text=Nomad+Backpack"}]' \
        >/dev/null
    "$WP_CLI" wc product create --user="$ADMIN_USER" \
        --name="Aero Fast Charger 65W" \
        --regular_price="49.00" \
        --in_stock="false" \
        --images='[{"src":"https://placehold.co/800x800.jpg?text=Aero+Charger"}]' \
        >/dev/null
fi

# ---------- 12. summary ----------

echo
info "Done. Site: ${WP_HOME}  |  Admin: ${WP_HOME}/wp/wp-admin"
info "Credentials are saved in .env (not printed above)."
if [ "$GENERATED_DB_PASSWORD" = "1" ]; then
    warn "Auto-generated app DB password: ${DB_PASSWORD}"
fi
