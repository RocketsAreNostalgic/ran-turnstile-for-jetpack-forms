# Pre-release checklist

Use this checklist on the proposed release commit before merging the Release
Please PR. It covers the general repository gate and the Turnstile/Jetpack
behaviour that cannot be established by source checks alone.

## Release identity

- [ ] The release PR contains only the intended commits since the previous
      release or the configured `0.1.0` bootstrap boundary.
- [ ] `CHANGELOG.md` describes the user-visible changes accurately.
- [ ] The plugin header version and
      `RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION` in
      `ran-turnstile-for-jetpack-forms.php`, the `readme.txt` stable tag, the
      POT project version, and the proposed `v<version>` tag all agree.
- [ ] The supported WordPress 6.5+ and PHP 8.0+ baseline is unchanged unless a
      deliberate compatibility change is documented and tested.
- [ ] GitHub Actions is allowed to create pull requests for Release Please, and
      the release workflow uses the repository `GITHUB_TOKEN` rather than a
      personal token.
- [ ] The release workflow can also be dispatched manually with an existing
      `v<version>` tag, but WordPress.org deployment remains disabled until
      `wordpress-org/deployment.json` is deliberately enabled.

## Source and archive gates

- [ ] Run the focused local checks from a clean checkout:

```sh
composer install --no-interaction
composer validate --strict
composer run phpcs
node --check assets/turnstile.js
node --check scripts/make-pot.mjs
node scripts/make-pot.mjs
git diff --exit-code -- languages/ran-turnstile-for-jetpack-forms.pot
WP_TESTS_DIR=/path/to/wordpress-tests-lib composer run test
sh scripts/build-release.sh
```

- [ ] All required CI jobs pass for the minimum WordPress 6.5/PHP 8.0/Jetpack
      combination and the current supported WordPress/PHP/Jetpack combination.
- [ ] Plugin Check passes against the unpacked release artifact, with any
      accepted warnings recorded and reviewed rather than ignored globally.
- [ ] The ZIP contains a single
      `ran-turnstile-for-jetpack-forms/` root and only paths named by
      `release-contents.txt`.
- [ ] The ZIP excludes `.git`, `.github`, `.dex`, `vendor`, tests, development
      configuration, credentials, caches, logs, and prior release artifacts.
- [ ] The ZIP filename, embedded plugin header, runtime constant, readme stable
      tag, and POT project version all match the proposed release.
- [ ] The GitHub release assets include the ZIP, checksum, and sorted JSON
      manifest for the exact tagged commit.
- [ ] Install and activate the ZIP in a clean WordPress installation with the
      supported Jetpack version active. Confirm activation produces no PHP
      notices or fatal errors and **Settings > RAN Turnstile** is available.

## Cloudflare credentials and widget behaviour

- [ ] No production site key, secret key, token, request payload, or diagnostic
      response appears in Git history, logs, CI output, screenshots, fixtures,
      the POT file, or the release ZIP.
- [ ] Use Cloudflare's documented always-pass and always-fail pairs only on a
      non-production WordPress environment. Confirm the plugin blocks its
      always-pass pair when `wp_get_environment_type()` reports `production`.
- [ ] For production-like verification, use credentials intended for the test
      hostname and inject secrets through site configuration or the settings UI;
      do not add them to repository files.
- [ ] With protection disabled, representative Jetpack forms render and submit
      without this plugin's widget or validation.
- [ ] With protection enabled, a page containing one Jetpack form loads the
      Cloudflare script, renders one plugin-owned widget, and accepts a valid
      token.
- [ ] A page containing multiple Jetpack forms gives each form its own widget
      and fresh response token; a failed AJAX submission resets only the form
      that failed.
- [ ] The default `interaction-only` appearance remains unobtrusive until
      Cloudflare requires interaction. The **Always show the Turnstile widget**
      toggle changes frontend widgets to `always`, while the troubleshooting
      widget remains visible in both configurations.
- [ ] Managed/challenge behaviour is verified with the intended Cloudflare site
      key configuration; the WordPress appearance toggle does not claim to
      change the Cloudflare dashboard's site-key mode.
- [ ] Missing, expired, duplicate, always-fail, and rejected tokens fail closed
      with usable retry messaging. Network or Siteverify failures do not allow
      the submission through.
- [ ] **Run health check** reports the expected result with both a valid local
      test pair and a deliberate failure pair, without submitting a Jetpack form
      or creating feedback.

## Jetpack, Akismet, and provider compatibility

- [ ] Jetpack Forms is active during clean-install and manual functional tests.
      Confirm Jetpack's normal submission, feedback, notification, and error
      behaviour remains intact after successful Turnstile validation.
- [ ] With Jetpack's Akismet integration enabled, successful Turnstile
      validation still reaches Jetpack's later spam checks. Exercise both an
      accepted submission and content that the configured spam path rejects.
- [ ] When another Turnstile integration has already rendered a `cf-turnstile`
      widget in the same form, this plugin does not render a second widget and
      the submission fails closed with the configuration error.
- [ ] Exercise
      `ran_turnstile_for_jetpack_forms_should_protect_form` with a stable form
      ID or hash to hand one form to another provider. Confirm the callback makes
      the same decision during render and submission and that the delegated form
      is not validated by this plugin.
- [ ] With legacy RAN Octopus Forms active and its Turnstile option enabled,
      this plugin pauses runtime protection and displays its conflict notice.
      Confirm first-run settings import remains non-destructive.

## Disclosures and release boundaries

- [ ] `README.md`, `readme.txt`, and `THIRD-PARTY.md` accurately describe
      Cloudflare Turnstile and Jetpack Forms, when external requests occur, and
      what this plugin sends directly.
- [ ] User-facing privacy or service disclosures for the destination site have
      been reviewed against the actual Cloudflare and Jetpack configuration.
- [ ] Provider names, links, and behaviour claims have been checked against
      their current documentation; the repository does not imply affiliation or
      make legal conclusions.
- [ ] Any WordPress.org publication or site deployment is separately approved.
      Merging the Release Please PR creates only the Git tag and GitHub Release.
