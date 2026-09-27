#!/usr/bin/env bash
# Installs a WordPress release, activates QueryNova, and leaves the files
# ready for the browser job. It does not start a web server or a browser.
set -euo pipefail

root=$(cd "$(dirname "$0")/.." && pwd)

: "${QUERYNOVA_WP_VERSION:?QUERYNOVA_WP_VERSION is required}"
: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${WP_BASE_URL:?WP_BASE_URL is required}"
: "${QUERYNOVA_DB_NAME:?QUERYNOVA_DB_NAME is required}"
: "${QUERYNOVA_DB_USER:?QUERYNOVA_DB_USER is required}"
: "${QUERYNOVA_DB_PASSWORD:?QUERYNOVA_DB_PASSWORD is required}"
: "${QUERYNOVA_DB_HOST:?QUERYNOVA_DB_HOST is required}"

if [[ "$WP_CORE_DIR" != /tmp/* ]]; then
	echo "Refusing to install WordPress outside /tmp: ${WP_CORE_DIR}" >&2
	exit 1
fi
if [[ ! -f "$root/build/admin.js" ]]; then
	echo "build/admin.js is missing. Run npm run build before this script." >&2
	exit 1
fi
if [[ ! -f "$root/vendor/autoload.php" ]]; then
	echo "Plugin vendor autoload is missing. Run composer install --no-dev first." >&2
	exit 1
fi

php "$root/bin/wait-for-mysql.php"

rm -rf "$WP_CORE_DIR"
wp core download --path="$WP_CORE_DIR" --version="$QUERYNOVA_WP_VERSION" --force
wp config create \
	--path="$WP_CORE_DIR" \
	--dbname="$QUERYNOVA_DB_NAME" \
	--dbuser="$QUERYNOVA_DB_USER" \
	--dbpass="$QUERYNOVA_DB_PASSWORD" \
	--dbhost="$QUERYNOVA_DB_HOST" \
	--skip-check
wp core install \
	--path="$WP_CORE_DIR" \
	--url="$WP_BASE_URL" \
	--title="QueryNova" \
	--admin_user="admin" \
	--admin_password="password" \
	--admin_email="admin@example.com" \
	--skip-email

mkdir -p "$WP_CORE_DIR/wp-content/plugins"
ln -sfn "$root" "$WP_CORE_DIR/wp-content/plugins/querynova"
wp plugin activate querynova --path="$WP_CORE_DIR"
wp core version --path="$WP_CORE_DIR"
