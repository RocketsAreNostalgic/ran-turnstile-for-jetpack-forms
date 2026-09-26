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
composer check
pnpm install --frozen-lockfile
pnpm check
node --check assets/turnstile.js
node --check scripts/make-pot.mjs
node scripts/make-pot.mjs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test:integration
sh scripts/build-release.sh
```

Do not commit `vendor/`, `node_modules/`, PHPUnit caches, `.dex`, editor files,
or other generated runtime state. Keep the tracked POT current when translatable
source changes. `scripts/build-release.sh` builds from `release-contents.txt`,
validates the archive, refuses to overwrite an existing archive, and writes to
`dist/` unless an output directory is supplied.

## Quality profile and ownership

This repository uses the RAN `wordpress-plugin` quality profile.

- `ran/coding-standards` owns the organisation-wide PHP, WordPress Coding
  Standards, and PHPCompatibility ancestry. This repository continues to own
  its WordPress 6.5+ / PHP 8.0+ support range, source paths, text domain, and
  justified filename exceptions.
- `@rocketsarenostalgic/quality-config` owns shared ESLint and Prettier ancestry.
  This repository continues to own the JavaScript source globs, browser and Node
  runtime globals, and generated-file formatting exclusions. There is no CSS
  quality surface at present, so do not add Stylelint dependencies solely for
  profile symmetry.
- `composer check` is the deterministic standards, PHP syntax and syntax-regression contract consumed
  by the shared baseline. WordPress integration PHPUnit remains repository-owned.
- `pnpm check` is the deterministic package-managed JavaScript/formatting quality
  contract consumed by the shared baseline. Node syntax checks, the Plugin Check
  filter tests, POT convergence, and Plugin Check itself remain specialist gates.
- Canonical release-archive creation and identity proof, the WordPress/Jetpack
  compatibility matrix, fresh-ZIP install/activation, and the scoped Cloudflare
  Plugin Check acceptance policy remain
  repository-owned and must not be removed or weakened by shared-quality work.

See [QUALITY.md](QUALITY.md) for source coverage and test-environment classification.

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

## External AI agent prohibition

Do not invoke, delegate work to, tag, enable, or otherwise use Blacksmith [code]smith,
`@codesmith-bot`, Blacksmith Autofix, Blacksmith CI Tuning, Blacksmith Testbox agents,
or any other Blacksmith AI/agent feature.

Blacksmith may be used only as infrastructure for ordinary GitHub Actions runners where
the repository workflow explicitly specifies a Blacksmith runner.

Do not click or trigger "Enable autofix", do not ask [code]smith to investigate or repair
CI, and do not call Blacksmith agent/MCP/CLI/API features that perform AI inference.

If CI fails, inspect GitHub Actions logs directly and diagnose/fix the failure yourself.

This prohibition is a cost-control requirement and must not be overridden by convenience,
CI failure, review comments, or suggestions from GitHub/Blacksmith UI.
