# WordPress.org publishing checklist

This document is the publication gate for **RAN Turnstile for Jetpack Forms**.
It is deliberately separate from [RELEASE.md](RELEASE.md): Release Please
creates Git tags and GitHub Releases, whereas publishing to WordPress.org is a
separate, manually authorised SVN deployment.

Use this checklist for the first directory submission and for every subsequent
WordPress.org update. Do not upload a development checkout or a GitHub source
archive.

## Current pre-submission gaps

- [ ] Reconcile the release identity before doing anything else. The checked-in
      plugin header, version constant, `readme.txt` stable tag, POT project
      version, changelog, and release manifest currently say `0.1.0`, while
      the intended public release is `0.1.1`. Review the actual Release Please
      release commit/tag, then make one reviewed release candidate the source
      of truth. Never hand-edit versioned files outside the Release Please
      release PR.
- [ ] Add the reviewed `0.1.1` user-facing release notes to the `readme.txt`
      changelog through that release process. Keep `CHANGELOG.md` and the
      directory changelog consistent.
- [ ] Create the directory-listing artwork described below. It belongs in the
      WordPress.org SVN `/assets` directory, not in the plugin ZIP and not in
      the runtime `assets/` directory that contains `turnstile.js`.
- [ ] Approve final listing copy and provider-name treatment. RAN owns the
      listing voice and artwork; do not imply affiliation with Cloudflare,
      Turnstile, Jetpack, Automattic, or WordPress.

## Release candidate and translation gate

- [ ] Review the Release Please PR/version source update and merge it only
      after the candidate is ready. Confirm all of these agree exactly:

  - plugin header `Version`;
  - `RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION`;
  - `readme.txt` `Stable tag`;
  - `languages/ran-turnstile-for-jetpack-forms.pot`
    `Project-Id-Version`;
  - `CHANGELOG.md`, release manifest, Git tag, and ZIP filename.

- [ ] Regenerate the POT after final copy changes and confirm no uncommitted
      POT diff remains:

  ```sh
  node scripts/make-pot.mjs
  git diff --exit-code -- languages/ran-turnstile-for-jetpack-forms.pot
  ```

- [ ] Run the focussed i18n lint. The plugin is catalog-ready when it passes;
      translated `.po`/`.mo` files are not required for a first submission.
      WordPress.org can later provide language packs through its translation
      platform.

  ```sh
  composer run phpcs -- --sniffs=WordPress.WP.I18n
  ```

- [ ] Ensure all new public listing copy, screenshot captions, and user-facing
      strings are translatable or deliberately directory-only text. Do not add
      a bundled translation merely to satisfy the directory submission.

## WordPress.org listing copy

Prepare the following copy for the directory listing and have it approved by
the RAN owner before submission. Keep the first sentence precise and useful;
avoid keyword stuffing and unsupported security claims.

### Suggested short description

> Adds Cloudflare Turnstile protection to every Jetpack form, with server-side
> verification, safe diagnostics, and an unobtrusive interaction-only default.

### Suggested directory description structure

1. **What it does:** protects every Jetpack form site-wide when enabled.
2. **How it behaves:** renders a Turnstile widget and validates its token
   before Jetpack accepts a submission; Jetpack and Akismet continue their own
   normal checks.
3. **Why it is unobtrusive:** the recommended default is Cloudflare's
   interaction-only appearance, with one optional always-visible toggle.
4. **What it does not do:** it does not replace Jetpack notifications, feedback
   storage, spam classification, email delivery, or newsletter subscriptions.
5. **What site operators need:** Jetpack Forms and Cloudflare Turnstile keys.
6. **Privacy/external services:** link to the accurate Cloudflare and Jetpack
   disclosure already maintained in `readme.txt` and review it against the
   final runtime before each release.

### Suggested tags

Retain the concise, relevant five-tag set unless the directory reviewer asks
otherwise:

`cloudflare`, `turnstile`, `jetpack`, `forms`, `spam`

### Screenshots and captions

If screenshots are supplied, use these captions in `readme.txt`:

1. **RAN Turnstile settings** — Cloudflare keys, site-wide enablement, and the
   interaction-only appearance recommendation.
2. **Troubleshooting** — the visible diagnostic widget and health check, using
   non-production credentials and redacted diagnostic output.
3. **Protected Jetpack form** — an ordinary form in its unobtrusive default
   state, without visitor data or an unnecessary forced challenge.

Screenshots must show the actual current UI, use the current RAN identity, and
contain no secrets, personal data, real response tokens, IP addresses, or
third-party dashboard content that the project does not have permission to
redistribute.

## Directory graphics brief

Create the artwork outside the release package, for example in a local
`wordpress-org/assets/` source directory that is excluded from
`release-contents.txt`. Upload the final raster files to the top-level
WordPress.org SVN `/assets` directory after approval.

- [ ] `icon-128x128.png` — required practical listing icon; clear at small
      size, RAN-led, and not a copied Cloudflare/Turnstile logo.
- [ ] `icon-256x256.png` — retina equivalent of the icon.
- [ ] `banner-772x250.png` — standard banner with concise RAN Turnstile for
      Jetpack Forms wording and legible contrast.
- [ ] `banner-1544x500.png` — retina equivalent of the banner.
- [ ] Optional `screenshot-1.png`, `screenshot-2.png`, and
      `screenshot-3.png` corresponding exactly to the `readme.txt` captions.
- [ ] Keep editable design sources in the project/design system, not in the
      WordPress.org release ZIP. Confirm RAN owns or is licensed to use every
      image, font, mark, and illustration.

Avoid visual claims such as “official”, “certified”, “guaranteed”, or “100%
spam-free”. A simple RAN octopus/verification motif is preferable to borrowed
Cloudflare branding.

## Quality and archive proof

- [ ] Start from a clean checkout of the exact release candidate.
- [ ] Run the repository's pre-release checks:

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

- [ ] Confirm required CI lanes, Plugin Check, archive verification, and
      clean-ZIP activation with Jetpack pass for the release candidate.
- [ ] Inspect the ZIP: it contains one
      `ran-turnstile-for-jetpack-forms/` root and only entries listed in
      `release-contents.txt`. It must not contain `.git`, CI files, tests,
      `vendor`, caches, credentials, WordPress.org design sources, or old ZIPs.
- [ ] Install that exact ZIP in a clean supported WordPress installation with
      Jetpack active. Verify activation, the settings screen, the health check,
      accepted and rejected submissions, Akismet coexistence, and the other
      Turnstile-provider collision path.
- [ ] Re-read the `readme.txt` external-services disclosure against the final
      runtime. It must explain Cloudflare script loading, Siteverify token/IP
      handling, administrator-triggered diagnostics, and the Jetpack dependency
      accurately.

## Submission and WordPress.org SVN

- [ ] Confirm the intended WordPress.org slug is available and that the
      `bnjmnrsh` contributor account and submitting account are correct.
- [ ] Confirm a monitored WordPress.org account email address and review
      contact process are in place.
- [ ] Submit the complete, reviewed, production ZIP (under the directory size
      limit) for manual review. Do not treat a GitHub Release as a submission.
- [ ] Do not publish to WordPress.org SVN until the directory team approves the
      plugin and grants repository access.
- [ ] On approval, commit the exact reviewed source contents to SVN `trunk`.
- [ ] Copy that exact source to `tags/<version>`; the SVN tag must match the
      plugin header and `Stable tag`.
- [ ] Commit directory artwork separately to SVN `/assets`. Do not put it in
      `trunk` or a version tag.
- [ ] Verify the public directory page, rendered readme, icon/banner, external
      service links, and downloadable ZIP after propagation.
- [ ] Record the submitted ZIP checksum, Git commit, Release Please PR, Git
      tag, SVN revision, and reviewer correspondence in the release record.

## Ongoing release rule

For later updates, repeat the release-candidate, validation, and SVN sections.
A GitHub Release is evidence of the source release; it never deploys the
plugin to WordPress.org or to a WordPress site.
