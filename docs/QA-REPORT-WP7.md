# QA report — WP7 final release qualification

**Date:** 2026-07-20
**Candidate:** Simple Events by MiMe 0.1.1
**Scope:** all eight acceptance-testing findings and WP0–WP6 deliverables
**Archive SHA-256:** `70a980d3b8f9ccb706c8aa2bd7412d8605668a6016d2f43d3070b2626931839a`

## Result

WP7 accepts Simple Events by MiMe 0.1.1 as a locally qualified installable candidate. The exact staged package, not only the source tree, passed the supported WordPress matrix, browser regression suite and deterministic release checks. All eight findings in `docs/BACKLOG-TESTING.md` now have implementation and release-qualification evidence.

This is not approval for public publication by itself. Publication remains conditional on the official strict WordPress Plugin Check job passing for the release commit in CI.

## Final automated evidence

| Gate | Result |
| --- | --- |
| Composer strict validation | Pass |
| PHP coding standards | Pass, 8/8 groups |
| PHPStan level 8 | Pass, 129 files, no errors |
| PHPUnit | Pass, 262 tests and 1,003 assertions |
| Composer dependency audit | Pass, no advisories |
| Production/frontend build | Pass |
| JavaScript and CSS lint | Pass, zero warnings |
| Node tooling contracts | Pass, 11 tests |
| npm audit | Pass, zero vulnerabilities |
| Translation catalogue | Pass, regenerated and byte-for-byte current with WP-CLI 2.12.0 |
| Release verification | Pass, including allowlist, shipped PHP syntax, authoritative autoloader and checksum binding |
| Reproducibility | Pass, two consecutive builds produced identical bytes |
| Packaged WordPress 6.9 / PHP 8.3 smoke | Pass |
| Packaged WordPress 7.0.1 / PHP 8.3 smoke | Pass |
| Packaged Playwright suite | Pass, 15/15 journeys |
| Elementor 3.x host contract | Pass on 3.35.9 / WordPress 7.0.1 / PHP 8.3 |
| Elementor current host contract | Pass on 4.1.5 / WordPress 7.0.1 / PHP 8.3 |

## Regression and compatibility coverage

- Calendar controls retain readable normal, hover, focus, pressed, selected and disabled states, including custom Elementor colors and forced-colors mode.
- Empty/disabled filters, namespaced query state, multiple calendar instances and no-JavaScript fallback remain isolated.
- Same-day, multi-day, all-day, captured-wall-time, DST and WordPress 12/24-hour presentation contracts are protected across server and browser paths.
- Delayed, failed and initially hidden calendar rendering retains usable geometry and accessible fallback states.
- External action labels and optional timezone visibility share native, shortcode, Elementor and Gutenberg presentation services.
- Explicit event sources reject malformed, draft, private, password-protected and non-event records without fallback or data leakage.
- Twelve Elementor widgets and twelve Gutenberg blocks share semantic field output, empty states, escaping, recursion protection and request-local presentation reuse.
- Gutenberg registration, dynamic serialization, authenticated ServerSideRender preview, Query Loop context and visitor asset isolation run against the package.
- Elementor Free-compatible integration registers all fifteen widgets and their required controls on both tested major host lines; template assignment remains host-owned and does not require a separate plugin implementation.
- Native smoke journeys run with Elementor and WooCommerce absent, confirming that both remain optional rather than core dependencies. Earlier joint-host qualification with WooCommerce 10.9.4 remains valid because WP1–WP6 introduced no WooCommerce integration or dependency.

## Senior developer review

- The complete backlog remains inside the agreed version-one scope: no recurrence, interactive maps, ticketing, geocoding or custom event table was introduced.
- Storage and public-query contracts remain centralized in WordPress post, taxonomy, metadata, REST and query APIs. Presentation hosts do not read arbitrary metadata or issue unbounded queries.
- Calendar, Elementor and Gutenberg integrations are thin adapters over shared, access-aware services. Optional host integrations remain version-gated and cannot prevent core plugin boot.
- Editor-only payloads are not emitted on visitor pages; event selectors are bounded to fifty published, password-free events and reused within a request.
- Existing shortcode identifiers, Elementor widget names/control identifiers, native fallback blocks and saved-event semantics remain backward compatible.
- Production dependencies remain unchanged and all runtime assets are local and covered by the release allowlist.

## Senior QA and security review

- Capability, nonce, REST permission/schema, validation, sanitization and late contextual escaping checks remain covered for all state-changing and public boundaries.
- Public queries and explicit component sources fail closed for non-public or protected content. Tests cover invalid input, corrupt optional values and unauthorized access.
- Keyboard focus, semantic links/headings, live status messages, empty/loading/error states, responsive calendar geometry and theme-inherited styling have automated contract coverage.
- Event correctness covers timed/all-day, same-day/multi-day, DST, IANA/fixed zones, WordPress time notation, upcoming/ongoing/past ordering and invalid ranges.
- The archive was rebuilt after the final translation update, verified twice, then rerun on both supported WordPress versions. No local Playground process or temporary compatibility configuration remains.

## Residual release conditions

1. The configured `wordpress/plugin-check-action@v1` job must be observed green against `.release/simple-events-by-mime` for the WP7 release commit before public publication.
2. Automated semantic and host-contract coverage replaces a full manual visual tour of every Elementor, Gutenberg and Site Editor composition. A final stakeholder visual acceptance pass is advisable but is not a code/security blocker.
3. The translatable source catalogue is complete, but the plugin does not yet ship a human-authored Dutch or other locale translation.

## Candidate

Installable archive: `dist/simple-events-by-mime-0.1.1.zip`
Checksum file: `dist/simple-events-by-mime-0.1.1.zip.sha256`
