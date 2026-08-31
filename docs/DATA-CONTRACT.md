# Event data contract

This document freezes the storage and authorization contract introduced in the first functional increment. Changes require an explicit migration decision in `docs/DECISIONS.md`.

## Native content

Events are public, non-hierarchical `wpse_event` posts. WordPress owns title, content, excerpt, featured image, author, revisions, publication status and slug. Event categories and tags use the separate `wpse_event_category` and `wpse_event_tag` taxonomies; blog terms are never shared.

The archive and single permalink base is `events`. The post type is available to the block editor and core REST API and declares `custom-fields` support so registered public meta can participate in REST.

## Canonical dates

The publication date is never an event date.

- Timed local values are stored as `Y-m-d\TH:i:s`, without an embedded offset.
- All-day values are stored as inclusive `Y-m-d` dates.
- A missing end is normalized to the start value.
- A single all-day event ends at local `23:59:59` for its inclusive end date.
- `_wpse_start_utc` and `_wpse_end_utc` are derived Unix timestamps used for chronological sorting, active/past queries and machine-instant output.
- `_wpse_end_utc` is inclusive. The public calendar validates it against canonical local data, while all-day calendar output converts the canonical inclusive local end to an exclusive date.
- Public calendar placement and visible-window overlap use `_wpse_start_local` and `_wpse_end_local`; visitor timezone offsets never change those saved wall-time dates.
- The stored timezone is an IANA identifier where possible. WordPress fixed offsets from `-14:00` through `+14:00` are accepted for sites configured without a named timezone.
- A new event captures WordPress' current site timezone. An existing event retains its captured timezone when the site setting changes; the plugin does not retroactively reinterpret its local wall time.

The boolean `wpse_show_event_timezone` option is disabled by default. When strictly enabled, timed event-detail presentation appends the captured IANA zone and event-date UTC offset, or a concise `UTC±HH:MM` label for fixed offsets. Ranges crossing a DST transition show both boundary offsets. All-day details omit the label. This option does not change canonical dates, UTC indexes, cards, calendar placement or feeds, REST values, or structured-data machine values.

The boolean `wpse_show_native_calendar_action` option is also disabled by default.
When strictly enabled, only the plugin-owned single-event fallback appends the
local ICS action. It stores no provider account, event snapshot or visitor state,
does not alter builder-owned templates and does not change canonical event or
occurrence data.

`EventDateRange` rejects invalid calendar values, reversed ranges, nonexistent local times during a spring DST jump and ambiguous repeated local times during an autumn DST rollback. Ambiguous input is safer to reject because a local value without an offset cannot express which occurrence the editor intended.

## Metadata

| Key | Type | Default | Core REST | Purpose |
|---|---|---:|---:|---|
| `_wpse_start_local` | string | empty | yes | Canonical local start |
| `_wpse_end_local` | string | empty | yes | Canonical local end |
| `_wpse_start_utc` | integer | `0` | no | Internal start index |
| `_wpse_end_utc` | integer | `0` | no | Internal inclusive end index |
| `_wpse_all_day` | boolean | `false` | yes | All-day flag |
| `_wpse_timezone` | string | site timezone | yes | Timezone captured at save time |
| `_wpse_venue` | string | empty | yes | Venue name, maximum 200 characters |
| `_wpse_address` | string | empty | yes | Address, maximum 500 characters |
| `_wpse_location_url` | string | empty | yes | HTTP(S) route/location URL |
| `_wpse_event_url` | string | empty | yes | HTTP(S) information/registration URL |
| `_wpse_event_url_label` | string | empty | yes | Optional plain-text external-link label, maximum 120 characters |
| `_wpse_event_status` | string | `scheduled` | yes | `scheduled`, `cancelled` or `postponed` |
| `_wpse_dates_need_review` | boolean | `false` | no | Internal editor warning after event duplication |
| `_wpse_series_uid` | canonical UUID | empty until indexed | no | Immutable series/occurrence identity seed |
| `_wpse_occurrence_generation` | positive integer | `0` | no | Complete active occurrence-table generation |
| `_wpse_occurrence_index_dirty` | boolean | `false` | no | Repair marker after an incomplete projection write |
| `_wpse_occurrence_coverage_from` | canonical `Y-m-d` | empty | no | Inclusive local start of the active recurring projection |
| `_wpse_occurrence_coverage_through` | canonical `Y-m-d` | empty | no | Inclusive local end of the active recurring projection |
| `_wpse_occurrence_coverage_generation` | positive integer | `0` | no | Generation token binding coverage to its active recurring projection |
| `_wpse_recurrence_definition` | canonical JSON string | empty | no | Internal version-one recurrence aggregate, maximum 2 MiB |

Every field is single-value, typed, sanitized and capability-protected. Coverage
dates and their generation binding are disposable derived state and deliberately
excluded from revisions; the other listed metadata remains revision-enabled. The
derived UTC indexes are absent from core REST so clients cannot overwrite query
indexes independently of the local date range. A later custom event feed may
expose calculated dates without exposing writable index metadata.

The plugin-owned `{$wpdb->prefix}wpse_event_occurrences` table is a rebuildable
projection governed by [RECURRENCE-CONTRACT.md](RECURRENCE-CONTRACT.md). It stores
only identity, date, timezone, source, generation, an internal UTC row-creation
timestamp and effective status fields.
Titles, content, passwords, taxonomies and publication eligibility always remain
in canonical WordPress tables. The store sets dirty before derived mutation. The
active generation is switched only after every row in that generation has been
inserted, and dirty is cleared only after activation and recurring coverage agree
twice. A dirty marker does not alter canonical event data or the established
one-off public read path; it records that the future occurrence read path must
repair or fail closed.

The hidden occurrence repository returns occurrence rows rather than `WP_Post`
objects so multiple dates from one series cannot be deduplicated accidentally.
Every public SQL plan joins the canonical parent at read time, requires a published
password-free `wpse_event`, matches only its active generation and applies event
category/tag filters through the canonical WordPress taxonomy tables. Result and
count statements share the exact same predicates. Equal start values use ascending
event ID and occurrence key as stable tie-breakers; the established post-meta
queries now use the same ascending event-ID tie-breaker. Stored projection rows are
revalidated against `EventDateRange` before use. A missing table, incomplete
migration, public event without a healthy active generation, invalid row or
inconsistent total keeps the public read-side switch disabled.

Inactive projection rows are storage garbage, not history. A scheduled worker
removes them in batches of at most 100 only after 24 hours, only while their
generation differs from the active metadata marker and only when the parent has
no dirty marker. The delete repeats those eligibility checks after selecting the
bounded row IDs. Cleanup never changes canonical event metadata or decides which
generation is active.

The recurrence engine does not read request data or WordPress globals. It accepts
only an `EventDateRange`, a factory-validated plugin-owned rule/specific-date value
and an explicit `RecurrenceGenerationWindow`. It returns immutable recurrence
slots whose identity is the original generated local start.

The complete recurrence aggregate is persisted as one protected, single-value,
revision-enabled `_wpse_recurrence_definition` JSON string. JSON is a bounded
WordPress storage envelope rather than a second domain model: decoding must pass
the exact versioned aggregate codec, and encoding always returns the same ordered
shape. The value is limited to 2 MiB and is absent from core REST. A dedicated
application service requires an event post, `edit_post`, an exact stored series
UID and the event's captured timezone. It marks the derived projection dirty
before replacing changed canonical data; a failed guard or failed write therefore
cannot leave a falsely healthy occurrence index. Restoring any event revision
also marks the projection dirty. The canonical-first save coordinator then builds
one complete bounded generation from canonical event status, segments, exceptions
and manual additions. It retries unchanged dirty aggregates, accepts a complete
empty generation and leaves dirty-marker clearing to the verified storage
boundary.
Projection failure leaves canonical storage intact and read eligibility disabled.
A pure recurrence-impact service compares current and proposed complete
aggregates through the same bounded occurrence builder used for persistence. It
keys changes by immutable recurrence identity, reports additions, removals,
moves, status/source changes and exception mutations, and rejects scope leakage
before any write. New manual additions use random manual identities; a modified
generated occurrence detached by broad reconciliation retains its generated
identity so its public key does not change. Editor input and public recurrence
output remain disabled until the scope-first UX and public occurrence context are
complete.

Interactive recurrence writes use a SHA-256 revision token derived from a
plugin-specific context plus the exact canonical JSON (or a dedicated one-off
sentinel). The token is an identifier, not a secret. WordPress storage uses the
current raw metadata value as `update_post_meta()`'s compare value, or a unique
`add_post_meta()` when recurrence is first enabled. Stale tokens fail without
replacing canonical data; the dirty-before-write invariant remains stronger than
editor convenience during a race.

The dedicated recurrence editor API is authenticated and capability checked. It
exposes only a validated editor context, a bounded impact preview and a confirmed
save operation. Mutations require the complete exact aggregate, explicit scope,
target, generation window and current revision. A server HMAC binds the preview
to that event, editor, proposal and window; save revalidates the full proposal and
uses atomic compare-and-replace. The signature is never persisted, is not an
authorization substitute and cannot be replayed after the canonical revision
changes. A successful canonical change explicitly invokes WordPress' post-
revision boundary so recurrence metadata participates in the event's revision
history even though the dedicated route does not update the post record itself.
An unchanged aggregate creates no revision noise. Core REST never receives the
protected aggregate.

Occurrence-scoped editing adds one read-only context boundary. A selected target
must exist in the supplied bounded occurrence window. The response carries its
effective occurrence, its inherited occurrence with only target exceptions
removed, its complete sparse override, its cancellation action and the canonical
revision. Moved targets receive one bounded identity-local fallback lookup at the
original generated or manual date. This response never writes partial metadata;
the existing complete-aggregate preview and confirmed save remain the only
mutation boundary.

## Write and publication rules

The native Event details panel and core REST API both pass through the same `EventValidator`. A write is persisted only when the complete submitted event record is valid; invalid updates never partially replace existing event metadata.

- Draft, pending and auto-draft events may omit the complete date range.
- Published, privately published and future/scheduled posts require a valid start.
- A timed start requires both a date and time. A timed end is either completely absent or contains both a date and time.
- An all-day start requires a date; submitted time controls are ignored. The end date remains optional and inclusive.
- Optional non-empty URLs must be valid HTTP(S) URLs. Invalid protocols are errors rather than silently becoming empty values.
- The external-link label is sanitized as plain text and bounded to 120 characters. It may be stored without a URL so an editor does not lose work, but public output renders it only with a valid external event URL. Empty and legacy labels use the translated default.
- The explicit event status and timezone must pass their allowlists.
- Native editor writes require the event nonce, the mapped event edit capability, and must not be autosaves, revisions or switched multisite writes.

For a native editor publication attempt, validation runs through `wp_insert_post_data` before the database write. An invalid published/future/private request is downgraded to `draft`, leaves existing event metadata untouched and returns allowlisted error codes through the editor redirect. Quick Edit and other non-REST status writes without the Event details payload must satisfy the same publication invariant using the already stored event record. In the block editor, the Event details controls mirror their typed values into Gutenberg's registered post-meta state so the fields and publication status travel in one authoritative REST request; the legacy metabox request remains the classic-editor fallback. REST writes use `rest_pre_insert_wpse_event`, return `wpse_invalid_event` with HTTP 400 and surface the first allowlisted validation message before WordPress inserts or updates anything.

After core REST metadata processing completes, or after a valid native save, `EventPersistence` replaces the event record and computes `_wpse_start_utc` and `_wpse_end_utc` from the accepted canonical range. Empty optional values and dates removed from a valid incomplete draft are deleted rather than stored as stale values.

Event duplication creates a new password-free draft and copies only the documented editorial fields, event metadata, featured image and event taxonomies. `_wpse_event_url`, `_wpse_event_url_label` and arbitrary custom metadata are deliberately excluded. Copied dates set `_wpse_dates_need_review`; any subsequent save that passes the shared validator and persistence gateway removes that flag.

## Capabilities

The post type uses explicit meta and primitive capabilities with `map_meta_cap` enabled. Administrator and editor receive the full primitive event/editorial set. Meta capabilities such as `edit_wpse_event` are mapped by WordPress and are not granted directly.

Term management and assignment use their own event capabilities. WooCommerce `shop_manager` receives no event rights automatically. Custom role support can be added later through an explicit administrator workflow rather than implicit WooCommerce coupling.

## Public presentation boundary

Public presentation never accepts a metadata key from a shortcode, block, widget or template. `EventPresentationFactory` reads the fixed internal allowlist once, normalizes stored values through the registered sanitizers and creates named presentation data. `EventFieldRenderer` is the shared HTML boundary for title, featured image, date/time/timezone, event status, venue, address, location action, content, excerpt, external action, categories and tags.

Current event context may include a non-public event only when WordPress grants `read_post`; explicit selections are restricted to published, password-free events. Password-protected atomic fields render nothing, while the complete native details output preserves WordPress' password form. Resolved presentation snapshots live only in one resolver instance for the current PHP request. See `docs/PRESENTATION-CONTRACT.md` for the stable field classes and adapter rules.

## Lifecycle

Activation registers content, creates or upgrades the occurrence projection table
and only then grants capabilities and stores `wpse_schema_version`. A failed table
creation does not claim the schema version, so normal boot can retry safely.
Deactivation flushes rewrite rules but does not delete events, metadata, terms,
capabilities, options or the projection table.

Deactivation and uninstall both clear the plugin-owned migration,
inactive-generation cleanup and projection-renewal schedules plus the disposable
renewal cursor. Scheduled callbacks are executable plugin state rather than
retained user data and must not survive removal of the code that implements them.

Uninstall also preserves all data by default. Destructive cleanup runs only when the per-site `wpse_delete_data_on_uninstall` option is strictly `true`, `1` or `'1'`. That path permanently deletes `wpse_event` posts (including their metadata, revisions, comments and term relationships through WordPress core), all terms in the two event taxonomies, the occurrence projection table, the explicitly allowlisted plugin-owned options and the capabilities granted to administrator/editor. Attachments are deliberately retained because featured media can be shared. Posts and terms are processed in batches of 100; direct SQL is confined to dropping the plugin-owned derived table. Options are removed last and remain if content or table cleanup cannot complete. In multisite, every site is visited in batches and its own opt-in is evaluated independently.

Network-wide multisite activation is explicitly blocked in this version; individual sites can activate the plugin separately. This prevents a partial capability installation that would appear successful across a network.

The complete supported historical paths and preservation invariants are defined
in [UPGRADE-CONTRACT.md](UPGRADE-CONTRACT.md).

## Maintenance

Events → Settings exposes two administrator-only POST maintenance actions. Both require `manage_options` and an action-specific nonce.

Capability repair reruns the idempotent `RoleManager::grant()` contract for administrator and editor. It does not remove, replace or inspect unrelated capabilities.

UTC-index repair reads only canonical `_wpse_start_local`, `_wpse_end_local`, `_wpse_all_day` and `_wpse_timezone` plus the WordPress publication status. Those untrusted stored values pass through the same date validator and publication-completeness policy as editor/REST writes. Valid ranges may update only `_wpse_start_utc` and `_wpse_end_utc`; incomplete drafts may have stale derived indexes removed. Invalid canonical values, incomplete published events and malformed booleans/timezones are left untouched for manual review. Location, event status, canonical dates and `_wpse_dates_need_review` are never changed by maintenance.

Reindexing uses ID-only, ascending pages of 50 across published, scheduled, draft, pending and private events. Each submitted batch reports inspected, changed, skipped-invalid and write-failure counts. A continuation requires another nonce-protected administrator POST; there is no automatic unbounded redirect loop.
