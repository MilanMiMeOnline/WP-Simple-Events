# Changelog

All notable changes to MiMe Simple Events and Calendar are documented here.

## [0.4.0] - 2026-08-25

### Added

- Add bounded daily, selected-weekday weekly, monthly, yearly and specific-date recurrence with captured timezone and deterministic DST behaviour.
- Add explicit **Complete series**, **Only this occurrence** and **This and following** editor scopes with server-owned impact previews and signed review-before-apply confirmations.
- Add manual occurrences, reversible skips and cancellations, future schedule segments and sparse occurrence overrides for date, status, title, note, image, venue, address, location and external actions.
- Add a rebuildable generation-isolated occurrence index, bounded migration, health reporting, administrator repair, inactive-generation cleanup and scheduled projection renewal.
- Add stable occurrence routes, exact read-only occurrence REST resources and bounded WordPress Core sitemap discovery.

### Changed

- Make event lists, calendars, the calendar feed, native event and taxonomy archives, Gutenberg blocks and Elementor widgets occurrence-aware while preserving one-off output and established component identifiers.
- Make native, block-theme and Elementor Theme Builder single-event presentation resolve the same effective occurrence context, including core and third-party canonicals and Event JSON-LD.
- Replace stale ordinary date/time controls on recurring events with an explicit schedule-ownership notice while keeping inherited series status and shared event content editable.
- Use fail-closed projection readiness: incomplete, stale, corrupt, unpublished or protected recurrence state never falls back to a misleading series date.

### Security and privacy

- Keep recurrence aggregates protected and absent from public core REST, while the disposable occurrence index stores no event body, password, taxonomy copy, visitor data or remote identifier.
- Keep every public occurrence lookup exact, bounded and parent-eligibility checked; malformed, ambiguous and inaccessible identities return the same non-cacheable not-found response.
- Prevent stale virtual leaves through WordPress no-store headers, the de facto `DONOTCACHEPAGE` boundary and LiteSpeed Cache's documented no-cache action without weakening ordinary page caching.

### Fixed

- Schedule one late soft rewrite flush after a successful schema upgrade so existing installations recognize occurrence URLs with both default and custom event archive slugs without resaving Permalinks.
- Preserve nullable canonical values from Yoast SEO, Rank Math and AIOSEO so Elementor Theme Builder previews cannot fail before an occurrence context exists.
- Repair a missing, dirty or insufficient occurrence projection synchronously when an event first becomes public, including publication through Classic Editor and WP-Cron.
- Describe recurrence-owned schedules accurately in the native metabox even while the protected comparison aggregate still represents a one-off event.

### Testing

- Add deterministic unit coverage for recurrence rules, calendar boundaries, DST, immutable identity, aggregate validation, editor concurrency, scope reconciliation, projection lifecycle, occurrence queries, presentation, REST, SEO, caching and upgrade behaviour.
- Add real WordPress REST, smoke and Gutenberg browser journeys for recurrence enablement, editing scopes, disablement, repair, renewal, archives, collections, exact leaves, schema, canonicals and protected-content denial.
- Add a production Elementor Pro qualification journey for exact recurring leaves, Theme Builder output, occurrence-specific canonicals and schema, plus regressions for nullable SEO canonicals and publication-time projection repair.
- Qualify the corrected package on Taranartos with Elementor 4.2.3 and Elementor Pro 4.2.2: Theme Builder preview loads without a canonical-filter failure, Classic Editor publication immediately creates a healthy three-occurrence projection, all exact leaves render, and permanent cleanup removes every temporary public route.
- Qualify the complete smoke journey on WordPress 6.9 and 7.0.1 with PHP 8.2.

## [0.3.0] - 2026-08-19

### Added

- Add native dynamic Gutenberg blocks for Event List / Grid, Event Calendar and complete Event Details, with bounded Inspector controls and server-rendered previews.
- Give Gutenberg collection blocks initial category and tag constraints while preserving the existing visitor-filter and no-JavaScript contracts.
- Add cross-host card controls for title/date visibility, excerpt length and semantic title headings.
- Add a validated calendar initial date, optional navigation, Today and view-switcher groups, plus a semantic fallback heading.
- Let complete Event Details hide established field groups and customize bounded date, venue, location, action, category and tag labels.

### Fixed

- Initialize Event Calendar widgets when Elementor dynamically renders or rerenders their editor preview, while preventing duplicate calendar instances and listeners.
- Present event category and tag archives as bounded event collections ordered by event start date, with the active classic, hybrid or block-theme shell intact.
- Exclude draft, private and password-protected events from event taxonomy archives and enqueue their shared component styling.
- Preserve an intentionally empty filter selection across multiple list and calendar instances, and reset only the active component to its configured initial constraints.
- Open event-card location destinations in an isolated new tab and omit empty complete-details wrappers when every field is hidden.

### Changed

- Keep event taxonomy archives complete across past, active and future events, while preserving their fixed term scope and omitting cross-archive filters.
- Leave blog, product and mixed WordPress search ordering and publication-date presentation unchanged.
- Make event-card columns respond to their available component width, with the established viewport behaviour retained as a fallback for older browsers.
- Add opt-in Elementor presentation controls for card content and gaps, filter panels and controls, pagination containers, calendar states, composite details, featured images and external actions without changing existing saved defaults.
- Condition irrelevant Elementor and Gutenberg controls, keep internal event links same-tab and preserve accessible component names when visible titles are disabled.

### Testing

- Requalify all fifteen widgets and Event editing against Elementor 3.35.9 and 4.2.2 on WordPress 7.0.1 with PHP 8.2.
- Add an isolated browser regression for delayed Elementor initialization, repeated element-ready hooks and widget-root replacement.
- Add unit and packaged WordPress smoke regressions for taxonomy chronology, term titles, theme ownership, protected-content exclusion and unrelated-query isolation.
- Add control-contract and stylesheet regressions for the expanded Elementor presentation system, scoped variables and container-width fallback.
- Add unit regressions for strict composite-block attribute mapping, native renderer delegation and current versus explicit event access.
- Add browser coverage for Gutenberg registration, attribute-only serialization, server previews, anonymous output and a useful no-JavaScript fallback.
- Add regressions for bounded presentation options, heading semantics, isolated external links, filter apply/reset state and multiple-instance preservation.
- Add browser coverage for calendar initial-date/toolbar configuration and the expanded Gutenberg Inspector controls.

## [0.2.5] - 2026-08-12

### Changed

- Lower the supported PHP floor from 8.3 to 8.2 while continuing to recommend a newer maintained PHP release when hosting permits it.
- Resolve Composer development tooling against PHP 8.2 so the compatibility promise is enforced by the dependency graph.

### Security

- Refresh vulnerable Composer and npm development dependencies to patched releases; development tools remain excluded from the production plugin archive.

### Testing

- Run PHP quality gates on PHP 8.2, 8.3, 8.4 and 8.5.
- Run the packaged WordPress 6.9 and 7.0.1 smoke matrix on PHP 8.2 and allow local smoke runs to select their Playground PHP runtime explicitly.
- Requalify Elementor 3.35.9 and 4.1.5 on WordPress 7.0.1 with PHP 8.2.
- Freeze the browser harness clock so calendar regressions remain deterministic across real calendar months.

## [0.2.4] - 2026-07-28

### Fixed

- Preserve the active theme and Elementor header/footer shell on event archives and individual events when a classic or hybrid classic theme contains `theme.json`.
- Let explicit PHP theme overrides win before block-template discovery and pass the correct WordPress filename hierarchy to full block themes.

### Testing

- Add isolated regressions for classic, hybrid, full block and theme-override template selection.
- Extend the packaged WordPress smoke journey with five fixture themes and exact header, content and footer assertions on both event routes.

## [0.2.3] - 2026-07-26

### Changed

- Adopt **MiMe Simple Events and Calendar** and `mime-simple-events-calendar` as the pre-approval public identity requested after WordPress.org prereview.
- Keep all `wpse_` storage, content, REST, shortcode, block and Elementor identifiers stable so existing private-test data remains compatible.
- Replace the WordPress.org banners with the owner-approved MiMe-first artwork and synchronize public documentation, translations and release tooling.

### Tooling

- Refresh vulnerable transitive development dependencies while keeping the production plugin dependency-free from Node.js packages.

## [0.2.2] - 2026-07-22

### Security

- Remove registered event metadata from anonymous core REST responses while a password-protected event remains locked, without blocking authorized editor access.
- Pin every third-party GitHub Action to an immutable reviewed commit and enforce that policy with a tooling regression test.

### Documentation

- Replace the public readmes with user-focused installation, feature, privacy, support and builder guidance for WordPress.org preparation.
- Document the final security/privacy audit and WordPress.org visual-asset plan, and publish the direct GitHub Private Vulnerability Reporting route.
- Add a step-by-step WordPress.org submission and SVN handoff checklist.
- Replace the original Dutch analysis document with a concise English product specification and documentation index.
- Add reviewed WordPress.org icons, banners and seven product screenshots from fictional demo content.

### Tooling

- Verify the exact WordPress.org visual filenames, PNG dimensions and screenshot-caption count in automated quality checks.
- Make the disposable WordPress smoke environment resilient to bounded startup and nonce-readiness delays.
- Update vulnerable transitive development dependencies to patched versions.

## [0.2.1] - 2026-07-20

### Changed

- Establish the initial pre-approval distribution identity while preserving all event storage and content identifiers.
- Open the public location and external event actions in isolated new browser tabs.
- Include the complete GPL-2.0-or-later licence in source and production archives.
- Ship the Composer production manifest and plain-text third-party notices with the optimized release autoloader.

### Fixed

- Declare native Elementor support for Events so compatible installations expose **Edit with Elementor** without mutating Elementor settings.
- Keep strict official Plugin Check compatible with the required-date publication invariant, and stabilize clean-CI PHPStan and Gutenberg browser execution.
- Sanitize editor, maintenance and duplication request values directly at their input boundaries.
- Keep destructive cleanup fail-safe when third-party query filters hide event posts.
- Document the bounded WordPress metadata queries required by event ordering, periods and taxonomy filters.

## [0.2.0] - 2026-07-20

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
- Twelve metadata-registered dynamic Gutenberg blocks matching the atomic Elementor field palette, with current template/query context and strict explicit public-event selection.
- Field-specific Gutenberg Inspector controls, server previews, editor-only empty states and native typography, color, spacing and alignment supports.
- An opt-in single-event block pattern, bounded editor event choices and browser coverage for block registration, serialization, server rendering, recursion safety and frontend asset isolation.

### Fixed

- Save Event details atomically with Gutenberg publication instead of racing the legacy metabox request, and surface the first actionable validation message in the editor.
- Preserve WordPress' complete password form while continuing to hide protected event metadata.
- Scope visible single-event ordering assertions to the article so matching JSON-LD values in the document head do not create false failures.
- Render the calendar against a measurable canvas on first load and recalculate its geometry when an initially hidden integration container becomes visible.
- Keep calendar toolbar labels readable across normal, hover, keyboard-focus, pressed, selected and disabled states, including custom Elementor accent colors.
- Give the two Event Calendar typography controls distinct translated labels without changing their saved Elementor identifiers.
