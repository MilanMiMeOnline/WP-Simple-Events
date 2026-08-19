# QA report — 0.3.0 WP4 Gutenberg composite parity

**Date:** 19 August 2026
**Scope:** Event List / Grid, Event Calendar and Event Details dynamic blocks
**Status:** complete

## Intended behaviour

Gutenberg users can insert the three primary public event components without
copying shortcodes. The blocks remain thin WordPress adapters: their attributes
are normalized into the established shortcode and complete-details contracts,
and their public output is produced by the existing shared renderers.

The change deliberately adds no new event storage, public endpoint, query model,
recurrence feature or raw metadata surface. Existing atomic blocks, shortcodes,
Elementor widgets and serialized content keep their names and behaviour.

## Senior developer review

- Registration is metadata-driven through three dedicated `block.json` files and
  the existing editor script and frontend style handles.
- Collection attributes use strict types, enumerated values, bounded integers and
  at most 20 sanitized, deduplicated taxonomy slugs.
- Editor taxonomy choices are loaded only on block-editor requests and are bounded
  to 100 terms per taxonomy.
- List and Calendar delegate to their request-shared native shortcode renderers;
  Event Details delegates to the shared complete-event renderer.
- Explicit Event Details selection accepts only a positive integer and uses the
  public-source path. Current context accepts only an event `postId`/`postType`
  pair, with a queried event route as the documented fallback.
- Dynamic blocks save no rendered HTML. ServerSideRender loading, empty and error
  states stay inside the editor.
- Native block-support wrapper attributes are emitted only when shared output is
  non-empty. Existing renderer escaping remains the final output boundary.

No duplicate query, metadata or presentation implementation was found during the
review.

## Senior QA review

Automated regressions cover:

- the exact three-block metadata catalogue and stable handles;
- valid and hostile attribute normalization, bounds and fallbacks;
- delegation to the native list and calendar renderers;
- successful current-event details plus fail-closed explicit draft access;
- client registration and the shared inserter category;
- dynamic attribute-only serialization;
- authenticated server previews for all three components;
- bounded category and tag choices in the editor;
- anonymous frontend list, calendar and details output;
- a visible list/calendar fallback when visitor JavaScript is disabled;
- absence of the Gutenberg editor bundle from the public page.

The isolated browser fixture initializes its capability-protected data through an
administrator request before opening a separate anonymous JavaScript-free context.
This keeps the public assertion representative without making the test seed data
publicly writable.

## Security and privacy

- No state-changing browser request or new persistence path was introduced.
- No private, draft, scheduled or password-protected explicit event can be
  selected through Event Details public rendering.
- Collection visibility continues to use the existing bounded public query and
  calendar REST contracts.
- No telemetry, cookies, remote assets, personal-data logging or third-party
  request was added.
- Every user-facing string is translatable with the plugin text domain.

## Quality evidence

- Focused PHPUnit composite-block suite: passed.
- `composer validate --strict`: passed.
- `composer qa`: passed.
- `npm run qa`: passed.
- Isolated WordPress 7.0.1 browser suite: passed.
- Packaged WordPress 6.9 and 7.0.1 smoke matrix: passed.
- Translation catalogue freshness: passed.
- Reproducible release archive verification (`npm run test:release`): passed.
- Official Plugin Check remains a final WP6 release gate and is not claimed by
  this work package.

## Residual risk

ServerSideRender validates real editor registration, serialization and previews,
but automated tests do not assess subjective discoverability of every native
Gutenberg control at every viewport. WP5 retains the cross-host interaction and
semantic polish work, and WP6 retains the final manual supported-version/editor
qualification.
