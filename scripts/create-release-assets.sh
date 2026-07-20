#!/usr/bin/env bash

set -euo pipefail

export LC_ALL=C
export TZ=UTC

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONFIG_PATH="${PLUGIN_ROOT}/wordpress-org/deployment.json"
TAG_NAME="${1:?Usage: create-release-assets.sh <vX.Y.Z>}"
VERSION="${TAG_NAME#v}"

if [[ "${TAG_NAME}" != "v${VERSION}" || ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	echo "A semantic vX.Y.Z release tag is required." >&2
	exit 1
fi

PACKAGE_SLUG="$(jq -er '.packageSlug' "${CONFIG_PATH}")"
MAIN_PLUGIN_FILE="$(jq -er '.mainPluginFile' "${CONFIG_PATH}")"
ARCHIVE_TEMPLATE="$(jq -er '.archivePath' "${CONFIG_PATH}")"
ARCHIVE_PATH="${PLUGIN_ROOT}/${ARCHIVE_TEMPLATE//\{version\}/${VERSION}}"
OUTPUT_DIRECTORY="$(dirname "${ARCHIVE_PATH}")"
CHECKSUM_PATH="${ARCHIVE_PATH}.sha256"
MANIFEST_PATH="${ARCHIVE_PATH%.zip}.manifest.json"
BUILD_COMMAND="$(jq -er '.buildCommand' "${CONFIG_PATH}")"
VERIFY_COMMAND="$(jq -er '.verifyCommand' "${CONFIG_PATH}")"

if [[ "${ARCHIVE_TEMPLATE}" = /* || "${ARCHIVE_TEMPLATE}" == *".."* || "${ARCHIVE_TEMPLATE}" != *"{version}"* ]]; then
	echo "The configured archive path must be a relative {version} template." >&2
	exit 1
fi

PLUGIN_VERSION="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "${PLUGIN_ROOT}/${MAIN_PLUGIN_FILE}")"
if [[ "${PLUGIN_VERSION}" != "${VERSION}" ]]; then
	echo "The release tag does not match the plugin header version." >&2
	exit 1
fi

mkdir -p "${OUTPUT_DIRECTORY}"
rm -f "${ARCHIVE_PATH}" "${CHECKSUM_PATH}" "${MANIFEST_PATH}"

export ARCHIVE_PATH OUTPUT_DIRECTORY
(
	cd "${PLUGIN_ROOT}"
	bash -euo pipefail -c "${BUILD_COMMAND}"
	bash -euo pipefail -c "${VERIFY_COMMAND}"
)

if [[ ! -f "${ARCHIVE_PATH}" ]]; then
	echo "The configured build command did not create ${ARCHIVE_PATH}." >&2
	exit 1
fi

(
	cd "$(dirname "${ARCHIVE_PATH}")"
	sha256sum "$(basename "${ARCHIVE_PATH}")" > "$(basename "${CHECKSUM_PATH}")"
)

ARCHIVE_SHA256="$(cut -d ' ' -f 1 "${CHECKSUM_PATH}")"
ARCHIVE_FILES="$(unzip -Z1 "${ARCHIVE_PATH}" | LC_ALL=C sort | jq -Rsc 'split("\n") | map(select(length > 0))')"

jq -n \
	--arg archive "$(basename "${ARCHIVE_PATH}")" \
	--arg commit "$(git -C "${PLUGIN_ROOT}" rev-parse HEAD)" \
	--arg mainPluginFile "${MAIN_PLUGIN_FILE}" \
	--arg packageSlug "${PACKAGE_SLUG}" \
	--arg sha256 "${ARCHIVE_SHA256}" \
	--arg tag "${TAG_NAME}" \
	--arg version "${VERSION}" \
	--arg wordpressOrgSlug "$(jq -r '.wordpressOrgSlug' "${CONFIG_PATH}")" \
	--argjson files "${ARCHIVE_FILES}" \
	'{
		schemaVersion: 1,
		archive: $archive,
		sha256: $sha256,
		tag: $tag,
		version: $version,
		commit: $commit,
		packageSlug: $packageSlug,
		wordpressOrgSlug: $wordpressOrgSlug,
		mainPluginFile: $mainPluginFile,
		files: $files
	}' > "${MANIFEST_PATH}"

echo "Created ${ARCHIVE_PATH}, ${CHECKSUM_PATH}, and ${MANIFEST_PATH}"
