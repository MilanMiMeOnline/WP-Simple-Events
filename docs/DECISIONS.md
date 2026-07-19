# Architecture decision log

## ADR-001: Native WordPress event model

**Status:** Accepted

Events use the `wpse_event` custom post type, registered metadata and native category/tag taxonomies. Version 1 does not use a custom event/occurrence table because recurrence is out of scope and native queries are sufficient for the intended scale.

## ADR-002: Optional integrations

**Status:** Accepted

The core has no runtime dependency on Elementor or WooCommerce. Elementor integration is a later phase and consumes stable core query/rendering services rather than owning event logic.

## ADR-003: Theme-inheriting presentation

**Status:** Accepted

Native templates are required as a reliable baseline. Front-end styles stay component-scoped and inherit the active theme. Elementor Theme Builder support may override presentation later without changing event data.

## ADR-004: Supported platform floor

**Status:** Accepted

The first release requires WordPress 6.9 and PHP 8.3. This matches the selected ecosystem baseline and permits strict modern PHP while retaining current security support.

## ADR-005: Excluded complexity in version 1

**Status:** Accepted

Recurrence, interactive maps, geocoding and ticketing are explicit non-goals. Adding recurrence later would require a separate occurrence model and migration design; it must not be approximated with ad-hoc duplicated posts.

## ADR-006: Canonical local values with derived UTC indexes

**Status:** Accepted

Timed values use local ISO `Y-m-d\TH:i:s`; all-day values use inclusive `Y-m-d` dates. A stored timezone makes the intended wall time stable while derived UTC timestamps support chronological sorting, active/past queries and machine-instant output. Calendar wall-time overlap is refined in ADR-024. Invalid, nonexistent and ambiguous local times are rejected rather than silently normalized.

## ADR-007: WordPress fixed-offset timezone compatibility

**Status:** Accepted

IANA identifiers remain preferred, but fixed offsets from `-14:00` to `+14:00` are accepted because WordPress permits sites to use a numeric UTC offset instead of a named timezone. Fixed offsets intentionally have no daylight-saving behaviour.

## ADR-008: Internal UTC metadata is not exposed by core REST

**Status:** Accepted

Local editor fields, status and location fields are registered in core REST. Derived `_wpse_start_utc` and `_wpse_end_utc` indexes are registered but hidden from core REST so a client cannot create inconsistent local and UTC values. Custom read-only representations can be added through the later event-feed controller.

## ADR-009: Explicit single-site activation

**Status:** Accepted

This version blocks network-wide multisite activation and supports per-site activation. That is safer than reporting success while granting roles and flushing rewrites on only one site. Full network lifecycle handling requires a dedicated, bounded migration design.

## ADR-010: One validator and persistence gateway for every write interface

**Status:** Accepted

The native editor and core REST API adapt their input into one event input model and use the same validation service. Only validated data reaches the persistence gateway, which owns canonical and derived metadata together. Native invalid publication is downgraded before insertion; REST rejects it before insertion. This avoids divergent rules and partially updated event records as later import or Elementor interfaces are added.

## ADR-011: One bounded repository contract for every public event collection

**Status:** Accepted

Standalone shortcodes query through `EventRepository`; the native main archive reuses the same `EventQueryArguments` builder. The repository always fixes post type, public status, password visibility, ordering, period boundary and query bounds. Shortcodes, later templates, REST feeds and Elementor adapters may choose only typed criteria instead of passing arbitrary `WP_Query` arguments.

## ADR-012: Native fallbacks participate in both WordPress template systems

**Status:** Accepted

Classic themes receive thin plugin PHP templates with fixed child-theme override paths. Block themes receive plugin-registered block templates that can be overridden in the theme or Site Editor. Both delegate to the same native renderers, and the archive block consumes the existing bounded main query. The fallback candidate is installed early and checks Elementor's public theme locations at render time, preserving Theme Builder precedence without coupling core event logic to Elementor classes.

## ADR-013: Locally bundle only the required FullCalendar 6 modules

**Status:** Accepted

The calendar uses exact version 6.1.21 of the MIT-licensed `@fullcalendar/core`, `@fullcalendar/daygrid` and `@fullcalendar/list` packages. They are build-time dependencies and are bundled into a local production asset; the visitor never contacts a CDN. Interaction, drag-and-drop, time-grid, multi-month, resource and premium packages are excluded.

FullCalendar 7.0.0 was released immediately before this implementation and its standard browser bundle includes functionality outside this plugin's scope. The maintained modular 6.1 package line therefore has the smaller and lower-risk integration surface for version 1. This choice must be reassessed before the first stable release and during normal dependency reviews. Removal would require replacing the calendar adapter and generated bundle, but does not affect stored events, queries, REST response shapes or the no-JavaScript event-list fallback.

## ADR-014: Elementor remains a thin, version-gated adapter

**Status:** Accepted

The optional integration requires Elementor 3.35 or newer and is tested initially against Elementor 3.35.9 and 4.1.5. It registers three classic `Widget_Base` widgets through the current `elementor/widgets/register` hook only after `elementor/loaded` has fired. Sites without Elementor, or with an older version, keep the complete native plugin without loading any Elementor class.

Widgets translate allowlisted controls into the existing shortcode render contracts. They do not query posts, read event metadata or reproduce event markup. Because Elementor reconstructs each placed widget as a separate PHP object, all native renderers use one request-wide, component-specific ID sequence; shortcode and widget instances therefore cannot emit duplicate DOM IDs. Widget assets use `get_style_depends()` and `get_script_depends()`, and style selectors target WP Simple Events markup through Elementor's `{{WRAPPER}}` token instead of relying on Elementor's removable inner wrapper.

Elementor Pro dynamic tags remain an optional, separate increment. Deferring them does not reduce the three required Free widgets and avoids making Elementor Pro a dependency of the initial integration.

## ADR-015: Structured data is derived at render time from public event data

**Status:** Accepted

JSON-LD is emitted only for a published, password-free individual event and is built from the same validated UTC boundaries, captured timezone and public metadata used by native rendering. All-day boundaries remain ISO dates; timed boundaries include their local UTC offset. Empty optional values are omitted, event statuses map to Schema.org URLs, and the plugin does not invent offers, pricing or venue details.

The JSON document is encoded with all HTML-significant characters escaped so stored content cannot terminate the script element. Output can be disabled globally and filtered per event to avoid duplication with an SEO plugin. A user-facing toggle belongs on the minimal settings page in this hardening phase; the filter remains the stable programmatic override.

No structured-data cache is introduced. A single event schema is small and generated only on its canonical singular request, while request-time derivation avoids stale SEO data and a second invalidation path.

## ADR-016: Admin event discovery and duplication use explicit WordPress boundaries

**Status:** Accepted

The Events list table uses post-type-specific column hooks, one allowlisted view filter, the native event-category query variable and a main-admin-query adapter. Upcoming and past filters use the existing derived UTC metadata; cancelled and postponed remain event-status filters rather than WordPress publication statuses. Start and end are sortable only through allowlisted numeric metadata keys. The adapter never alters secondary, front-end or non-event queries.

Duplication is a deliberate replacement workflow for recurrence, not a generic post-meta clone. A nonce-protected `admin_action` requires permission to edit the source, create events and assign event terms. It creates a new draft, copies title/content/excerpt, featured image, event categories/tags and an explicit event-data allowlist. It does not copy the external event/registration URL or its label, passwords, revisions or arbitrary third-party metadata.

Copied date fields receive an internal review flag and a prominent editor message. The flag is removed only after the shared validator and persistence gateway accept an editor save. This keeps copied dates usable while making the required human review explicit.

## ADR-017: Uninstall preserves data unless each site explicitly opts in

**Status:** Accepted

Deactivation never removes persistent data. Deleting the plugin also preserves event posts, terms, options and capabilities by default. The administrator-only Settings API exposes one explicit destructive opt-in with an irreversible-action warning; unchecked, missing and malformed values all resolve to retention.

When enabled, cleanup uses only WordPress APIs and explicit plugin-owned allowlists. Event posts and event taxonomy terms are permanently removed in batches, plugin-granted capabilities are revoked, and options are removed last. Featured media is retained because attachments may be shared with pages, products or ordinary posts. A failed content or term deletion leaves the options in place instead of falsely representing complete cleanup.

Network activation remains unsupported, but individually activated multisite sites may carry different retention choices. Uninstall therefore enumerates sites in bounded batches, switches context safely and evaluates the opt-in separately for every site.

## ADR-018: Maintenance repairs derived state without rewriting canonical events

**Status:** Accepted

Capability repair is an explicit administrator action that reruns the existing idempotent role grant. UTC reindexing is a separate administrator action and must never use the full event persistence gateway: doing so could normalize unrelated fields or clear copied-date review guidance.

The UTC repairer reads stored canonical local dates, the captured timezone, all-day state and WordPress publication status as untrusted input. It reuses the central date validator and publication policy, then writes only `_wpse_start_utc` and `_wpse_end_utc`. Valid incomplete drafts may lose stale indexes; invalid or incomplete public records remain unchanged for manual review. The copy-review flag, canonical date strings, location and event status are outside the mutation boundary.

Catalogue work is split into stable ID-ordered pages of 50. The browser performs one authenticated, nonce-protected `admin-post` request per batch and receives privacy-safe aggregate counts. A visible Continue action replaces automatic redirect chains, avoiding an unbounded request or browser redirect loop. Concurrent catalogue edits can shift offset pages, but the operation is idempotent and safe to rerun; no invalid record is silently rewritten.

## ADR-019: Archive configuration is bounded and rewrite regeneration is change-driven

**Status:** Accepted

The native archive exposes only three administrator settings: one sanitized root path segment, 1 through 50 events per page, and an `upcoming` or `all` default period. The visitor's allowlisted period filter still wins for that request. Shortcodes, calendar feeds and Elementor widgets retain their own explicit bounded contracts and do not inherit archive presentation defaults.

The archive slug is also the single-event permalink base. Changing it therefore changes both archive and individual event URLs; version 1 does not invent redirects or keep an unbounded slug history. An administrator warning names this impact. A non-trashed WordPress page at the same root slug triggers a persistent warning on Events admin screens, offering the explicit choice to keep the native archive at that address or change its base.

Saving an equivalent normalized slug performs no rewrite work. A real successful option add/update stores the target slug as one-shot internal state. A late `init` callback validates that state against the current registered post type, performs one soft rewrite flush and removes the marker. Deactivation unregisters the event post type and both event taxonomies before its soft flush so stale plugin routes are not regenerated. Other plugins can create arbitrary rewrite collisions that cannot be diagnosed generically; WordPress page conflicts are the version 1 detection boundary required by the product specification.

## ADR-020: Releases are built from an explicit production allowlist

**Status:** Accepted

The installable plugin is never an archive of the working tree. A release builder copies an explicit set of runtime files into `.release/wp-simple-events`, generates a class-authoritative Composer autoloader without development dependencies or network access, normalizes file permissions and timestamps, and creates `dist/wp-simple-events-{version}.zip` plus a SHA-256 file. The plugin header, runtime constant, WordPress stable tag and npm package version must match before the build starts.

The archive contract rejects wrong roots, traversal, hidden files, development paths, unexpected file types, symlinks and missing runtime files. Verification reopens the archive, validates the complete checksum record, lints every shipped PHP file and loads the main plugin class through the shipped autoloader. Two consecutive builds must be byte-for-byte identical. Adding a production dependency or a new shipped file type therefore requires an intentional contract and test change rather than silently expanding the package.

The generated translation template is a required runtime artifact under `/languages`. WP-CLI 2.12.0 generates it with a blank creation timestamp, and CI regenerates and compares it byte-for-byte. WordPress Plugin Check runs against the staging directory, not the development repository, so its result describes the package users receive.

## ADR-021: Gutenberg owns one atomic event save request

**Status:** Accepted

The Event details interface remains a native metabox so it also works in the classic editor and does not introduce a custom React editor dependency. Gutenberg, however, saves the post through REST while legacy metaboxes use a separate request whose ordering is not atomic. Every editable Event detail is therefore mirrored into the block editor's registered, typed post-meta state through WordPress' `core/editor` data store. Gutenberg then submits the post status and event record together; the shared REST validator remains the authoritative security and correctness boundary.

The mirror preserves registered metadata owned by other plugins and never exposes or writes the internal UTC indexes. Classic-editor POST handling retains its existing nonce, capability and validation path. If the Gutenberg data store is unavailable, the editor script degrades to the classic time-field behaviour without attempting a separate custom request.

## ADR-022: Browser regressions use pinned Playwright against WordPress Playground

**Status:** Accepted

Layout and interaction failures cannot be proved by PHP stubs or HTTP-only smoke tests. The development toolchain therefore pins `@playwright/test` 1.61.1 under its Apache-2.0 licence and installs only its Chromium browser for the initial critical calendar journeys. Tests run against an isolated WordPress Playground site with bounded fixtures and assert component semantics, visibility, interaction and stable geometry rather than full-theme pixel snapshots.

Playwright remains development-only, loads no visitor asset and is excluded from production releases with all other `node_modules`. CI installs the matching browser explicitly. Removal requires replacing its browser-level calendar, responsive and accessibility evidence; HTTP smoke and manual screenshots alone are not equivalent coverage.

## ADR-023: Calendar constraints are independent from visitor-control visibility

**Status:** Accepted

The calendar's `category` and `tag` values are initial query constraints, while `filters` controls whether visitors receive selectors that can alter those values. The existing enabled default and stable shortcode/Elementor identifiers remain unchanged for backward compatibility. Elementor labels and descriptions must make this distinction explicit.

Visitor controls list only non-empty public event terms and the entire filter form is omitted when neither taxonomy offers a usable choice. This avoids a submit action with no possible effect. Server-rendered GET forms remain the no-JavaScript baseline, use instance-specific request names and preserve only allowlisted state belonging to other calendar instances.

Initial constraints are also embedded as bounded sanitized arrays in the calendar's JavaScript configuration. The feed uses them whenever the matching visitor selector is absent, including calendars with filters disabled and calendars whose only usable selector belongs to the other taxonomy. Hiding presentation controls therefore cannot silently broaden the public event query.

## ADR-024: Calendars preserve captured event wall time

**Status:** Accepted

Public calendars place every event on its canonical saved local date and clock time. They do not convert an event to the visitor browser's timezone. This matches the native event details, keeps mixed captured timezones meaningful and prevents a browser offset from moving a same-day event across midnight. A future visitor-local-time mode would require a separate explicit product contract.

The FullCalendar-facing `start` and `end` values are therefore floating local ISO values. Timed feed records also retain the captured timezone and offset-bearing machine instants as explicit presentation metadata; all-day records remain date-only with an exclusive end. Storage and structured-data machine values are unchanged.

Calendar requests are day-aligned wall-time windows with an explicit client offset for unambiguous transport. Their bounded overlap query uses canonical `_wpse_start_local` and `_wpse_end_local` values rather than one browser-relative UTC window. This keeps events at the supported `-14:00` and `+14:00` extremes in the correct visible month, preserves truthful WordPress pagination and avoids an unbounded post-filtering pass. UTC indexes remain authoritative for chronological lists, active/past classification and machine-instant output.

## ADR-025: Calendar time notation inherits WordPress

**Status:** Accepted

Public event details, cards and calendars inherit the site's WordPress `time_format`. WP Simple Events does not introduce a duplicate global setting in this package. A future atomic date/time component may offer an explicit presentation-only override, but inheritance remains the default.

Server-rendered output continues through localized `wp_date()`. A bounded adapter maps only the relevant unescaped PHP tokens (`H`, `G`, `h`, `g`, `i`, `a` and `A`) to FullCalendar options. Explicit `h23` and `h12` hour cycles prevent the visitor locale from silently changing WordPress' 12/24-hour choice; uppercase meridiems remain browser-localized rather than being hard-coded in English. Invalid formats fall back to zero-padded `H:i` presentation.

This is presentation only. Canonical local values, derived UTC indexes, captured timezones, feed boundaries and structured-data machine values do not change. Native HTML time controls may look platform-specific, but their submitted value remains canonical 24-hour input; the editor explains that distinction.

## ADR-026: The external event action has one optional plain-text label

**Status:** Accepted

The existing external event URL keeps one optional, revisioned label rather than becoming a repeatable resource-link model. The label is at most 120 characters, accepts scalar input only, is sanitized as plain text at the shared write boundary and is escaped when rendered. Existing events and whitespace-only labels retain the translated `More event information` fallback.

The label may be saved before its URL so an editor does not lose prepared text, but it never renders without a valid external event URL. Event duplication omits both the URL and its label because the destination may be registration- or event-specific. Uninstall needs no separate metadata deletion path because WordPress removes post metadata with the event; UTC-index maintenance deliberately leaves both fields untouched.
