# AGENTS.md

## Project contract

This directory is the standalone **RAN Turnstile for Jetpack Forms** WordPress
plugin. Work within this plugin unless a task explicitly expands the scope. The
supported baseline is WordPress 6.5+ and PHP 8.0+.

The public upstream repository is:

`https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms`

Release Please runs on pushes to `main` and maintains a release PR from
Conventional Commits. Merging that PR creates the version tag and GitHub
Release only. It does not publish to WordPress.org or deploy the plugin to a
site. Read `RELEASE.md` and use the global `$release-please` skill before
changing or operating the release workflow.

## Workflow

Use local Dex state for non-trivial implementation plans when this directory is
initialized as its own repository. Keep `.dex` private and uncommitted.

Run the focused quality gates before handoff:

```sh
composer install --no-interaction
composer validate --strict
composer run phpcs
node --check assets/turnstile.js
node --check scripts/make-pot.mjs
node scripts/make-pot.mjs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
sh scripts/build-release.sh
```

Do not commit `vendor/`, PHPUnit caches, `.dex`, editor files, or other generated
runtime state. Keep the tracked POT current when translatable source changes.
`scripts/build-release.sh` builds from `release-contents.txt`, validates the
archive, refuses to overwrite an existing archive, and writes to `dist/` unless
an output directory is supplied.

## Commits and releases

Use Conventional Commits with one coherent change per commit. `fix:` produces
a patch release, `feat:` produces a minor release, and `!` or a
`BREAKING CHANGE` footer produces a major release. Use types such as `docs:`,
`test:`, `build:`, `ci:`, or `chore:` when they accurately describe the work,
but do not assume they will suppress a release PR. Review Release Please's
generated version and changelog before merging.

The plugin header and runtime version constant in
`ran-turnstile-for-jetpack-forms.php`, the `readme.txt` stable tag, the POT
project version, and `CHANGELOG.md` must agree in a release. Release Please owns
those version updates through its tracked annotations; do not edit a generated
release PR's version files independently. Review the first and every subsequent
release PR against `PRE-RELEASE-CHECKLIST.md` before merging it.
