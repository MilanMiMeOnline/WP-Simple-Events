# QA report — WP4

**Date:** 2026-07-19
**Scope:** Shared atomic event presentation foundation for `WPSE-BL-008`

## Delivered behaviour

- One access-aware resolver supports current page/template context and explicit public event selection without falling back to unrelated content.
- Current context permits WordPress-authorized draft, private or scheduled previews; explicit selection permits only published, password-free events.
- Resolved event data is normalized and reused within one resolver/request. Metadata keys and taxonomy storage objects stay inside the presentation layer.
- Twelve named field methods cover title, featured image, date/time/timezone, exceptional status, venue, address, location action, content, excerpt, external action, categories and tags.
- Missing or corrupt optional values create no public wrapper. Stored scalar values and URLs are re-sanitized, then escaped at their final HTML context.
- Atomic fields hide password-protected content. The complete details renderer retains WordPress' full password form.
- Request-wide guards stop content and complete-details recursion even when separate renderer instances call each other.
- The native template, details shortcode and existing Elementor Event Details widget now compose the same named fields while retaining their established grouping classes and output order.

## Senior developer review

- `EventContextResolver` owns source and visibility decisions; shortcode and renderer no longer implement competing post-access checks.
- `EventPresentationFactory` is the sole WP4 metadata reader and reuses the existing strict `EventMetaSanitizer` for untrusted stored values.
- `EventPresentation` contains named normalized values, while `EventTermPresentation` removes taxonomy objects from host adapters.
- `EventFieldRenderer` performs late contextual escaping and delegates content, excerpt and image HTML to WordPress' public/KSES pipelines.
- Resolver caching is instance- and request-local. It stores no options, transients or persistent object-cache entries.
- Existing `EventDetailsRenderer` constructor dependencies and `render()` method remain compatible; `render_public()` adds the explicit-selection boundary.
- No database query, raw SQL, remote request, production dependency, option or schema migration was added.

## Senior QA and security review

- Resolver tests cover valid public events, invalid IDs, non-events, draft/private/password-protected explicit selections, authorized current previews and request-local snapshot reuse.
- Every populated named field is verified for semantic classes and escaped output. Separate fixtures cover absent and corrupt metadata, malicious URLs, invalid status and empty wrappers.
- A fully populated protected event verifies that all twelve atomic methods return no output.
- Composite tests protect established grouping classes, complete field order, explicit/current access behaviour, WordPress password forms and cross-instance recursion.
- Existing real-WordPress smoke coverage verifies native output, password protection, two explicit details instances with unique IDs, and rejection of draft, protected and malformed selections.
- The packaged plugin passes the same journey on WordPress 6.9 and 7.0.1. Existing Chromium calendar regressions remain green.

## Automated evidence

| Gate | Result |
|---|---|
| Focused WP4 context/field/composite regressions | Pass — 11 PHP tests / 90 assertions |
| `composer validate --strict` | Pass |
| `composer qa` | Pass — PHPCS, PHPStan, 227 tests / 822 assertions, Composer audit |
| `npm run qa` | Pass — production build, 11 tooling tests, JavaScript/CSS lint and npm audit |
| `npm run i18n:check` | Pass |
| Source WordPress integration smoke | Pass |
| `npm run test:e2e` | Pass — Chromium regression suite against the staging package |
| Supported WordPress packaged smoke matrix | Pass — WordPress 6.9 and 7.0.1 |
| `npm run test:release` | Pass — verified archive and byte-for-byte reproducibility |

## Residual scope

- WP4 delivers the shared foundation, not new visible editor components. Dedicated Elementor widgets are WP5 and Gutenberg dynamic blocks are WP6.
- Atomic field labels and image/title controls expose only safe PHP defaults in WP4; host-native controls and editor placeholders arrive with their adapters.
- Official WordPress Plugin Check remains the CI release gate; this checkout has no local `test:plugin-check` package script.
