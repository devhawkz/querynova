#!/usr/bin/env bash
# Downloads a WordPress release and the WordPress test library, then runs
# the release PHPUnit config with PHPUnit 9. The plugin's PHPUnit 11 suite
# is a different process and does not boot WordPress.
set -euo pipefail

config=${1:-}
root=$(cd "$(dirname "$0")/.." && pwd)

if [[ -z "$config" || ! -f "$root/$config" ]]; then
	echo "Usage: bin/boot-release-tests.sh phpunit.wp-release.xml.dist" >&2
	exit 1
fi

: "${QUERYNOVA_WP_VERSION:?QUERYNOVA_WP_VERSION is required}"
: "${WP_CORE_DIR:?WP_CORE_DIR is required}"
: "${WP_TESTS_DIR:?WP_TESTS_DIR is required}"
: "${QUERYNOVA_DB_NAME:?QUERYNOVA_DB_NAME is required}"
: "${QUERYNOVA_DB_USER:?QUERYNOVA_DB_USER is required}"
: "${QUERYNOVA_DB_PASSWORD:?QUERYNOVA_DB_PASSWORD is required}"
: "${QUERYNOVA_DB_HOST:?QUERYNOVA_DB_HOST is required}"

if [[ ! -f "$root/vendor/autoload.php" ]]; then
	echo "Plugin vendor autoload is missing. Run composer install --no-dev first." >&2
	exit 1
fi

php "$root/bin/wait-for-mysql.php"

tmpdir=$(mktemp -d)
trap 'rm -rf "$tmpdir"' EXIT

echo "Downloading WordPress ${QUERYNOVA_WP_VERSION}"
curl -fsSL -o "$tmpdir/wordpress.tar.gz" "https://wordpress.org/wordpress-${QUERYNOVA_WP_VERSION}.tar.gz"
rm -rf "$WP_CORE_DIR"
mkdir -p "$(dirname "$WP_CORE_DIR")"
tar -xzf "$tmpdir/wordpress.tar.gz" -C "$tmpdir"
mv "$tmpdir/wordpress" "$WP_CORE_DIR"

echo "Downloading the WordPress ${QUERYNOVA_WP_VERSION} test library"
curl -fsSL -o "$tmpdir/wordpress-develop.tar.gz" "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${QUERYNOVA_WP_VERSION}.tar.gz"
tar -xzf "$tmpdir/wordpress-develop.tar.gz" -C "$tmpdir"
develop="$tmpdir/wordpress-develop-${QUERYNOVA_WP_VERSION}"
rm -rf "$WP_TESTS_DIR"
mkdir -p "$WP_TESTS_DIR"
cp -a "$develop/tests/phpunit/includes" "$WP_TESTS_DIR/includes"
cp -a "$develop/tests/phpunit/data" "$WP_TESTS_DIR/data"
cp "$develop/wp-tests-config-sample.php" "$WP_TESTS_DIR/wp-tests-config.php"

core=$(cd "$WP_CORE_DIR" && pwd)
perl -0pi -e "s|dirname\\( __FILE__ \\) \\. '/src/'|'${core}/'|g" "$WP_TESTS_DIR/wp-tests-config.php"
perl -0pi -e "s/youremptytestdbnamehere/${QUERYNOVA_DB_NAME}/g" "$WP_TESTS_DIR/wp-tests-config.php"
perl -0pi -e "s/yourusernamehere/${QUERYNOVA_DB_USER}/g" "$WP_TESTS_DIR/wp-tests-config.php"
perl -0pi -e "s/yourpasswordhere/${QUERYNOVA_DB_PASSWORD}/g" "$WP_TESTS_DIR/wp-tests-config.php"
perl -0pi -e "s/'localhost'/'${QUERYNOVA_DB_HOST}'/g" "$WP_TESTS_DIR/wp-tests-config.php"

mkdir -p "$WP_CORE_DIR/wp-content/plugins"
ln -sfn "$root" "$WP_CORE_DIR/wp-content/plugins/querynova"

if [[ "${QUERYNOVA_INSTALL_WOOCOMMERCE:-}" == "1" ]]; then
	: "${QUERYNOVA_WC_VERSION:?QUERYNOVA_WC_VERSION is required when WooCommerce is installed}"
	echo "Downloading WooCommerce ${QUERYNOVA_WC_VERSION}"
	curl -fsSL -o "$tmpdir/woocommerce.zip" "https://downloads.wordpress.org/plugin/woocommerce.${QUERYNOVA_WC_VERSION}.zip"
	unzip -q "$tmpdir/woocommerce.zip" -d "$WP_CORE_DIR/wp-content/plugins"
	test -f "$WP_CORE_DIR/wp-content/plugins/woocommerce/woocommerce.php"
fi

tooling=/tmp/querynova-phpunit9
rm -rf "$tooling"
mkdir -p "$tooling"
cat > "$tooling/composer.json" <<'EOF'
{
  "name": "querynova/release-phpunit",
  "description": "PHPUnit 9 for the WordPress test library. Separate from the plugin PHPUnit 11 suite.",
  "require-dev": {
    "phpunit/phpunit": "11.5.56",
    "yoast/phpunit-polyfills": "3.1.2"
  }
}
EOF
composer update --no-interaction --no-progress --working-dir="$tooling"

export WP_TESTS_PHPUNIT_POLYFILLS_PATH="$tooling/vendor/yoast/phpunit-polyfills"
cd "$root"
php -d memory_limit=512M "$tooling/vendor/bin/phpunit" --configuration "$config"
