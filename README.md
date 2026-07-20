# RAN Turnstile for Jetpack Forms

Adds Cloudflare Turnstile protection to every Jetpack form on the site. The
plugin is deliberately a global on/off integration: there is no per-page or
per-form selection in the admin UI. It owns only Turnstile rendering,
server-side validation, settings, and safe diagnostics. It does not send email,
subscribe contacts, change Jetpack feedback storage, or replace Jetpack's spam
classification.

## Migration and compatibility

Turnstile was originally bundled in RAN Octopus Forms. It now lives only in
this plugin; the EmailOctopus connector has been renamed RAN EmailOctopus for
Jetpack Forms. A runtime conflict guard remains for sites that still have an
older copy of the bundled plugin active during an upgrade.

After activation, review **Settings > RAN Turnstile**, run the Troubleshooting
health check, confirm a widget appears on each Jetpack form, and test both an
accepted and rejected submission.

On first activation, when its own option does not exist, this plugin copies
`turnstile_enabled`, `turnstile_site_key`, and `turnstile_secret_key` from
`ran_octopus_forms_settings`. The old `contact_page_id` value is deliberately
ignored because protection is now site-wide. The source option is never changed
or deleted. No block class or content edit is required.

## Configuration

Open **Settings > RAN Turnstile**, configure the Cloudflare keys, and enable
protection. The setting applies to all Jetpack forms.

Leave **Always show the Turnstile widget** unchecked for the recommended
frontend behaviour. Turnstile runs when the form renders but stays out of sight
unless Cloudflare requires visitor interaction. Check it to keep the widget
visible throughout verification. The troubleshooting widget is always visible.

This setting controls only frontend appearance. The Cloudflare widget mode is
attached to the site key and remains configured in the Cloudflare dashboard;
Managed mode is recommended. The plugin deliberately keeps render-time
execution, automatic retry and refresh, automatic language, and automatic theme.

Cloudflare's always-pass keys are available from the **Set up local dev** button.
They are blocked when WordPress reports a `production` environment. Always-fail
keys are shown for deliberate failure-path testing. Production credentials can
instead be supplied from `wp-config.php`:

```php
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SITE_KEY', '...' );
define( 'RAN_TURNSTILE_FOR_JETPACK_FORMS_SECRET_KEY', '...' );
```

## External services

Jetpack Forms is a required plugin dependency. When protection is enabled and a
Jetpack form renders, the visitor's browser loads Cloudflare's Turnstile script.
On submission, this plugin sends the Turnstile response token and, when present
and valid, the visitor's remote IP address to Cloudflare's Siteverify endpoint.
The troubleshooting health check contacts the same service only when an
administrator runs it.

See [THIRD-PARTY.md](THIRD-PARTY.md) for the technical service and dependency
inventory. Site operators should review the providers' current documentation
and decide what disclosures are appropriate for their site before enabling the
integration.

## Runtime design

- Every rendered Jetpack form is protected while the plugin is enabled.
- The Cloudflare script is loaded only after a protected form renders.
- Frontend widgets default to `interaction-only`; the single visibility toggle
  can change them to `always`. This does not change the Cloudflare site-key mode.
- The widget uses a plugin-specific response field so another integration's
  token cannot accidentally satisfy this plugin's server-side check.
- Validation runs at priority 5 on `jetpack_contact_form_is_spam`. A successful
  Turnstile check returns the existing `false` value so Jetpack's later
  blocklist and Akismet checks can still classify the submission.
- An existing `true` spam state or `WP_Error` is returned unchanged. A missing,
  invalid, or unverifiable Turnstile token returns a `WP_Error` and prevents the
  submission from being accepted.
- After a failed Jetpack AJAX submission, the browser resets only that form's
  plugin-owned widget so a retry receives a fresh single-use token.
- Cloudflare receives a remote IP only after WordPress validates its format.

### Overriding appearance in code

The admin toggle sets the default for every frontend form. Code can make a
deterministic per-form override with
`ran_turnstile_for_jetpack_forms_widget_appearance`:

```php
add_filter(
	'ran_turnstile_for_jetpack_forms_widget_appearance',
	static function ( $appearance, $context ) {
		return 'my-stable-form-hash' === $context['form_hash']
			? 'always'
			: $appearance;
	},
	10,
	2
);
```

Only `always` and `interaction-only` are accepted. The filter does not alter
execution, retries, refresh, theme, language, response-field isolation, or the
always-visible troubleshooting widget.

### Excluding a form in code

There is intentionally no exclusion UI. Use
`ran_turnstile_for_jetpack_forms_should_protect_form` when another integration
must own a particular form:

```php
add_filter(
	'ran_turnstile_for_jetpack_forms_should_protect_form',
	static function ( $protect, $context ) {
		if ( 'my-stable-form-hash' === $context['form_hash'] ) {
			return false;
		}

		return $protect;
	},
	10,
	2
);
```

The filter runs twice for a protected submission: once with
`$context['phase'] === 'render'` and again with
`$context['phase'] === 'submission'`. The context also contains `form_id`,
`form_hash`, and a numeric `post_id` when Jetpack's form ID represents a post.
Make the same deterministic decision in both phases, preferably from the stable
form ID or hash rather than the current URL or request type. Returning `false`
for every call delegates all Jetpack forms to another protection provider.

### Akismet and other Turnstile integrations

Akismet and Turnstile can run on the same form. They perform different checks:
Turnstile verifies the interaction, while Akismet evaluates the submitted
content. A successful Turnstile check deliberately leaves the submission in the
filter chain for Jetpack and Akismet.

Two Turnstile integrations should not own the same form. If this plugin sees an
existing `cf-turnstile` widget in Jetpack's rendered form HTML, it does not add a
second widget. It marks the collision and fails the submission closed with a
configuration error instead of guessing which token belongs to which provider.
Use the exclusion filter above to give that form to the other integration, or
disable one provider. Integrations that inject markup after this plugin's late
render filter cannot be detected reliably, so the exclusion filter is also the
explicit compatibility mechanism for those cases.

## Development

Supported baseline: WordPress 6.5+ and PHP 8.0+.

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

The build script creates
`dist/ran-turnstile-for-jetpack-forms-<Version>.zip` from the explicit
[release allowlist](release-contents.txt). It runs the focused source and
generated-file checks, validates the archive, and refuses to overwrite an
existing ZIP. It writes fixed ZIP metadata and a sorted file list, so identical
sources produce an identical archive. It also rejects any missing or unexpected
runtime file and mismatched version metadata. Pass another output directory as
its first argument when needed.

## Releases

Release Please maintains release PRs from Conventional Commits on `main`.
Merging a release PR creates the version tag and GitHub Release; it does not
publish to WordPress.org or deploy to a WordPress site. See
[RELEASE.md](RELEASE.md) for the lifecycle and
[PRE-RELEASE-CHECKLIST.md](PRE-RELEASE-CHECKLIST.md) for the product-specific
release gate.

## Support and contributing

Report reproducible issues at
[RocketsAreNostalgic/ran-turnstile-for-jetpack-forms](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/issues).
Include the relevant coding-standards and test output with changes.

Use Conventional Commits so Release Please can classify the next version.
