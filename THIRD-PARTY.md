# External services and third-party dependencies

This file records the plugin's runtime integrations and relevant data flow. It
is a technical inventory, not a legal determination. Provider behaviour and
policies can change; site operators should review the providers' current
documentation for their own configuration.

## Cloudflare Turnstile

Cloudflare Turnstile is the external verification service used by this plugin.
It is optional until an administrator configures keys and enables protection.

When an enabled Jetpack form renders, the visitor's browser requests the
Turnstile JavaScript API from
`https://challenges.cloudflare.com/turnstile/v0/api.js`. The rendered widget
includes the configured public site key and Cloudflare can require visitor
interaction according to that key's configuration.

When the visitor submits a protected form, the WordPress server sends these
values to Cloudflare's Siteverify endpoint at
`https://challenges.cloudflare.com/turnstile/v0/siteverify`:

- the configured secret key;
- the response token produced by the browser widget; and
- the visitor's remote IP address when WordPress supplies a value that this
  plugin can validate as an IP address.

The secret key is used server-side and is not added to the browser widget. The
administrator troubleshooting check contacts the same service after the
administrator presses **Run health check**; it does not submit a Jetpack form.

Documentation:

- [Cloudflare Turnstile documentation](https://developers.cloudflare.com/turnstile/)
- [Cloudflare privacy policy](https://www.cloudflare.com/privacypolicy/)

## Jetpack Forms

Jetpack Forms is a required WordPress plugin dependency and provides the form
blocks, submission lifecycle, feedback handling, notifications, and spam-filter
hooks that this plugin integrates with. Jetpack is installed separately and is
not bundled in this repository's release archive.

This plugin appends a Turnstile widget to Jetpack's rendered form HTML and
returns its validation result through Jetpack's
`jetpack_contact_form_is_spam` filter. It reads Jetpack's submitted form ID and
form hash to keep render-time and submission-time decisions aligned. It does
not independently send form field values to Jetpack or replace Jetpack's own
storage, notification, or spam-processing behaviour.

Jetpack features and site configuration determine any additional Jetpack or
Automattic service requests. Those requests are outside this plugin's direct
Cloudflare validation request and should be assessed as part of the site's
Jetpack configuration.

Documentation:

- [Jetpack Forms documentation](https://jetpack.com/support/jetpack-forms/)
- [Jetpack privacy information](https://jetpack.com/support/privacy/)

## Bundled code

The release archive does not bundle Cloudflare Turnstile or Jetpack source.
Cloudflare's browser script is loaded from its service at runtime, and Jetpack
must be installed separately. Composer packages used for development and tests
are excluded from the release allowlist.
