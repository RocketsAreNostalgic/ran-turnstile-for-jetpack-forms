# AGENTS.md

## Project contract

This directory is the standalone **RAN Turnstile for Jetpack Forms** WordPress
plugin. Work within this plugin unless a task explicitly expands the scope. The
supported baseline is WordPress 6.5+ and PHP 8.0+.

The plugin has no upstream repository or release automation yet. Do not create,
publish, or configure either implicitly.

## Workflow

Use local Dex state for non-trivial implementation plans when this directory is
initialized as its own repository. Keep `.dex` private and uncommitted.

Run the focused quality gates before handoff:

```sh
composer install --no-interaction
composer run phpcs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
wp i18n make-pot . languages/ran-turnstile-for-jetpack-forms.pot \
  --domain=ran-turnstile-for-jetpack-forms --exclude=vendor,tests
```

Do not commit `vendor/`, PHPUnit caches, `.dex`, editor files, or other generated
runtime state. Keep the tracked POT current when translatable source changes.
