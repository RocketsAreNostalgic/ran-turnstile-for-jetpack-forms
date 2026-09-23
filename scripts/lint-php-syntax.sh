#!/usr/bin/env bash
set -euo pipefail

file_list="$(mktemp)"
trap 'rm -f "$file_list"' EXIT

find . -path './vendor' -prune -o -type f -name '*.php' -print0 > "$file_list"
if [[ ! -s "$file_list" ]]; then
    echo 'No PHP source files found.' >&2
    exit 1
fi
xargs -0 -r -n1 php -l < "$file_list"
