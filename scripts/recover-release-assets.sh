#!/usr/bin/env bash

set -euo pipefail

export LC_ALL=C
export TZ=UTC

TAG_NAME="${1:?Usage: recover-release-assets.sh <vX.Y.Z>}"
REPOSITORY="${GITHUB_REPOSITORY:?GITHUB_REPOSITORY is required}"
VERSION="${TAG_NAME#v}"

if [[ "${TAG_NAME}" != "v${VERSION}" || ! "${VERSION}" =~ ^[0-9]+\.[0-9]+\.[0-9]+([.-][0-9A-Za-z.-]+)?$ ]]; then
	echo "A semantic vX.Y.Z release tag is required." >&2
	exit 1
fi

commit="$(git rev-parse HEAD)"
local_tag_commit="$(git rev-parse "${TAG_NAME}^{commit}")"
if [[ "${local_tag_commit}" != "${commit}" ]]; then
	echo "The checked-out release tag does not resolve to HEAD." >&2
	exit 1
fi

tag_ref_before="$(gh api "repos/${REPOSITORY}/git/ref/tags/${TAG_NAME}")"
jq -e --arg commit "${commit}" '.object.type == "commit" and .object.sha == $commit' <<< "${tag_ref_before}" >/dev/null

release_before="$(gh api "repos/${REPOSITORY}/releases/tags/${TAG_NAME}")"
jq -e \
	--arg commit "${commit}" \
	--arg tag "${TAG_NAME}" \
	'.tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false' \
	<<< "${release_before}" >/dev/null
release_id="$(jq -er '.id' <<< "${release_before}")"

bash scripts/create-release-assets.sh "${TAG_NAME}"

archive="dist/ran-turnstile-for-jetpack-forms-${VERSION}.zip"
checksum="${archive}.sha256"
manifest="dist/ran-turnstile-for-jetpack-forms-${VERSION}.manifest.json"

manifest_sha256="$(jq -er '.sha256 | select(test("^[0-9a-f]{64}$"))' "${manifest}")"
archive_sha256="$(sha256sum "${archive}" | awk '{print $1}')"
if [[ "${manifest_sha256}" != "${archive_sha256}" ]]; then
	echo "The rebuilt manifest digest does not match the rebuilt archive." >&2
	exit 1
fi

jq -e \
	--arg archive "$(basename "${archive}")" \
	--arg commit "${commit}" \
	--arg sha256 "${archive_sha256}" \
	--arg tag "${TAG_NAME}" \
	--arg version "${VERSION}" \
	'.archive == $archive and .commit == $commit and .sha256 == $sha256 and .tag == $tag and .version == $version' \
	"${manifest}" >/dev/null
(cd dist && sha256sum --check --strict "$(basename "${checksum}")")
unzip -tqq "${archive}"

assets=("${archive}" "${checksum}" "${manifest}")
gh release upload "${TAG_NAME}" "${assets[@]}" --clobber --repo "${REPOSITORY}"

release_after="$(gh api "repos/${REPOSITORY}/releases/tags/${TAG_NAME}")"
jq -e \
	--arg commit "${commit}" \
	--argjson release_id "${release_id}" \
	--arg tag "${TAG_NAME}" \
	'.id == $release_id and .tag_name == $tag and .target_commitish == $commit and .draft == false and .immutable == false' \
	<<< "${release_after}" >/dev/null

tag_ref_after="$(gh api "repos/${REPOSITORY}/git/ref/tags/${TAG_NAME}")"
jq -e --arg commit "${commit}" '.object.type == "commit" and .object.sha == $commit' <<< "${tag_ref_after}" >/dev/null

expected_assets="$(printf '%s\n' "$(basename "${manifest}")" "$(basename "${archive}")" "$(basename "${checksum}")" | jq -Rsc 'split("\n") | map(select(length > 0)) | sort')"
actual_assets="$(jq -c '[.assets[].name] | sort' <<< "${release_after}")"
if [[ "${actual_assets}" != "${expected_assets}" ]]; then
	echo "The recovered release does not contain exactly the expected asset set." >&2
	exit 1
fi

for asset in "${assets[@]}"; do
	name="$(basename "${asset}")"
	local_digest="sha256:$(sha256sum "${asset}" | awk '{print $1}')"
	remote_digest="$(jq -er --arg name "${name}" '.assets[] | select(.name == $name) | .digest' <<< "${release_after}")"
	if [[ "${remote_digest}" != "${local_digest}" ]]; then
		echo "Published digest mismatch for ${name}." >&2
		exit 1
	fi
done

echo "Recovered ${TAG_NAME} from exact commit ${commit} and verified the published asset digests."
