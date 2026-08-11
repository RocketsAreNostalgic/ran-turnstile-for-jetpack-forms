# RAN Turnstile for Jetpack Forms

RAN Turnstile for Jetpack Forms adds Cloudflare Turnstile protection to Jetpack
Forms.

It exists to keep visitor verification separate from form delivery and
newsletter subscriptions. The former RAN Octopus Forms plugin was split into two
focused companions: RAN EmailOctopus for Jetpack Forms handles subscriber
routing, while this plugin handles Turnstile verification. They can be used
together or independently.

This plugin verifies the visitor before Jetpack accepts the submission. Jetpack
remains responsible for the form, notifications, feedback storage, and its
existing Akismet integration.

## What it does

- Protects every Jetpack form on the site.
- Verifies Turnstile tokens before Jetpack accepts a submission.
- Supports Cloudflare's interaction-only and always-visible widget appearances.
- Provides a health check for credentials and Cloudflare validation.
- Includes safe test-key setup for local development.
- Continues to allow Jetpack and Akismet to perform their own checks.

Protection is site-wide. There is no per-form selection in the administration
interface.

## Requirements

- WordPress 6.5 or later.
- PHP 8.0 or later.
- Jetpack with Jetpack Forms.
- A Cloudflare Turnstile site key and secret key.

## Setup

1. Install and activate Jetpack.
2. Install and activate RAN Turnstile for Jetpack Forms.
3. Open **Settings > RAN Turnstile**.
4. Add the Cloudflare site and secret keys.
5. Enable Turnstile protection and save the settings.
6. Run the health check.
7. Test an accepted and rejected form submission.

Credentials can alternatively be supplied through `wp-config.php`:

```php
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SITE_KEY', '...' );
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SECRET_KEY', '...' );
```

## Using it with RAN EmailOctopus

RAN Turnstile and RAN EmailOctopus can operate on the same Jetpack forms because
they have different responsibilities:

- RAN Turnstile verifies the visitor.
- Jetpack processes and stores the form submission.
- RAN EmailOctopus handles configured newsletter subscriptions.

Akismet can remain enabled alongside Turnstile.

On first activation, if this plugin has no settings of its own, it imports the
existing Turnstile settings from the former RAN Octopus Forms plugin without
changing or deleting them. Runtime protection remains paused while an active
legacy copy still has Turnstile enabled, preventing duplicate widgets during
cutover.

## External services

When a protected form is displayed, the visitor's browser loads Cloudflare's
Turnstile script. On submission, the plugin sends the Turnstile response token
and, when available and valid, the visitor's IP address to Cloudflare for
verification.

The health check contacts Cloudflare only when an administrator runs it.

See [THIRD-PARTY.md](THIRD-PARTY.md) for the dependency and external-service
inventory.

## Development

```sh
composer install --no-interaction
composer validate --strict
composer run phpcs
node scripts/make-pot.mjs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
sh scripts/build-release.sh
```

See [AGENTS.md](AGENTS.md) for the complete contributor workflow and
[RELEASE.md](RELEASE.md) for release management.

## Support

Report bugs and reproducible problems through
[GitHub Issues](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/issues).

RAN Turnstile for Jetpack Forms is licensed under
[GPL-2.0-or-later](LICENSE).
