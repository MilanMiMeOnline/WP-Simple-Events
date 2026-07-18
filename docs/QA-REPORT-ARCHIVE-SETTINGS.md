# QA report: native archive settings and routing

## Scope

This increment adds bounded native archive configuration, WordPress-page path conflict diagnosis and change-driven rewrite maintenance. It does not add redirects, permalink history, caching, generic third-party rewrite inspection or Site Health tests.

## Automated evidence

- Settings tests cover defaults, sanitization, malformed stored options, the 1/50 page-size boundaries and the upcoming/all allowlist.
- Content registration tests prove that `has_archive` and the single-event rewrite base use the same validated configured slug.
- Archive-query tests prove that the configured page size/default period apply and that a valid visitor period overrides the default while invalid input does not.
- Conflict tests distinguish live/draft-capable pages from absent, trashed and non-page content.
- Rewrite tests prove that equivalent saves do not schedule work, custom first saves do, matching state flushes exactly once and stale/malformed state never flushes.
- Deactivation tests prove that the event post type and both event taxonomies are unregistered before one soft flush.
- The WordPress Playground journey saves a real conflict, observes the administrator warning, changes to a conflict-free archive path, verifies one-event pagination and the all-events default, overrides it with the upcoming filter, then restores and verifies the original route.
- The smoke runner recreates a dedicated temporary `WP_ENV_HOME`, so an interrupted previous run cannot leak a custom slug or fixtures and normal local development data is never reset or destroyed.
- The final PHP suite passes with 173 tests and 608 assertions. PHPCS passes and PHPStan reports no errors across 101 analyzed files.
- Composer validation and the locked Composer security audit pass without advisories.
- The calendar production build, JavaScript lint and CSS lint pass. The production npm audit reports zero vulnerabilities.
- The full development audit reports one low-severity advisory in nested WordPress lint-tooling copies of esbuild. It is not shipped as a production dependency and does not block the high/critical gate.
- The final isolated smoke journey passes on WordPress 7.0.1 with PHP 8.3.

## Senior developer review

Archive settings are isolated from shortcodes, REST and Elementor adapters. The main archive continues to use the existing bounded `EventQueryArguments` builder and only replaces typed criteria. Both archive and singular permalinks use one setting resolver, preventing route drift.

Rewrite regeneration runs only after a successfully persisted real slug change and only after post-type registration. The pending value names the target slug rather than acting as an unvalidated boolean. A stale or tampered marker is removed without expensive work.

## Senior QA and security review

- Every persisted setting is revalidated at read time.
- The slug is one sanitized segment and cannot introduce nested rewrite syntax.
- Page size remains inside the public 1–50 query bound.
- Period values use an enum allowlist; `past` remains an explicit visitor filter, not an accidental site default.
- All settings use the administrator-only native Settings API nonce/capability path.
- Admin messages escape the configured slug and reveal no page title, content or personal data.
- Rewrite flushes are soft, change-driven and one-shot.

## Residual risk

- Changing a permalink base can break external links; the UI documents this and version 1 intentionally creates no redirect history.
- Generic rewrite conflicts from arbitrary plugins, taxonomies or server configuration cannot be detected reliably. The required WordPress-page conflict is covered.
- Concurrent requests immediately after a slug save may race to perform the same idempotent soft flush before the marker is removed. This is harmless but could duplicate one expensive operation in that narrow window.
- WordPress 6.9 compatibility and Plugin Check remain release-matrix evidence.
