# Protected WordPress.org deployment

GitHub's immutable release is the canonical source. Shared Profile B publishes
only the exact Quality-tested ZIP and SHA-256 checksum. The repository manifest
remains CI evidence. The downstream deployment observer requires the exact
Release Please workflow success, immutable release and tag target, and exact
GitHub asset digests before a protected SVN deployment.

Routine deployment is disabled while `deployment.json` has `enabled: false`.
Do not set a `wordpressOrgSlug`, add environment secrets, or enable
deployment until WordPress.org has approved the submitted ZIP and assigned the
real slug. Enabling requires a reviewed change to the committed contract.
The protected `wordpress-org` environment supplies scoped
`WORDPRESS_ORG_USERNAME` and `WORDPRESS_ORG_PASSWORD` only when deployment
is enabled. Listing artwork sync is separately controlled by
`syncListingAssets` in the committed contract.

A failed or missing canonical release cannot be repaired by manual dispatch,
tag rebuild, or asset replacement. Fix the source/build, qualify a fresh
candidate, and publish a new immutable version and tag. Listing artwork stays
outside the installable ZIP and SVN trunk.
