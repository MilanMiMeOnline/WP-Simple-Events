# QA report — WP1.3

**Date:** 2026-07-18
**Scope:** Calendar filter empty state, configuration clarity and initial-constraint parity (`WPSE-BL-002`)

## Delivered behaviour

- Calendar visitor filters remain enabled by default and can still be disabled explicitly.
- No filter form or `Apply filters` action is rendered when neither event taxonomy has a non-empty public term.
- Category-only, tag-only and combined term states render only useful selectors.
- Elementor now distinguishes `Initial categories`, `Initial tags` and `Show visitor filters` without changing their saved control IDs.
- Initial category/tag constraints apply to both the server fallback and interactive feed, including when visitor controls are disabled or omitted.

## Senior developer review

- The empty-state fix is one early return after the existing bounded `get_terms()` calls; no new query, database table or dependency was introduced.
- The shortcode and Elementor defaults, attribute names and stored setting identifiers remain backward compatible.
- JavaScript receives only the already normalized slug arrays and falls back to them solely when the corresponding visitor selector does not exist.
- The progressively enhanced GET contract remains the baseline. Instance-specific field names and allowlisted preservation prevent cross-calendar state leakage.
- The E2E harness now supports explicit local core paths and deterministic offline version resolution; fixtures publish through the real nonce/capability save path.

## Senior QA and security review

- Renderer regressions cover no terms, category only, tag only and both taxonomies, including selected values, reset output, GET semantics and preserved state from a second calendar instance.
- Attribute coverage proves disabled filters ignore forged matching query values.
- Browser coverage applies category and tag filters, verifies namespaced URL state survives reload, resets only this instance, checks live status updates and retains multiple-calendar isolation.
- A browser regression proves hidden visitor controls no longer drop configured initial constraints from REST feed requests.
- Only published-event term counts are exposed; the existing public feed, password exclusion, slug bounds and REST schemas are unchanged.
- One narrow PHPCS suppression exists only in the E2E fixture where the original request array is preserved before injecting a test nonce; production code has no suppression or weakened authorization.

## Automated evidence

| Gate | Result |
|---|---|
| `composer qa` | Pass — PHPCS, PHPStan, 180 tests / 633 assertions, Composer audit |
| `npm run qa` | Pass — build, tooling tests, JS/CSS lint, npm audit |
| `npm run i18n:check` | Pass |
| `composer test:integration` | Pass — real WordPress Playground smoke journey |
| `npm run test:e2e` | Pass — 8 Chromium journeys against WordPress 7.0.1 and the exact staging package |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- Calendar wall-time and 12/24-hour correctness remain the next WP2 package (`WPSE-BL-003`, `WPSE-BL-004`).
- Final visual comparison inside supported Elementor 3.x/4.x editors remains part of release-candidate host-matrix QA; stable control contracts and shared frontend behaviour are automated here.
