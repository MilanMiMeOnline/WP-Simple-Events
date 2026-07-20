# Changelog

All notable changes to WP Simple Events are documented here.

## [Unreleased]

### Added

- Development, security and senior-QA guardrails.
- Automated code quality, static analysis, test and dependency-audit configuration.
- Minimal safe plugin bootstrap.
- Native `wpse_event` post type with event-specific categories and tags.
- Typed, revisioned and capability-protected event metadata.
- Explicit event capabilities for administrators and editors.
- DST-aware canonical event date-range model with derived UTC indexes.
- Idempotent activation, schema-version and rewrite lifecycle handling.
- Native, accessible Event details editor panel with date, location and status fields.
- Shared validation for native editor and REST writes, including actionable error codes.
- Publication guard that keeps incomplete or invalid events in draft state.
- Stored-record publication invariant for Quick Edit and other non-REST status writes.
- Canonical local and derived UTC metadata persistence after complete validation.
- Scoped event-editor CSS and JavaScript that inherit the WordPress admin interface.
- WordPress Playground smoke coverage for editor availability and atomic REST writes.
- Central, bounded `EventRepository` with upcoming/active, past and all period criteria.
- Upcoming-by-default native event archive ordering and visibility rules.
- Allowlisted `[wpse_events]` list/grid shortcode with isolated filters and pagination.
- Reusable event date, card and collection renderers independent from the global loop.
- Responsive, theme-inheriting and component-scoped public event styles.
- Public-query smoke coverage for active, future, past, draft and password-protected events.
- Native single-event and event-archive fallbacks for classic and block themes.
- `[wpse_event_details]` shortcode with public visibility and recursion guards.
- Elementor Theme Builder location precedence and theme override paths.
- Bounded `wpse/v1/events` calendar feed with half-open overlap queries and strict schemas.
- Allowlisted `[wpse_calendar]` shortcode with isolated category/tag filters and event-list fallback.
- Locally bundled FullCalendar core, day-grid and list modules with theme-inheriting responsive styles.
- Calendar loading, empty, truncation and error states with live announcements and visible event statuses.
- WordPress smoke coverage for feed visibility, pagination, filters, multiple instances and local assets.
- Optional, version-gated Elementor 3.35+ integration with a dedicated widget category.
- Event List / Grid, Event Calendar and Event Details widgets backed by the native shortcode render contracts.
- Responsive Elementor controls for event selection, layouts, filters, visibility, typography, colors, spacing, borders and buttons.
- Editor-only details placeholder and bounded public event preview selector.
- Request-wide component ID sequences that remain unique across separately constructed shortcode and Elementor renderers.
- Compatibility verification with Elementor 3.35.9 and 4.1.5, plus joint activation with WooCommerce 10.9.4, on WordPress 7.0.1 and PHP 8.3.
- Safe Schema.org Event JSON-LD on eligible individual event pages, with all-day and timezone-aware timed boundaries.
- An administrator-only Events settings page with a nonce-protected structured-data toggle and a per-event override filter.
- WordPress smoke coverage for structured-data visibility, password protection, settings persistence and SEO-plugin opt-out.
- Event-specific admin columns, timing/status/category filters and sortable start/end dates.
- A nonce- and capability-protected “Duplicate event” action with atomic rollback, explicit copy allowlists and copied-date review guidance.
- WordPress smoke coverage for admin views, category filtering, sort order, forged duplication nonces, duplicate copy policy and review-flag clearing.
- A fail-safe uninstall retention setting that preserves all event data by default and warns clearly before destructive opt-in.
- Batched, allowlisted single-site and multisite cleanup for event posts, event terms, plugin options and granted capabilities, while retaining shared media.
- Unit and WordPress smoke coverage for default retention, explicit cleanup, interrupted cleanup and per-site multisite preferences.
- Administrator maintenance tools for idempotent event-capability repair and bounded UTC date-index rebuilding.
- UTC-only repair that validates canonical local values, respects captured timezones and publication requirements, preserves copied-date review state, skips invalid events and reports write failures without event titles.
- WordPress smoke coverage for maintenance visibility, action-specific nonces, forged-nonce rejection, protected redirects and bounded progress feedback.
- Bounded native archive settings for the permalink base, events per page and upcoming/all default view.
- Administrator diagnosis for a WordPress page occupying the event archive path, with explicit event-URL impact guidance.
- Change-driven one-shot rewrite regeneration and deactivation cleanup that removes the post type before flushing stale routes.
- Unit and WordPress smoke coverage for invalid archive settings, page conflicts, custom routes, page size, period overrides and rewrite lifecycle behaviour.
- Deterministic production-allowlist release archives with a minimal class-authoritative Composer autoloader and SHA-256 checksum.
- Release-contract tests for version consistency, archive roots, traversal, hidden/development files, file types and required runtime assets.
- Archive verification for checksum filename binding, symbolic links, shipped PHP syntax and production autoloading, plus byte-for-byte reproducibility proof.
- A deterministic WordPress translation template and CI freshness check using WP-CLI 2.12.0.
- Packaged WordPress Playground smoke coverage on WordPress 6.9 and 7.0.1 with PHP 8.3.
- Strict official WordPress Plugin Check and release-artifact upload jobs against the exact staging package.
- Pinned Playwright browser regression coverage against a disposable WordPress Playground site, including reusable calendar boundary fixtures and CI execution.
- An optional, revisioned external event link label with a translated fallback, bounded plain-text validation and atomic native/Gutenberg persistence.
- Administrator visibility of WordPress' authoritative event timezone plus an optional, disabled-by-default timed-event label with event-date and DST-transition offsets.
- An access-aware, request-local event presentation layer with named renderers for every atomic event field and a backward-compatible composite details renderer.
- Twelve dedicated Elementor widgets for title, featured image, date/time, status, venue, address, location link, content, excerpt, external action, categories and tags, usable with an explicit public event on Elementor Free pages or current context in templates.
- Field-specific Elementor controls for labels, heading level, links, image size, decorative alt behaviour, typography, color and spacing, with editor-only empty-field guidance and request-shared presentation reuse.
- A real-host Elementor compatibility inspector covering the complete widget palette, control contracts, optimized DOM and strict public source rendering on the supported 3.x and tested 4.x versions.

### Fixed

- Save Event details atomically with Gutenberg publication instead of racing the legacy metabox request, and surface the first actionable validation message in the editor.
- Preserve WordPress' complete password form while continuing to hide protected event metadata.
- Scope visible single-event ordering assertions to the article so matching JSON-LD values in the document head do not create false failures.
- Render the calendar against a measurable canvas on first load and recalculate its geometry when an initially hidden integration container becomes visible.
- Keep calendar toolbar labels readable across normal, hover, keyboard-focus, pressed, selected and disabled states, including custom Elementor accent colors.
- Give the two Event Calendar typography controls distinct translated labels without changing their saved Elementor identifiers.
