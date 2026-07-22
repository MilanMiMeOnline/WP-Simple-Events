# QA report — Simple Events by MiMe 0.2.2

**Date:** 2026-07-22
**Candidate:** Simple Events by MiMe 0.2.2
**Purpose:** security/privacy hardening, public documentation and WordPress.org
release preparation

## Result

The exact local 0.2.2 package is accepted as the release candidate. One real
confidentiality defect found during the final audit is fixed: WordPress core REST
previously exposed registered metadata belonging to a published
password-protected event. Anonymous responses now omit that event metadata while
the password is required; authorized edit-context requests retain access.

No other code vulnerability or privacy leak was identified in the runtime,
repository history or generated package. The plugin has no telemetry, external
service, remote runtime asset, visitor cookie or browser storage, custom table,
raw SQL or production logging path.

Public documentation is English, user-oriented and internally linked. The former
Dutch analysis file has been replaced by a concise English product specification
under `docs/`. The private-test migration paragraph has been removed from both
public readmes.

## Security and privacy evidence

- State-changing admin actions require explicit capabilities and action-specific
  nonces.
- Settings use the administrator-only WordPress Settings API and are not exposed
  through REST.
- Native editor and REST writes share strict validation and canonical
  persistence.
- Public queries are fixed to published, password-free events and hard bounds.
- Core REST protected-event metadata denial is covered by a real WordPress
  regression; authorized editor access is asserted separately.
- Event output is escaped by context; rich content uses WordPress formatting/KSES
  and JSON-LD uses HTML-significant JSON escaping.
- External event and location URLs allow only HTTP(S) and open with `noopener
  noreferrer` and a no-referrer policy.
- No raw database access, unsafe deserialization, runtime shell, remote include or
  arbitrary metadata access was found.
- Tracked source and generated package scans found no high-confidence secret,
  private key, workstation path or personal email address.
- CI permissions are read-only and every external GitHub Action is pinned to an
  immutable reviewed commit.
- Release packaging uses an explicit allowlist and now explicitly rejects the
  `.wordpress-org` visual directory.

## Automated evidence

- `composer validate --strict`: passed.
- WordPress/PHP coding standards: passed.
- PHPStan level 8 over 129 files: passed with no errors.
- PHPUnit: passed — 264 tests, 1,011 assertions.
- Composer advisory audit: passed — no known advisories.
- Front-end production builds: passed.
- Node tooling tests: passed — 18/18, including the immutable Action policy and
  complete WordPress.org visual-asset contract.
- JavaScript and CSS lint: passed with zero warnings.
- npm audit at high severity: passed — zero vulnerabilities.
- Newly published high-severity advisories in `fast-uri` and `fast-xml-parser`
  were resolved with patched transitive development versions before acceptance.
- WP-CLI 2.12.0 translation generation and byte comparison: passed. Local PHP
  8.5 emitted deprecations from WP-CLI's bundled dependencies, not the plugin.
- Release archive content, PHP syntax, production autoloader and checksum
  verification: passed.
- Consecutive release builds: byte-for-byte identical.
- Packaged WordPress 6.9 smoke journey: passed.
- Packaged WordPress 7.0.1 smoke journey: passed.
- Playwright browser regressions on WordPress 7.0.1: passed — 15/15.

## Release artifact

- Archive: `dist/simple-events-by-mime-0.2.2.zip`
- SHA-256:
  `816dec2199f7fef8483371bdea0785c8d008ba92a3a0f519c8f201be94b3cc32`
- WordPress.org display set: two icons, two banners and seven screenshots under
  `.wordpress-org/`; excluded from the plugin archive.

## Senior developer review

- The REST fix is at the post-type-specific response boundary and delegates
  password state to WordPress.
- Password-free public REST behaviour remains backward compatible.
- Calendar feeds and public query services keep their stricter total exclusion of
  protected events.
- Version values, stable tag, lockfile and POT project identifier are synchronized
  to 0.2.2.
- The English product specification preserves the existing architecture and scope
  boundaries without introducing new runtime behaviour.
- Visual assets are separate from runtime assets and cannot enter the allowlisted
  release archive.

## Senior QA review

- The security regression proves both anonymous denial and authorized editor
  success on real WordPress.
- Both supported WordPress versions passed against the exact packaged staging
  tree.
- Browser coverage verifies calendar loading, filtering, fallback and geometry,
  12/24-hour display, captured wall time and Gutenberg atomic fields.
- The WordPress.org readme uses the exact contributor account `mimeonline`, stays
  below 10 KB and has seven captions synchronized with the seven prepared images.
- Prepared screenshots contain fictional demo data and omit local-link status and
  external URL values.
- The disposable smoke harness now tolerates bounded WordPress startup and REST
  nonce-readiness delays observed during repeated compatibility runs without
  weakening any plugin assertion.

## Remaining publication gates

1. Push the reviewed 0.2.2 commit and require the pinned strict official
   WordPress Plugin Check workflow to pass on that exact commit.
2. Confirm WordPress.org/GitHub account two-factor authentication and recovery
   details.
3. Submit the verified zip through the `mimeonline` WordPress.org account.

No push, tag, GitHub release or WordPress.org submission was performed as part of
this local qualification.
