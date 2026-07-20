# Release lifecycle

RAN Turnstile for Jetpack Forms uses Release Please to prepare releases from
Conventional Commits on `main`. The repository starts at version `0.1.0`; the
configured bootstrap boundary prevents the pre-automation extraction history
from being released again.

## GitHub release automation

Release Please runs after pushes to `main`. It opens or updates one release PR
containing the proposed version, generated `CHANGELOG.md`, and synchronized
WordPress version sources:

- `Version` in `ran-turnstile-for-jetpack-forms.php`;
- `RAN_TURNSTILE_FOR_JETPACK_FORMS_VERSION` in the same file;
- `Stable tag` in `readme.txt`; and
- `Project-Id-Version` in
  `languages/ran-turnstile-for-jetpack-forms.pot`.

The release bump is derived from Conventional Commits:

- `fix:` requests a patch release;
- `feat:` requests a minor release; and
- `!` or a `BREAKING CHANGE` footer requests a major release.

Other types such as `docs:`, `test:`, `build:`, `ci:`, and `chore:` classify
non-feature work, but the configured PHP release strategy can still include
them in a patch release, particularly immediately after the bootstrap
boundary. Treat the generated release PR as a proposal to review, not as proof
that the requested version is correct.

Before merging the release PR:

1. Confirm the proposed changelog starts at the intended bootstrap boundary.
2. Confirm all four version sources above agree with the proposed tag.
3. Run the checks and product verification in
   `PRE-RELEASE-CHECKLIST.md` against the release PR commit.
4. Build and inspect the allowlisted release ZIP.
5. Merge the release PR only after the candidate is ready.

Merging the release PR creates the `v<version>` tag and GitHub Release. Release
Please does not build or install the plugin, attach an independently validated
ZIP, publish to WordPress.org, or deploy a release to any WordPress site.

The release workflow also packages a canonical ZIP, SHA-256 checksum, and JSON
manifest from the exact tagged release. WordPress.org deployment stays
disabled in source control until the deployment contract is deliberately
enabled.

## Release archive

Build the distributable from a clean checkout of the release candidate:

```sh
composer install --no-interaction
sh scripts/build-release.sh
```

The script derives the version from the plugin header and creates
`dist/ran-turnstile-for-jetpack-forms-<Version>.zip`. It copies only entries in
`release-contents.txt`, runs the focused PHP, JavaScript, coding-standard, and
POT-freshness checks, verifies the archive against its exact runtime file list,
and refuses to overwrite an existing file. It uses fixed ZIP metadata and
sorted entries so the same source produces the same archive. Use a new output
directory or remove a previously reviewed local artifact before rebuilding; do
not silently replace a release candidate.

CI independently runs the supported compatibility lanes, Plugin Check,
archive verification, and clean-install activation with Jetpack. Downloaded CI
artifacts are evidence for review, not an automatic deployment channel.

For manual WordPress.org publication, dispatch the release workflow with an
existing `v<version>` tag. The protected deploy job rebuilds and verifies the
canonical release assets before touching SVN.

## WordPress.org publication

WordPress.org publication is a separate, explicitly authorized operation. A
GitHub Release does not update WordPress.org SVN.

If the plugin is submitted to WordPress.org in future, first confirm the final
directory slug, contributor access, listing copy, external-service disclosures,
and directory assets. Publish only the exact reviewed release contents to SVN
`trunk` and the matching version under `tags/<version>`. The plugin header,
`readme.txt` stable tag, SVN tag, and validated archive must agree. Directory
artwork belongs in the WordPress.org SVN `/assets` directory and not in the
plugin ZIP.

## Site deployment

Installing or updating the plugin on a development, staging, or production site
is also separate from GitHub and WordPress.org publication. Deploy only after
the target site, backup/rollback plan, credentials, and maintenance window have
been explicitly approved. Verify Jetpack, Cloudflare keys, the health check,
and representative form submissions on that environment after deployment.
