#!/usr/bin/env bash

# Install the official WordPress PHPUnit library and a disposable test database.
# Usage: scripts/install-wp-tests.sh [db-name] [db-user] [db-pass] [db-host] [wp-version]
set -euo pipefail

DB_NAME="${1:-wordpress_test}"
DB_USER="${2:-root}"
DB_PASS="${3:-}"
DB_HOST="${4:-localhost}"
WP_VERSION="${5:-latest}"
WP_TESTS_DIR="${WP_TESTS_DIR:-/tmp/wordpress-tests-lib}"
WP_CORE_DIR="${WP_CORE_DIR:-/tmp/wordpress}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ "${WP_VERSION}" == "latest" ]]; then
	WP_ARCHIVE="https://wordpress.org/latest.tar.gz"
else
	WP_ARCHIVE="https://wordpress.org/wordpress-${WP_VERSION}.tar.gz"
fi

if [[ ! -f "${WP_CORE_DIR}/wp-includes/version.php" ]]; then
	mkdir -p "${WP_CORE_DIR}"
	curl --fail --location --silent --show-error "${WP_ARCHIVE}" | tar xz --strip-components=1 -C "${WP_CORE_DIR}"
fi

if [[ "${WP_VERSION}" == "latest" ]]; then
	WP_RESOLVED_VERSION="$(php -r "require '${WP_CORE_DIR}/wp-includes/version.php'; echo \$wp_version;")"
else
	WP_RESOLVED_VERSION="${WP_VERSION}"
fi

WP_TESTS_REF="tags/${WP_RESOLVED_VERSION}"

if [[ ! -f "${WP_TESTS_DIR}/includes/functions.php" ]]; then
	mkdir -p "${WP_TESTS_DIR}"

	if command -v svn >/dev/null 2>&1; then
		svn export --force --quiet "https://develop.svn.wordpress.org/${WP_TESTS_REF}/tests/phpunit" "${WP_TESTS_DIR}"
	else
		DEVELOP_TAG="${WP_RESOLVED_VERSION}"

		if [[ "${DEVELOP_TAG}" =~ ^[0-9]+\.[0-9]+$ ]]; then
			DEVELOP_TAG="${DEVELOP_TAG}.0"
		fi

		DEVELOP_ARCHIVE="https://github.com/WordPress/wordpress-develop/archive/refs/tags/${DEVELOP_TAG}.zip"

		TEMP_DIR="$(mktemp -d)"
		trap 'rm -rf "${TEMP_DIR}"' EXIT
		curl --fail --location --silent --show-error "${DEVELOP_ARCHIVE}" --output "${TEMP_DIR}/wordpress-develop.zip"
		unzip -q "${TEMP_DIR}/wordpress-develop.zip" -d "${TEMP_DIR}/extract"
		TEST_SOURCE="$(find "${TEMP_DIR}/extract" -type d -path '*/tests/phpunit' -print -quit)"

		if [[ -z "${TEST_SOURCE}" ]]; then
			echo "Unable to locate the WordPress PHPUnit library in ${DEVELOP_ARCHIVE}." >&2
			exit 1
		fi

		cp -R "${TEST_SOURCE}/." "${WP_TESTS_DIR}/"
	fi
fi

CONFIG_FILE="${WP_TESTS_DIR}/wp-tests-config.php"

cp "${SCRIPT_DIR}/../tests/wp-tests-config.php.template" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_NAME}}|${DB_NAME}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_USER}}|${DB_USER}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_PASS}}|${DB_PASS}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{DB_HOST}}|${DB_HOST}|g" "${CONFIG_FILE}"
sed -i.bak "s|{{WP_CORE_DIR}}|${WP_CORE_DIR}|g" "${CONFIG_FILE}"
rm -f "${CONFIG_FILE}.bak"

MYSQL_ARGS=( "--user=${DB_USER}" )

if [[ "${DB_HOST}" == localhost:/*.sock ]]; then
	MYSQL_ARGS+=( "--socket=${DB_HOST#localhost:}" )
	MYSQL_ARGS+=( "--protocol=SOCKET" )
elif [[ "${DB_HOST}" == *:* ]]; then
	MYSQL_HOST="${DB_HOST%:*}"
	MYSQL_PORT="${DB_HOST##*:}"

	if [[ ! "${MYSQL_PORT}" =~ ^[0-9]+$ ]]; then
		echo "Invalid MySQL port in DB host: ${DB_HOST}" >&2
		exit 1
	fi

	MYSQL_ARGS+=( "--host=${MYSQL_HOST}" "--port=${MYSQL_PORT}" "--protocol=TCP" )
else
	MYSQL_ARGS+=( "--host=${DB_HOST}" )
fi

if [[ -n "${DB_PASS}" ]]; then
	MYSQL_ARGS+=( "--password=${DB_PASS}" )
fi

mysql "${MYSQL_ARGS[@]}" --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"

printf 'Installed WordPress %s test library at %s.\n' "${WP_RESOLVED_VERSION}" "${WP_TESTS_DIR}"
