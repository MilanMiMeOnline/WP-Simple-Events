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

The optional integration requires Elementor 3.35 or newer and is tested initially against Elementor 3.35.9 and 4.1.5. It registers classic `Widget_Base` widgets through the current `elementor/widgets/register` hook only after `elementor/loaded` has fired. Sites without Elementor, or with an older version, keep the complete native plugin without loading any Elementor class.

The original list, calendar and composite details widgets translate allowlisted controls into the existing shortcode render contracts. The atomic field widgets added after the shared WP4 presentation contract resolve either one explicitly selected published, password-free event or the current event context and then request one named field from `EventFieldRenderer`. No Elementor widget queries posts directly, reads raw event metadata or reproduces event markup. One request-scoped service set is shared across atomic widget objects so repeated fields for the same event reuse the normalized presentation snapshot.

An explicit event selection is the real source on an ordinary Elementor Free page and a safe preview/source override in a template. With no selection, the widget consumes the current queried event. The field semantics and saved control identifiers do not change between those contexts. Missing or inaccessible fields emit only an editor placeholder; public rendering emits no plugin wrapper. Elementor Pro Theme Builder remains host-owned and optional rather than a plugin dependency.

Because Elementor reconstructs each placed widget as a separate PHP object, all native renderers use one request-wide, component-specific ID sequence; shortcode and widget instances therefore cannot emit duplicate DOM IDs. Widget assets use `get_style_depends()` and `get_script_depends()`, and style selectors target MiMe Simple Events and Calendar markup through Elementor's `{{WRAPPER}}` token instead of relying on Elementor's removable inner wrapper.

Elementor Pro dynamic tags remain an optional, separate increment. Field widgets work without dynamic tags in both Elementor Free static layouts and host-provided dynamic templates, so deferring dynamic tags does not reduce the supported component palette or make Elementor Pro a dependency.

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

The installable plugin is never an archive of the working tree. A release builder copies an explicit set of runtime files into `.release/mime-simple-events-calendar`, generates a class-authoritative Composer autoloader without development dependencies or network access, normalizes file permissions and timestamps, and creates `dist/mime-simple-events-calendar-{version}.zip` plus a SHA-256 file. The plugin header, runtime constant, WordPress stable tag and npm package version must match before the build starts.

The archive contract rejects wrong roots, traversal, hidden files, development paths, unexpected file types, symlinks and missing runtime files. Verification reopens the archive, validates the complete checksum record, lints every shipped PHP file and loads the main plugin class through the shipped autoloader. The production package retains `composer.json` beside the generated Composer autoloader and ships third-party licence notices as a WordPress.org-compatible text file; the development lockfile and dependencies remain excluded. Two consecutive builds must be byte-for-byte identical. Adding a production dependency or a new shipped file type therefore requires an intentional contract and test change rather than silently expanding the package.

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

Public event details, cards and calendars inherit the site's WordPress `time_format`. MiMe Simple Events and Calendar does not introduce a duplicate global setting in this package. A future atomic date/time component may offer an explicit presentation-only override, but inheritance remains the default.

Server-rendered output continues through localized `wp_date()`. A bounded adapter maps only the relevant unescaped PHP tokens (`H`, `G`, `h`, `g`, `i`, `a` and `A`) to FullCalendar options. Explicit `h23` and `h12` hour cycles prevent the visitor locale from silently changing WordPress' 12/24-hour choice; uppercase meridiems remain browser-localized rather than being hard-coded in English. Invalid formats fall back to zero-padded `H:i` presentation.

This is presentation only. Canonical local values, derived UTC indexes, captured timezones, feed boundaries and structured-data machine values do not change. Native HTML time controls may look platform-specific, but their submitted value remains canonical 24-hour input; the editor explains that distinction.

## ADR-026: The external event action has one optional plain-text label

**Status:** Accepted

The existing external event URL keeps one optional, revisioned label rather than becoming a repeatable resource-link model. The label is at most 120 characters, accepts scalar input only, is sanitized as plain text at the shared write boundary and is escaped when rendered. Existing events and whitespace-only labels retain the translated `More event information` fallback.

The label may be saved before its URL so an editor does not lose prepared text, but it never renders without a valid external event URL. Event duplication omits both the URL and its label because the destination may be registration- or event-specific. Uninstall needs no separate metadata deletion path because WordPress removes post metadata with the event; UTC-index maintenance deliberately leaves both fields untouched.

## ADR-027: Public timezone context is global, optional and presentation-only

**Status:** Accepted

WordPress remains the sole authority for the site timezone. Event Settings reports that value and links authorized administrators to WordPress General Settings; it does not introduce a competing timezone selector. New events capture the current site zone, while existing events retain the zone saved with them. Fixed numeric offsets remain valid but are explicitly described as having no daylight-saving behaviour.

One strictly validated global option controls visible timezone context and is disabled by default for backward compatibility. When enabled, timed native details and the shared composite Elementor details widget show an IANA identifier with the offset at the event boundary. A range crossing an offset transition shows both boundary offsets; fixed-offset zones use a concise `UTC±HH:MM` label. All-day events omit timezone context. Long identifiers may wrap within the component instead of overflowing it.

The option changes presentation only. Canonical dates, derived UTC indexes, cards, calendar placement and feeds, REST values and structured-data machine values remain unchanged. Per-event timezone editing and atomic component-level overrides are deferred product choices for the shared presentation work rather than hidden extensions of this setting.

## ADR-028: Atomic hosts share one named, access-aware presentation layer

**Status:** Accepted

Current template context and explicit event selection have different authorization contracts. The shared resolver permits published events and authorized non-public previews in current context, while explicit selection accepts only published, password-free events and never falls back to another post. Positive and negative presentation snapshots are reused only within one resolver/request; cross-request caching is deliberately absent.

Stored metadata and taxonomy objects remain inside the presentation factory. The host-facing renderer exposes named title, image, date/time/timezone, status, venue, address, location action, content, excerpt, external action, category and tag methods rather than arbitrary metadata access. Stored values are normalized again as untrusted input and escaped at the final HTML context. Empty or corrupt optional values produce no wrapper.

Atomic protected fields return nothing, while the established composite details output returns WordPress' complete password form. Request-wide recursion guards cover both content fields and complete details across separate renderer instances. The existing composite renderer is rebuilt from the named fragments without changing its grouping classes, field order or public access behaviour. Future Elementor and Gutenberg adapters must share these services; host-specific copies of field logic are not accepted.

## ADR-029: Gutenberg atomic fields are metadata-registered dynamic blocks

**Status:** Accepted

The Gutenberg palette consists of twelve dedicated public blocks matching the frozen WP4 field set. Every block is registered from its own `block.json`, but all callbacks delegate to one allowlisted block adapter and the shared event-context and field-rendering services. Blocks never read arbitrary metadata, accept a metadata key or duplicate field markup. Their saved comment contains attributes only; public HTML is generated on the server so event edits, access decisions and WordPress formatting remain current without block migrations.

An empty `eventId` consumes `postId` and `postType` block context and may fall back only to an event queried object when context is unavailable. A non-empty identifier is an actual static-page source, not a preview-only hint: it must resolve to a published, password-free event and never falls back. Draft/private template previews remain available only through authorized current-event context. Missing, protected and corrupt values return no public wrapper; the editor's shared ServerSideRender adapter owns the explanatory empty-state placeholder.

One editor-only script registers the twelve client block interfaces and receives at most fifty published, password-free event choices from the existing bounded repository. It is registered with WordPress dependencies during block registration but the choices are queried and localized only on block-editor screens. Field-specific Inspector controls mirror the Elementor allowlists; typography, color, link color, margin and alignment use native block supports. A thin host wrapper carries those supports only when the named field produces output.

The plugin also registers one opt-in single-event block pattern composed from the atomic blocks. Existing `wpse/native-single` and `wpse/native-archive` fallback bridges and customized templates are not replaced. The Event Content block remains protected by the request-wide shared recursion guard, including when it appears inside the event content it renders.

## ADR-030: Official Plugin Check receives a command-scoped disposable fixture exception

**Status:** Accepted

Official Plugin Check 2.0 runtime performance checks publish one generic fixture for every viewable post type and provide no extension point for required custom metadata. MiMe Simple Events and Calendar normally and intentionally downgrades such an incomplete event to draft, which prevents Plugin Check from retrieving the temporary URL and aborts the checker before it can report on the package.

The native publication guard therefore yields only while the Plugin Check plugin is loaded inside the exact contiguous WP-CLI `plugin check` command. The exception does not apply to the WordPress editor, REST, cron, frontend requests or any other WP-CLI command. Plugin Check deletes its fixture in the same preparation lifecycle, and the exception changes no stored user event or public product behaviour. CI continues to run every stable Plugin Check category in strict mode; no check, warning or error is excluded.

## ADR-031: Event discovery remains on bounded WordPress metadata queries

**Status:** Accepted

Version 1 deliberately stores events as a custom post type with registered metadata and taxonomies. Its public lists, calendar windows and Events admin views therefore require `meta_key`, `meta_query` and optional `tax_query` arguments to order and select events by date, status, category and tag. Replacing these queries with a custom table would violate the accepted version-1 product and migration contract.

Every public query is bounded and paginated, exposes published password-free events only, and uses allowlisted criteria. Admin queries run only inside WordPress' paginated Events list. Narrow inline Plugin Check acknowledgements document these intentional query keys; no database or performance check is disabled globally. The decision must be reassessed if production scale requirements demonstrate that WordPress metadata storage is no longer adequate.

## ADR-032: The first public identity is Simple Events by MiMe

**Status:** Superseded by ADR-034

Before the first public release, the product name becomes **Simple Events by MiMe** and the canonical WordPress.org slug and text domain become `simple-events-by-mime`. The production directory, main plugin filename, translation catalogue and release archive use that slug. This avoids reserving a first-release identity that fails WordPress.org trademark validation and is the final public identity chosen by the product owner.

The rename is presentation and distribution metadata only. The `MiMe\WPSimpleEvents` PHP namespace, `WPSE_` constants, `wpse_` global identifiers, event post type, taxonomies, metadata, shortcodes, REST namespace, block names and Elementor widget identifiers remain unchanged so tester data and composed content stay compatible. Because the previous slug was never publicly released, no automatic-update migration is provided. Test installations must deactivate and remove the old plugin directory before installing the renamed package; retained event data will be recognized by the unchanged storage identifiers.

## ADR-033: Password protection also covers registered event metadata in core REST

**Status:** Accepted

WordPress core protects post content in its REST response but still adds registered `show_in_rest` post metadata to a published password-protected event. Those fields can contain an address, venue, schedule and external URLs and therefore belong to the protected event-detail boundary.

The `rest_prepare_wpse_event` adapter removes the complete `meta` member while WordPress still requires the event password. An authorized user requesting edit context retains metadata access so Gutenberg and other authenticated editors continue to work. Password-free published events keep their existing public core REST contract, while the plugin's calendar feed and public collections continue to exclude password-protected events entirely.

This response-time protection complements rather than replaces the central metadata authorization callback: that callback controls writes, while this decision controls disclosure. A real WordPress regression test covers both anonymous denial and authorized edit-context access.

## ADR-034: The pre-approval identity is MiMe Simple Events and Calendar

**Status:** Accepted

WordPress.org prereview identified the submitted `Simple Events by MiMe` name and
`simple-events-by-mime` slug as insufficiently distinctive because the generic
product terms preceded the owner's identifier. Before directory approval, the
public product name therefore becomes **MiMe Simple Events and Calendar** and the
canonical slug and text domain become `mime-simple-events-calendar`. The
production directory, main plugin filename, translation catalogue and release
archive use that slug. This owner-selected name places the established MiMe
identity first and describes both event publishing and calendar presentation.

The migration is limited to public identity, internationalization and
distribution metadata. The `MiMe\WPSimpleEvents` PHP namespace, `WPSE_`
constants, `wpse_` global identifiers, event post type, taxonomies, metadata,
options, capabilities, shortcodes, REST namespace, block names and Elementor
widget identifiers remain stable. Existing events and composed content are
therefore recognized after installing the renamed package.

The earlier slug was never approved or distributed through WordPress.org, so
there is no public automatic-update channel to migrate. Private test sites must
deactivate and remove the earlier plugin directory before installing version
0.2.3 under the new slug; the default data-retention policy preserves their
events and settings. The WordPress.org review reply must explicitly request the
new `mime-simple-events-calendar` permalink before approval.

## ADR-035: Development tooling pins the patched brace-expansion implementation

**Status:** Superseded by ADR-038

The current WordPress environment and linting packages retain transitive
`minimatch`, `glob` and `rimraf` branches whose declared ranges resolve to
`brace-expansion` releases affected by GHSA-mh99-v99m-4gvg. Their upstream
dependency ranges do not yet select the only patched release, while a forced
audit fix proposes unrelated breaking package downgrades.

The root development manifest therefore overrides this one transitive package
to `brace-expansion` 5.0.8. That release supports the repository's Node 20 floor
and exposes both CommonJS and ESM entry points. The override is acceptable only
while the full lint, build, test, WordPress smoke and browser journeys pass; it
must be removed when the direct WordPress tooling resolves to patched ranges.
Node dependencies remain development-only and are excluded from the production
plugin archive.

## ADR-036: Full block templates require a full block theme

**Status:** Accepted

WordPress enables the `block-templates` theme feature for both full block themes
and classic themes that contain `theme.json`. Feature support alone therefore
does not prove that the active theme owns block `header` and `footer` template
parts. Selecting the plugin block template in a hybrid classic theme can replace
its PHP `header.php` and `footer.php` with unresolved block template parts and
remove the complete site shell.

MiMe Simple Events and Calendar uses plugin block templates only when
`wp_is_block_theme()` identifies a full block theme. Classic and hybrid classic
themes use the bundled PHP templates, which call `get_header()` and
`get_footer()` and consequently preserve theme and Elementor Pro locations.
Internal native render blocks remain registered on every site, while the two
plugin template definitions are registered only for a full block theme.

An explicit PHP override under
`mime-simple-events-calendar/single-wpse_event.php` or
`mime-simple-events-calendar/archive-wpse_event.php` always wins before plugin
block-template discovery. A full block theme or Site Editor may instead override
the matching `single-wpse_event` or `archive-wpse_event` block template. The
release matrix must cover a classic theme, a hybrid classic theme with
`theme.json`, a PHP override, a full block theme and a block-template override,
and must assert the complete header/content/footer shell rather than event
content alone.

## ADR-037: PHP 8.2 is the public runtime floor from version 0.2.5

**Status:** Accepted

The minimum supported PHP version is lowered from 8.3 to 8.2 while the minimum
WordPress version remains 6.9. PHP 8.2 is still receiving upstream security
fixes at the time of this decision and represents a substantial share of active
WordPress installations that cannot use the plugin under the earlier metadata
floor. PHP 8.3 or newer remains recommended for site operators when their
hosting platform supports it.

The compatibility claim applies to the shipped plugin and to the complete
quality suite. Composer resolves development tooling against a PHP 8.2 platform,
and CI executes the PHP gates on 8.2 as well as the newer supported runtimes.
The WordPress compatibility smoke matrix runs its minimum WordPress target on
PHP 8.2. A metadata-only change without executable 8.2 evidence is not an
acceptable release.

This decision does not permit compatibility shims, conditional feature loss or
weaker security controls. PHP 8.1 and 8.0 remain unsupported because reaching
them would require broad production refactors of readonly classes, readonly
properties, enums and constructor defaults with disproportionately high
maintenance and regression cost. Historical release evidence continues to
describe the PHP version on which it was produced.

## ADR-038: The transitive brace-expansion override tracks patched releases

**Status:** Accepted

After ADR-035, a second high-severity denial-of-service advisory also affected
the previously pinned 5.0.8 release. The narrow root override therefore advances
to 5.0.9, the first release outside the new affected range. The architectural
boundary remains unchanged: this dependency belongs only to linting and local
WordPress tooling, is excluded from the production archive and may be overridden
only while all frontend, release, browser and WordPress compatibility gates pass.

The override is intentionally patch-specific instead of accepting npm's proposed
forced major upgrade of ESLint. It must continue to move to a patched compatible
release when a relevant advisory appears, and it must be removed once the direct
WordPress tooling dependency tree resolves safely without it. High or critical
audit findings are never ignored or allowlisted to preserve a green build.

## ADR-039: Post-release work starts with builder and presentation stabilization

**Status:** Accepted

Real-world use and exploratory testing after version 0.2.5 identified unfinished
presentation controls, an Elementor editor lifecycle gap, event-taxonomy archive
semantics, narrow-container behaviour and missing composite Gutenberg components.
The next feature release is therefore 0.3.0 and resolves those existing-product
gaps before recurrence, another page-builder adapter or a lower platform floor is
introduced.

The active ordered plan lives in `docs/ROADMAP.md`; completed historical backlogs
remain immutable evidence. Version 0.3.0 must preserve current event storage,
shortcodes, block names, widget identifiers and saved Elementor control IDs. It
may add bounded controls and dynamic blocks, but it may not duplicate event
queries, access decisions or presentation logic inside a host adapter.

Recurrence follows as a separate discovery and specification phase. No recurring
event implementation begins until an ADR defines series ownership, occurrences,
exceptions, edit scope, timezones, deletion and migrations. Divi 5 follows through
the shared renderer/query boundaries and remains optional. WordPress 6.9 and PHP
8.2 remain the supported floor; broader compatibility requires separate evidence
and must not weaken security or create conditional feature loss.

## ADR-040: Event taxonomy archives are complete, fixed-scope event collections

**Status:** Accepted

The public `wpse_event_category` and `wpse_event_tag` routes are known event-only
collections, but WordPress' default theme loop orders and labels them by post
publication date. They now reuse the public event visibility and start-date
ordering rules while preserving the term constraint already resolved by
WordPress. These archives intentionally use the `all` period in ascending order:
a taxonomy URL represents the complete public history of that term, independent
of the configurable upcoming-only landing-page preference.

The shared event archive renderer is used inside classic, hybrid and block-theme
shells. The general archive filters are omitted because a second category or tag
selection could replace or contradict the fixed route term. Taxonomy-specific
theme overrides take priority. Classic themes may then reuse the existing generic
PHP event-archive override; full block themes receive registered taxonomy-specific
fallbacks because WordPress does not reliably fall through from a plugin taxonomy
template to a theme's custom post-type archive template. Draft, private and
password-protected events remain excluded.

No global post-date, loop or search filter is introduced. Blog categories, post
tags, products and mixed WordPress search keep their native relevance, ordering
and publication-date semantics. Event-specific search presentation may be
reconsidered only behind a separately scoped contract that cannot alter non-event
results.

## ADR-041: Builder styling extends shared components through opt-in variables

**Status:** Accepted

Elementor presentation controls target the existing semantic event components and
shared CSS custom properties. New visual controls do not receive saved defaults;
Elementor emits a scoped value only after an editor selects one. Optional colors
that were not part of the existing native stylesheet remain undefined until then,
so themes keep ownership of typography and backgrounds on update. Existing widget
names, saved control IDs and shortcode/render output remain unchanged.

Cards, filters, controls, pagination, composite details and calendar interaction
states each have a stable target instead of sharing one broad button rule. Atomic
Featured Image and External Action widgets extend the shared field-widget style
section rather than introducing alternate markup or metadata access. The composite
Details widget uses the same image, summary and action targets.

Grid columns respond to the inline width available to `.wpse-events`, which is the
relevant constraint inside builder columns and theme containers. Browsers without
container-query support retain the established viewport breakpoints through an
explicit feature-detected fallback. No JavaScript layout observer or builder
dependency is introduced.

## ADR-042: Composite Gutenberg blocks are dynamic adapters over native renderers

**Status:** Accepted

The Event List / Grid, Event Calendar and Event Details components become
metadata-registered dynamic Gutenberg blocks. Their saved form contains only a
block comment and bounded attributes; public HTML is produced on every request by
the existing shortcode, query, context and presentation services. The blocks do
not copy an event query, calendar configuration, access rule or details template.

Block attributes use Gutenberg-native camelCase names and are translated through
one strict server-side adapter to the established shortcode contract. Collection
limits, layouts, periods, views, taxonomy slugs and booleans retain the existing
allowlists and bounds. An explicit Details source must be a published,
password-free event. A zero source may use an event supplied by block context;
editorial preview access then follows the current-event resolver rather than
silently falling back to another event.

All three blocks share the existing editor script, inserter category and bounded
public event/term choices. `ServerSideRender` owns loading, error and empty editor
states; no placeholder is serialized or emitted publicly. Frontend styles and the
calendar enhancement remain on-demand through their existing handles. Existing
atomic block names, serialized attributes and pattern content remain unchanged.

## ADR-043: Interaction controls remain bounded presentation state

**Status:** Accepted

The 0.3.0 interaction polish extends the existing list, calendar and complete
details contracts instead of introducing a second builder-only presentation
path. Event cards may hide title or date, bound excerpts to 1–100 words and use
an allowlisted H2–H6 title. Calendars may open on one validated Gregorian date,
hide individual toolbar groups and choose an H2–H6 fallback heading. Complete
details may hide established field groups, use an H1–H6 title and override six
short plain-text labels. Defaults reproduce the previous public output.

Visitor filtering remains a read-only GET interaction. A namespaced apply marker
distinguishes an intentionally empty visitor selection from an untouched initial
constraint. Reset removes only the current component's state and restores its
configured initial terms while preserving bounded, allowlisted state belonging
to other list or calendar instances. JavaScript enhancement mirrors that
behaviour without becoming required for filtering or fallback content.

All host adapters normalize into the same shortcode and renderer services.
External location destinations consistently open in an isolated new tab;
internal event links remain same-tab. Hiding every complete-details field emits
no empty public wrapper. These controls affect presentation only: event storage,
dates, timezones, eligibility, queries, feeds and structured data are unchanged.

## ADR-044: Recurrence uses one series post and a rebuildable occurrence projection

**Status:** Accepted

This decision supersedes the recurrence and custom-table exclusions in ADR-001,
ADR-005 and ADR-031 for development from 0.4.0 onward. Those earlier decisions
remain accurate historical contracts for released versions through 0.3.0.

The recurrence phase keeps one `wpse_event` post as the canonical series. Shared
WordPress content, taxonomies and event metadata remain on that post. A versioned,
revision-enabled recurrence definition owns the normalized rule, timezone,
segments, manual additions, exclusions and sparse occurrence overrides. Raw
RRULE input, duplicate occurrence posts and an externally hosted recurrence
service are not accepted storage models.

One plugin-owned occurrence table is introduced as a rebuildable read projection
for both recurring and one-off events. A one-off event has exactly one projected
occurrence. Public chronological collections, pagination, calendar windows and
occurrence routing will migrate to this common projection only after one-off
parity is proven. Search remains series-oriented. The event post and registered
metadata remain the source of truth; deleting or rebuilding projection rows may
never delete canonical event content.

Projection replacement is generation-based. New rows are built and validated
under a new generation before one event-level active-generation marker is
switched. Interrupted generation therefore leaves the previous complete
projection active. Queries are bounded, join only eligible event posts and their
active generation, and use prepared SQL at the custom-table boundary. Infinite
rules always receive both a time horizon and a hard occurrence cap.

Regular rules cover daily, weekly, monthly and yearly intervals with inclusive
end-date or occurrence-count bounds. A specific-dates mode, manual additions and
individual cancellations are included. Multiple simultaneous rule patterns,
hourly/minutely recurrence, patterned exclusions, tickets, external sync and a
separate page-builder body per occurrence remain excluded from this increment.

An occurrence has an immutable identity based on the series UID and its original
local recurrence slot. Moving it does not change that identity or public URL.
Sparse overrides may change its title, short note, featured image, dates, status,
venue, address and external actions; body content and taxonomies continue to
inherit from the series. Broad schedule edits preserve past occurrences and never
silently discard a modified future occurrence.

Editors choose **only this occurrence**, **this and following occurrences**, or
**the complete series** before editing. The selected scope remains visible and a
broad change previews added, removed, moved and exception-affected dates before
commit. Cancellation is reversible and distinct from permanent deletion.

Recurring public events receive a series overview plus stable leaf URLs for
individual occurrences. Canonical URLs, document titles, structured data,
sitemaps, cache context, Elementor, Gutenberg and REST must resolve the same
occurrence. Lists default to the next occurrence per series while calendars show
all eligible occurrences in their bounded window. The complete normative contract
is in `docs/RECURRENCE-CONTRACT.md`.

## ADR-045: The recurrence aggregate uses bounded canonical JSON metadata

**Status:** Accepted

The complete recurrence aggregate is stored under one protected, single-value,
revision-enabled post-meta key as a canonical JSON string. JSON is only the
WordPress persistence envelope: the versioned plugin-owned aggregate and its
strict codec remain the domain and schema authority. The key is not writable or
readable through core REST. Editor mutations must pass through a dedicated
capability-checked application service and replace the aggregate atomically.

An accepted value is decoded with exceptions enabled, bounded by a 2 MiB encoded
size and validated as the complete exact aggregate before use. Re-encoding fixes
root, nested and sparse-field ordering so semantically equal aggregates have one
stored representation. Invalid JSON, unsupported versions, unknown fields,
excessive collections and weak scalar coercion fail closed. PHP serialization,
raw RRULE strings and partial nested metadata are never accepted.

WordPress supports revision-enabled string metadata natively. A string envelope
also avoids the single-object metadata ambiguity in WordPress' revision-preview
filter while retaining one atomic value. Restoring a revision must mark the
derived occurrence projection dirty before any occurrence-aware public read can
resume. The current 0.3.0 public paths remain unchanged until aggregate writes,
revision restoration and projection rebuilding pass their complete integration
matrix.

## ADR-046: Recurrence saves are canonical-first and projection-complete

**Status:** Accepted

A recurrence mutation is one application operation with two ordered durability
stages. The capability-, post-, identity- and timezone-checked aggregate service
first replaces the complete canonical JSON value after setting the occurrence
index dirty. A separate coordinator then derives and activates one complete
bounded occurrence generation using the event status read from canonical metadata.
It never accepts request-supplied status as projection authority.

An unchanged clean aggregate is a no-op. An unchanged dirty aggregate deliberately
retries projection, making interrupted or previously failed builds repairable
without changing the rule again. If projection fails after canonical storage has
changed, the canonical value is not rolled back: the dirty marker remains and the
result reports both the projection failure and the fact that canonical storage
changed. Occurrence-aware reads therefore fail closed while the authoritative
editor state remains recoverable.

Projection accepts a complete empty window and activates its generation marker.
Every schedule segment must generate its effective seed, and each non-root anchor
must be generated by the preceding schedule. A seed moved earlier may move the
selected occurrence but later slots on or before its original anchor date are not
regenerated, preventing duplicate identities across the segment boundary.

Sparse exceptions are reconciled against bounded engine output. A date override
whose immutable original identity lies outside the requested window but whose
effective date moves into it receives an additional membership check and remains
visible. This path is capped at 25 inbound moves per complete build to prevent a
large aggregate from multiplying catch-up work. Missing membership, duplicate
identity, unsafe expansion or an excessive inbound set rejects the complete
generation; no exception is silently dropped or partially projected.

## ADR-047: Recurrence impact is identity-based and scope-validated server-side

**Status:** Accepted

The recurrence editor must select **only this occurrence**, **this and
following**, or **complete series** before it submits a proposed complete
aggregate. Scope is not a client-side hint. A pure application service validates
the structural mutation against that scope, builds current and proposed bounded
occurrence sets through the exact same projection builder used during saves, and
compares them by immutable recurrence identity.

The preview reports additions, removals, moves, status changes, source changes and
manual/exclusion/override impact in chronological order. Only-this edits cannot
change segments or another exception. This-and-following requires a generated
target and rejects any changed prior segment, prior exception or generated impact
before the target. Changed exception state without a row inside the explicit
bounded preview window fails closed rather than disappearing from the summary.

Broad schedule reconciliation retains modified slots by converting them to the
manual collection when their new rule membership disappears. Such a detached
slot deliberately keeps its original generated recurrence identity; only a new
manual addition receives `manual:{UUID}`. Because the public key derives from the
series UID and recurrence identity, the detached event keeps its established URL
while its projection source changes from rule to manual.

## ADR-048: Recurrence editor writes use canonical compare-and-replace

**Status:** Accepted

Every editable recurrence snapshot receives a deterministic SHA-256 revision
token derived from a plugin-specific context and its exact canonical JSON. The
absence of recurrence has its own one-off token. Tokens reveal no secret and are
used only to prove that preview and save began from the same canonical state.

The authorized persistence service validates the expected token before marking
the derived projection dirty. The WordPress aggregate adapter then performs the
actual race-safe write with the previous raw metadata value as
`update_post_meta()`'s compare argument. First-time recurrence uses unique
`add_post_meta()`. A changed value between preview and write returns a stable
stale-revision result and never overwrites the newer aggregate. Because derived
health is invalidated before canonical mutation, a race discovered at the final
compare may conservatively leave the event dirty; repair is preferable to a
window in which stale projection is falsely healthy.

## ADR-049: Recurrence edits require an authorized preview-confirm-save cycle

**Status:** Accepted

The recurrence editor uses dedicated authenticated `wpse/v1` routes for context,
impact preview and confirmed save. The canonical aggregate remains absent from
core REST and is exposed only to a user who can edit the selected event. Every
mutation accepts a complete exact aggregate, explicit edit scope and target,
bounded generation window and the revision loaded by that editor. WordPress REST
cookie authentication supplies its normal nonce protection; route permission
callbacks additionally recheck the event post type and mapped `edit_post`
capability.

Preview is server-owned. It runs the proposed aggregate through the exact codec,
scope validator and occurrence builder used by persistence, then returns a
bounded impact summary. The confirmation is an HMAC over the event, current user,
canonical revision, proposed aggregate, scope, target and complete preview
window. Save re-runs the preview, verifies that exact confirmation and finally
uses canonical compare-and-replace. A changed event, different user, altered
proposal, broader window or replay after a successful save therefore fails
without overwriting current state.

Because the custom recurrence route does not perform a normal post update, a
changed canonical aggregate explicitly requests a WordPress post revision after
the compare-and-replace attempt. This also happens when canonical storage changed
but derived projection failed, because that new canonical state remains
authoritative and repairable. Unchanged saves do not add a revision.

The first Gutenberg adapter deliberately exposes only **complete series**
schedule changes. It follows the site's configured week start, preserves the
difference between an ordinal weekday and the last weekday of a month, blocks
preview while ordinary post fields are dirty, and requires an exact impact
preview before apply. Localized numeric settings are normalized before date
arithmetic or strict REST submission. Series containing future segments or
individual exceptions remain locked until their occurrence-scoped editor is
available.

The context route may bootstrap a valid one-off event as a one-date comparison
aggregate, but does not store recurrence merely by being opened. Returning a
series to **does not repeat** remains a separate future operation because it must
select the surviving occurrence, update canonical one-off dates and remove the
aggregate under one explicit preview and concurrency contract.

## ADR-050: Disabling recurrence retains one explicitly selected occurrence

**Status:** Accepted

Disabling recurrence is a distinct destructive operation, never an empty or
degenerate recurrence rule. The editor must select one effective occurrence that
will survive as the canonical one-off event. Preview identifies that occurrence,
counts the other occurrences removed inside its explicit bounded window and
states unambiguously that every occurrence outside the preview window is also
removed. The existing aggregate revision, selected immutable recurrence identity,
window and authenticated user are bound into a dedicated confirmation signature.

Survivor discovery remains bounded but may not assume that the first schedule
anchor is recent. The editor starts near the site's current date for an active
or open-ended series, near the final bounded period for an already-ended series,
and falls back to the anchor period when the initial window is empty. It also
offers an explicit ISO-date search start so an editor can move the bounded window
to an old or distant occurrence. The loaded window, not an unsubmitted date-field
value, is bound into preview and save. Changing the search date clears the prior
selection and confirmation instead of silently retargeting either one.

Save rebuilds and revalidates that exact preview before changing state. It marks
the occurrence projection dirty first, prepares the ordinary event date, time,
timezone and status metadata from the selected effective occurrence, and then
removes the complete aggregate with an exact compare-and-delete against the
previewed revision. A stale aggregate or failed delete rolls back only metadata
that still equals the values prepared by this operation, so a concurrent change
is never overwritten. The small multi-key WordPress metadata transition is
therefore guarded by post locking, optimistic comparison and a dirty derived
index rather than represented as an unsafe healthy intermediate state.

After the aggregate has been removed, the normal one-off projector becomes the
only derived representation. Projection failure leaves the authoritative
one-off metadata intact and the index dirty for deterministic repair; it does not
recreate the old series. A successful canonical conversion explicitly creates a
WordPress post revision. No-op, rejected and stale requests do not create one.

## ADR-051: Occurrence override input is canonical before scope editing

**Status:** Accepted

The recurrence aggregate's strict domain codec is necessary but not sufficient
as a WordPress input boundary. Before an authenticated editor context exposes a
stored aggregate, and before any proposed complete aggregate reaches impact
preview, every plain-text, multiline-text and URL occurrence override must equal
its value after the corresponding WordPress sanitizer. The editor rejects rather
than silently rewrites a proposal so the exact aggregate signed during preview is
also the exact aggregate considered for persistence.

Date ranges, statuses and non-negative image identifiers retain their strict
typed domain validation. Rendering must still escape every override for its
eventual output context. This guard prevents stored HTML or normalization drift
from entering the future occurrence editor while keeping the recurrence domain
independent of WordPress globals.

## ADR-052: Occurrence editing starts from server-resolved effective and inherited state

**Status:** Accepted

An occurrence-scoped editor may not reconstruct inheritance from the visible date,
the first schedule segment or client-side aggregate inspection. After an editor
selects one occurrence from a bounded occurrence window, a dedicated authorized
read route resolves the immutable target against that exact window and returns the
current canonical revision, current effective occurrence, inherited occurrence,
existing sparse override and existing cancellation action.

Inherited state is derived by removing only the selected identity's override and
exclusion from an otherwise unchanged aggregate. A moved occurrence may have its
current effective date inside the selected window while its original inherited
slot lies outside it. The application therefore retries inheritance through one
identity-local one-day window. Manual and detached occurrences resolve that
fallback from their stored manual range before a generated-looking identity is
interpreted as a rule slot. Every expansion remains bounded.

This route is read-only and does not create an alternative persistence path. The
client preserves unedited sparse fields, constructs one complete aggregate and
continues through the existing scope-validated preview, signed confirmation and
compare-and-replace save contract. Removing an override field restores inheritance;
an empty sparse override is removed. Cancellation remains a separate reversible
exclusion. The selected window and revision returned by the server are the values
that must be carried into preview, preventing a changed search period or stale
editor from silently retargeting an edit.

## ADR-053: Only-this editing exposes reversible exceptions before content overrides

**Status:** Accepted

The first occurrence-scoped Gutenberg workflow exposes date/time, all-day state,
event status and reversible cancellation. Editors enter **only this occurrence**
explicitly, search one bounded period, select an immutable occurrence identity and
load its server-resolved current/inherited context before any field is editable.
The panel repeats the scope, captured timezone and selected occurrence. It never
infers a target from a visible date or silently widens the loaded window.

Date/time and status each have a named “use series” action. That action removes
the corresponding sparse override key instead of copying today's inherited value
into a new exception. Cancellation remains a separate reversible exclusion, not
a destructive deletion or an implicit status rewrite. Existing supported content,
location and image override fields remain byte-for-byte represented in the complete
proposal even though this first UI slice does not expose controls for them.

Every change must pass native-control validation, the ordinary-post dirty guard and
the existing impact preview before the signed save button is enabled. The save uses
the same complete aggregate, scope validator, confirmation and compare-and-replace
path as complete-series editing. A successful save reloads the same immutable target
from the exact selected window before another edit is allowed. If that refresh fails,
the UI reports that the save succeeded and requires an explicit reload rather than
misreporting a committed change as failed.

## ADR-054: This-and-following replaces the future schedule at one immutable boundary

**Status:** Accepted

An editor who chooses **this and following** selects one generated occurrence
identity after the root occurrence. The selected immutable identity becomes the
anchor of one replacement schedule segment. Every earlier segment remains
byte-for-byte canonical; the segment at that anchor, when present, is replaced,
and all later schedule segments are removed. This makes “following” mean the
complete future from the selected occurrence instead of silently stopping at a
previously scheduled future change.

Choosing the root occurrence is presented as a complete-series edit because both
actions would affect the same set. Manual occurrences, exclusions and sparse
overrides remain unchanged by the structural split. Before a proposal can be
saved, server-owned reconciliation must prove that each retained exception still
belongs to the proposed future schedule or detach the modified occurrence under
its existing identity. No exception may disappear because a future segment was
replaced.

New segment IDs are monotonic within the aggregate and never reuse a removed
segment ID. Replacing a segment already anchored at the target retains that
segment ID. The editor repeats the selected boundary, states that later scheduled
changes will be replaced, and requires the normal bounded impact preview before
save.

## ADR-055: The server constructs every this-and-following proposal

**Status:** Accepted

The **this and following** browser request contains only one selected generated
boundary, current canonical revision, exact bounded occurrence window and one
strict replacement schedule. It does not submit earlier segments, exception
collections, a series identity or a timezone. The server reuses the canonical
series timezone, proves that the selected boundary is an effective non-root
generated occurrence, applies ADR-054 and reconciles every retained exception.
Unknown keys, weak scalar values, manual identities, skipped identities, the root
identity and stale revisions fail before an impact confirmation can be issued.

The authenticated preview response returns the exact complete aggregate proposal
alongside its bounded impact and server signature. The established generic
recurrence save route remains the only persistence boundary: it decodes that
complete proposal again, rebuilds the impact, verifies the exact editor, event,
revision, scope, target and window signature, and performs compare-and-replace.
This avoids trusting a browser-side aggregate rewrite while retaining one audited
atomic save path and one replay/stale-state contract for every editing scope.

## ADR-056: This-and-following editing starts from generated inheritance

**Status:** Accepted

The Gutenberg boundary picker offers only effective `rule` occurrences after the
root identity. The root remains a complete-series edit; manual and detached
occurrences remain only-this edits. This prevents a visually date-shaped manual
identity from being presented as a valid generated split and mirrors the server's
authoritative membership checks.

After selection, the editor loads the authorized occurrence context and initializes
the replacement template from the inherited generated range, not from a sparse
date override on that one occurrence. It initializes the recurrence controls from
the schedule segment active at the selected identity. Existing overrides and
cancellations remain explicit exceptions and are reconciled by the server. The UI
therefore cannot accidentally turn one exceptional occurrence into the pattern
for every future occurrence.

The panel repeats the selected scope, captured timezone and immutable boundary,
warns that every later schedule segment is replaced, clears previews when any
boundary or field changes and blocks ordinary unsaved post state. It submits only
ADR-055's narrow request and applies only the exact server-returned proposal
through the generic confirmed save route.

## ADR-057: Occurrence content controls keep inheritance explicit

**Status:** Accepted

The only-this editor exposes every sparse presentation field already accepted by
the recurrence aggregate: title, bounded note, featured image, venue, address,
location URL, external event URL and external action label. The authorized
occurrence context returns a separate normalized `inherited_fields` snapshot from
the canonical series post. It never exposes metadata keys and never treats a
browser copy of series content as authoritative persistence input.

Each control carries explicit ownership state. Editing a field creates or updates
that occurrence's sparse key; **Use series value** removes the key. Empty venue,
address and URL values deliberately hide inheritance, while an empty title, note
or action label remains invalid. Featured-image ID zero deliberately hides the
series image. Browser limits come from the same PHP domain constants, but the
complete aggregate codec, WordPress canonical-content guard, capability check,
signed preview and compare-and-replace save remain authoritative.

The media picker uses WordPress' own editor and permission UI and is loaded only
for event block editors. All controls remain in the existing document panel;
there is no second post save, raw metadata endpoint or occurrence body. A content-
only change appears in the impact preview as an individual-field change even when
its date and status do not move.

Stopping recurrence retains the ordinary series post and, by the accepted
conversion contract, copies only the selected occurrence's effective date,
timezone and status. Individual occurrence title, note, image, location and
external-action values are removed with the aggregate. The destructive preview
states this explicitly instead of implying that those values are promoted to the
one-off event.

## ADR-058: Public occurrence presentation resolves through one exact identity

**Status:** Accepted

Before any public consumer switches to the occurrence projection, the shared
presentation boundary must resolve exactly one published, password-free
occurrence by its canonical event ID and stable public key. The lookup rechecks
the parent event, active projection generation and exact public key in one bounded
query. Zero rows mean no public context; duplicate, corrupt or inconsistent rows
fail closed.

The resolver loads the protected canonical recurrence aggregate, proves that the
projected recurrence identity and stored series UID derive the requested public
key, and applies only that identity's sparse override. Effective date and status
always come from the validated active projection row. Title, note, featured image,
venue, address, location URL, external URL and action label use the aggregate
override when present and otherwise inherit from one normalized series
presentation snapshot. Body, excerpt and taxonomies remain series-owned.

This increment exposes a request-local named presentation context only. Positive
and negative exact identities are cached for that request so multiple atomic
widgets reuse the same snapshot without repeated or divergent storage reads. It does
not register a rewrite, alter canonical URLs, switch list/calendar queries or make
recurrence publicly discoverable. Native templates, Gutenberg, Elementor, REST,
schema and sitemap adapters may migrate only after occurrence routing and their
complete parity tests use this same resolver instead of reimplementing override
logic.

## ADR-059: Occurrence leaf routing is an explicit development feature

**Status:** Superseded for activation by ADR-073

The virtual leaf route uses the configured event archive segment followed by the
canonical series slug, the fixed `occurrence` segment and one lowercase 32-byte
hexadecimal public key. Its dedicated query variable is registered only when the
strict boolean `WPSE_ENABLE_OCCURRENCE_ROUTES` constant is explicitly enabled.
Ordinary releases therefore cannot expose a recurring leaf accidentally while
template, builder, schema and SEO parity remain unfinished.

An enabled request must still resolve as the queried public event post and pass
the shared exact presentation provider. Malformed keys, missing or ambiguous
projection rows, nonrecurring events, private/draft/password-protected parents and
canonical-aggregate inconsistencies all become non-cacheable 404 responses. Core
canonical redirection is suppressed for any recognized occurrence request so an
invalid private leaf cannot reveal or redirect to its series URL.

The route controller retains one request-local resolved context for future native,
Gutenberg and builder adapters. Pretty permalinks use the documented leaf path;
plain permalinks retain the canonical series URL and add only the allowlisted
occurrence query variable. This increment does not yet change templates, document
titles, schema, sitemaps, REST output or the public collection read switch.

## ADR-060: Ordinary recurring-event saves cannot create a one-off projection

**Status:** Accepted

Once a non-empty canonical recurrence aggregate exists, its schedule owns the
event date range, all-day state and captured timezone. A normal WordPress post or
REST save may continue to update series-owned venue, address, external actions,
title, content, excerpt, featured image and taxonomies, but it cannot overwrite
those schedule fields or invoke the one-off projector. This prevents an ordinary
editor save from leaving one canonical recurrence aggregate beside a falsely
healthy one-row projection.

The ordinary event-status field remains the inherited series status. When that
value changes, persistence stores the validated status and marks the derived
occurrence index dirty; the next confirmed recurrence save repairs the same
bounded projection through the canonical recurrence coordinator. A status-neutral
series-content save does not dirty the index because those fields are resolved
live from the series context and are not duplicated in the occurrence table.

Every occurrence-level public query excludes a parent whenever the dirty marker
exists, even if an older active generation remains. That is intentional fail-safe
behaviour: temporary unavailability is preferable to stale dates or statuses.
The editor must make schedule ownership visible before public recurrence is
enabled; this server rule remains authoritative regardless of browser state.

## ADR-061: Native occurrence output derives from one shared presentation

**Status:** Accepted

An exact route context is converted once into the existing named event-
presentation shape. Effective title, date, status, note, featured image, venue,
address, location action and external action come from that context. The series
post continues to own body, excerpt and taxonomies. Invalid canonical URLs or
unformattable date values fail closed before native output is assembled.

The development-gated occurrence leaf uses this presentation for the bundled PHP
and block-theme fallback, the core document-title part, WordPress' core canonical
filter and the plugin-owned Event JSON-LD graph. Featured-image overrides render
only through WordPress attachment APIs; an unavailable attachment creates no
image markup or raw identifier. The occurrence note is escaped as bounded plain
text and never enters the post-content filter pipeline.

Elementor Theme Builder was initially bypassed until its event widgets consumed
the same current occurrence context. Once that adapter parity is present, an
applicable Elementor single location may own the leaf again; without a matching
location the exact native fallback remains authoritative. Core one-off routes and
explicit series selections remain unchanged. Third-party SEO canonical adapters,
sitemaps and REST remain separate gated work at this boundary; ADR-063 and
ADR-064 subsequently complete the REST leaf and supported SEO canonical filters.

## ADR-062: Host adapters distinguish current occurrence context from explicit series selection

**Status:** Accepted

Gutenberg and page-builder fields use one shared current-presentation resolver.
When a validated occurrence leaf is active and a component consumes the current
event context, that resolver returns the exact occurrence presentation already
used by native output and schema. If no occurrence leaf is active, it preserves
the established authorized current-event preview rules.

The existing explicit event identifier remains an explicit public series
selection. It never silently changes to the occurrence merely because the
component happens to render on an occurrence URL. A future additional context
selector may offer next or specific occurrences without changing that identifier's
meaning. This keeps static pages, query loops and editor previews deterministic
and preserves the saved 0.3.0 block and widget contract.
If an active occurrence cannot produce a valid canonical presentation, the
current component fails closed rather than falling back to potentially incorrect
series dates or occurrence-owned fields.

Elementor's reconstructed widget objects receive the same request-shared
resolver through its runtime service set. The existing twelve atomic widgets and
composite Event Details widget therefore follow the same current-versus-explicit
contract as Gutenberg. The Event Details shortcode shares that boundary because
the composite widget is intentionally a thin shortcode adapter. List and calendar
collection reads do not switch in this decision.

## ADR-063: Public occurrence REST leaves are versioned, exact and development-gated

**Status:** Accepted

The first public occurrence REST resource uses the new `wpse/v2` namespace and
one exact event-ID/public-key path. It is registered only while the same strict
development feature that owns occurrence routes is active. The existing
`wpse/v1/events` calendar feed and WordPress core event resource keep their 0.3.0
meaning; neither an event ID nor an existing response silently changes from a
series to an occurrence.

The controller delegates eligibility to the shared occurrence-presentation
provider and canonical URL builder already used by the native leaf. Missing,
private, password-protected, stale, ambiguous, dirty, corrupt or mismatched
identities all return one indistinguishable 404 response. The route is read-only,
has strict scalar argument schemas and performs no editor capability shortcut.

The version-one response is a bounded scalar presentation. It exposes the event
ID, stable public occurrence key, canonical URL, effective title, optional note,
effective date/status/image/location/action data and public category/tag names
and destinations. It deliberately omits recurrence identities, generation and
segment numbers, protected aggregate JSON, internal metadata keys, passwords,
editor confirmations and raw post content. Image URLs are resolved through the
WordPress attachment API; unavailable images become `null`. This contract is the
leaf reference for later collection, sitemap and cache adapters, but does not
enable occurrence discovery by itself.

## ADR-064: Supported SEO canonicals delegate to the exact occurrence route

**Status:** Accepted

When occurrence routing is enabled, one optional adapter filters the documented
canonical URL extension points for [Yoast SEO](https://developer.yoast.com/features/seo-tags/canonical-urls/api/),
[Rank Math](https://rankmath.com/docs/filters-and-hooks/frontend/meta-data/) and
[AIOSEO](https://aioseo.com/docs/aioseo_canonical_url/). WordPress accepts filter
registrations even when those plugins are absent, so this introduces no runtime
dependency, version check, class probe or activation notice.

The adapter reads only the occurrence route's already validated current context
and returns its strict HTTP(S) canonical. Ordinary pages, series roots, invalid
or unresolved leaves and unsafe canonical values preserve the SEO plugin's
original value exactly, including Yoast's supported `false` value. It does not
read query input, resolve an occurrence independently or add Open Graph, schema
or sitemap behaviour. Registration remains behind the same development gate as
the occurrence leaf so released 0.3.x output cannot change accidentally.

## ADR-065: Core occurrence sitemap discovery is bounded by the active projection

**Status:** Accepted

When occurrence routing is enabled, WordPress Core receives one dedicated
`occurrences` sitemap provider through its public sitemap registration API. The
provider lists only non-one-off rows from the active, clean projection generation
whose parent is published and password-free. A row is emitted only after the
shared public presentation resolver proves its exact event ID, public key and
canonical aggregate identity; corrupt or otherwise ineligible rows fail closed.

The provider uses the Core maximum-URL setting but enforces a plugin ceiling of
100 rows per database page. This preserves deterministic pagination and prevents
a filtered Core limit or a large projection from turning one public request into
an unbounded query or thousands of aggregate resolutions. Infinite schedules do
not expand during sitemap generation: only the finite coverage already present in
the disposable active projection can be discovered. One-off events remain in the
normal post-type sitemap and are deliberately excluded from this provider.

Entries contain only the strict HTTP(S) occurrence canonical. They omit
`lastmod`, because a recurrence aggregate and sparse occurrence overrides can
change without a reliable public modification timestamp; an inaccurate date is
worse than no optional date. Registration remains behind the occurrence route
development gate. SEO plugins that replace WordPress Core sitemaps need separate,
documented adapters and real-plugin qualification before public recurrence is
enabled; this Core provider does not pretend to cover those host-specific APIs.

## ADR-066: Occurrence leaves fail safe with a no-store cache policy

**Status:** Accepted

An occurrence public key remains stable when its date, status or sparse content
changes. Standard post-cache invalidation cleans the parent post object, but it
cannot guarantee that every full-page cache product discovers and purges all
virtual occurrence URLs. A validated occurrence browser leaf calls WordPress'
native `nocache_headers()` after exact route resolution and before template
output. It also defines the de facto `DONOTCACHEPAGE` constant used by WP Rocket
and comparable WordPress cache products, and invokes LiteSpeed Cache's documented
`litespeed_control_set_nocache` action. These exclusions remove the need to
discover and purge every virtual leaf after recurrence changes.

The policy applies only when the shared route has a valid current occurrence
context. Ordinary events, archives,
lists, calendars, sitemap resources and unrelated pages retain their existing
cache behaviour. Invalid occurrence identities already use the same Core no-cache
headers with their generic 404. The exact REST leaf retains WordPress REST's own
cache contract and is not changed by this browser-page policy.

This conservative boundary prioritizes correct event information over anonymous
full-page cache hits. It was qualified against WP Rocket's documented
`DONOTCACHEPAGE` handling and LiteSpeed Cache's public no-cache API on 25 August
2026. A host or CDN that deliberately overrides origin no-store directives is
outside the plugin's safe control. Re-enabling full-page caching would require
product-specific proof of purge coverage for moved, cancelled, edited, detached
and removed leaves; a stable URL or post-object cache flush alone is insufficient.

## ADR-067: Inactive occurrence generations are cleaned asynchronously and conservatively

**Status:** Accepted

Complete occurrence replacement intentionally leaves the previous generation in
the disposable projection table. Removing it inside the writer is unsafe because
another request may still be constructing a generation for the same event. The
projection therefore records an internal UTC creation timestamp on every row and
uses a separate bounded WordPress-Cron worker for later cleanup.

One cleanup batch may select and remove at most 100 rows. A row is eligible only
when its generation is no longer the event's active generation, its canonical
parent has no dirty projection marker and the row is at least 24 hours old. The
delete repeats those predicates after the bounded ID selection so an intervening
generation switch or repair cannot turn an active or dirty row into collateral
damage. Existing rows created before this schema field existed receive the safe
legacy value `0` and become eligible only when they are inactive and clean.

The worker uses single scheduled events rather than visitor-request cleanup. A
full batch schedules a continuation after five minutes; an incomplete batch
schedules the next maintenance pass after one day. Database or schema failure is
not reported as successful cleanup and uses the bounded retry delay. Deactivation
removes the scheduled hook but never removes occurrence data. Permanent event
deletion and explicit uninstall retain their existing, separate cleanup paths.

This is storage hygiene only. It may not repair canonical recurrence data, choose
an active generation, clear a dirty marker or make an unhealthy event publicly
readable.

## ADR-068: Occurrence health and repair share one canonical type-aware boundary

**Status:** Accepted

Administrators receive the same occurrence-index health summary on the event
settings screen and in WordPress Site Health. It distinguishes an unavailable
schema, an initial background build, public events that require repair and a
healthy index. The summary exposes only state and bounded aggregate counters; it
never lists event titles, recurrence definitions, internal identities or other
event content.

The initial migration and explicit administrator repair both delegate each event
to one type-aware repair service. That service loads the protected recurrence
aggregate first. A valid aggregate is rebuilt with the recurring projector and
the canonical inherited event status; the absence of an aggregate delegates to
the established one-off repairer. Corrupt aggregate data remains dirty and is
reported as invalid. It must never be mistaken for a one-off event or overwritten
with a one-row projection.

Recurring repair uses the same production horizon as the editor: the current
WordPress local date through 540 calendar days later, with the existing 1,000-row
cap. This is a derived read-model repair policy, not a canonical recurrence edit.
It does not change the aggregate, event dates, timezone, content, taxonomy or
publication state.

Manual repair selects only published, password-free events with a canonical start
and either no active generation or an explicit dirty marker. Each request handles
at most 25 events. Successfully repaired events disappear from the candidate set;
invalid or failed events remain fail-closed. Continuation therefore carries only
a bounded offset equal to the accumulated unresolved candidates, preventing one
bad event from trapping all later repairs without persisting event identifiers in
URLs or logs. A final health probe remains authoritative, so concurrent changes
can never turn an incomplete run into a falsely healthy state.

The action is available only through authenticated `admin-post.php`, requires
`manage_options` and an action nonce, and redirects with allowlisted bounded
counters. Background migration uses the same type-aware service but retains its
separate missing-generation selection contract.

## ADR-069: Recurring projection coverage is explicit and renewed with a safety buffer

**Status:** Accepted

An active generation token proves that one complete build was activated, but it
does not prove which local dates that build covers. Every successful recurring
projection therefore stores two protected canonical date metadata values: the
inclusive projection start and inclusive projection end, plus the generation
token that binds those dates to the active rows. One-off projections and
projection removal delete both values so stale recurrence coverage can never be
mistaken for current state. These values are derived state, are excluded from
REST and revisions, and never change the canonical recurrence aggregate.

Production builds cover the current WordPress-local date through 540 calendar
days later. A clean recurring projection is public-read ready only while its
stored start is on or before today and its stored end is at least 365 days after
today. Missing, malformed or shorter coverage is a repair gap and fails closed.
The 365-day minimum is not a new generation limit; it is the guaranteed forward
read window retained while maintenance catches up.

A dedicated WordPress-Cron worker checks at most 25 published, password-free
recurring events per pass. It renews a projection when its start is after today,
its end is missing, or fewer than 450 covered days remain. Successful rebuilds
disappear from the candidate set. Invalid or failed candidates remain dirty and
are skipped through the same bounded unresolved-offset strategy as manual repair,
so one event cannot trap later series. A full batch continues after five minutes;
an incomplete pass schedules the next check after one day.

The 450-day renewal threshold leaves an 85-day buffer before the 365-day public
minimum is crossed. This avoids rebuilding every series every day while allowing
substantial delay in low-traffic WP-Cron. Site Health reports only an actual
public-read gap, not routine buffered renewal work. Deactivation clears the
renewal hook and preserves all canonical and derived event data.

The projection store sets the dirty marker before changing any derived rows or
metadata. It writes recurring coverage before activating the generation, checks
the coverage-generation binding, clears dirty only after complete activation and
then checks the binding a second time. Projectors may add a failure marker but
never clear one after the store returns. Any partial write, concurrent mismatch,
activation failure or later concurrent mutation therefore remains dirty.
Recurring SQL reads also require the coverage-generation token to match their row
generation.

## ADR-070: Occurrence collections share one exact presentation bridge

**Status:** Accepted

Occurrence-aware lists, calendars, feeds and archives must paginate the derived
occurrence rows rather than WordPress posts. Repeated event IDs are valid and
must not be collapsed: each active row keeps its own date, status, stable public
key and canonical occurrence URL. Exact totals and page counts therefore remain
owned by `OccurrencePage`; no adapter may recalculate them from the number of
distinct parent posts it happened to render.

One shared collection presenter joins every already-authorized projection row to
its public series presentation. A one-off row inherits the normalized series
fields and retains the existing series URL. A recurring row resolves sparse
overrides from the canonical aggregate and receives the virtual occurrence URL.
The exact-route resolver and collection presenter share the same identity,
inheritance and URL builders, while the collection path consumes its already
validated row directly instead of issuing one additional projection query per
item.

The bridge fails closed for the complete requested page when any row cannot be
bound to a published, password-free parent, current canonical aggregate,
matching public identity or safe HTTP(S) URL. Silently dropping a corrupt row
would make totals, pagination and calendar counts dishonest; falling back to the
parent post date would reveal stale or semantically different data. The caller
may expose an empty or unavailable state, but may not mix legacy series dates
with an occurrence result page.

Existing public consumers remain on their one-off WordPress-query path while the
occurrence route feature is disabled. When the development gate is enabled, a
consumer may switch only after occurrence readiness is healthy and must use this
shared bridge. Archive routing requires a dedicated adapter because the native
main query cannot represent repeated parent post IDs; it may not emulate
occurrence pagination through a `post__in` query.
No visitor request expands recurrence or performs renewal.

## ADR-071: Native archives use an occurrence-backed WordPress templateshell

**Status:** Accepted

The native archive cannot paginate recurring output through `post__in`, because
WordPress collections represent posts and collapse or miscount repeated parent
IDs. When the occurrence feature and read-readiness gates are active, the archive
adapter therefore owns one exact occurrence query and stores its page in
request-local plugin state keyed by the main `WP_Query` object. Domain criteria
objects never enter WordPress query variables or cache-key serialization.

`posts_pre_query` short-circuits the redundant post SQL and returns one validated
published, password-free parent object for every occurrence row, including
repeated objects for one series. These posts are a routing and compatibility shell
only. The adapter assigns the occurrence total and total-page count directly and
sets `no_found_rows`, so Core does not overwrite those values with post counts.
The native renderer retrieves the stored occurrence page and uses the shared
collection presenter for effective fields, stable leaf URLs and cards.

If occurrence storage fails, a parent changes visibility between the projection
read and shell construction, or presentation cannot bind every row, the complete
page fails closed. No partial cards, parent-date fallback or dishonest pagination
is permitted. Empty page one remains a normal archive empty state; WordPress keeps
its standard paged-empty 404 decision. With either gate disabled, the established
one-off `WP_Query` archive remains byte-for-byte in control.

## ADR-072: Recurrence-owned schedule fields are replaced by an explicit editor boundary

**Status:** Accepted

Once an event has a non-empty protected recurrence aggregate, its ordinary
start, end, all-day and timezone metadata are bootstrap history rather than the
current series schedule. Showing those values as disabled reference controls is
misleading after a complete-series or future-segment change. The native Event
details metabox therefore replaces the ordinary schedule controls with a visible
notice that directs editors to the **Repeating event** document panel and its
three explicit scopes. The server-side recurrence ownership rule remains the
authoritative protection when JavaScript is absent or a request is forged.

The ordinary event-status control remains editable because it is the inherited
status of the complete series. Venue, address, external actions and other normal
post content also remain series-owned fields. Gutenberg mirrors only those
editable values into its REST save while recurrence owns the schedule. Enabling
recurrence updates the metabox state immediately through one namespaced editor
event; disabling recurrence reloads the editor and restores the ordinary schedule
controls. Existing classic-editor submissions retain canonical hidden schedule
values solely so an unrelated series-field save continues through the shared
validator without exposing a second schedule editor.

## ADR-073: Public occurrence reads are enabled by default for 0.4.0 qualification

**Status:** Accepted

The recurrence authoring, projection and read contracts now share one fail-closed
boundary from the editor through native archives, lists, calendars, REST,
Gutenberg, Elementor, schema and canonical output. Public recurrence therefore no
longer depends on the undocumented `WPSE_ENABLE_OCCURRENCE_ROUTES` development
constant. The 0.4.0 development branch registers occurrence routing and reads by
default. An explicit constructor decision remains only for deterministic tests of
the legacy one-off path.

Activation does not relax readiness or eligibility. Dirty, stale, incomplete,
ambiguous, unpublished, password-protected or corrupt occurrence state still
fails closed and never falls back to a parent date. Exact leaf pages remain
non-cacheable under ADR-066. WordPress Core supplies the bounded occurrence
sitemap. Replacement SEO products retain exact canonical parity, while dedicated
replacement-sitemap adapters remain optional follow-up compatibility work rather
than a security or data-correctness release gate.

Elementor Pro remains optional. Its single-template host continues to own the
document shell while atomic widgets resolve the same request-local occurrence
presentation. A real Elementor Pro release-candidate journey is required before
publishing 0.4.0, but does not justify keeping the implementation behind a secret
runtime constant.

## ADR-074: Schema upgrades schedule one late rewrite flush

**Status:** Accepted

Updating a plugin does not run its activation hook. The first release with public
occurrence leaves therefore cannot rely on activation to add the new pretty route
to WordPress' stored rewrite rules. A successful schema change records the current
validated event archive slug in the existing one-shot rewrite manager. Its late
`init` callback flushes softly only after the post type and occurrence rule have
registered, then deletes the marker.

A failed install does not schedule a flush. Re-running an unchanged schema does
not schedule one either. This makes the upgrade self-healing for default and
custom archive slugs without flushing on visitor requests indefinitely or asking
an administrator to resave Permalinks.

## ADR-075: Third-party canonical filters preserve nullable host values

**Status:** Accepted

SEO integrations do not share one strict canonical input type. Yoast SEO may pass
`null` while rendering an Elementor Theme Builder preview, even though ordinary
public requests commonly pass a string or `false`. The compatibility adapter
therefore accepts and preserves `string|false|null` when no exact occurrence is
active. A validated occurrence still replaces every supported host value with
its exact safe HTTP(S) leaf URL.

This is a compatibility boundary, not weak typing inside the occurrence domain.
Unknown structures remain rejected by PHP, and an unsafe generated URL still
preserves the original host value. Elementor previews must never fail merely
because an optional SEO plugin suppresses its canonical with `null`.

## ADR-076: Publishing an event repairs its occurrence projection synchronously

**Status:** Accepted

A transition from any non-public WordPress status to `publish` is the final point
at which a public event becomes eligible for occurrence reads. That transition
therefore repairs the event's bounded occurrence projection from canonical state
before the request completes, regardless of whether publication came from the
block editor, Classic Editor, WP-Cron or another WordPress-native workflow.

The hook is event-only and transition-only: unrelated posts and `publish` to
`publish` updates do no work. The shared type-aware repair service keeps corrupt
recurrence fail-closed, marks failed work dirty and uses the same bounded
production horizon as manual maintenance. Public requests may still use the safe
one-off fallback while a failed projection is repaired, but a successful ordinary
publication must not leave administrators with a manual **Repair occurrence
index** step.

## ADR-077: Occurrence-table queries use narrow documented Plugin Check suppressions

**Status:** Accepted

WordPress has no metadata or query API that can represent repeated event IDs with
exact occurrence pagination. The occurrence index is therefore a deliberately
small, rebuildable plugin-owned table behind dedicated read, projection,
maintenance and lifecycle adapters. Direct-database and no-cache warnings are
suppressed only on those exact calls: reads require current projection state,
mutations and destructive uninstall cannot return a cached result, and no other
production class may inherit those suppressions.

Dynamic read and cleanup SQL may originate only from `OccurrenceSqlQuery`. That
value object validates the complete internal template, accepts only `%d`, `%f`
and `%s`, proves one strictly typed value per placeholder and rejects every
unexpected percent sequence. The adapters then pass every value through a
literal `wpdb::prepare()` format before execution. Plugin Check cannot trace this
custom compiler and reports its final local SQL variable as unescaped, so the
corresponding warning is suppressed at the four execution calls with the exact
security invariant documented inline. Public eligibility, bounds and active
generation predicates remain mandatory in the query builders and their tests.

The legacy one-off calendar fallback intentionally sorts registered local date
metadata in a bounded WordPress query. Its slow-meta-value warning is likewise
suppressed only at the exact `orderby` value; recurring collections use the
indexed occurrence projection. These suppressions do not exempt the code from
strict official Plugin Check on every release candidate.

## ADR-078: Divi 5 is an optional native-module adapter over shared presentation

**Status:** Accepted

The 0.5.0 integration uses Divi 5's documented module metadata, PHP registration,
Visual Builder package and REST preview contracts. It does not render events from
raw post metadata, duplicate public queries or introduce Divi-owned event state.
Every module maps its allowlisted attributes to the existing context resolvers,
field renderers, shortcode renderers and component stylesheet.

The integration feature-detects Divi 5 APIs before registering modules or assets.
The dormant WordPress post-type allowlist filter is safe to register without Divi
because no absent host consumes it. The plugin never includes files from the Divi
theme, bundles licensed Divi code or makes Divi a Composer/npm production
dependency. Visual Builder assets load only in Divi's editor. Frontend assets
retain the component-scoped loading rules already used by Gutenberg, Elementor
and shortcodes.

`wpse_event` is registered through Divi's supported third-party-post-type filter.
That gives a site without an existing preference Divi's normal supported-plugin
default and exposes the native **Use The Divi Builder** workflow. Divi's saved
Post Type Integration option remains authoritative: an administrator who has
explicitly disabled Events stays opted out. Theme Builder event assignments do
not depend on enabling individual event content editing.

The Visual Builder must not infer access from its client state. Explicit event
selection remains public-only. Current draft previews require WordPress
capability checks and authenticated nonces through the preview boundary. Exact
recurring leaves resolve through `CurrentEventPresentationResolver`; a Divi
module may not fall back to the canonical series when an occurrence context is
invalid, stale, protected or ambiguous.

The editor bootstrap likewise localizes the current presentation and usable
document ID only after resolving the exact current post and checking `edit_post`
server-side. Divi's app state is never accepted as authorization. Public explicit
event choices remain bounded and password-free independently of that editorial
context.

The Event Title vertical slice proved the host boundary before the adapter grew to
all twelve atomic and three composite modules. Query-backed Visual Builder
previews use one capability-protected, bounded REST route; stale requests are
aborted and debounced. Dynamic calendar previews bundle the existing calendar
runtime only in the Divi editor and initialize it idempotently after insertion.
Because Divi fetches each module in a separate request, editor-only HTML IDs and
their local label/ARIA/fragment references are namespaced with Divi's stable
module identifier before insertion. Public server output remains unchanged.

Ordinary-page save/reload and editor/frontend parity are proven. A temporary All
Events Theme Builder body also proved current-context Event Title output for a
one-off event and an exact recurring leaf with an occurrence-only override; its
generic editor state remained non-sensitive and its public test content and
assignment were removed. Post-run inspection found unreachable derived rows for
the deleted series; ADR-079 hardens the lifecycle and the bounded worker repaired
that local state to zero orphans. Browser-flow confirmation therefore joins
protected-content denial, composite Theme Builder coverage, multi-instance
resilience, the supported Divi floor and the packaged compatibility matrix as a
mandatory release gate. D4 shortcodes and conversion support are not added: these
are new Divi 5 modules with no historical D4 representation.

## ADR-079: Event deletion uses two lifecycle guards and bounded orphan repair

**Status:** Accepted

Occurrence rows are derived state and must not outlive their canonical event.
The normal `before_delete_post` hook continues to mark the event dirty, remove all
of its generations and verify removal of projection metadata. A second
`deleted_post` guard repeats the table-only deletion after WordPress has removed
the post and its metadata. The second guard accepts only the deleted event object
provided by WordPress and refuses to purge an ID that currently resolves to a
post.

A concurrent projector verifies that the canonical event exists both before
mutation and after all rows have been inserted. If deletion wins that race, the
inactive generation is removed and never activated. The scheduled stale-generation
cleaner also treats an old row with no parent post, or with a parent of another
post type, as an orphan. Candidate selection and deletion remain age-gated,
ID-bounded to 100 rows and repeat the same mutable predicates at deletion time.
This gives existing installations a gradual repair path without an unbounded
activation query or visitor-request cleanup.

## ADR-080: Calendar view follows the configured responsive breakpoint after initialization

**Status:** Accepted

Calendar modules choose their configured desktop or mobile view at initial render
and continue following the same 599-pixel viewport breakpoint while they remain
mounted. A resize that crosses the breakpoint changes FullCalendar's active view;
ordinary size changes only repair its geometry. This distinction is required in
builder canvases, where one calendar instance can survive device-preview changes,
row resizing, duplicate/copy operations and undo/redo without being recreated.

The responsive observer is instance-local and is removed with the existing
calendar cleanup callback. It never infers a view from container width, because
the public component contract defines desktop/mobile modes against the browser
viewport. A packaged browser regression starts in month view, crosses into the
configured mobile list view and returns to a healthy month grid. Existing manual
Month/List choices may be replaced only when the viewport crosses that configured
breakpoint, matching the declared responsive settings.

## ADR-081: Confirmed recurrence saves project the production horizon

**Status:** Accepted

The recurrence editor's impact window and the public occurrence projection serve
different purposes. Preview and confirmation remain bound to the exact bounded
window the editor reviewed. After canonical persistence succeeds, however, the
derived generation is always rebuilt from the current WordPress-local date
through the shared 540-day production horizon defined by ADR-069. A future series
anchor therefore cannot become the stored coverage start.

Using the preview window for production made a valid future series fail the global
readiness check because its coverage started after today. Public lists, feeds and
archives then correctly fell back to the canonical parent and appeared to lose
repeated dates. A browser regression now applies a three-date future recurrence
and requires three unique occurrence URLs in both the calendar feed and native
archive. Projection-window failure keeps the canonical change, dirty guard and
specific projection error exactly as before.

## ADR-082: Divi modules preserve the current native wrapper and preset contract

**Status:** Accepted

All fifteen native Divi modules declare the wrapper settings exposed by Divi
5.11.1's own modules. The module metadata includes the current meta, advanced and
decoration keys, while retaining the legacy `htmlAttributes` advanced key already
used by the integration. This is an additive compatibility rule: saved module
names, event attributes, control paths and renderer inputs may not be renamed or
removed to follow a host metadata revision.

Style-capable module fields continue to use Divi's native font preset group and
style clipboard category. A repository contract compares every generated module
and the hand-authored Event Title slice against the same wrapper and preset shape.
This gives global presets, copy/paste and responsive host controls the metadata
they expect without duplicating Divi's styling engine or persisting plugin-owned
preset state.

The licensed Divi package remains an external qualification input and is never
copied into source or release artifacts. A real Visual Builder no-save pass must
still prove that the current package loads the module palette without JavaScript
errors. Device-button and global-preset interactions that browser automation
cannot activate reliably are recorded as manual host checks rather than inferred
from metadata alone.
