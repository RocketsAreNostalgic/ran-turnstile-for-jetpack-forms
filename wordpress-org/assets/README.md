# WordPress.org listing assets

This directory is the local source of the listing artwork for **RAN Turnstile
for Jetpack Forms**. It is intentionally excluded from the plugin release ZIP.

## Approved source artwork

The root PNG files are the approved **Verification Gate** direction, copied
from the dated original study at
`drafts/2026-07-19-turnstile-concepts/verification-gate/`:

- `banner-1544x500.png` — high-resolution directory banner
- `banner-772x250.png` — standard directory banner
- `icon-256x256.png` — high-resolution directory icon
- `icon-128x128.png` — standard directory icon
- `screenshot-1.png` — settings and safe troubleshooting panel, using a public
  local test site key and no production credentials

The retained drafts are original RocketAreNostalgic artwork in the site's
retro-futurist, 1950s science-fiction cover-inspired visual language. They do
not use Cloudflare or Turnstile logos, CAPTCHA imagery, locks, or unsupported
security claims. Do not overwrite or delete a dated study when refreshing the
approved artwork; create a new dated draft set instead.

## WordPress.org deployment

After the plugin has been approved, upload the approved root files to the
WordPress.org SVN repository's top-level `/assets/` directory. They do **not**
belong in SVN `trunk` or a version tag, and they do **not** belong in the
distributed plugin archive. See the WordPress.org asset guidance before
publishing.
