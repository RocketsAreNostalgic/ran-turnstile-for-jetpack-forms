=== RAN Turnstile for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: cloudflare, turnstile, jetpack, forms, spam
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 8.0
X-Release-Please-Start-Version: x-release-please-start-version
Stable tag: 0.3.0
X-Release-Please-End: x-release-please-end
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect every Jetpack form with one straightforward Cloudflare Turnstile setup.

== Description ==

RAN Turnstile for Jetpack Forms adds Cloudflare Turnstile to every Jetpack form
on a site. Configure your keys once, enable protection, and visitors receive a
lightweight check only when Cloudflare decides one is needed. Jetpack continues
to handle the form itself and its normal notifications.

It is deliberately a site-wide on/off integration: there is no per-page or
per-form selection in the admin UI. The plugin includes:

* protection for every rendered Jetpack form;
* an interaction-only widget by default, with an optional always-visible mode;
* a focused settings screen, local test-key setup, and troubleshooting
  diagnostics; and
* compatibility with Jetpack's later blocklist and Akismet checks.

The plugin validates the Turnstile token before Jetpack accepts the submission.
It does not replace Jetpack's form processing, notification, or spam controls.

The Cloudflare script loads only when a protected form renders. Server-side
validation uses a plugin-specific response field and runs before Jetpack's
later blocklist and Akismet checks. Akismet can remain enabled: Turnstile checks
the interaction, while Akismet evaluates the submitted content.

After a failed Jetpack AJAX submission, the browser resets only that form's
plugin-owned widget so a retry receives a fresh single-use token.

Frontend widgets default to showing only when Cloudflare requires visitor
interaction. Settings > RAN Turnstile contains one optional "Always show the
Turnstile widget" checkbox. It affects only frontend appearance; the widget
mode remains attached to the site key in Cloudflare, and the troubleshooting
widget remains visible. Execution, retry, refresh, language, and theme retain
their automatic defaults.

Two Turnstile integrations should not own the same form. When this plugin sees
an existing Turnstile widget in Jetpack's rendered form HTML, it does not render
a second widget and fails the submission closed with a configuration error. A
developer can exclude that form with the documented filter and let the other
integration own it.

Turnstile was originally bundled in RAN Octopus Forms. It now lives only in
this plugin; the EmailOctopus connector has been renamed RAN EmailOctopus for
Jetpack Forms. A runtime conflict guard prevents duplicate behaviour if an
older copy of the bundled plugin remains active during an upgrade.

On first activation, if this plugin has no settings option, it imports only
`turnstile_enabled`, `turnstile_site_key`, and `turnstile_secret_key` from
`ran_octopus_forms_settings`. The legacy `contact_page_id` is ignored because
protection is site-wide. The source option is never changed or deleted.

= Excluding a form in code =

There is intentionally no exclusion UI. The
`ran_turnstile_for_jetpack_forms_should_protect_form` filter receives the
default boolean decision and a context array:

`add_filter( 'ran_turnstile_for_jetpack_forms_should_protect_form', function ( $protect, $context ) { return 'my-stable-form-hash' === $context['form_hash'] ? false : $protect; }, 10, 2 );`

The context contains `phase` (`render` or `submission`), `form_id`, `form_hash`,
and a numeric `post_id` when available. The callback must make the same
deterministic decision in both phases, preferably from the stable form ID or
hash. Return `false` for a form that another Turnstile integration owns.

= Overriding widget appearance in code =

The `ran_turnstile_for_jetpack_forms_widget_appearance` filter receives the
default appearance and a context containing `form_id` and `form_hash`. It may
return only `always` or `interaction-only`. It affects frontend appearance only
and is not applied to the always-visible troubleshooting widget.

== External services ==

This plugin uses Cloudflare Turnstile to verify whether a form submission is
likely to come from a human. It loads Cloudflare's Turnstile `api.js` in the
visitor's browser and sends the resulting verification token, together with the
validated visitor IP address when available, to Cloudflare's Siteverify API.

See the [Cloudflare Turnstile documentation](https://developers.cloudflare.com/turnstile/),
[Cloudflare Privacy Policy](https://www.cloudflare.com/privacypolicy/), and
[Cloudflare Terms](https://www.cloudflare.com/website-terms/).

Jetpack Forms is required. Jetpack may process or store form submissions under
its own terms; see the [Jetpack Privacy Center](https://jetpack.com/support/privacy/)
and [Automattic Terms of Service](https://wordpress.com/tos/).

== Screenshots ==

1. Site-wide Turnstile settings and the safe troubleshooting panel, using a
   public local test site key and no production credentials.

== Installation ==

1. Activate RAN Turnstile for Jetpack Forms.
2. Open Settings > RAN Turnstile.
3. Configure the Cloudflare keys and enable site-wide protection.
4. Leave Always show the Turnstile widget off for the recommended
   interaction-only frontend, or enable it for a persistently visible widget.
5. Run the health check and test an accepted and rejected submission.

== Changelog ==

= 0.1.0 =
* Initial extraction from RAN Octopus Forms.
