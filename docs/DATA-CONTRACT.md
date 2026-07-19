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

Every field is single-value, typed, sanitized, capability-protected and revision-enabled. The derived UTC indexes are deliberately absent from core REST so clients cannot overwrite query indexes independently of the local date range. A later custom event feed may expose calculated dates without exposing writable index metadata.

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

## Lifecycle

Activation registers content before flushing rewrite rules, grants capabilities idempotently and stores `wpse_schema_version`. Normal boot reruns installation only when that version changes. Deactivation flushes rewrite rules but does not delete events, metadata, terms, capabilities or options.

Uninstall also preserves all data by default. Destructive cleanup runs only when the per-site `wpse_delete_data_on_uninstall` option is strictly `true`, `1` or `'1'`. That path permanently deletes `wpse_event` posts (including their metadata, revisions, comments and term relationships through WordPress core), all terms in the two event taxonomies, the explicitly allowlisted plugin-owned options and the capabilities granted to administrator/editor. Attachments are deliberately retained because featured media can be shared. Posts and terms are processed in batches of 100 without direct SQL; options are removed last and remain if content cleanup cannot complete. In multisite, every site is visited in batches and its own opt-in is evaluated independently.

Network-wide multisite activation is explicitly blocked in this version; individual sites can activate the plugin separately. This prevents a partial capability installation that would appear successful across a network.

## Maintenance

Events → Settings exposes two administrator-only POST maintenance actions. Both require `manage_options` and an action-specific nonce.

Capability repair reruns the idempotent `RoleManager::grant()` contract for administrator and editor. It does not remove, replace or inspect unrelated capabilities.

UTC-index repair reads only canonical `_wpse_start_local`, `_wpse_end_local`, `_wpse_all_day` and `_wpse_timezone` plus the WordPress publication status. Those untrusted stored values pass through the same date validator and publication-completeness policy as editor/REST writes. Valid ranges may update only `_wpse_start_utc` and `_wpse_end_utc`; incomplete drafts may have stale derived indexes removed. Invalid canonical values, incomplete published events and malformed booleans/timezones are left untouched for manual review. Location, event status, canonical dates and `_wpse_dates_need_review` are never changed by maintenance.

Reindexing uses ID-only, ascending pages of 50 across published, scheduled, draft, pending and private events. Each submitted batch reports inspected, changed, skipped-invalid and write-failure counts. A continuation requires another nonce-protected administrator POST; there is no automatic unbounded redirect loop.
