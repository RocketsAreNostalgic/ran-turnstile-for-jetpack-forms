#!/usr/bin/env sh
# Build the distributable twice and require byte-for-byte deterministic output.
set -eu

root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
workspace=$(mktemp -d)
first_output="$workspace/first"
second_output="$workspace/second"

cleanup() {
	rm -rf "$workspace"
}

trap cleanup EXIT HUP INT TERM

sh "$root/scripts/build-release.sh" "$first_output"
sh "$root/scripts/build-release.sh" "$second_output"

first_archive=$(sh "$root/scripts/release-archive-path.sh" "$first_output")
second_archive=$(sh "$root/scripts/release-archive-path.sh" "$second_output")

if ! cmp -s "$first_archive" "$second_archive"; then
	echo 'Release archives are not byte-for-byte deterministic.' >&2
	exit 1
fi

echo 'Verified deterministic release archive.'
