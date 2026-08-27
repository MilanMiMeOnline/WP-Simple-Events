# QA evidence — 0.6.0 shared filters and URL state

**Date:** 2026-08-27

**Scope:** FCR-2 shared filter view model, semantic taxonomy controls, active
choices and bounded no-JavaScript URL behaviour

## Intended behaviour

Event lists, calendars and the native event archive now use one public filter
contract. Categories and tags are ordinary labelled checkbox groups; active,
still-public terms appear as independent removal links. Visitors can remove one
choice, one taxonomy group or all visitor choices. A distinct restore action is
shown only when configured component defaults exist and differ from the clean
state.

Every action remains a valid GET request. JavaScript enhances the calendar but is
not required to submit, remove, clear or restore a filter. Existing namespaced
request keys and apply markers remain compatible.

## Red-green evidence

- Four focused control tests first failed against the former native multiple
  selects. They now require semantic fieldsets, legends, checkbox labels and
  stable namespaced field names.
- URL-state tests first failed because no shared bounded state service existed.
  They now cover current-instance exclusion, other list/calendar instance state,
  unknown and non-string keys, excessive values, oversized strings and escaped
  hidden fields. The non-string-key regression reproduced a public `TypeError`
  before the allowlist boundary was hardened.
- Active-choice tests first failed because no shared renderer existed. They now
  cover safe public term resolution, markup stripping, individual removal, group
  clearing, clear-all, conditional restore and exclusion of unknown slugs.
- The browser calendar journey was updated from native multi-select interaction
  to checkbox interaction and passed with persistent namespaced URL state and
  restored defaults.

## Security and privacy review

- Only the established namespaced list/calendar keys are preserved between
  component actions; arbitrary request keys are rejected.
- Preserved state is bounded to 80 values in total, 20 values per key and 200
  characters per scalar value before rendering.
- Request values are sanitized at the input boundary and escaped again for their
  URL, attribute or text output context.
- Active chips are resolved from available public `WP_Term` objects. Unknown,
  deleted or non-public selected slugs never become visitor-visible chip content.
- The change adds no endpoint, cookie, analytics, remote request, personal-data
  processing or public eligibility rule. Draft, private, protected and bounded
  event-query rules remain owned by the existing query layer.

## Automated qualification

The completed implementation passed:

- `composer validate --strict`;
- `composer qa`: PHPCS, PHPStan, 693 PHPUnit tests with 2,621 assertions and the
  Composer advisory audit;
- `npm run qa`: deterministic production builds, 54 tool-contract tests, ESLint,
  Stylelint and the high-severity dependency audit;
- the focused calendar browser regression for checkbox filtering and persistent
  namespaced state;
- `npm run test:smoke` against the packaged plugin in an isolated WordPress
  Playground environment;
- translation-template regeneration and `git diff --check`.

No high or critical dependency vulnerability was reported.

## Senior developer review

The new objects have one responsibility each: immutable view state, bounded URL
state, taxonomy group markup and active-choice markup. Lists, calendars and the
native archive delegate to those objects rather than forking request or escaping
logic. Existing shortcode, block, Elementor and Divi attributes continue to reach
the same shared server renderer.

## Senior QA review and residual scope

The no-JavaScript baseline, keyboard-native checkbox semantics, instance
isolation, malformed state and empty/default distinctions are protected. FCR-2
does not claim the responsive disclosure, enhanced focus/history behaviour or
complete editor style controls planned for FCR-3 and FCR-4. Category/event colors
and legends remain isolated in FCR-5 and FCR-6. Those are deliberate next work
packages, not defects hidden by this handoff.
