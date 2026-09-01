# Security permission matrix

**Baseline:** 0.9.0 RC3

**Reviewed:** 1 September 2026

This matrix defines the authorization, integrity, visibility and bounding rules
for the frozen 1.x surface. A route described as public is intentionally readable
without a nonce; it must still resolve only published, password-free event data.
WordPress' normal REST cookie authentication supplies the REST nonce for signed-in
editor requests.

## Public read surfaces

| Surface | Method/input | Authorization and privacy boundary | Bounds and failure behaviour |
| --- | --- | --- | --- |
| Native single/archive/taxonomy/occurrence pages | `GET` route/query variables | WordPress publication rules plus plugin password-free occurrence eligibility | Invalid or stale exact occurrences return a generic 404; collections use configured pagination |
| Shortcodes, Gutenberg, Elementor and Divi public output | Saved allowlisted component attributes and optional explicit event ID | Explicit selections resolve only published, password-free events; current protected content follows the native password boundary | Query sizes, taxonomy IDs/slugs, labels and presentation values are normalized and bounded |
| Calendar feed `wpse/v1/events` | Public `GET` | Explicit `__return_true`; query layer forces published, password-free parent events and active occurrence generations | Maximum 400-day window, 20 category slugs, 20 tag slugs, page 1000 and 100 rows per page; invalid input is rejected |
| Exact occurrence REST `wpse/v1/occurrences/<identity>` | Public `GET` | Explicit `__return_true`; exact identity must resolve to a public, password-free active occurrence | No fallback to a series or nearby occurrence; invalid/private/stale identities return a generic 404 |
| Core event REST | Core REST methods | Registered metadata uses `edit_post`; public published metadata is removed completely while a post password is required | Core schema validation and WordPress capability handling apply |
| ICS download | Same-origin `GET`/`HEAD` query endpoint | Re-resolves one published, password-free, non-cancelled one-off or exact occurrence snapshot | No series fallback; generic failure, attachment plus `no-store`, `no-cache` and `nosniff` headers |
| Google/Outlook calendar actions | Visitor follows generated external link | Component author must opt in; the plugin makes no provider request itself | Only the same bounded public snapshot enters the provider URL; isolated tab without referrer |
| Event JSON-LD and sitemaps | Eligible public page/discovery request | Published, password-free and valid event or occurrence only | One normalized Event document per eligible context; no editor-only recurrence aggregate |

## Authenticated editor and administrator surfaces

| Surface | Integrity control | Required capability | Additional validation |
| --- | --- | --- | --- |
| Native/Core REST event save | WordPress post/REST nonce | Mapped `edit_post`, plus publish/delete capabilities when applicable | Registered typed metadata, strict dates/timezones, HTTP(S) URLs, allowlisted states and shared schedule validation |
| Recurrence context and occurrence reads | WordPress REST nonce | Exact event `edit_post` | Positive event ID, exact event post type, bounded occurrence window/identity |
| Recurrence preview/save, following preview/save and disable preview/save | WordPress REST nonce plus server-signed confirmation for broad writes | Exact event `edit_post`; service repeats authorization | Typed bounded aggregate, optimistic revision, exact impact token and stale-write rejection |
| Divi composite preview | WordPress REST nonce | Exact builder document `edit_post` | Allowlisted module, positive post ID, object payload no larger than 20 KB; output passes shared renderers |
| Duplicate event | Event-specific action nonce | Source `edit_post`, event creation and term-assignment capabilities | Exact event post type; only allowlisted event/recurrence/color metadata and permitted terms are copied |
| Event category color | Plugin taxonomy nonce | Event-category term edit capability | Optional strict six-digit hexadecimal value; other term metadata is untouched |
| Settings | WordPress Settings API nonce | `manage_options` | Per-setting allowlists, numeric bounds and explicit destructive-data warning |
| Occurrence maintenance/repair | Action-specific nonce | `manage_options` | Bounded cursors and batch sizes; derived rows only |
| Uninstall cleanup | Saved administrator opt-in plus WordPress uninstall context | WordPress plugin deletion authority | Allowlisted plugin data; shared media retained; executable scheduled jobs always removed |

## Storage and SQL boundary

Canonical events, recurrence aggregates and occurrence overrides remain protected
WordPress post metadata. Public lists never query the aggregate directly. The
custom occurrence table is a disposable, rebuildable chronological index that
stores dates, status, generation, stable identity and parent relationship—not
event bodies, passwords, visitor records, copied taxonomies or remote IDs.

`$wpdb` is permitted only in:

- `OccurrenceTable.php` for schema lifecycle;
- `WordPressOccurrenceProjectionStore.php` for derived-row replacement/removal;
- `WordPressOccurrenceGenerationCleaner.php` for bounded stale-generation cleanup;
- `OccurrenceReadRepository.php` for typed query construction with public parent predicates;
- `WordPressOccurrenceReadGateway.php` for prepared bounded execution.

Any new direct database file, public route, remote request, visitor-storage
primitive or privileged surface requires a decision record, threat review and a
failing security-contract test before implementation.
