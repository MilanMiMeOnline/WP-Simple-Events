# QA report — WP6 Gutenberg dynamic blocks

**Date:** 2026-07-20  
**Scope:** `WPSE-BL-008` Gutenberg field components and cross-host parity

## Result

WP6 implements the complete twelve-field Gutenberg palette and closes BL-008. Every block is metadata-registered and dynamically rendered through the frozen WP4 services. Static pages can select one public event; templates and query descendants consume inherited event context. Elementor and Gutenberg now share field semantics, access decisions, formatting, empty states and recursion protection.

## Functional evidence

- Twelve dedicated blocks cover title, featured image, date/time/timezone, exceptional status, venue, address, location action, content, excerpt, external action, categories and tags.
- Field-specific Inspector controls match the Elementor allowlists, while native block supports provide typography, text/link colors, margin and alignment.
- ServerSideRender provides live previews plus separate loading, error and empty states. Dynamic blocks serialize attributes only.
- One opt-in Single Event Fields pattern demonstrates the complete palette without changing template assignment or existing fallback blocks.
- Missing optional values emit no public host wrapper. The editor bundle is absent from visitor pages.

## Security and performance evidence

- Explicit IDs accept only positive schema-shaped integers and resolve only published, password-free events. Invalid, draft, protected and non-event sources fail closed without current-context fallback.
- Current context requires matching `postId` and `postType`; a queried-object fallback is event-only and used only when inherited keys are absent.
- No block accepts metadata keys or builds a query. Presentation data and negative lookups are reused within one request.
- Editor choices use the bounded public repository, cache at most fifty options per request and are queried only on block-editor screens.
- Visible control text is bounded plain text; enums and booleans use strict allowlists. Shared renderers retain late contextual escaping and recursion guards.

## Automated evidence at implementation review

- Focused Gutenberg/Elementor tests: passed — 39 tests, 185 assertions.
- Complete PHPUnit suite: passed — 262 tests, 1,003 assertions.
- Strict Composer validation, PHP coding standards, full PHPStan level 8, JavaScript/CSS lint and tooling tests: passed.
- Composer and production npm dependency audits: passed with no known vulnerabilities.
- Translation-template freshness and release-contract verification: passed.
- Expanded real-WordPress source smoke: passed, including all twelve registrations, public/editor asset isolation, explicit/current rendering, native block supports, missing-value omission and draft rejection.
- Packaged WordPress smoke matrix: passed on WordPress 6.9 and 7.0.1 with PHP 8.3.
- Packaged Playwright suite: passed — 15 journeys, including complete visitor palette, real Query Loop context and authenticated Gutenberg registration/serialization/ServerSideRender preview.
- Two consecutive release builds are byte-for-byte reproducible. Release SHA-256: `70a980d3b8f9ccb706c8aa2bd7412d8605668a6016d2f43d3070b2626931839a`.

## Senior developer review

Dedicated metadata keeps every block discoverable while one renderer owns source resolution and field dispatch. The shared editor bundle avoids twelve duplicated payloads. Existing native fallback blocks and Elementor identifiers are untouched. Block support wrappers are host-owned and conditional, preserving inner semantic parity and empty-field behaviour.

## Senior QA review and residual risk

- Automated coverage exercises ordinary pages, current event content, REST previews and packaged browser rendering. A manual Site Editor drag-and-drop tour remains useful visual acceptance but is not required to establish the render/access contract.
- Official WordPress Plugin Check remains the strict CI publication gate.
- WP7 remains the final combined release-qualification package across all completed backlog items.
