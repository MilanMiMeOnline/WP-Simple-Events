# MiMe 1.1.2 release qualification

**Date:** 5 November 2026  
**Status:** Owner approved; final qualification in progress, not yet published.

## Authorization and scope

After testing `1.1.2-beta.1`, the owner explicitly approved publication following
a final exploratory review. This fulfills ADR-104. The final candidate contains
the same runtime fixes as the owner-tested beta; only release version metadata
and qualification documentation have changed since that candidate.

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
- Final performance, packaged smoke and historical upgrade checks are pending.
- Hosted PHP compatibility, browser regression, strict Plugin Check and full
  CI qualification remain publication gates.

Candidate: `dist/mime-simple-events-calendar-1.1.2.zip`  
SHA-256: `66ca83a5c7c97f7f392845ff0fe1e1fdedea969ceebfdfed0af8d3fbc28bab4f`.

## Boundaries

The Google Rich Results Test was not submitted. The address shape follows its
documented complete-address example and is covered by output regressions; it
does not guarantee rich-result eligibility. Proprietary Divi/Elementor host
combinations were not independently requalified for this presentation patch;
their existing interfaces, saved layouts and shared rendering paths remain
covered by the supported regression suite.

Publication evidence will be recorded after the qualified package is released.
