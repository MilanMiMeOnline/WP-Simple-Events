# 0.9.0 RC5 performance qualification

**Date:** 1 September 2026

**Scope:** bounded public occurrence queries, event-list rendering, calendar REST
feeds, Elementor/Divi event-option providers, recurrence expansion and the
production release package.

**Status:** local qualification complete. Hosted compatibility, upgrade and
strict Plugin Check evidence is pending the reviewed commit.

## Outcome

RC5 establishes the first executable performance contract for the plugin. A
deterministic disposable WordPress installation now measures 500 public event
series, including 5,000 projected recurring occurrences, against hard result,
query and payload budgets plus deliberately broad WebAssembly timing backstops.

The first representative list measurement exposed a genuine N+1 hotspot: 50
event cards required 262 database queries. The shared occurrence presenter now
primes the already-authorized parent posts, metadata and terms in one bounded
WordPress cache operation before rendering. The final list measurement uses 18
queries. It preserves the existing repository, visibility and presentation
contracts and adds no persistent cache, invalidation path, schema or dependency.

## Reference dataset and budgets

The normative contract is documented in
[PERFORMANCE-BUDGETS.md](PERFORMANCE-BUDGETS.md). Its test-only fixture creates:

- 400 published one-off events;
- 100 published recurring-series representatives;
- 50 active rule occurrences per recurring series, or 5,000 recurring rows;
- 20 categories and 40 tags with deterministic assignments; and
- enough matching content to fill every production-bounded result window.

The fixture is mounted beside the release package only inside a destroyed
WordPress Playground. It is excluded from the distribution archive.

## Measured local results

Each HTTP scenario ran once as warm-up and five times for measurement. The table
reports the observed median PHP time and maximum deterministic resource values.

| Scenario | Observed result | Maximum queries | Output size | Median PHP time | Budget result |
| --- | ---: | ---: | ---: | ---: | --- |
| Filtered occurrence window | 100 rows | 2 | n/a | 192.673 ms | Pass |
| Event list with visitor filters | 50 cards | 18 | 53,707 bytes | 4,180.204 ms | Pass |
| Calendar REST feed | 100 items | 17 | 41,901 bytes | 4,092.346 ms | Pass |
| Builder event options | 50 options / 16 reads | 7 | n/a | 24.356 ms | Pass |
| Maximum recurrence expansion | 551 slots | 0 | n/a | 28.961 ms | Pass |

The wall-time values include the substantial cost of the CI-safe WebAssembly
Playground runtime and are not normal-hosting claims. Result cardinality, query
count and payload size are the primary cross-environment regression signals.

## Senior developer review

- The optimization uses WordPress' request-local post cache for a maximum bounded
  occurrence page. It does not persist data or introduce cache invalidation risk.
- Event IDs originate from the public occurrence repository. Individual context
  resolution still enforces published and password-free parent events; priming a
  cache is not an authorization decision.
- Duplicate and invalid IDs are removed before one cache-prime call. Metadata and
  terms are primed because both are consumed by the shared card presenter.
- The public occurrence repository retains its exact one rows query plus one count
  query contract, including category and tag predicates.
- The performance fixture's direct occurrence inserts model valid derived rows in
  a disposable test database only. Production direct-database access remains
  confined to the reviewed occurrence adapters.
- No public REST schema, block attribute, shortcode, Elementor widget, Divi
  module, CSS selector, database schema or runtime dependency changed.

## Senior QA review

The harness deliberately fails on an unhealthy fixture before taking any timing
sample. During its construction it detected and helped correct three fixture
assumptions: the seed needed an authorized WordPress publishing context, raw
derived rows also include legitimate non-rule rows, and REST taxonomy filters use
their public array shape. These were fixture defects, not suppressed failures.

The final health gate proves the representative parent events are public,
projection generation and coverage are current, no sampled series is dirty, all
5,000 rule occurrences are publicly queryable and a filtered taxonomy result can
fill the production limit. Card counting uses the exact article component marker,
not a loose class-prefix match. A transient Playground process failure was not
reclassified as a pass; the clean rerun completed every scenario within budget.

The full packaged browser suite also passed all 35 visitor, responsive,
no-JavaScript, Gutenberg, recurrence, privacy and accessibility journeys after
the optimization.

## Local qualification evidence

| Gate | Result |
| --- | --- |
| `composer validate --strict` | Pass |
| PHP coding standards and compatibility | Pass, 8/8 |
| PHPStan | Pass, 323 files, zero errors |
| PHPUnit | Pass, 776 tests / 3,201 assertions |
| Composer audit | Pass, zero advisories/abandoned packages |
| Frontend/Divi build and tooling contracts | Pass, 76/76 |
| ESLint and Stylelint | Pass, zero warnings |
| npm audit | Pass, zero vulnerabilities |
| Performance contract | Pass, 5/5 scenarios |
| Complete packaged browser matrix | Pass, 35/35 |
| Translation catalogue | Pass, current |
| WordPress 6.9 / PHP 8.2 packaged smoke | Pass |
| WordPress 7.1 / PHP 8.2 packaged smoke | Pass |
| Historical upgrades and lifecycle paths | Pass, 8 releases plus retention/repair/uninstall paths |
| Release verification and reproducibility | Pass, two byte-identical builds |
| Package SHA-256 | `6a65ff0c7243bb5c8fbce71c86e5c988218cdc2ecf9ddade3e8c13125daba944` |

The staging archive still reports public version 0.7.0 because the feature-frozen
0.9.0 version bump belongs to RC8. It is not published from this checkpoint.

## Hosted acceptance evidence

Pending the reviewed commit and complete GitHub Actions run. This section will be
updated with the immutable run and commit identifiers before final handoff.

## Residual non-blocking risks

- WebAssembly timing is a stable regression backstop, not a prediction for every
  database server, object cache, theme, plugin stack or hosting provider.
- The dataset exercises every public limit and substantial recurrence volume but
  cannot reproduce every real site's taxonomy distribution or database history.
- The budgets protect plugin-owned bounded paths. External page builders, themes,
  CDNs and full-page caches remain outside this measurement boundary.
- FullCalendar 6.1.21 remains the frozen 1.0 integration. ADR-096 schedules a
  backwards-compatible FullCalendar 7 investigation after 1.0 rather than mixing
  a dependency migration into performance hardening.
