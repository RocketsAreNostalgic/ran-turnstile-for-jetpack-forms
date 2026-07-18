#!/usr/bin/env sh
# Print the archive path from the canonical WordPress plugin header.
set -eu

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
slug=ran-turnstile-for-jetpack-forms
output=${1:-"$root/dist"}
version_count=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "$root/ran-turnstile-for-jetpack-forms.php" | wc -l | tr -d ' ')
version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*$/\1/p' "$root/ran-turnstile-for-jetpack-forms.php")

if [ "$version_count" -ne 1 ] || [ -z "$version" ]; then
	echo 'Unable to read exactly one plugin version from ran-turnstile-for-jetpack-forms.php.' >&2
	exit 1
fi

case "$version" in
	*[!0-9A-Za-z.+-]*)
		echo "Unsafe plugin version for an archive filename: $version" >&2
		exit 1
		;;
esac

printf '%s/%s-%s.zip\n' "$output" "$slug" "$version"
