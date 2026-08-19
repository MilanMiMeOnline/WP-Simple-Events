# QA report — 0.3.0 WP5 interaction and semantic polish

**Date:** 19 August 2026
**Scope:** shared list, calendar, details, Elementor and Gutenberg interactions
**Status:** complete

## Intended behaviour

The existing public components gain the bounded controls needed for practical
page composition without changing event storage, access, queries or defaults.
List/Grid controls card sections, excerpt length and title semantics. Calendar
controls its initial date, toolbar groups and fallback heading. Complete Details
controls field groups, title semantics and meaningful labels.

Visitor filters remain usable as normal GET forms without JavaScript. An explicit
empty selection is retained, reset restores the component's configured initial
constraints, and multiple instances preserve one another's bounded state.
Elementor and Gutenberg expose the same content choices through their own native
control systems.

## Senior developer review

- All public inputs are normalized through existing shortcode adapters, strict
  booleans, choice allowlists, a real Gregorian date check, bounded integers,
  sanitized term slugs and 120-character plain-text labels.
- Defaults reproduce existing card, calendar and complete-details output.
- Elementor and Gutenberg remain presentation adapters; they add no event query,
  metadata read, endpoint or persistence model.
- Namespaced GET apply markers distinguish an untouched initial constraint from
  an intentionally empty visitor selection. Preserved state is allowlisted,
  value-bounded and isolated from the current instance.
- Calendar enhancement restores configured terms and removes only its own URL
  parameters. The server-rendered form remains authoritative without JavaScript.
- External event-card location links use `target="_blank"` with
  `rel="noopener noreferrer"`; event permalinks remain same-tab.
- Hidden titles retain an escaped accessible component name, heading elements
  are allowlisted, and all-hidden Details output creates no empty wrapper.

The review found and corrected one multiple-calendar regression before handoff:
a scalar apply marker for another calendar was being serialized as an array when
the first calendar built its reset URL. A failing unit test now protects the
correct scalar shape and intentional empty-selection behaviour.

## Senior QA review

Automated regressions cover:

- default and hostile List/Grid, Calendar and Details attribute normalization;
- title/date visibility, excerpt bounds, heading allowlists and accessible names;
- real versus invalid initial dates and individually optional toolbar groups;
- complete-details field visibility, label sanitization and all-hidden output;
- Elementor control presence, defaults, conditions and shared-renderer mapping;
- Gutenberg schema parity, strict adapter mapping and Inspector discoverability;
- no-JavaScript GET filtering, apply markers, reset URLs and multiple instances;
- enhanced calendar reset, URL cleanup, empty/error/loading states and toolbar
  configuration;
- isolated external links and unchanged internal event navigation.

## Security and privacy

- No state-changing public action, nonce boundary or privileged capability was
  added; filters remain read-only GET state.
- No private, draft, scheduled or password-protected event becomes eligible.
- No arbitrary metadata key, HTML label, remote resource, cookie, telemetry or
  personal-data log was introduced.
- Request preservation accepts only documented instance keys and bounded scalar
  values. Rendering continues to escape late for its exact context.
- Production JavaScript dependencies remain local and dependency audits report no
  high or critical vulnerability.

## Quality evidence

- Focused unit and adapter suites: passed.
- `composer validate --strict`: passed.
- `composer qa`: passed.
- `npm run qa`: passed.
- Isolated WordPress 7.0.1 browser suite: passed.
- Packaged WordPress 6.9 and 7.0.1 smoke matrix: passed.
- Translation catalogue freshness: passed.
- Reproducible release archive verification: passed.

Official Plugin Check, the supported Elementor matrix and the final
non-destructive production check remain WP6 release-qualification gates; they are
not claimed as part of this work package.

## Residual risk

Native browser rendering of multi-select controls varies by operating system, but
labels, help text, keyboard access and the no-JavaScript submission path remain
standard HTML. Subjective production-theme fit and the supported Elementor
version matrix receive one final check in WP6 before release.
