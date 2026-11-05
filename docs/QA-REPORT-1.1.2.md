# MiMe 1.1.2 release qualification

**Date:** 5 November 2026  
**Status:** Released and publicly verified on GitHub and WordPress.org.

## Authorization and scope

After testing `1.1.2-beta.1`, the owner explicitly approved publication following
a final exploratory review. This fulfills ADR-104. The final candidate contains
the same runtime fixes as the owner-tested beta; only release version metadata
and qualification documentation have changed since that candidate. CI scheduling
is additionally split across independent machines under ADR-111; runtime files
and the qualified package checksum are unchanged.

CAL-001 restores component-owned calendar defaults by omitting missing semantic
color properties. SEO-001 wraps the existing effective free-text address in
`PostalAddress.name`, with the venue still in `Place.name` and empty addresses
omitted. ADR-110 records the contract. There are no new editor fields, database
migrations, inferred address parts, permission changes or dependency upgrades.

## Senior developer and QA review

The final runtime diff is confined to the calendar adapter and schema builder,
plus the two rebuilt bundles. The hexadecimal color allowlist, category/event
precedence, safe JSON encoding, public eligibility checks and occurrence address
resolution remain intact. Storage and save paths are unchanged.

The [beta qualification report](QA-REPORT-1.1.2-beta.1.md) records the original
failing regressions, successful fix, address-retention checks and all 42 local
browser journeys. The original transparent bar was also reproduced against the
published 1.1.1 package. Final review used `QA-CHECKLIST.md`; no assertions,
budgets, timeouts or quality rules were weakened.

The final package contains 384 files and differs from public 1.1.1 in exactly
seven expected files: schema builder, calendar and Divi-editor bundles,
bootstrap, translation header, changelog and readme. Tests, development packages
and qualification documents are excluded from the installable archive.

## Exploratory use of the final candidate

On an isolated, disposable WordPress site, the reviewer used the normal event
editor to set a timed event to 14 November 11:00 through 15 November 18:00,
select the calendar default color, and save a multiline address containing an
accent and a venue containing an ampersand. Reopening confirmed the saved data.

- Month navigation and category-colored multi-day/all-day bars worked.
- The November event rendered as one solid, readable two-day bar, with the
  component default background rather than an undefined CSS value.
- List view showed 14 November as starting at 11:00 and 15 November as ending
  at 18:00, preserving the postponed status.
- The public event page showed the saved venue and all three address lines.
  Its JSON-LD contained the same complete address in `PostalAddress.name` and
  the venue in `Place.name`, preserving the saved timezone and exact dates.

The temporary site was stopped and removed after the test. No customer site or
database was changed. Local screenshot/log evidence is in
`/private/tmp/mime-112-release`; it is not shipped.

## Final candidate checks

- Composer strict validation and QA passed on PHP 8.3.36: 792 tests / 3,278
  assertions, coding standards, static analysis and zero audit findings.
- npm QA passed on Node 24.21.0: 107 tooling tests, browser builds, JS/CSS lint
  and zero audit findings.
- Translation generation and consistency checks passed.
- Two consecutive archive builds were byte-identical; archive contents, PHP
  syntax, production autoloader and checksums passed verification.
- Final WordPress 6.9 and 7.1 / PHP 8.2 packaged smoke checks passed.
- All six unchanged performance budgets and 17 coverage cases passed on the
  500-series / 5,000-occurrence dataset. The local feed returned 100 items in
  41,901 bytes, with 21 queries and a 158.353 ms median (test-environment data).
- All 12 historical upgrades from 0.2.3 through 1.1.1 and the complete lifecycle
  matrix passed locally. An initial run stopped on a database error while
  snapshotting old 0.2.3 before replacing its files; a clean, unchanged full
  rerun passed. The exact initial database error cause was not established.
- All 42 final-package browser journeys passed locally in 4.9 minutes.
- [Final hosted qualification](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/42059559812)
  passed all 13 jobs on `15c7cd109fccd5216b2173220b2b70b40fd8f169`: PHP
  8.2–8.5, JS/CSS, translations, 32 + 10 browser journeys, both WordPress
  smoke targets, performance, all historical upgrades and strict Plugin Check.
  Both browser shards passed without retries (2.7 and 3.9 minutes). The final
  independent CI archive again matches the local SHA-256.

Candidate: `dist/mime-simple-events-calendar-1.1.2.zip`  
SHA-256: `66ca83a5c7c97f7f392845ff0fe1e1fdedea969ceebfdfed0af8d3fbc28bab4f`.

## Boundaries

The Google Rich Results Test was not submitted. The address shape follows its
documented complete-address example and is covered by output regressions; it
does not guarantee rich-result eligibility. Proprietary Divi/Elementor host
combinations were not independently requalified for this presentation patch;
their existing interfaces, saved layouts and shared rendering paths remain
covered by the supported regression suite.

## Hosted qualification and CI infrastructure

The first [hosted run](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/42054573015)
passed the PHP 8.2–8.5, JS/CSS, translation, both smoke, performance, historical
upgrade and strict Plugin Check jobs. Its independent package was byte-identical
to the local candidate. The browser job twice reached its aggregate six-minute
limit after 41 and 40 passing tests; no individual assertion failed. An
intervening attempt was cancelled during slow Ubuntu font downloads, before any
browser test started. These incomplete runs do not count as successful gates.

ADR-111 splits the complete browser suite into two native file-level shards on
independent machines. One worker, zero retries, all test assertions and timeout
values are unchanged. Test discovery verifies that the two disjoint inventories
cover all 42 tests exactly once. Both shards and the full hosted matrix
subsequently passed before publication. The installation ZIP is unaffected by
this CI-only change.

GitHub initially printed an obsolete moderate-alert banner when pushing. The
alert API reported alert 21 as withdrawn (HTTP 410), and a separate list of open
Dependabot alerts returned none. Composer and npm audits also returned no findings.

## Publication

- Owner-approved [PR #28](https://github.com/MilanMiMeOnline/WP-Simple-Events/pull/28)
  merged as `0dbea3e16bc42833e949866f930f150cc32c7019`. Its source tree is
  identical to the qualified PR head; the local main branch was fast-forwarded.
- Tag `v1.1.2` points to that exact merge commit. The
  [GitHub release](https://github.com/MilanMiMeOnline/WP-Simple-Events/releases/tag/v1.1.2)
  was published at 16:00:11 UTC on 5 November 2026. Its public ZIP was downloaded
  and is byte-identical to the qualified local and CI packages.
- WordPress.org trunk and tag `1.1.2` were committed atomically in SVN revision
  `3786330`. Before commit, both trees matched all 384 qualified files byte for
  byte. The public plugin API initially still reported 1.1.1 while distribution
  propagated. It subsequently reported 1.1.2, and the public WordPress.org ZIP
  was downloaded: all 384 paths and file contents match the qualified package.
  WordPress.org repackages ZIP metadata; its download SHA-256 is
  `eb234c0243222b8e6639e058fbfcaf534430fdba000474aaa116f79021aaa60b`.
