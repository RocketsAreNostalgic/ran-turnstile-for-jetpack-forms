=== RAN Turnstile for Jetpack Forms ===
Contributors: bnjmnrsh
Tags: cloudflare, turnstile, jetpack, forms, spam
Requires at least: 6.5
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Cloudflare Turnstile protection to one selected Jetpack contact form.

== Description ==

RAN Turnstile for Jetpack Forms renders a Cloudflare Turnstile widget and
validates its token before Jetpack accepts a selected form submission. It
includes independent settings, local test-key setup, production safeguards,
and safe troubleshooting diagnostics.

Turnstile was originally bundled in RAN Octopus Forms. It now lives only in
this plugin; the EmailOctopus connector has been renamed RAN EmailOctopus for
Jetpack Forms. A runtime conflict guard prevents duplicate behaviour if an
older copy of the bundled plugin remains active during an upgrade.

== Installation ==

1. Activate RAN Turnstile for Jetpack Forms.
2. Open Settings > RAN Turnstile.
3. Confirm the imported settings and run the health check.

== Changelog ==

= 0.1.0 =
* Initial extraction from RAN Octopus Forms.
