#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
fixture="$(mktemp -d)"
trap 'rm -rf "$fixture"' EXIT
mkdir -p "$fixture/source" "$fixture/bin"
cp "$repo_root/scripts/lint-php-syntax.sh" "$fixture/lint.sh"
cd "$fixture/source"

expect_status() {
    local expected="$1"
    shift
    local actual=0
    "$@" > "$fixture/output" 2>&1 || actual=$?
    if [[ "$actual" != "$expected" ]]; then
        cat "$fixture/output" >&2
        printf 'Expected exit %s, got %s\n' "$expected" "$actual" >&2
        exit 1
    fi
}

# An empty selection must fail rather than report an untested success.
expect_status 1 bash "$fixture/lint.sh"
grep -Fq 'No PHP source files found.' "$fixture/output"

# Exercise the real PHP parser with paths that must not be split or evaluated.
for directory in includes tests scripts; do
    mkdir -p "$directory"
    source_file="$directory/space ' quote ; \$ name.php"
    printf '<?php\n' > "$source_file"
    expect_status 0 bash "$fixture/lint.sh"
    printf '<?php function broken( {\n' > "$source_file"
    expect_status 123 bash "$fixture/lint.sh"
    grep -Fq -- "$source_file" "$fixture/output"
    rm "$source_file"
done

# Third-party and Git internals must not be parsed as first-party PHP.
printf '<?php\n' > clean.php
for directory in vendor node_modules .git; do
    mkdir -p "$directory/nested"
    printf '<?php function broken( {\n' > "$directory/nested/broken.php"
done
expect_status 0 bash "$fixture/lint.sh"

# Inject a discovery failure after partial output. It must retain find's
# failure status, not lint the partial list and report success.
cat > "$fixture/bin/find" <<'FIND'
#!/usr/bin/env bash
printf './clean.php\0'
exit 73
FIND
chmod +x "$fixture/bin/find"
expect_status 73 env PATH="$fixture/bin:$PATH" bash "$fixture/lint.sh"
rm "$fixture/bin/find"

# A PHP process failure must also reach the aggregate caller.
cat > "$fixture/bin/php" <<'PHP'
#!/usr/bin/env bash
exit 7
PHP
chmod +x "$fixture/bin/php"
expect_status 123 env PATH="$fixture/bin:$PATH" bash "$fixture/lint.sh"

printf 'PHP syntax regression checks passed.\n'
