# Protected WordPress.org deployment

GitHub releases are the canonical release source. The Release Please workflow
builds the exact release tag, verifies the ZIP, and attaches the ZIP, SHA-256,
and file manifest to the GitHub release. The protected deployment job downloads
those assets again before staging WordPress.org SVN.

Routine deployment is disabled while `deployment.json` has `enabled: false`.
Do not set a `wordpressOrgSlug`, add environment secrets, or enable routine
deployment until WordPress.org has approved the manually submitted ZIP and
assigned the real slug.

The one-time first deployment uses `workflow_dispatch` with an existing release
tag and `deploy: true`. It may leave `enabled` false, but still requires approval
through the `wordpress-org` GitHub Environment and its scoped
`WORDPRESS_ORG_USERNAME` and `WORDPRESS_ORG_PASSWORD` secrets. Set
`sync_assets: true` only for a deliberate listing-artwork sync.

After the first public update is verified, set `enabled: true` to permit routine
deployment following a newly created GitHub release. Listing artwork remains
outside the installable ZIP and is never copied to SVN `trunk` or release tags.
