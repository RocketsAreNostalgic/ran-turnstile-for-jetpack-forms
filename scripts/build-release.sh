#!/usr/bin/env sh
# Build a deterministic, reviewable plugin ZIP from the explicit release allowlist.
set -eu

export LC_ALL=C
export TZ=UTC

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
output=${1:-"$root/dist"}
slug=ran-turnstile-for-jetpack-forms
archive_mtime=198001010000
stage=$(mktemp -d)
expected="$stage/expected-files.txt"
actual="$stage/actual-files.txt"
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

version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' ran-turnstile-for-jetpack-forms.php)

if ! grep -Fq "RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION', '$version'" ran-turnstile-for-jetpack-forms.php \
	|| ! grep -Eq "^[[:space:]]*Stable tag:[[:space:]]*$version[[:space:]]*$" readme.txt \
	|| ! grep -Fq "Project-Id-Version: RAN Turnstile for Jetpack Forms $version" languages/ran-turnstile-for-jetpack-forms.pot; then
	echo 'Plugin header, runtime constant, readme.txt, and POT project version must agree.' >&2
	exit 1
fi

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

while IFS= read -r release_path || [ -n "$release_path" ]; do
	case "$release_path" in
		''|'#'*)
			continue
			;;
		/*|..|../*|*/../*|*/..|.*|*/.*)
			echo "Unsafe release allowlist path: $release_path" >&2
			exit 1
			;;
	esac

	if [ ! -e "$release_path" ] || [ -L "$release_path" ]; then
		echo "Release allowlist path does not exist or is a symbolic link: $release_path" >&2
		exit 1
	fi

	case "$release_path" in
		*/)
			if find "$release_path" -type l -print -quit | grep -q .; then
				echo "Release runtime directory contains a symbolic link: $release_path" >&2
				exit 1
			fi
			if find "$release_path" -type f -name '.*' -print -quit | grep -q .; then
				echo "Release runtime directory contains a hidden file: $release_path" >&2
				exit 1
			fi
			mkdir -p "$stage/$slug/$release_path"
			cp -R "$release_path". "$stage/$slug/$release_path"
			;;
		*)
			if [ ! -f "$release_path" ]; then
				echo "Release allowlist path is not a regular file: $release_path" >&2
				exit 1
			fi
			mkdir -p "$(dirname "$stage/$slug/$release_path")"
			cp "$release_path" "$stage/$slug/$release_path"
			;;
	esac
done < release-contents.txt

if find "$stage/$slug" \( -type l -o -name '.*' \) -print -quit | grep -q .; then
	echo 'Release archive staging area contains an unsafe path.' >&2
	exit 1
fi

find "$stage/$slug" -type f -print | sed "s#^$stage/##" | sort > "$expected"

if [ ! -s "$expected" ] || ! grep -Fxq "$slug/ran-turnstile-for-jetpack-forms.php" "$expected"; then
	echo 'Release allowlist did not stage the plugin entry point.' >&2
	exit 1
fi

# Fixed timestamps, modes, and lexicographic input ordering make a reviewed
# release candidate byte-for-byte reproducible from identical source files.
find "$stage/$slug" -type f -exec touch -t "$archive_mtime" {} +
find "$stage/$slug" -type f -exec chmod 0644 {} +

(
	cd "$stage"
	zip -X -q "$archive" -@ < "$expected"
)

unzip -t "$archive" >/dev/null
unzip -Z1 "$archive" | sort > "$actual"

if ! cmp -s "$expected" "$actual"; then
	echo 'Release archive contents do not exactly match the approved runtime file list.' >&2
	diff -u "$expected" "$actual" >&2 || true
	exit 1
fi

if unzip -Z1 "$archive" | grep -Eq "^$slug/(vendor|tests|scripts|\.git|\.github|\.dex|dist|wordpress-org)(/|$)"; then
	echo 'The release archive contains development-only files.' >&2
	exit 1
fi

if ! unzip -p "$archive" "$slug/ran-turnstile-for-jetpack-forms.php" | grep -Eq "^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*$version[[:space:]]*$" \
	|| ! unzip -p "$archive" "$slug/ran-turnstile-for-jetpack-forms.php" | grep -Fq "RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION', '$version'" \
	|| ! unzip -p "$archive" "$slug/readme.txt" | grep -Eq "^[[:space:]]*Stable tag:[[:space:]]*$version[[:space:]]*$" \
	|| ! unzip -p "$archive" "$slug/languages/ran-turnstile-for-jetpack-forms.pot" | grep -Fq "Project-Id-Version: RAN Turnstile for Jetpack Forms $version"; then
	echo 'Release archive version metadata does not match the expected version.' >&2
	exit 1
fi

echo "Created and validated $archive"
