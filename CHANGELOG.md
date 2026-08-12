# Changelog

## [0.4.0](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/compare/v0.3.1...v0.4.0) (2026-08-12)


### Features

* add Settings and Overview admin tabs ([98fe0a4](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/98fe0a4c264f3710bd933bc218314688ff456ad5))
* add Turnstile header mark ([795fe1d](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/795fe1deaf4ed2fe832fd2f3cfbff7894be7716e))
* adopt shared RAN admin shell ([148f4ea](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/148f4eabbf25cb3a333ff491fd116ae9496048ef))
* preview shared shell navigation ([da50193](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/da50193284ae8062efc00ad0ad50db1155cf8103))
* show saved Turnstile secret indicator ([ceed125](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/ceed125718752a0d0ab063c02ba438d6667f5f90))


### Bug Fixes

* address admin continuity review ([522c708](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/522c70866d6ab0801c06711fa1ca7ffb8d7ef678))
* align admin navigation and footer ([1d7f634](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/1d7f6348f63a54574a743a24a22cff2ce53be168))
* bound PHP setup and protect admin shell ([9c8150c](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/9c8150c59c04295c2e744632066071e14ca453b4))
* extend admin shell across content gutter ([f8e5403](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/f8e540363a2c482e1ee1f185bf277e4c04ca9279))
* improve clarity in overview text for admin UI ([2ce323e](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/2ce323e562dbacbcaf4253ef011b8c29c22678eb))
* make shared admin shell full width ([0582876](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/058287654418cb7be79ff045dffe4cbe58e5cf71))
* update strapline for clarity in admin UI ([8402eb4](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/8402eb4ea71e80549970b15019d5d91e1e1fe829))


### Miscellaneous Chores

* regenerate POT for updated overview copy ([18ac474](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/18ac474892c458a6843f998910d336b8c7f74a97))

## [0.3.1](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/compare/v0.3.0...v0.3.1) (2026-07-30)


### Miscellaneous Chores

* **deps:** update WPCS security patch ([8f0392f](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/8f0392fb16b0bc826593fa3cd0722942485b9145))

## [0.3.0](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/compare/v0.2.0...v0.3.0) (2026-07-20)


### Features

* add wp-cli to tools in release workflow ([9872034](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/9872034f795860eb6d09c6ef15e2abf1855799cb))

## [0.2.0](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/compare/v0.1.1...v0.2.0) (2026-07-20)


### Features

* standardize GitHub-backed WordPress.org releases ([5de3958](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/5de395842782ab172c81cf04e164401c4634eec9))

## [0.1.1](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/compare/v0.1.0...v0.1.1) (2026-07-18)


### Bug Fixes

* keep CI compatible with PHP 8.0 ([a26bb86](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/a26bb86a1a426a7bf71590a0b7a2fc567a1582b8))
* satisfy release artifact checks ([cd0c0d7](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/cd0c0d7992056dc9eb62bb00149f603d12a5094b))


### Miscellaneous Chores

* add release and CI foundation ([e9de57e](https://github.com/RocketsAreNostalgic/ran-turnstile-for-jetpack-forms/commit/e9de57e6619a8a1487c9ec3cbb3f6d5e78d7094d))

## 0.1.0

- Extracted site-wide Cloudflare Turnstile protection from RAN Octopus Forms.
- Added a global enablement setting, interaction-only widget appearance, and
  troubleshooting diagnostics for Jetpack Forms.
- Added per-form developer filters and duplicate-integration safeguards.
