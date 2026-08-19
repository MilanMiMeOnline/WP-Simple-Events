# QA report — 0.3.0 WP2 event-aware discovery surfaces

**Date:** 18 August 2026
**Scope:** roadmap UX-002 / WP2
**Release state:** unreleased development increment

## Intended behaviour

Public event category and tag routes must behave as event collections rather than
ordinary publication-date archives. They retain the active theme shell, term
title and term constraint; expose only public, non-password events; render shared
event cards and pagination; and order the complete term history by event start
ascending. Blog, product and mixed search queries must remain unchanged.

## Risk review

- **Query isolation:** the adapter runs only for the front-end main event post-type
  archive or the two registered event taxonomies. Admin, secondary and unrelated
  main queries return before mutation.
- **Term integrity:** taxonomy archives do not translate visitor category or tag
  inputs into a replacement `tax_query`; WordPress' resolved route term remains
  authoritative.
- **Visibility:** the shared bounded query builder enforces `publish`, no post
  password, a maximum configured page size and valid pagination.
- **Template ownership:** classic, hybrid and full block themes retain one header,
  main event collection and footer. Classic generic and block taxonomy-specific
  theme overrides are covered separately.
- **Security/privacy:** no endpoint, write action, personal data, remote asset or
  new request parameter is introduced. Draft, private and password-protected
  events are excluded in the packaged WordPress journey.
- **Accessibility:** term titles, semantic cards, empty state, pagination and
  existing visible focus behaviour come from the shared renderers.

## Regression evidence

Unit coverage freezes:

1. event taxonomy recognition and ascending `_wpse_start_utc` ordering;
2. preservation of the native term query variable;
3. explicit public and non-password visibility;
4. no changes to mixed post/product/event relevance queries;
5. classic and block-theme taxonomy template selection;
6. taxonomy titles and omission of cross-archive filters;
7. registration of both taxonomy block fallbacks.

The packaged WordPress journey creates separate calendar and archive terms,
assigns past, active, future, protected and draft fixtures, and verifies category
and tag routes. It also visits the category archive under classic, hybrid, PHP
override, block and block-override fixture themes.

## Automated checks

| Check | Result |
|---|---|
| `composer validate --strict` | Pass |
| `composer qa` | Pass — 276 tests, 1042 assertions; PHPStan clean; no Composer advisories |
| `npm run qa` | Pass — build, 21 tooling tests, JS/CSS lint and npm audit |
| WordPress 6.9 packaged smoke journey | Pass |
| WordPress 7.0.1 packaged smoke journey | Pass |
| `npm run test:release` | Pass — verified and byte-for-byte reproducible archive |
| `git diff --check` | Pass |

## Senior developer review

- Query rules remain centralized in `EventQueryArguments`; no duplicate SQL or
  renderer-specific access rule was added.
- Taxonomy archives consume the existing main `WP_Query` rather than creating a
  second collection and retain WordPress' term scope.
- Template discovery remains at priority `0`, leaving later builder integrations
  free to own the archive location.
- Block taxonomy fallbacks are explicit because WordPress does not reliably use a
  custom post-type archive template as a taxonomy fallback. Existing identifiers,
  event metadata and public URLs remain compatible.
- Mixed search was inspected and deliberately left native: applying an event meta
  key or event ordering to a mixed query would exclude or misorder non-events.

## Senior QA review

- The regression would fail on the previous publication-date taxonomy behaviour.
- Past, active and future event order is asserted independently of publication
  dates.
- Protected and draft titles are asserted absent from public taxonomy output.
- Category and tag routes both receive shared event presentation and no general
  archive filters.
- Theme-shell checks assert exact header/footer counts, not only event content.
- The smoke environment deletes its fixtures and is destroyed after each run; no
  production site or local user content was changed.

## Residual risk

The official WordPress Plugin Check and Elementor compatibility matrix are release
qualification gates and were not repeated for this isolated WP2 increment. An
Elementor Pro archive display condition can still replace the low-priority native
fallback through the existing archive location, but taxonomy-specific Theme
Builder presentation remains part of the final 0.3.0 manual compatibility pass.
