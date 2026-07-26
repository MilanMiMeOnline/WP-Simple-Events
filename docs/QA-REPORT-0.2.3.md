# QA report — MiMe Simple Events and Calendar 0.2.3

**Date:** 2026-07-26

**Candidate:** MiMe Simple Events and Calendar 0.2.3

**Purpose:** WordPress.org prereview response, public-identity migration and final
release qualification

## Result

The exact local 0.2.3 package passes the complete release qualification. The
public name, plugin directory, bootstrap file, text domain and translation
catalogue are now **MiMe Simple Events and Calendar** and
`mime-simple-events-calendar`.

The migration deliberately preserves every durable `wpse_` event post type,
taxonomy, metadata, option, capability, REST, shortcode, Gutenberg block and
Elementor widget identifier. Existing private-test content therefore remains
compatible when 0.2.3 replaces an earlier build.

No runtime security or privacy control was weakened. No high or critical
dependency advisory remains. The official current WordPress Plugin Check package
completed all General, Plugin Repo, Security, Performance and Accessibility
checks with errors and warnings enabled and reported: **Checks complete. No
errors found.**

## WordPress.org prereview response

- The owner-controlled **MiMe** brand now leads the descriptive plugin name.
- The requested new slug and text domain are `mime-simple-events-calendar`.
- The WordPress.org contributor remains the exact account `mimeonline`.
- Public readmes, source headers, translation calls, release automation and CI
  agree on the new identity.
- The approved MiMe-first banners are prepared at 1544 × 500 and 772 × 250.
- The unchanged calendar mark remains suitable for the square WordPress.org
  icons.
- One obsolete Elementor screenshot containing the former public label was
  removed; the remaining six screenshot captions and images are synchronized.
- Historical architecture prefixes remain private implementation contracts and
  are not presented as the public plugin name.

## Security and privacy evidence

- State-changing browser actions retain capability checks and action-specific
  WordPress nonces.
- Native editor and REST input retain strict shape validation, normalization and
  atomic persistence.
- Public queries remain bounded to published, password-free events; protected
  event metadata remains absent from anonymous core REST responses.
- Output remains escaped for HTML, attribute, URL, JavaScript and JSON contexts.
- External event and location links remain HTTP(S)-only and open with an isolated
  no-referrer browser context.
- The plugin still has no telemetry, analytics, visitor cookie, browser storage,
  remote runtime asset, custom database table, raw SQL or production logging
  path.
- Repository and production-package scans found no obsolete public text domain,
  former bootstrap filename, workstation path, secret or private credential.
- Composer reports no advisory; full and production npm audits report zero
  vulnerabilities.
- The development-only `brace-expansion` override resolves the reviewed advisory
  to patched version 5.0.8 and is documented for later removal when upstream
  ranges no longer require it.

## Automated evidence

- `composer validate --strict`: passed.
- `composer qa`: passed.
  - WordPress/PHP coding standards: 8/8 filesets passed.
  - PHPStan level 8: 129/129 files, no errors.
  - PHPUnit: 264 tests, 1,011 assertions.
  - Composer audit: zero advisories.
- `npm run qa`: passed.
  - Production builds completed.
  - Tool contract tests: 18/18.
  - JavaScript and CSS lint: zero warnings.
  - Full npm audit: zero vulnerabilities.
- Production npm audit: zero vulnerabilities.
- Translation template generation and byte comparison: passed with the official
  WP-CLI PHAR and verified SHA-512 checksum. Local PHP 8.5 deprecations came from
  WP-CLI's bundled dependencies, not plugin code.
- Release archive content, PHP syntax, production autoloader, checksum and
  reproducibility checks: passed.
- Packaged WordPress 6.9 smoke journey: passed.
- Packaged WordPress 7.0.1 smoke journey: passed.
- Playwright browser journeys on WordPress 7.0.1: 15/15 passed.
- Elementor 3.35.9 / WordPress 7.0.1 / PHP 8.3 real-host contract: passed.
- Elementor 4.1.5 / WordPress 7.0.1 / PHP 8.3 real-host contract: passed.
- Official WordPress Plugin Check, all five categories with errors and warnings:
  passed with no findings.
- `git diff --check`: passed.

## Release artifact

- Archive: `dist/mime-simple-events-calendar-0.2.3.zip`
- SHA-256:
  `9a24dc4d21a498ddc72ca8980ec0249c99bd1ecd7a516e46b0065cc3d50c49ac`
- WordPress.org display set: two icons, two banners and six screenshots under
  `.wordpress-org/`; excluded from the installable archive.

## Senior developer review

- Public-identity values were migrated consistently without changing stored data
  keys or external integration identifiers.
- The new bootstrap filename and release root match the requested slug; no
  compatibility shim is required because WordPress.org has not approved or
  distributed the previous prerelease slug.
- The text-domain change covers PHP, block metadata and editor JavaScript. The POT
  catalogue is current.
- Production PHP dependencies are unchanged; Node dependency changes affect
  development tooling only.
- The release allowlist excludes tests, internal documentation, source-control
  metadata and WordPress.org display assets.
- The two official Elementor packages confirm the renamed category while all 15
  saved widget identifiers remain stable.

## Senior QA review

- The exact packaged tree, rather than only the repository source, passed both
  supported WordPress smoke runs and the official Plugin Check runtime.
- Browser coverage revalidates calendar loading, filtering, responsive views,
  same-day wall time, timezone display, 12/24-hour formatting, failures and
  Gutenberg field blocks after the rename.
- Elementor Free static-page selection and editable Event-document support remain
  available on both supported Elementor generations.
- The six screenshots use fictional demonstration content and do not expose
  credentials, private user data or local-link status.
- The plugin remains functional with Elementor absent; WooCommerce remains an
  optional, untouched host integration.

## Remaining publication gates

1. Review and commit the 0.2.3 migration, then push it so the pinned GitHub Actions
   matrix can confirm the same commit.
2. Reply in the existing WordPress.org review thread and explicitly request the
   `mime-simple-events-calendar` slug.
3. Upload the verified 0.2.3 zip only when WordPress.org requests a replacement
   package or enables a new submission.
4. Confirm account recovery and two-factor authentication for the WordPress.org
   and GitHub accounts.

No commit, push, tag, GitHub release, WordPress.org upload or review-email reply
was performed during this local qualification.
