=== RAN Turnstile for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: cloudflare, turnstile, jetpack, forms, spam
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Cloudflare Turnstile protection to every Jetpack form on the site.

== Description ==

RAN Turnstile for Jetpack Forms renders a Cloudflare Turnstile widget on every
Jetpack form and validates its token before Jetpack accepts the submission. It
is deliberately a global on/off integration, with no per-page or per-form
selection in the admin UI. It includes independent settings, local test-key
setup, production safeguards, and safe troubleshooting diagnostics.

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
