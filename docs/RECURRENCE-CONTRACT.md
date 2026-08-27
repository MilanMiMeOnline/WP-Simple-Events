# Recurrence and occurrence contract

**Status:** normative public contract since 0.4.0
**Accepted:** 20 August 2026
**Current release candidate:** 0.5.0 retains the qualified 0.4.0 recurrence contract.

This document defines the recurring-event model approved in ADR-044. It extends
the product, data, public-query and presentation contracts. Public occurrence
reads fail closed whenever their migration, projection or parent eligibility is
not healthy; they never fall back to stale series dates.

## 1. Product boundaries

One `wpse_event` post represents one series. A one-off event is a series with one
occurrence and no recurrence rule. WordPress title, body, excerpt, featured image,
author, revisions, password and event taxonomies remain owned by the series post.

The first recurrence release supports:

- daily, weekly, monthly and yearly intervals;
- selected weekdays for weekly rules;
- same calendar day or ordinal weekday for monthly rules;
- no end, an inclusive end date, or a bounded occurrence count;
- a specific-dates schedule;
- a manual added occurrence;
- a reversible skipped or cancelled occurrence;
- this occurrence, this-and-following and complete-series edits.

It does not support multiple simultaneous generated rules, hourly/minutely rules,
patterned exclusions, ticketing, registration, external synchronization or a
separate Gutenberg/Elementor body per occurrence.

## 2. Ownership and inheritance

Series-owned values apply to every occurrence unless the field is explicitly
overridden. An occurrence may override only:

- title and a bounded plain-text note;
- featured image;
- date range and explicit event status;
- venue, address and location URL;
- external event URL and action label.

Content, excerpt, author, categories and tags remain series-owned. Removing an
override restores inheritance; it does not copy the current series value into the
exception.

After recurrence exists, ordinary WordPress saves may update those series-owned
values and the inherited series event status, but the recurrence aggregate owns
date range, all-day state and timezone. Ordinary persistence must never invoke the
one-off projector while a non-empty aggregate exists. A changed inherited status
marks occurrence projection dirty for bounded repair; dirty parents are excluded
from every occurrence read until that repair succeeds.

## 3. Recurrence definition

The canonical definition is registered, revision-enabled post metadata with an
explicit schema version. It contains a generated immutable series UID, captured
timezone, normalized allowlisted rule, future schedule segments, manual additions,
exclusions and sparse overrides. It is validated as a complete aggregate before
replacement. Unknown fields, excessive collections, invalid dates and raw RRULE
strings are rejected rather than retained.

The rule engine is accessed through a plugin-owned interface. A third-party RFC
5545 implementation may be bundled only after its licence, maintenance status,
namespace isolation and removal cost are recorded. The application, REST and
editor boundaries never expose that library's object or raw syntax.

The internal schedule codec accepts only keyed arrays with exact variant fields.
Regular rules store `type`, `frequency`, integer `interval`, one explicit `end`
variant and only the fields required by that frequency. Specific schedules store
only `type: specific_dates` and their bounded canonical date list. Unknown keys,
numeric strings, raw RRULE values and partially recognized future variants fail
the complete decode. Re-encoding a decoded value produces one stable canonical
shape; this schedule value will be nested inside the versioned aggregate rather
than exposed as independently writable REST metadata.

### 3.1 Version-one aggregate shape

The canonical aggregate is one exact keyed value with no optional root keys:

```text
schema_version: 1
series_uid: canonical lowercase UUID
timezone: canonical captured timezone
segments: 1..100 schedule segments
manuals: 0..1000 manual additions
exclusions: 0..1000 generated-slot exclusions
overrides: 0..1000 sparse occurrence overrides
```

Every nested object also has an exact key set. The root segment uses ID `0`; its
anchor equals its template start. Segment IDs and original anchors are unique,
and segments are ordered by ascending original anchor. An anchor is the immutable
generated recurrence identity at which that schedule becomes effective; a moved
effective template does not rewrite it. Every template, manual range and moved
override uses the aggregate timezone.

A newly added manual occurrence stores a canonical `manual:{UUID}` identity,
complete date range and explicit effective status. Broad-edit reconciliation may
also detach a previously generated occurrence into this collection under its
original generated identity. That exception is deliberate: it preserves the
existing public key and URL when the new rule no longer produces a modified slot.
An exclusion applies only to a generated identity and records either `skip` or
`cancel`. An override stores an identity and at least one allowlisted field.
Manual overrides must reference an existing manual or detached occurrence. A
skipped occurrence cannot retain a public override; a cancelled one may retain
presentation fields for its stable leaf page.

Sparse overrides accept only the inheritance fields listed in section 2. Text,
URLs and attachment IDs have explicit type and length limits; URLs are empty or
HTTP(S), and zero is the deliberate no-image value. Empty venue, address or URL
values hide an inherited value. Removing a key restores inheritance. Unknown
keys, numeric strings, non-list collections, unsupported schema versions and
partially valid aggregates fail the complete decode.

The aggregate codec is an internal persistence boundary, not a public request
schema. Re-encoding any accepted value produces one canonical representation.
Whether a generated exception still belongs to an actual schedule slot is
reconciled against bounded engine output during application validation and impact
preview; the shape codec never performs unbounded expansion while decoding.

WordPress stores that canonical representation as one protected,
revision-enabled JSON string with a maximum encoded size of 2 MiB. The string is
not exposed through core REST and is never decoded through PHP serialization.
Application writes require event edit permission plus an exact match with the
event's immutable series UID and captured timezone. A changed aggregate marks its
derived projection dirty before canonical replacement. Restoring an event
revision does the same, including for one-off date/status metadata restored by
WordPress core.

The complete save coordinator writes canonical metadata first and rebuilds its
derived generation second. Preview and confirmation remain bound to the exact
editor impact window, while the saved generation always uses the production
window from the current WordPress-local date through 540 days later. Projection
always inherits status from canonical event metadata rather than request data. An
unchanged healthy aggregate avoids needless work; an unchanged dirty aggregate
deliberately retries projection. If rebuilding fails after a canonical change,
the canonical change remains stored, the dirty guard remains set and the caller
receives a specific projection failure. No occurrence-aware read may use the
stale generation in that state.

## 4. Occurrence identity

Generated occurrences use the original canonical local recurrence slot as their
recurrence identity. That identity does not change when an occurrence is moved.
A public occurrence key is deterministically derived from the immutable series
UID and recurrence identity. New manual additions receive an immutable random
identity at creation. A generated occurrence detached during broad reconciliation
keeps its original generated identity and therefore its exact public key.

Keys are identifiers, not authorization secrets. Every read still checks the
parent event's post type, publication state and password. A draft, private,
trashed or password-protected parent cannot leak through the occurrence table,
REST, builders, calendar, schema or sitemap.

## 5. Date and timezone semantics

Rules are evaluated in the series' captured local timezone. Local wall time is
preserved across daylight-saving changes and each generated range receives its
own UTC indices. Invalid or nonexistent local times are not silently shifted.
Ambiguous repeated local times require an explicit policy before publication and
must remain deterministic in tests.

The built-in deterministic engine therefore fails the complete generation when a
timed rule reaches either kind of unsupported local time. It preserves local start
and end clock fields plus the template's calendar-day span; UTC durations may
legitimately become one hour shorter or longer across DST. A future editor policy
may offer an explicit disambiguation choice, but neither storage nor expansion may
guess one.

An inclusive end date includes a valid occurrence on that date. “After N” counts
the first generated date and counts scheduled slots, not replacement dates after
cancellation. Invalid monthly or yearly calendar dates are skipped, not moved to
the month's final day. The editor explains this for the 29th, 30th, 31st and leap
day.

## 6. Projection table

The occurrence table is a derived, disposable read model for recurring and
one-off events. Its minimum logical columns are:

| Column | Purpose |
|---|---|
| `id` | Internal row identity |
| `event_id` | Canonical `wpse_event` post |
| `public_key` | Stable occurrence route key |
| `recurrence_id` | Immutable original local slot/manual identity |
| `generation` | Rebuild generation for safe switching |
| `segment_id` | Definition segment that generated the row |
| `source` | `one_off`, `rule` or `manual` |
| `start_local`, `end_local` | Canonical wall-time boundaries |
| `start_utc`, `end_utc` | Comparable UTC indices |
| `timezone` | Captured timezone or WordPress fixed offset |
| `all_day` | All-day flag |
| `event_status` | Effective scheduled/postponed/cancelled status |

The table is indexed for event/generation/key uniqueness, event chronology,
public chronology and status/date filtering. It stores no body, password,
taxonomy copy, personal data or secret. Taxonomy filtering joins WordPress term
relationships at query time so canonical ownership cannot drift.

## 7. Safe generation and repair

Generation is always bounded by both a date horizon and a row cap. The engine
accepts at most 550 inclusive calendar days and 1,000 output rows per call. It also
stops after 100,000 internal calendar evaluations so an ancient series cannot
force unbounded catch-up work. Rule intervals are limited to 999, explicit end
counts to 10,000 scheduled slots and specific-date definitions to 1,000 unique
canonical dates. The production projection policy targets 18 future calendar
months inside these engine limits.

Output includes an occurrence that starts before the requested window when its
template-derived end date overlaps the first window date. Count termination counts
valid scheduled slots from the series seed, including slots before the requested
window. Deliberately skipped invalid month days and non-leap-year February 29 do
not consume that count. Exceeding a row or evaluation bound fails the complete
generation; output is never silently truncated.

Replacement follows this order:

1. allocate the next positive generation;
2. set the external dirty marker before mutating derived state;
3. generate and insert all rows under that inactive generation;
4. validate count, identity uniqueness and date ordering;
5. write recurring coverage bound to that generation;
6. switch the event's active-generation marker;
7. clear dirty only after two matching activation/coverage checks;
8. remove stale generations later in bounded cleanup.

Every projection row records an internal UTC creation timestamp for cleanup only.
A WordPress-Cron worker removes no more than 100 inactive rows per pass, and only
when the row is at least 24 hours old and its parent has no dirty projection
marker. The bounded delete repeats active-generation and dirty-state predicates
after selecting candidate IDs. It never repairs canonical state, changes the
active marker or runs unbounded work during a visitor request.

Failure before step 4 leaves the old generation active. A missing or stale
projection fails closed for the occurrence read path and is surfaced for repair;
it never expands an unbounded rule during a visitor request. Site Health and an
administrator-only maintenance action report and repair insufficient coverage.

Every successful recurring generation records its inclusive local projection
start and end plus their generation binding as protected derived metadata.
Recurring reads require that binding to match their active rows. Production
builds target today through 540 days later. Buffered background renewal begins
below 450 remaining days, while public occurrence readiness requires the window
to begin no later than today and extend at least 365 days beyond today. Missing
or malformed coverage fails closed. One-off projection and removal clear these
three recurrence-only values, and no visitor request performs a renewal.

Initial migration and administrator repair share one type-aware service. It must
decode protected recurrence state before choosing a projector: valid recurrence
uses the recurring projector, absent recurrence uses the one-off projector, and
corrupt non-empty recurrence remains dirty and invalid. Manual recurring repair
rebuilds the production window from the current WordPress local date through 540
calendar days later. One request inspects at most 25 public password-free events;
unresolved items remain fail-closed and are skipped by a bounded continuation
offset so they cannot block repair of later candidates.

A complete bounded generation may contain zero rows, for example when a yearly
rule has no occurrence inside a short requested window. The generation marker is
still activated so “complete and empty” is distinguishable from “never built”.

Every segment definition must generate its own effective template seed. Every
non-root anchor must also be an actual generated identity of the immediately
preceding segment. The selected seed maps to that immutable anchor; later slots
on or before the anchor's original calendar date are ignored so moving the seed
earlier cannot duplicate identities still owned by the preceding schedule.

Date overrides are normally reconciled while their immutable identity is inside
the generation window. A bounded secondary membership check also includes an
occurrence whose original identity lies outside the window but whose effective
override was moved into it. At most 25 such inbound moves are resolved per build;
the complete build fails rather than performing aggregate-sized repeated
catch-up. An inbound identity that no longer belongs to its segment also fails the
complete build. Broad-edit impact reconciliation must convert or remove such an
exception explicitly before save; it may never be discarded silently.

## 8. Editing scopes and exception reconciliation

The editor chooses scope before fields become editable. A persistent banner and
save-button label repeat the scope and affected date range.

- **Only this occurrence** writes or removes a sparse override.
- **This and following** creates or updates a segment anchored to the selected
  recurrence identity. It preserves every earlier segment, replaces an existing
  segment at that boundary when present and removes all later schedule segments.
  The root occurrence is edited through **complete series**, because it has no
  earlier history to preserve.
- **Complete series** updates shared content or the root schedule while retaining
  historical occurrence identity.

Past schedule rows are immutable under broad future schedule changes. Shared
series content may update historical pages unless a supported occurrence override
exists.

Before a broad schedule save, the server builds current and proposed occurrences
through the same bounded projection service and presents the exact added, removed,
moved, status-changed, source-changed and exception-affected dates. Comparison is
by immutable recurrence identity rather than effective date, so moving an event
cannot appear as an unrelated deletion plus addition. Individual changes are kept
by default. If the new rule no longer generates a modified occurrence, it becomes
a detached manual occurrence under its existing identity unless the editor
explicitly removes it. No exception is silently discarded.

Scope validation is server-owned. **Only this occurrence** may change exception
state for exactly the selected identity and cannot alter a schedule segment.
**This and following** requires a generated target and rejects changes to an
earlier segment, exception or generated output. **Complete series** may alter the
whole aggregate but still passes the same bounded reconciliation. A changed
exception outside the explicit preview horizon fails closed instead of being
omitted from the confirmation.

Every editor response includes a deterministic revision token for the exact
canonical aggregate used by the preview. Saving requires that token. The storage
adapter performs an atomic compare-and-replace against the same canonical JSON;
if another editor or revision restore changed the aggregate, the save is rejected
as stale and the user must reload and preview again. A conflict may leave the
derived projection marked dirty as a conservative repair guard, but it never
overwrites the newer canonical aggregate.

Editor transport is a three-step authenticated API: load context, preview one
complete proposal, then save that exact confirmed proposal. Each route rechecks
the event post type and mapped edit capability. Mutation input is schema-bounded
and then decoded by the exact aggregate codec; partial or unknown nested fields
cannot pass. Preview issues a server HMAC bound to event, current user, revision,
aggregate, scope, target and bounded window. Save re-runs impact validation before
checking the HMAC and compare-and-replace, so changing any confirmed input or
replaying it after a successful save fails closed.

For **this and following**, the browser does not construct or submit a complete
aggregate. It submits only the selected generated boundary, current revision,
exact bounded window and one strict replacement template plus recurrence
definition. The replacement contains no timezone: the server always reuses the
captured canonical series timezone. A dedicated authenticated preview route
validates that the boundary is an effective non-root generated occurrence,
constructs the replacement segment, removes later schedule segments, reconciles
all exceptions and returns the exact complete proposal. The ordinary confirmed
save route remains the sole write boundary and accepts only that returned proposal
with its scope, target, window, revision and server signature.

Before fields for **only this occurrence** are populated, the editor resolves the
selected immutable identity through a dedicated capability-checked read request.
That request carries the exact bounded window in which the user selected the
occurrence. Its response includes the current canonical revision, current
effective date/status/source, the inherited date/status/source with only this
identity's exceptions removed, all existing sparse override fields and any
separate cancellation action. A target absent from the supplied window is rejected
instead of being inferred from a date-like string.

For a moved occurrence, the inherited slot may fall outside the supplied window.
The server then performs one additional bounded lookup at the target's original
generated date or stored manual date. Detached generated identities in the manual
collection use their stored manual range. This makes “restore inherited value”
deterministic while preserving a stable identity and without expanding an entire
historic series. The read response is context only: all writes still submit a
complete aggregate to the existing preview-confirm-save workflow.

The initial Gutenberg **only this occurrence** controls cover the occurrence's
all-day state, start/end local date and time, status and reversible cancellation.
The captured series timezone is visible beside the date controls. Separate
inheritance actions remove the date-range or status override key; they do not copy
an inherited value into a redundant exception. Title, note, featured image, venue,
address, location URL, external event URL and external action label use the same
explicit ownership model. Their normalized server-owned inheritance snapshot is
separate from the exact sparse override map. Editing creates an individual key;
**Use series value** removes it. Empty venue, address and URL values hide
inheritance, while empty title, note and action-label overrides remain invalid.
Image ID zero hides the inherited image. All fields use the same complete proposal.
Changing the search period or selected identity discards loaded context and any
preview. Ordinary unsaved post changes block preview, and every occurrence change
must complete the same impact-preview and signed-save cycle as broader scopes.

The Gutenberg **this and following** workflow uses the same bounded occurrence
search but offers only non-root effective generated occurrences as boundaries.
Manual and detached occurrences remain available through **only this** and cannot
be misrepresented as rule anchors. After the server resolves the selected
identity, the form starts from its inherited generated date range and active
schedule definition. Any existing occurrence override remains an explicit
exception rather than being silently copied into the new schedule template.
Editors can choose every supported recurrence type and date/time field, see the
captured timezone and must acknowledge that every later schedule segment will be
replaced. Changing boundary or search period clears form and confirmation state.

After a custom-route save changes canonical recurrence, the application asks
WordPress to save a post revision explicitly. This is required because no normal
post update occurs on that route. A canonical change is revisioned even when its
derived projection subsequently needs repair; an unchanged save does not create
revision noise.

When recurrence is first enabled, the server revalidates the event's canonical
stored one-off date, status, timezone and series UID. It represents that source as
a one-date specific schedule for comparison; incomplete drafts, malformed
booleans and corrupt dates cannot initialize recurrence. Returning a recurring
series to **does not repeat** is a separate explicit operation that removes the
aggregate and rebuilds a one-off projection. The editor must choose one effective
occurrence that survives as the ordinary event. A dedicated preview binds that
immutable recurrence identity, the current aggregate revision, authenticated
user and exact bounded window into its own confirmation signature. It lists
removals inside the window and warns that all other dates outside it are removed
as well; recurrence removal may not be encoded as an empty rule.

The editor discovers survivor choices through one bounded window at a time. Its
initial window starts near the WordPress site's current date for an active series
and near the final bounded period for an already-ended rule or selected-date
schedule. If that window is empty, the editor retries the anchor period. Editors
can search another period by choosing an explicit start date. A changed search
date invalidates the loaded choice and any destructive preview; preview and save
always carry the exact window from which the selected identity was loaded.

Save rebuilds the choices and exact preview before marking projection dirty. It
prepares the survivor's effective local/UTC dates, all-day value, timezone and
status with compare-aware metadata writes, then compare-and-deletes the exact
current aggregate. Conflict or storage failure rolls back only values that still
equal this operation's prepared state. After deletion, the normal one-off
projector is authoritative. Projection failure keeps the one-off metadata and
dirty repair marker rather than restoring the obsolete series. A changed
conversion receives a WordPress revision; rejected operations do not.

The ordinary event keeps the canonical series title, content, excerpt, featured
image, taxonomies, location and external action when recurrence is removed.
Occurrence-specific presentation fields, including its note, are not promoted to
the series post and are removed with the aggregate. The destructive editor preview
must state this before confirmation.

Cancellation remains reversible and keeps the occurrence identity and leaf URL.
Permanent deletion is an advanced action. Trashing the series hides all of its
occurrences immediately; restoring the series restores eligibility. Permanent
series deletion removes its projection and canonical recurrence definition under
the existing uninstall/data-ownership rules.

## 9. Public queries and presentation

All chronological plugin collections eventually query occurrences, then join to
eligible series posts. Pagination counts occurrences according to the component's
display mode, not posts. Lists default to one next occurrence per series and may
opt into all occurrences. Calendars return every eligible occurrence inside the
strict request window. Search remains one result per series.

Recurring routes use:

```text
/events/{series-slug}/
/events/{series-slug}/occurrence/{public-key}/
```

Before a leaf adapter renders, one shared request-local resolver looks up the
exact event ID and lowercase public key against only the active occurrence
generation. The bounded lookup also rechecks the published, password-free parent
and treats duplicate rows as corruption. The resolver then proves the key again
from the protected aggregate's series UID and projected recurrence identity.
Negative and positive results are cached per exact identity for the request so
multiple blocks or builder widgets cannot perform divergent reads.

All leaf adapters consume the resulting named presentation context. Effective
date and status come only from the active projection; sparse title, note, image,
location and external-action overrides inherit from the normalized series when
absent. Body, excerpt and taxonomies remain series-owned. Adapters must use
WordPress media APIs for image output and hide an unavailable attachment rather
than exposing a raw occurrence attachment identifier.

One-off event permalinks remain unchanged. A moved occurrence keeps its key and
may expose `previousStartDate`; a cancelled occurrence remains directly readable
with an explicit cancelled presentation while normal upcoming collections omit
it by default.

Each public leaf receives one occurrence-aware canonical, title, schema document,
cache context and bounded sitemap entry. SEO-plugin integration must not collapse
leaf canonicals to the series root. Related-event output treats a series as one
related event.

The first native adapter builds one shared effective presentation for the bundled
PHP and block-theme fallback, core document title, core canonical and plugin Event
JSON-LD. Occurrence notes remain bounded escaped plain text; body filtering is
series-only. Featured-image IDs are never printed and must resolve through a
WordPress attachment API. The native fallback remains authoritative whenever no
builder template handles the current single location.

On that native fallback, an exact occurrence leaf identifies its canonical
series and offers previous/next active occurrence links when available. The
neighbours come from two bounded active-projection reads and never from expanding
a recurrence rule on the visitor request. Builder-owned single locations remain
unchanged; they may compose their own navigation.

The Gutenberg adapter uses the same request-local current-presentation resolver
for the existing atomic blocks and composite Event Details block. A block with
current event context renders the exact occurrence leaf; the existing positive
`eventId` attribute remains an explicit public series source. Blocks inside a
Query Loop for a different event do not inherit the outer occurrence. Invalid
occurrence canonicals produce no block output instead of falling back to series
dates or sparse fields.

The existing twelve Elementor atomic widgets and composite Event Details widget
use that same boundary, including when Elementor reconstructs widget objects.
Empty source means current occurrence context; the existing selected event ID
remains a public series source. Once this parity is active, a matching Elementor
Pro single template may own the occurrence leaf instead of being bypassed.

The native event and event-taxonomy archives use the occurrence projection as
their authoritative collection whenever public occurrence routing is enabled and
the read index is healthy. The main `WP_Query` remains only a WordPress routing
and template shell: a pre-query adapter supplies public parent objects without
deduplicating repeated series IDs and writes the occurrence total and page count
back to Core. The bundled archive renderer consumes the exact request-local
occurrence page, not the shell posts. Fixed taxonomy routes are translated into
the same canonical category or tag criteria as lists and feeds. An empty first
page remains a valid empty archive; a paged request without occurrence rows keeps
Core's normal 404 handling. A parent visibility race or corrupt projection
invalidates the complete page rather than exposing stale series dates.

## 10. REST, shortcodes and builders

The existing `wpse/v1` event feed remains backward compatible. Occurrence
collections use a versioned endpoint rather than changing the meaning of an
existing event ID in place. Every endpoint has explicit schemas, permission
callbacks, public-parent eligibility checks and date/result bounds.

The exact read-only leaf is
`wpse/v2/events/{event_id}/occurrences/{occurrence_key}`. It is registered with
the public recurrence read path.
It returns one bounded effective presentation and never exposes the protected
aggregate, recurrence identity, projection generation, segment identifiers,
internal metadata or raw post content. Every missing, ineligible, dirty, stale,
ambiguous or corrupt target has the same not-found response.

When the development route is active, the documented canonical filters from
Yoast SEO, Rank Math and AIOSEO receive the same exact occurrence URL as core
WordPress and the plugin's JSON-LD. The adapters remain inert on ordinary pages
and require none of those optional plugins to be installed.

WordPress Core's sitemap index receives an `occurrences`
provider. It reads at most 100 already-projected active recurring rows per page,
then repeats exact public presentation and canonical validation before emitting
an HTTP(S) leaf URL. It never expands a recurrence rule while serving a sitemap,
never includes one-off projection rows and exposes no protected recurrence
identity or aggregate data. Optional `lastmod` is omitted until canonical
recurrence changes have a reliable public modification timestamp. SEO products
that replace the Core sitemap require separate qualified adapters.

Because an occurrence key intentionally survives date and content changes, a
validated browser leaf is never admitted to full-page cache by the plugin. It
sends WordPress' standard no-store/no-cache headers, defines the de facto
`DONOTCACHEPAGE` interoperability constant and calls LiteSpeed Cache's documented
`litespeed_control_set_nocache` action. Ordinary event pages and collection
surfaces retain their existing cache behaviour. Invalid leaves already use Core
no-cache headers with their generic 404. A host or CDN that deliberately ignores
origin no-store rules remains an infrastructure concern rather than a cache state
the plugin can safely purge.

The schema upgrade schedules one late soft rewrite flush using the current
validated event archive slug. WordPress therefore learns the occurrence leaf on
ordinary plugin update as well as first activation; administrators do not need to
resave Permalinks. The marker is written only after successful installation and
is deleted after the one-shot flush.

WordPress Core remains the sitemap implementation covered by the 0.4.0 occurrence
read contract. Yoast exposes custom sitemap registration and index APIs, Rank
Math exposes a different custom sitemap surface, and AIOSEO's documented
additional-pages filter depends on a site option being enabled. These materially
different integration boundaries require installed-plugin compatibility work
rather than one speculative generic hook. Their absence is not a correctness or
privacy failure: exact occurrence canonicals remain present in server-rendered
public collections and all three products receive the validated leaf canonical.

Existing shortcode, Gutenberg block and Elementor widget identifiers remain
valid. Collection components add an allowlisted `next` or `all` occurrence mode.
Atomic fields resolve the current occurrence on a leaf page. The existing
explicit event identifier continues to mean series summary; a future additional
context selector may offer current, next or a specific occurrence without
overloading that stable identifier. Ordinary static-page defaults remain a later
product decision until those occurrence contexts are implemented.

The ordinary Event details metabox never acts as a second recurrence editor. As
soon as protected recurrence state exists, it replaces its one-off date, time,
all-day and timezone controls with an explicit ownership notice directing the
editor to the **Repeating event** document panel. Those ordinary metadata values
are bootstrap history and may be stale after a series change, so they are not
shown as reference dates. Event status remains editable as the inherited status
for the complete series; venue, address and external actions remain normal shared
series fields. A namespaced editor event applies the same state immediately after
recurrence is enabled, while server persistence remains authoritative without
JavaScript. Corrupt non-empty recurrence state fails safe as recurrence-owned.

## 11. Accessibility and UX acceptance

- Recurrence controls use native inputs where practical and follow site locale
  and WordPress' first-day-of-week setting.
- A human-readable schedule summary and computed date preview are available
  before publication.
- Dialogs trap and restore focus, support Escape where safe and initially focus
  the least destructive action for irreversible choices.
- Status is conveyed through text in addition to color and iconography.
- Preview changes are announced through a polite live region without moving
  keyboard focus.
- Validation names the affected field and never silently normalizes another date.

## 12. Compatibility and rollout

The occurrence table and one-off projection ship before recurring controls are
enabled. Existing events are indexed in bounded, resumable batches. Until parity
checks succeed, production renderers continue to use the established post-meta
queries. The read-side switch is a separately tested migration step with a safe
fallback and repair path.

The release must prove one-off parity, deterministic DST behaviour, migration and
interruption recovery, exact pagination, taxonomy filtering, Elementor Free/Pro,
Gutenberg, canonical/schema consistency, uninstall cleanup and realistic query
budgets. No release may depend on WP-Cron having run immediately after update.
