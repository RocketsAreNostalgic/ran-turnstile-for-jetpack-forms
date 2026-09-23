#!/usr/bin/env bash
# Verify a release bundle and deploy it to WordPress.org SVN.
set -euo pipefail

export LC_ALL=C
export TZ=UTC

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
config="$root/wordpress-org/deployment.json"
archive=${1:?Usage: deploy-wordpress-org.sh <archive> <checksum> [--sync-assets]}
checksum=${2:?A SHA-256 file is required.}
shift 2

sync_assets=false
for argument in "$@"; do
	case "$argument" in
		--sync-assets) sync_assets=true ;;
		*) echo "Unknown deployment option: $argument" >&2; exit 1 ;;
	esac
done

enabled=$(jq -r '.enabled' "$config")
if [ "$enabled" != true ]; then
	echo "Routine WordPress.org deployment is disabled in $config." >&2
	exit 1
fi

wordpress_org_slug=$(jq -er '.wordpressOrgSlug | select(length > 0)' "$config")
package_slug=$(jq -er '.packageSlug' "$config")
main_plugin_file=$(jq -er '.mainPluginFile' "$config")
assets_directory=$(jq -er '.listingAssetsDirectory' "$config")
version="${archive##*/}"
version="${version#ran-turnstile-for-jetpack-forms-}"
version="${version%.zip}"
[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$ ]] || exit 1
tag_commit=$(git -C "$root" rev-parse HEAD)
test "$(basename "$archive")" = "ran-turnstile-for-jetpack-forms-${version}.zip"
test "$(basename "$checksum")" = "$(basename "$archive").sha256"
test "$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\\1/p' "$root/$main_plugin_file")" = "$version"
test "$(jq -er '."." | select(type == "string")' "$root/.release-please-manifest.json")" = "$version"

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
