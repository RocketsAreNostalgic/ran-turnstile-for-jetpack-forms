# RAN Turnstile for Jetpack Forms

Adds Cloudflare Turnstile to exactly one Jetpack contact form selected by an
administrator. The plugin owns only Turnstile rendering, server-side validation,
target-form routing, settings, and safe diagnostics. It does not send email,
subscribe contacts, or change Jetpack feedback storage.

## Migration and compatibility

Turnstile was originally bundled in RAN Octopus Forms. It now lives only in
this plugin; the EmailOctopus connector has been renamed RAN EmailOctopus for
Jetpack Forms. A runtime conflict guard remains for sites that still have an
older copy of the bundled plugin active during an upgrade.

After activation, review **Settings > RAN Turnstile**, run the Troubleshooting
health check, confirm one widget appears, and test both an accepted and rejected
submission.

On first activation, when its own option does not exist, this plugin copies
`contact_page_id`, `turnstile_enabled`, `turnstile_site_key`, and
`turnstile_secret_key` from `ran_octopus_forms_settings`. The source option is
never changed or deleted. The existing `ran-octopus-forms-contact-form` block
class remains supported, so the cutover does not require a content edit.

## Configuration

Open **Settings > RAN Turnstile** and select a published page containing exactly
one Jetpack contact form. Configure Cloudflare keys and enable protection.

Cloudflare's always-pass keys are available from the **Set up local dev** button.
They are blocked when WordPress reports a `production` environment. Always-fail
keys are shown for deliberate failure-path testing. Production credentials can
instead be supplied from `wp-config.php`:

```php
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SITE_KEY', '...' );
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SECRET_KEY', '...' );
```

## Runtime design

- The selected page must contain exactly one `jetpack/contact-form` block.
- Rendering is scoped using the block-render context and either the new
  `ran-turnstile-for-jetpack-forms-contact-form` marker or the legacy marker.
- A plugin-owned nonce marks the rendered form and submission.
- Validation runs at priority 5 on `jetpack_contact_form_is_spam`, before Jetpack
  accepts the submission.
- Existing `true` spam state or `WP_Error` is returned unchanged.
- Cloudflare receives a remote IP only after WordPress validates its format.

## Development

Supported baseline: WordPress 6.5+ and PHP 8.0+.

```sh
composer install
composer run phpcs
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
wp i18n make-pot . languages/ran-turnstile-for-jetpack-forms.pot \
  --domain=ran-turnstile-for-jetpack-forms --exclude=vendor,tests
```

This package deliberately has no upstream repository or release automation yet.
