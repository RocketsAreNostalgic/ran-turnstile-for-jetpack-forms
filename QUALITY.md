# PHP quality coverage

Issue #30 audit baseline: `66f555aaf866e43cb6fea116bf7729203a30cb03`.

PHPCS and PHPCBF share `phpcs.xml.dist`: the plugin entrypoint, `includes/`
and `tests/` use the same RAN WordPress rules, PHP 8.0+ compatibility target
and local filename exceptions. The generated Admin Shell copy is checked by
its separate immutable-content contract; this slice changes neither its
dependency lock nor its generated content. No PHP-CS-Fixer is configured.

`composer check` runs standards, PHP syntax and `test:quality`. The syntax
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

## Remaining acceptance

This slice supplies syntax negative controls and test-environment classification.
It does not claim full issue #30 completion. PHPStan adoption needs its own
measured blocking floor and separate review. Broader PHPCS path/exception
reconciliation and formatter repeatability evidence also remain open; preserve
the Admin Shell content contract and support floor in any later change.
