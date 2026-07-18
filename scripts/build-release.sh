#!/usr/bin/env sh
# Build a clean, reviewable plugin ZIP from the explicit release allowlist.
set -eu

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
output=${1:-"$root/dist"}
slug=ran-turnstile-for-jetpack-forms
stage=$(mktemp -d)
pot_before="$stage/ran-turnstile-for-jetpack-forms.pot"

cleanup() {
	rm -rf "$stage"
}

trap cleanup EXIT HUP INT TERM

mkdir -p "$output"
output=$(CDPATH='' cd -- "$output" && pwd)
archive=$(sh "$root/scripts/release-archive-path.sh" "$output")

if [ -e "$archive" ]; then
	echo "Refusing to overwrite existing archive: $archive" >&2
	exit 1
fi

cd "$root"

node --check assets/turnstile.js
node --check scripts/make-pot.mjs
find includes -name '*.php' -print0 | xargs -0 -n 1 php -l
php -l ran-turnstile-for-jetpack-forms.php
composer run phpcs

cp languages/ran-turnstile-for-jetpack-forms.pot "$pot_before"

if ! node scripts/make-pot.mjs; then
	cp "$pot_before" languages/ran-turnstile-for-jetpack-forms.pot
	echo 'Unable to regenerate the translation template.' >&2
	exit 1
fi

if ! cmp -s "$pot_before" languages/ran-turnstile-for-jetpack-forms.pot; then
	cp "$pot_before" languages/ran-turnstile-for-jetpack-forms.pot
	echo 'The translation template is stale. Run node scripts/make-pot.mjs and commit the result.' >&2
	exit 1
fi

mkdir -p "$stage/$slug"

while IFS= read -r release_path; do
	[ -n "$release_path" ] || continue

	case "$release_path" in
		/*|..|../*|*/../*|*/..)
			echo "Unsafe release allowlist path: $release_path" >&2
			exit 1
			;;
	esac

	if [ ! -e "$release_path" ]; then
		echo "Release allowlist path does not exist: $release_path" >&2
		exit 1
	fi

	case "$release_path" in
		*/)
			mkdir -p "$stage/$slug/$release_path"
			cp -R "$release_path". "$stage/$slug/$release_path"
			;;
		*)
			cp "$release_path" "$stage/$slug/$release_path"
			;;
	esac
done < release-contents.txt

(
	cd "$stage"
	zip -qr "$archive" "$slug"
)

unzip -t "$archive" >/dev/null
unzip -Z1 "$archive" | grep -qx "$slug/ran-turnstile-for-jetpack-forms.php"

if unzip -Z1 "$archive" | grep -Eq "^$slug/(vendor|tests|scripts|\.git|\.github|\.dex)(/|$)"; then
	echo 'The release archive contains development-only files.' >&2
	exit 1
fi

echo "Created and validated $archive"
