#!/usr/bin/env bash
# Verify a release bundle and deploy it to WordPress.org SVN.
set -euo pipefail

export LC_ALL=C
export TZ=UTC

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
config="$root/wordpress-org/deployment.json"
archive=${1:?Usage: deploy-wordpress-org.sh <archive> <checksum> <manifest> [--allow-disabled] [--sync-assets]}
checksum=${2:?A SHA-256 file is required.}
manifest=${3:?A release manifest is required.}
shift 3

allow_disabled=false
sync_assets=false
for argument in "$@"; do
	case "$argument" in
		--allow-disabled) allow_disabled=true ;;
		--sync-assets) sync_assets=true ;;
		*) echo "Unknown deployment option: $argument" >&2; exit 1 ;;
	esac
done

enabled=$(jq -r '.enabled' "$config")
if [ "$enabled" != true ] && [ "$allow_disabled" != true ]; then
	echo "Routine WordPress.org deployment is disabled in $config." >&2
	exit 1
fi

wordpress_org_slug=$(jq -er '.wordpressOrgSlug | select(length > 0)' "$config")
package_slug=$(jq -er '.packageSlug' "$config")
main_plugin_file=$(jq -er '.mainPluginFile' "$config")
assets_directory=$(jq -er '.listingAssetsDirectory' "$config")
version=$(jq -er '.version' "$manifest")
tag=$(jq -er '.tag' "$manifest")
manifest_archive=$(jq -er '.archive' "$manifest")
manifest_sha256=$(jq -er '.sha256' "$manifest")
manifest_commit=$(jq -er '.commit' "$manifest")
manifest_package_slug=$(jq -er '.packageSlug' "$manifest")
manifest_wordpress_org_slug=$(jq -r '.wordpressOrgSlug' "$manifest")
manifest_main_plugin_file=$(jq -er '.mainPluginFile' "$manifest")
manifest_files=$(jq -cer '.files' "$manifest")
archive_sha256=$(sha256sum "$archive" | awk '{print $1}')
archive_files=$(unzip -Z1 "$archive" | LC_ALL=C sort | jq -Rsc 'split("\n") | map(select(length > 0))')
tag_commit=$(git -C "$root" rev-parse HEAD)

if [ "$tag" != "v$version" ] || [ "$tag_commit" != "$manifest_commit" ]; then
	echo 'Release manifest tag, version, and commit do not agree.' >&2
	exit 1
fi

if [ "$manifest_archive" != "$(basename "$archive")" ] || \
	[ "$manifest_sha256" != "$archive_sha256" ] || \
	[ "$manifest_commit" != "$tag_commit" ] || \
	[ "$manifest_package_slug" != "$package_slug" ] || \
	[ "$manifest_wordpress_org_slug" != "$wordpress_org_slug" ] || \
	[ "$manifest_main_plugin_file" != "$main_plugin_file" ] || \
	[ "$manifest_files" != "$archive_files" ]; then
	echo 'Release manifest does not match the deployment contract.' >&2
	exit 1
fi

(
	cd "$(dirname "$archive")"
	sha256sum --check "$(basename "$checksum")"
)

workdir=$(mktemp -d)
cleanup() {
	rm -rf "$workdir"
}
trap cleanup EXIT HUP INT TERM

unzip -q "$archive" -d "$workdir/release"
if [ ! -f "$workdir/release/$package_slug/$main_plugin_file" ]; then
	echo 'The verified archive does not contain the configured main plugin file.' >&2
	exit 1
fi

: "${WORDPRESS_ORG_USERNAME:?WORDPRESS_ORG_USERNAME is required.}"
: "${WORDPRESS_ORG_PASSWORD:?WORDPRESS_ORG_PASSWORD is required.}"

svn_url="https://plugins.svn.wordpress.org/$wordpress_org_slug"
svn_checkout="$workdir/svn"
svn checkout --non-interactive --no-auth-cache --username "$WORDPRESS_ORG_USERNAME" --password "$WORDPRESS_ORG_PASSWORD" "$svn_url" "$svn_checkout"

rsync -a --delete --exclude='.svn' "$workdir/release/$package_slug/" "$svn_checkout/trunk/"
while IFS= read -r missing_path; do
	[ -n "$missing_path" ] || continue
	svn rm --force "$missing_path"
done < <(svn status "$svn_checkout/trunk" | sed -n 's/^!.......//p')
svn add --force "$svn_checkout/trunk" --parents

if [ "$sync_assets" = true ]; then
	rsync -a --delete --exclude='README.md' --exclude='drafts/' --exclude='.svn' \
		"$root/$assets_directory/" "$svn_checkout/assets/"
	svn add --force "$svn_checkout/assets" --parents
fi

if svn ls "$svn_url/tags/$version" --non-interactive --no-auth-cache --username "$WORDPRESS_ORG_USERNAME" --password "$WORDPRESS_ORG_PASSWORD" >/dev/null 2>&1; then
	echo "WordPress.org tag $version already exists; refusing to replace it." >&2
	exit 1
fi

svn status "$svn_checkout"
svn commit "$svn_checkout" -m "Release $version" --non-interactive --no-auth-cache --username "$WORDPRESS_ORG_USERNAME" --password "$WORDPRESS_ORG_PASSWORD"
svn copy "$svn_url/trunk" "$svn_url/tags/$version" -m "Tag $version" --non-interactive --no-auth-cache --username "$WORDPRESS_ORG_USERNAME" --password "$WORDPRESS_ORG_PASSWORD"
