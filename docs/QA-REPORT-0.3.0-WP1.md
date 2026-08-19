# QA report — 0.3.0 WP1 Elementor calendar lifecycle

**Date:** 18 August 2026
**Scope:** roadmap UX-001 / WP1
**Release state:** unreleased development increment

## Intended behaviour

An Event Calendar must progressively enhance during an ordinary document load and
when Elementor dynamically adds or rerenders the widget in its editor preview.
Repeating Elementor's element-ready hook for the same rendered root must not add a
second FullCalendar instance, event listener or feed request.

## Risk review

- **Compatibility:** existing native and shortcode calendars keep their original
  document-load path; Elementor remains optional.
- **Performance:** one `WeakMap` tracks live roots and no page-wide mutation
  observer or polling loop is introduced.
- **Lifecycle:** changed roots remove their owned listeners, resize observer and
  FullCalendar instance before replacement.
- **Security/privacy:** the change adds no endpoint, input, storage, remote asset or
  public data surface. Existing same-origin bounded feed rules are unchanged.
- **Accessibility:** existing server fallback, live status and FullCalendar controls
  remain the rendered interface; no semantic output changed.

## Regression evidence

The new isolated browser fixture keeps the server-rendered calendar in an inert
template until a delayed Elementor frontend init and
`frontend/element_ready/wpse-event-calendar.default` action occur. It verifies:

1. the current document-only bundle fails to initialize the inserted widget;
2. the lifecycle adapter initializes one usable calendar and one feed request;
3. repeating the hook creates no duplicate instance or request;
4. replacing the widget root creates exactly one fresh instance and one fresh
   request;
5. month-grid geometry remains healthy.

The regression failed before the runtime change and passed afterward.

## Automated checks

| Check | Result |
|---|---|
| `composer validate --strict` | Pass |
| `composer qa` | Pass — 271 tests, 1023 assertions; PHPStan clean; no Composer advisories |
| `npm run qa` | Pass — build, 21 tooling tests, JS/CSS lint and npm audit |
| Full Playwright suite on WordPress 7.0.1 | Pass — 16 journeys |
| WordPress 6.9 smoke journey | Pass |
| WordPress 7.0.1 smoke journey | Pass |
| `npm run test:release` | Pass — verified and byte-for-byte reproducible archive |
| `git diff --check` | Pass |

## Manual Elementor verification

The generated 0.2.5 development package was installed over the existing copy on
the disposable `simpleevents.local` site. On WordPress 7.0.4, PHP 8.3.30,
Elementor Free 4.2.2 and Twenty Twenty-Five 1.5, the existing Home editor preview
contained:

- one Event Calendar root;
- one FullCalendar instance;
- the expected `No events match your selection.` status;
- no plugin-related warning or error in the captured browser console.

Opening the same editor through the in-app proxy port `:10012` produced an
Elementor-owned cross-origin `SecurityError` because the preview uses the site's
canonical URL without that port. Repeating the check through
`http://simpleevents.local/` restored the complete editor and calendar. This is a
local access-origin limitation, not a plugin regression.

## Senior developer review

- The initializer remains host-neutral and reuses the existing calendar renderer.
- Elementor integration is feature-detected; no Elementor script dependency is
  added to native pages.
- Instance ownership and cleanup are local to one rendered calendar root.
- Existing public configuration, selectors and feed contracts remain compatible.

## Senior QA review

- The regression asserts outcomes and request counts rather than implementation
  trivia.
- Ordinary, mobile, multiple-instance, hidden-container, delayed-feed, feed-error,
  timezone, 12/24-hour and Gutenberg journeys still pass.
- No test data or Theme Builder condition was added to the production site.
- The local disposable site intentionally retains its existing QA content and the
  updated development plugin build.

## Residual risk

The automated lifecycle fixture exercises Elementor's documented hook shape and
the manual check covers Elementor 4.2.2. The formal pre-release Elementor matrix
must still rerun against the declared minimum 3.35.x and the then-current 4.x
release before 0.3.0 is published.
