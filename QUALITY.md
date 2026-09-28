# PHP quality coverage

Issue #30 audit baseline: `66f555aaf866e43cb6fea116bf7729203a30cb03`.

PHPCS and PHPCBF share `phpcs.xml.dist`: the plugin entrypoint, `includes/`
and `tests/` use the same RAN WordPress rules, PHP 8.0+ compatibility target
and narrowly scoped class-loader filename exceptions. The generated Admin Shell copy is checked by
its separate immutable-content contract; this slice changes neither its
locked package reference nor its generated content. No PHP-CS-Fixer is configured.

`composer check` runs standards, PHP syntax, `test:quality` and `analyze`. The syntax
runner discovers PHP recursively while pruning root `vendor/`, `node_modules/`
and `.git/`. It retains source, test and tool coverage. Its regression script
uses temporary fixtures and the real PHP parser to prove malformed-PHP failure,
safe handling of filenames with whitespace and shell metacharacters, exclusion
of dependency/Git files and rejection of an empty source selection. Injected
`find` and PHP failures prove that discovery and process errors reach the caller.
These fixtures never modify the repository source.

The PHPUnit bootstrap requires the WordPress test library and database.
`composer test` and `composer test:integration` are aliases for that same suite;
the required WordPress/Jetpack compatibility lane executes it once per matrix
entry. Neither alias belongs in the database-free `composer check` aggregate.
The new syntax regression suite is independently runnable with Bash and PHP.

The existing frontend checks, Node parser checks, Plugin Check filter tests,
POT convergence, archive verification, fresh-ZIP installation and activation,
WordPress/Jetpack compatibility and scoped Plugin Check policy remain required.

## Static analysis and remaining acceptance

`composer analyze` is blocking PHPStan level 5 with an explicit PHP 8.0 target
and 512 MB limit. Its direct roots cover the plugin entrypoint and all PHP in
`includes/`, including the shipped generated Admin Shell copy. The immutable
Admin Shell parity gate remains separately required. Tests use WordPress/database
execution and syntax/standards gates; they are not production analysis roots.

The locked WordPress 6.5-generation stubs supply symbols only. Analysis bootstrap
constants model the runtime-derived plugin paths and WordPress duration constant
without executing plugin hooks. The narrowly scoped `next_tag` stub restores the
`array|string|null` parameter documented and implemented by WordPress 6.5:
https://github.com/WordPress/wordpress-develop/blob/6.5/src/wp-includes/html-api/class-wp-html-tag-processor.php
The upstream generated stub omits the string shorthand used by this plugin. No
runtime workaround, ignored error or blanket baseline is introduced. Analyzer
bootstrap/stubs live only under tests and are excluded by the release allowlist.

Native integration, installed ZIP, frontend, generated POT and Plugin Check
remain required before acceptance. Static analysis does not replace WordPress
execution or the deferred owner-held interactive UI verification. Final exact-head
review/CI and formatter/exception evidence are recorded in issue #30.

## Formatter and exception acceptance

The former blanket local filename severity overrides are replaced by exclusions
for only the five existing class-loader paths. The locked shared RANWordPress
profile currently also excludes these filename conventions; these local entries
record the actual repository contract if shared policy later tightens. This
change does not claim current filename enforcement absent from the shared profile.
Existing loader paths and generated Admin Shell bytes remain unchanged.

The ordinary `test:quality` gate also runs Python 3 controls against the actual
configured PHPCS/PHPCBF binaries: all three first-party roots reject formatting
defects, the canonical fixer repairs them and repeated passes are byte-stable.
No production source is mutated by these disposable-fixture tests. There is no
parallel PHP formatter to retire; WordPress/database tests remain native gates.
