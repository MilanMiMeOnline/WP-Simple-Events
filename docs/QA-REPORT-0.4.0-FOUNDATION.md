# QA report — 0.4.0 recurring-events release qualification

**Status:** complete — source, packaged, production Elementor Pro and official CI
qualification passed
**Reviewed:** 25 August 2026

## Scope

This increment accepts ADR-044 and establishes the safe storage boundary needed
before recurring events can be exposed:

- immutable occurrence identities and stable public keys;
- one rebuildable per-site occurrence projection table;
- generation-based replacement with the active marker switched last;
- immediate one-off projection after validated native or REST persistence;
- bounded background indexing of existing dated events;
- repair markers for invalid or failed projections;
- permanent-event and explicit-uninstall cleanup;
- dependency-free bounded expansion for daily, weekly, both monthly modes,
  yearly and specific-date schedules;
- a bounded occurrence-level read repository with exact count queries, canonical
  taxonomy joins and stable chronological tie-breakers;
- a request-scoped readiness gate covering schema, physical table, migration and
  per-event projection health;
- a strict canonical schedule codec for every qualified rule and specific-date
  engine variant, without exposing a writable metadata or REST path;
- a complete version-one recurrence aggregate for chronological schedule
  segments, manual additions, reversible exclusions and sparse overrides;
- an exact fail-closed aggregate codec with bounded nested collections and stable
  canonical round trips;
- a protected revision-enabled canonical JSON envelope, capped at 2 MiB and
  excluded from core REST;
- capability-, event-, identity- and timezone-checked aggregate replacement with
  a dirty-before-write projection guard;
- occurrence invalidation after WordPress restores any event revision;
- complete recurring projection across segments, generated exceptions, manual
  additions and effective-date windows;
- canonical-first save coordination with clean no-op, dirty retry and specific
  fail-closed projection outcomes;
- a shared pure occurrence builder plus scope-validated, immutable-identity impact
  previews for only-this, this-and-following and complete-series proposals;
- deterministic canonical snapshot revisions and atomic compare-and-replace for
  future editor saves;
- strict one-off metadata bootstrap into a one-date comparison aggregate;
- dedicated capability-checked recurrence context, preview and save routes with
  exact request boundaries, bounded serialization and server-signed confirmation;
- explicit conversion from recurrence to one selected one-off occurrence through
  a separate destructive preview, signed confirmation and compare-aware save;
- bounded survivor discovery near current or ended schedules, with an explicit
  searchable period and exact loaded-window binding;
- a capability-checked occurrence edit-context that resolves current, inherited,
  sparse-override and cancellation state for one selected immutable identity;
- a server-owned this-and-following proposal route that accepts one strict
  replacement schedule, reuses canonical timezone, reconciles exceptions and
  returns the exact complete aggregate for the existing confirmed save route;
- a scope-first Gutenberg this-and-following workflow with generated non-root
  boundaries, inherited schedule initialization, complete pattern/date controls
  and an explicit future-segment replacement warning;
- a normalized, server-owned inherited-field snapshot for title, note, featured
  image, venue, address, location URL, external event URL and action label;
- explicit only-this ownership controls for every supported sparse presentation
  field, including deliberate hides and reversible series inheritance;
- an exact bounded active-occurrence lookup by event ID and stable public key,
  with parent visibility, active-generation and duplicate-row enforcement;
- one shared occurrence-presentation resolver that proves the public
  key against canonical series identity, caches exact request-local results and
  overlays only its sparse fields;
- a public virtual occurrence route with strict query identity,
  pretty/plain canonical URL construction, non-cacheable 404 handling and parent-
  redirect suppression;
- recurrence-aware ordinary persistence that preserves aggregate-owned schedule
  fields, never invokes the one-off projector and dirties changed inherited status;
- occurrence reads that exclude every parent carrying a derived-index dirty marker;
- one shared current-presentation resolver that switches Gutenberg current
  context to the exact occurrence while preserving explicit series selections;
- occurrence-aware output for the existing twelve atomic Gutenberg blocks and
  the composite Event Details block;
- occurrence-aware output for the existing twelve atomic Elementor widgets,
  composite Event Details widget and its shared current-details shortcode path;
- restored Elementor Pro single-location precedence after occurrence widget
  parity, with exact native occurrence output retained as fallback;
- one exact, read-only `wpse/v2` occurrence resource using
  the same eligibility, effective presentation and canonical URL as the leaf;
- Yoast SEO, Rank Math and AIOSEO canonical adapters using the
  same already-resolved occurrence route without optional-plugin dependencies;
- a WordPress Core occurrence sitemap provider that reads only
  finite active recurring projection coverage, caps pages at 100 candidates and
  repeats exact public identity and canonical validation before discovery;
- a no-store/no-cache policy for validated browser leaves, strengthened by the
  de facto `DONOTCACHEPAGE` boundary and LiteSpeed's documented no-cache action;
- a schema-versioned internal row-creation timestamp plus guarded WordPress-Cron
  cleanup of at most 100 old inactive rows per pass;
- administrator-visible occurrence-index health in the event settings and
  WordPress Site Health, without exposing event identifiers or content;
- a capability- and nonce-protected repair action that processes at most 25
  public events per request and continues through unresolved failures without
  letting one corrupt event block later candidates;
- type-aware migration and repair that rebuilds canonical recurring aggregates
  across the production window instead of reducing them to one-off rows;
- protected inclusive coverage metadata for every active recurring generation,
  bound to its generation and cleared by one-off writes and projection removal;
- a central 540-day fresh, 450-day renewal and 365-day minimum-public window
  policy using WordPress-local calendar dates;
- a recurring-only WordPress-Cron renewal worker bounded to 25 public,
  password-free candidates per pass with unresolved-candidate continuation;
- one shared fail-closed occurrence collection presenter that preserves repeated
  series IDs, exact totals, sparse overrides and one canonical URL contract;
- occurrence adapters for event-list shortcodes, calendar
  fallbacks, the `wpse/v1` interactive calendar feed and native event/taxonomy
  archives, while an injected disabled decision retains the legacy one-off path
  for deterministic parity tests;
- an occurrence-backed native archive templateshell that preserves repeated
  parent IDs, exact totals and Core paged-empty 404 behaviour without serializing
  domain criteria into WordPress query variables;
- explicit native-editor schedule ownership that replaces stale ordinary date,
  time, all-day and timezone controls for recurring series, while keeping series
  status and shared event fields editable.

The migration worker processes at most 25 candidates, schedules continuation
through one-off WP-Cron events and never expands recurrence on a visitor request.
WP-Cron completion is not allowed to produce partial output: public occurrence
reads remain unavailable until schema, migration and per-event readiness are
healthy.

## Senior developer review

- Canonical posts and metadata remain the source of truth.
- The table stores no body, password, taxonomy copy, secret or personal data.
- Series UID allocation uses WordPress' UUID generator and an atomic unique-meta
  add so concurrent requests converge on one identity.
- Random positive generation tokens and delayed stale-row cleanup avoid generation
  collisions and prevent one request from deleting another in-progress build.
- A complete generation is inserted before the active metadata marker changes.
- Failed insert or activation removes the incomplete generation and marks repair.
- Direct database access is confined to documented plugin-owned derived-table
  adapters and uses WordPress insert/delete methods or strictly typed prepared
  query contracts.
- Table installation verifies the exact table before committing schema state.
- Deactivation preserves data but clears the plugin's pending migration and stale-
  generation maintenance hooks.
- Health checks perform one bounded coverage probe, distinguish unavailable,
  building, repair-needed and healthy states, and report only aggregate status.
- Manual repair repeats administrator capability and nonce validation, limits
  each request to 25 candidates and carries only bounded counters in redirects.
- Repair dispatches by canonical aggregate type. Corrupt non-empty recurrence
  state remains dirty and invalid; it is never silently projected as a one-off.
- Recurring repair derives a site-local current-date-to-540-day window on the
  server, while one-off repair continues through the established exact projector.
- Recurring activation persists and verifies exact coverage boundaries before
  clearing the dirty marker. It rechecks their generation binding after activation;
  partial, concurrent or mismatched writes remain dirty and SQL reads fail closed.
- Public coverage queries treat missing, future-starting and shorter-than-365-day
  recurring windows as gaps, while buffered renewal begins below 450 days.
- The renewal worker never expands on a visitor request, never selects one-off,
  private or password-protected events and renews at most 25 candidates per pass.
- Collection presentation consumes already-authorized projection rows directly,
  avoiding one exact-row SQL lookup per occurrence while repeating canonical
  parent, aggregate and public-key validation before output.
- One-off and recurring rows share the same presentation shape, but only recurring
  rows receive virtual leaf URLs. Repeated parent IDs keep distinct card DOM IDs
  and FullCalendar IDs through their stable public occurrence keys.
- A corrupt or unpresentable row rejects the complete requested page. The
  interactive feed returns a generic 503 and the accessible fallbacks expose an
  empty state rather than mixing stale series dates with occurrence totals.
- Native archive SQL is short-circuited only after the feature/readiness gates.
  The shell rechecks every parent as published and password-free, preserves one
  post object per occurrence, and leaves effective cards and pagination to the
  stored occurrence page. Fixed taxonomy routes become canonical occurrence term
  criteria rather than escaping their route scope.
- Normal upcoming occurrence queries exclude cancelled rows, while exact leaves
  and bounded calendar windows retain them for explicit cancellation display.
- Destructive uninstall drops the table only after the existing explicit opt-in;
  any failure retains settings and capabilities as recovery evidence.
- The recurrence engine is pure domain code and receives only validated date
  ranges, validated rule objects and an explicit bounded window.
- Expansion preserves local wall-clock fields and calendar-day spans. It neither
  converts rules through UTC nor silently normalizes invalid calendar values.
- Generation is bounded by a 550-day horizon, 1,000 output rows and 100,000
  internal calendar evaluations; row or evaluation exhaustion fails completely.
- Occurrence SQL is generated only from validated criteria and strictly validated
  WordPress table identifiers; all external values remain prepared placeholders.
- Ordinary WordPress persistence recognizes every non-empty protected recurrence
  aggregate before touching schedule metadata. It updates only series-owned event
  fields, never invokes the one-off projector and records changed inherited status
  as dirty derived state.
- Every read rechecks the parent post type, published state and empty password and
  matches only the generation currently selected in canonical post metadata.
  Parents with any dirty-index marker are excluded even when an older active
  generation still exists.
- Taxonomy filters query canonical WordPress relationships at read time and use OR
  within one taxonomy plus AND between categories and tags.
- Result rows are reconstructed through the central date primitive; corrupt local,
  timezone, enum, identity or UTC data disables the occurrence route.
- Exact result and count queries share their predicates, and multiple occurrences
  from one series remain separate pagination units.
- Schedule decoding requires exact keyed variants and strict scalar types, then
  delegates every calendar and bound check to the existing domain factories.
  Unknown fields, raw RRULE input and partial future schemas fail completely.
- The complete aggregate owns one canonical series identity and timezone. It
  rejects duplicate or reverse segment anchors, timezone drift, orphaned manual
  overrides, duplicate identities and skipped occurrences with public overrides.
- Sparse overrides expose only the accepted inheritance fields. Text, attachment
  IDs, statuses, date ranges and HTTP(S) URLs are validated through exact bounded
  domain contracts; unknown fields never survive a decode.
- Aggregate parsing is deliberately shape-only and bounded. Generated exception
  membership is reconciled later against bounded engine output rather than
  triggering schedule expansion at a metadata decode boundary.
- The WordPress storage adapter distinguishes missing one-off state from corrupt
  non-empty state and verifies the canonical value after an atomic metadata
  replacement. A failed write preserves the previous value.
- Authorized aggregate replacement cannot target another post type or series and
  cannot reinterpret an event in a different timezone. Guard and storage failures
  produce stable non-sensitive error codes and retain repair evidence.
- Revision restoration mutates only the derived health marker. WordPress remains
  responsible for authorization and restoration of canonical revision metadata.
- A complete recurring build activates only after all generated and manual rows
  reconcile. A legitimate empty window still receives an active generation.
- Every effective segment seed is generated by its own rule and every non-root
  anchor is proven against the preceding segment before output can activate.
- Earlier moved segment seeds cannot reclaim identities on or before their
  original anchor date; duplicate identities across segments reject the build.
- Generated date overrides moved into the effective window receive a separate
  bounded membership check. The path is capped at 25 inbound moves and rejects
  orphaned identities instead of dropping them.
- Save coordination derives series status from canonical event metadata, retries
  an unchanged dirty aggregate and retains both canonical-change evidence and the
  dirty guard when derived projection fails.
- Impact previews reuse the persistence projection algorithm instead of a second
  approximation. Scope leakage fails before a write, and detached modified slots
  retain their generated identity and stable public key while changing source.
- Editor concurrency tokens are context-bound hashes of canonical state rather
  than secrets. Existing metadata uses a previous-value compare and first-time
  recurrence uses a unique insert, preventing stale editors from overwriting a
  newer definition between preview and save.
- First-time recurrence reuses the shared event validator and refuses incomplete
  or corrupt canonical metadata instead of letting an editor client infer dates,
  booleans, identity or timezone.
- Recurrence editor routes recheck post type and mapped edit permission, keep the
  protected aggregate out of core REST and parse every proposed complete shape
  through the exact codec. A confirmation binds editor, event, revision, scope,
  target, aggregate and window and is revalidated before compare-and-replace.
- Stored and proposed occurrence override strings must already equal their
  WordPress text, textarea or HTTP(S) URL sanitizer result. The editor fails
  closed instead of returning or signing content that would later normalize.
- Canonical recurrence changes explicitly enter WordPress revision history even
  when projection subsequently needs repair; invalid, stale and unchanged saves
  create no misleading revision entry.
- The Gutenberg complete-series panel normalizes localized numeric settings,
  follows the WordPress week start and preserves last-weekday monthly rules
  rather than silently converting them to a numbered weekday.
- Disabling recurrence never accepts an empty rule. The selected effective
  occurrence is revalidated inside the signed bounded window, ordinary metadata
  is prepared compare-aware and the exact aggregate is compare-and-deleted.
- Survivor search defaults use the WordPress site-local date, retain a bounded
  540-day/1,000-row contract, recover to the anchor when an initial finite window
  is empty, and clear stale choices and previews whenever the search date changes.
- Occurrence edit-context rejects targets absent from their loaded bounded window,
  removes only the selected identity's exceptions to derive inheritance and uses
  one bounded original-date fallback for moved generated, manual or detached
  occurrences. It remains a read boundary; preview, confirmation and atomic save
  are not duplicated.
- The only-this Gutenberg workflow does not trust occurrence labels or native
  controls as authority. It reloads one selected identity through the exact
  authorized window, validates canonical date/time/status values, preserves
  unexposed supported overrides and submits one complete scope-bound aggregate.
- Date and status inheritance actions remove sparse keys, cancellation is a
  separate reversible exclusion, dirty ordinary post state blocks preview and a
  post-save context refresh failure cannot be reported as a failed committed save.
- The this-and-following browser boundary cannot rewrite the canonical aggregate.
  It supplies only a generated target, revision, bounded window and exact
  replacement schedule; the server owns segment mutation, canonical timezone and
  lossless exception reconciliation before signing one complete proposal.
- The following preview reuses the established generic save coordinator rather
  than adding a second write path. Capability checks, exact decoding, impact
  rebuilding, HMAC binding, optimistic comparison, revision history and derived
  projection therefore retain one security and concurrency contract.
- Gutenberg filters following boundaries by effective source and root identity
  before presentation, while the server repeats authoritative generated-membership
  validation. The form begins from inherited generated state so an individual
  date override cannot silently become the future series pattern.
- A recurring event's native metabox no longer presents bootstrap dates as the
  current series schedule. Non-empty or corrupt protected recurrence state fails
  safe, Gutenberg mirrors no schedule values while owned, classic submissions
  retain validator-safe hidden canonical values and the ordinary series status
  remains independently editable.
- The following UI never stores or reconstructs canonical collections. Every
  boundary and field change invalidates preview state, ordinary dirty post state
  blocks preview and only the exact returned proposal reaches confirmed save.
- Occurrence presentation inheritance is resolved from the canonical event post,
  normalized through the existing event sanitizers and serialized as named domain
  fields rather than metadata keys. The browser never becomes the authority for
  inherited content.
- Only-this title, note, image, venue, address and external-action changes remain
  sparse aggregate overrides. Explicit inheritance removes the key, deliberate
  empty location/action values hide the series value and the complete aggregate
  codec remains the authoritative validation and persistence boundary.
- WordPress' native media capability UI is used for featured images and its assets
  are enqueued only on event block-editor requests. No custom upload endpoint or
  second post save is introduced.
- Recurrence removal keeps the canonical series post and promotes only the chosen
  occurrence's date, timezone and status. Its destructive preview now explicitly
  states that occurrence-only presentation values are removed.
- Exact public-key lookup requests at most two rows so duplicate active identities
  cannot be hidden by a single-row limit. Repository mapping repeats the requested
  event/key check after strict row reconstruction.
- The occurrence-presentation resolver requires both the eligible active row and
  WordPress' published, password-free series context, then derives the public key
  again from canonical series UID plus recurrence identity. Missing, one-off,
  corrupt, mismatched and noncanonical states return no public context.
- Projection date/status remain authoritative effective values. Sparse title,
  note, image, location and action values overlay normalized series inheritance;
  body, excerpt and taxonomies remain series-owned.
- The public native fallback, core document title, core canonical and
  plugin Event JSON-LD consume one effective presentation. Invalid canonicals fail
  closed, notes remain escaped plain text and images resolve only through the
  WordPress attachment API. A matching Elementor Theme Builder location may own
  the leaf now that its event widgets consume the same exact context.
- Gutenberg blocks do not resolve recurrence themselves. The shared current-
  presentation boundary accepts only a route context already validated against
  public parent, active projection and canonical aggregate state.
- Current block context switches only when its event ID matches both the resolved
  series and occurrence IDs. Explicit positive event IDs keep the established
  published, password-free series rule; another Query Loop event remains isolated.
- If an exact occurrence cannot produce a safe canonical presentation, Gutenberg
  output fails closed instead of leaking or showing inconsistent series fields.
- Elementor widget reconstruction receives the configured current resolver and
  complete-details adapter through one request-local runtime service set. It does
  not create a second route or occurrence lookup contract.
- Elementor current sources and explicit selected series follow the same strict
  split as Gutenberg. Theme Builder may own the single location only after those
  widget adapters can consume the exact current occurrence.
- The occurrence REST leaf repeats event-ID and public-key equality after the
  shared provider, returns one generic 404 for every absent or ineligible target
  and never exposes editor access merely because a user is authenticated.
- Its bounded version-one serializer omits recurrence identities, generations,
  segments, canonical aggregate JSON, raw post content and metadata keys. Missing
  attachment resources and incomplete external actions serialize as `null`.
- Third-party canonical adapters never read request input or resolve another
  occurrence. They preserve each host value on ordinary/invalid contexts,
  including Yoast's supported `false`, and accept only strict HTTP(S) output.
- Core sitemap queries exclude one-off projection rows, repeat published,
  password-free, active-generation and clean-index predicates and cannot exceed
  100 candidates even when WordPress' filterable maximum is larger. Each
  candidate must still pass the shared exact canonical aggregate resolver.
- Sitemap requests never expand a recurrence definition. Infinite schedules are
  represented only by the finite projection coverage already built outside the
  visitor request, and optional `lastmod` is omitted rather than guessed from an
  unrelated timestamp.
- Valid occurrence browser leaves call WordPress' native no-cache header API only
  after shared exact resolution. Ordinary event pages and collections keep their
  existing cache policy, while invalid leaves retain their existing no-cache 404.
- Inactive-generation maintenance first selects at most 100 old row IDs, then
  repeats age, active-generation and dirty-parent predicates during deletion.
  Active rows and any rows owned by a parent under repair remain untouched.

## Senior QA review

Automated coverage includes:

- UUID, recurrence-slot and public-key validation;
- different-slot identity separation and move-stable identity;
- occurrence row completeness and generation bounds;
- dbDelta schema keys and identifier-injection rejection;
- cleanup SQL bounds, duplicate/invalid candidate rejection, schema-gated cron
  scheduling, dirty-parent protection and active-generation preservation;
- installer success/failure and migration-reset behaviour;
- successful, incomplete and failed one-off projection;
- strict legacy canonical validation and dirty markers;
- 25-row batch bounds and continuation scheduling;
- empty incomplete-draft exclusion from repeated migration selection;
- WordPress boolean option normalization;
- permanent event cleanup and unrelated-post isolation;
- table cleanup failure retention;
- production WordPress smoke assertion that a valid REST save activates exactly
  one occurrence row under its saved generation;
- production WordPress smoke assertion that a private recurring aggregate stores,
  projects skip/move/cancel/manual state and treats an identical second save as a
  generation-preserving no-op before fixture deletion;
- daily wall-clock continuity across DST offset changes;
- fail-closed nonexistent and ambiguous local DST times;
- weekly weekday/count and multiweek anchoring;
- inclusive end dates and counts exhausted before the requested window;
- month-end skipping, fifth/last weekday and multimonth anchoring;
- leap-day skipping without consuming a valid-occurrence count;
- sorted specific dates, cross-midnight ranges and overlapping window starts;
- row, horizon, definition and internal-evaluation limits.
- strict occurrence table-name validation and placeholder ordering;
- upcoming/past boundaries, stable tie ordering and local calendar overlap;
- exact occurrence-level totals without duplicate-series collapse;
- invalid projection timestamps and inconsistent gateway totals failing closed;
- readiness denial for missing schema, table, migration or healthy coverage;
- a bounded public coverage probe for missing generations and dirty indexes;
- real WordPress parity for first/second pages, exact totals, category+tag filters,
  calendar overlap, active generations and draft/password exclusion.
- stable encode/decode round trips across every recurrence frequency, both monthly
  modes, all termination variants and specific dates;
- rejection of unknown fields, unsupported types, weak numeric coercion, malformed
  termination values and unknown recurrence-definition implementations.
- complete aggregate acceptance across multiple segments, manual additions,
  cancellation and moved/status overrides;
- rejection of noncanonical identities, reverse or duplicate anchors, timezone
  drift, orphaned manual overrides and skip/override conflicts;
- exact aggregate round trips and rejection of unknown root/nested fields,
  non-list collections, weak scalar values, invalid actions/statuses and oversized
  collections.
- stable canonical JSON ordering, invalid/oversized JSON rejection and direct-meta
  sanitization to an empty fail-closed value;
- missing, successful, failed and corrupt WordPress aggregate storage;
- forbidden, wrong-post, identity/timezone mismatch, unchanged, dirty-guard
  failure and storage-failure persistence paths;
- event revision invalidation and non-event isolation;
- complete daily projection with skip, cancel, moved/status override and manual
  addition reconciliation;
- chronological segment hand-off, moved seed identity protection and orphaned
  segment-anchor rejection;
- non-root this-and-following splits, existing-boundary replacement, monotonic
  segment IDs, exact generated membership and invalid root/rule-gap rejection;
- lossless future-exception reconciliation, including moved/status overrides,
  prior-exception isolation and reversible detached cancellations and skips;
- complete empty windows, orphaned generated exceptions and moved-in date
  override membership;
- changed, unchanged-clean, unchanged-dirty, failed-storage and failed-projection
  save coordination.
- empty and series-wide impact, only-this move/status/cancellation, valid following
  segments, illegal prior edits and detached generated-identity retention.
- deterministic one-off/aggregate revisions, malformed tokens, current writes,
  stale preflight rejection and an intervening compare-and-replace race.
- timed and inclusive all-day one-off bootstrap plus malformed-state and wrong-
  post-type rejection.
- recurrence editor authorization, exact aggregate/identity/date/revision request
  validation, bounded response serialization, signed confirmation and stale
  revision rejection;
- explicit recurrence-disable survivor selection, destructive confirmation,
  compare-and-delete, conflict rollback and repairable one-off projection failure;
- custom-route post-revision creation for successful and projection-repair
  canonical changes, with no-op and rejected-save isolation;
- a real authenticated WordPress REST context-preview-save journey, including
  anonymous denial, one-off bootstrap, exact two-occurrence impact and replay
  rejection after the canonical revision changes.
- a real Gutenberg journey that selects a daily three-event schedule, previews
  the exact two additions, applies it, then chooses one surviving occurrence and
  confirms conversion back to a one-off event;
- bounded survivor-search UX for an empty distant period, recovery to a valid
  period, canonical ISO-date validation and old/ended schedule defaults;
- canonical and noncanonical override input across plain text, multiline text
  and URLs, including application-service rejection before impact preview;
- current/inherited resolution for moved and cancelled targets, moved detached
  manual sources, missing targets and exact sparse override serialization;
- lossless only-this proposal construction that preserves unrelated identities and
  untouched sparse fields, restores inheritance by key removal, keeps cancellation
  separate and rejects stale or unsupported client state;
- real authenticated and anonymous REST checks for the occurrence edit-context,
  including moved date/status inheritance, signed only-this preview, confirmed
  save, stable-identity reload and revision output;
- a real Gutenberg only-this journey that selects a bounded occurrence, moves and
  restores its date range, changes and restores status, cancels and restores it,
  verifies each impact preview and returns to complete-series editing;
- exact this-and-following replacement decoding, including unknown-key, weak-
  boolean and client-timezone rejection;
- authorized following preview construction, non-root generated-target
  enforcement, stale-revision rejection, future-exception detachment and generic
  confirmed-save compatibility;
- a real authenticated WordPress following journey covering anonymous denial,
  earlier-override preservation, future-cancellation detachment, exact proposal
  persistence and replay rejection after the canonical revision changes;
- generated-boundary filtering and narrow following-request browser utilities,
  including root/manual rejection and proof that aggregate/timezone are absent;
- a real Gutenberg scope journey that configures this-and-following, reviews one
  added and one removed occurrence, applies the replacement and continues safely
  into explicit recurrence removal;
- normalized inherited occurrence fields from unsafe canonical post/meta input,
  wrong-post rejection and exact bounded context serialization without metadata
  keys;
- browser ownership state, sparse mutations and client-side limit/URL validation
  for every only-this title, note, image, venue, address and external-action field;
- a real Gutenberg only-this journey that writes and saves individual content,
  location and external-action values, then removes every sparse field through
  explicit **Use series value** actions before continuing the scope journey;
- exact occurrence query plans, malformed identities, empty results, valid
  identity matches and duplicate/mismatched projection rows;
- occurrence presentation inheritance, deliberate empty/zero masks, canonical
  public-key derivation and fail-closed malformed or nonrecurring context;
- native occurrence title/date/status/note/image/location output, core title and
  canonical isolation, and occurrence-specific Event JSON-LD;
- atomic Gutenberg current-occurrence title/date/status/location fields, series-
  owned content/terms, explicit-series isolation and image-hide overrides;
- composite Gutenberg Event Details occurrence title/note/location output with
  explicit-series isolation;
- Elementor atomic and composite occurrence output, explicit-series isolation,
  reconstructed-widget service reuse and Theme Builder location precedence;
- strict REST leaf identifiers, exact provider matching, generic missing/mismatch
  responses, bounded effective serialization and protected-field omission;
- exact/preserved/invalid third-party canonical behaviour and gated registration
  for the documented Yoast SEO, Rank Math and AIOSEO filters;
- recurring-only sitemap SQL, exact matching counts, hard page-size limits,
  malformed page/subtype denial, Core registration and fail-closed canonical
  output;
- valid-leaf no-cache policy, ordinary-request isolation and default-public
  composition-root registration;
- real WordPress proof that an exact active one-off public key returns the same
  projected identity without issuing an unbounded or count query, selecting the
  target from the complete bounded calendar window rather than assuming it is on
  chronological page one;
- real WordPress proof that an ordinary publish operation on a recurring event
  preserves its recurring projection and exact occurrence route, while an unknown
  key remains a non-redirecting 404;
- transient smoke-site authentication recovery through a bounded clean re-login,
  without logging credentials, cookies or nonce values.
- health-state classification for unavailable schema, active migration, missing
  or dirty public projections and complete healthy coverage;
- Site Health registration and good/recommended/critical output without event
  details, plus the settings-page repair affordance;
- repair-window construction, recurring/one-off dispatch, corrupt aggregate
  rejection and bounded batches that advance beyond unresolved candidates;
- real WordPress proof that a clean but insufficient recurring projection appears
  as repair-needed, rejects a forged nonce, remains recurring and rebuilds the
  540-day production horizon before reporting healthy status;
- exact fresh, minimum and renewal horizon arithmetic across leap boundaries;
- public-gap and recurring-only renewal metadata query structures;
- protected coverage metadata registration and strict date-only sanitization;
- dirty-before-mutation storage and double-checked concurrent activation binding;
- bounded renewal scheduling, migration/schema gating and unresolved-offset reset;
- real WordPress proof that a still-public 400-day window renews proactively;
- mixed one-off/recurring collection presentation with exact totals, repeated
  parent IDs, sparse overrides, safe pretty/plain URLs and no per-row SQL lookup;
- occurrence-aware card output with unique accessible heading IDs and exact
  effective title, venue, date and leaf destination;
- occurrence-aware event-list and calendar fallback output using occurrence
  cardinality rather than distinct series posts;
- occurrence-aware calendar serialization and `wpse/v1` REST totals, including
  effective fields, stable public-key IDs and a generic fail-closed 503 response;
- occurrence-aware native event and taxonomy archives with exact totals,
  repeated parent IDs, fixed taxonomy scope and fail-closed parent races;

## Production Elementor Pro qualification

The first 0.4.0 release candidate was installed on the production Taranartos site
with Elementor 4.2.3, Elementor Pro 4.2.2 and its existing single-event Theme
Builder template. The bounded temporary event and every generated occurrence were
deleted after the journey; the event count, plugin settings and recurrence health
were restored and no Elementor template was saved or changed.

Verified behaviour:

- one temporary three-day daily series produced three distinct public calendar
  entries after occurrence-index repair;
- the first and second occurrence opened through the existing Elementor Pro
  single template with the correct occurrence date, exact canonical URL and exact
  Event JSON-LD;
- both leaves retained exactly one site header, main region and footer, rendered
  the plugin's atomic widgets, and returned private no-store cache headers;
- the calendar returned no temporary entries after permanent deletion, the Events
  list returned to its original 19 public items and occurrence health was healthy.

The journey also exposed two release blockers before public release:

- Yoast SEO may pass `null` through its canonical filter while Elementor constructs
  a Theme Builder preview. The plugin's former `string|false` callback rejected
  that valid host state and caused an HTTP 500 preview. The corrected callback now
  preserves `null`, with a regression test for the exact host contract.
- A recurring event published outside the recurrence save route could become
  public before its disposable occurrence projection existed. A narrow
  transition-only controller now repairs missing, dirty or insufficient projection
  state synchronously when an event first becomes public. Unit coverage and real
  WordPress 6.9/7.0.1 smoke journeys prove that ordinary publication is immediately
  healthy without administrator repair.

The corrected package was subsequently uploaded to the same production host and
passed both regressions:

- the existing Elementor Pro Theme Builder event template opened successfully,
  rendered its preview and exposed all fifteen plugin widgets without saving or
  changing the template;
- publishing a new daily three-occurrence series through the site's configured
  Classic Editor immediately reported a healthy occurrence index without manual
  repair;
- the calendar feed returned exactly three distinct occurrences and all three
  exact occurrence URLs rendered the expected localized date and time;
- permanent deletion removed the temporary event, feed rows and occurrence routes;
  the feed returned an empty result, an old occurrence leaf returned a normal 404,
  and the Events list returned to its original 19 public items;
- WordPress writing settings were restored to **Classic Editor** with editor
  switching disabled, and all temporary browser tabs were closed.

On this host, the Gutenberg publish control displayed an optimistic published
state but the post remained a draft after reload. The same event published
successfully through the site's configured Classic Editor. This is recorded as a
host/editor integration observation rather than a plugin release failure: the
plugin's Gutenberg recurrence save completed, its projection remained private
while the parent was a draft, and the publication transition then projected the
series correctly when WordPress actually made the parent public.

## Checks completed

```text
composer validate --strict                         pass
composer qa                                        pass
  PHPCS/WPCS                                       pass
  PHPStan                                          pass
  PHPUnit: 649 tests, 2,441 assertions             pass
  Composer security audit                          pass
npm run qa                                         pass
  production asset builds                          pass
  tooling tests: 44                                pass
  JS/CSS lint                                      pass
npm run audit                                      pass (0 vulnerabilities)
npm run i18n:check                                 pass
WordPress 7.1 targeted recurrence E2E              pass
  enable, preview and complete-series save         pass
  only-this move and inherited-date restore        pass
  only-this status override and inheritance        pass
  only-this cancellation and restoration           pass
  this-and-following boundary/preview/save          pass
  empty-period search and recovery                 pass
  explicit survivor and conversion to one-off      pass
  ordinary schedule locks/unlocks with recurrence  pass
Playwright package browser matrix: 20 journeys     pass
  calendar interaction, failure and responsive UX  pass
  Elementor calendar lifecycle adapter             pass
  Gutenberg recurrence preview and apply            pass
  Gutenberg block registration and previews         pass
npm run test:smoke                                 pass
  source: WordPress 6.9 / PHP 8.2 Playground       pass
  source: WordPress 7.0.1 / PHP 8.2 Playground     pass (earlier qualification)
  package: WordPress 6.9 / PHP 8.2 Playground      pass
  package: WordPress 7.1 / PHP 8.2 Playground      pass
  occurrence edit-context auth/inheritance         pass
  only-this preview/save/reload                     pass
  following preview/save/reconciliation/replay      pass
  exact active one-off occurrence assertion        pass
  real database one-off occurrence read parity     pass
  real database recurring projection/no-op/cleanup pass
  ordinary recurring publish preserves projection pass
  stale cleanup preserves dirty and active rows    pass
  dirty recurring index health and protected repair pass
  recurring repair preserves aggregate and horizon pass
  narrow clean coverage requires protected repair  pass
  valid 400-day coverage renews to 540 days         pass
  exact occurrence leaf / unknown-key 404          pass
  native archive repeated occurrence cardinality   pass
  native upcoming archive omits cancellations      pass
  native occurrence details/canonical/JSON-LD      pass
  Gutenberg title/date on exact occurrence leaf    pass
  v2 REST draft denial/exact leaf/generic 404       pass
  v2 REST omits recurrence projection internals    pass
  Yoast/Rank Math/AIOSEO exact canonical filters    pass
  Core sitemap index and all active occurrence URLs pass
  Core sitemap omits protected data and lastmod     pass
  exact occurrence browser leaf sends no-store      pass
  recurrence REST enable/disable/stale replay      pass
release archive verification                       pass
  content and production dependency boundary       pass
  shipped PHP syntax and production autoloader     pass
  checksum integrity and reproducible second build pass
  final SHA-256                                     5be06d352fa5c3a818999565738390593376c5cd2074e0c485948872eceb4ea3
translation catalogue synchronization              pass
```

The first official GitHub release run for commit `0d6f3ff` completed the PHP
8.2–8.5 matrix, JavaScript/CSS, translation and WordPress smoke jobs, but correctly
blocked publication. Strict Plugin Check could not infer the existing typed SQL
compiler for the rebuildable occurrence table, required explicit no-cache
justification on its mutations, flagged the bounded legacy metadata sort and
reported the obsolete `Tested up to: 7.0` header. The browser job also reached a
successful recurrence-disable reload before its five-second editor-hydration
assertion. ADR-077 narrows and documents the unavoidable database suppressions;
the release metadata and compatibility matrix now target WordPress 7.1; and the
browser regression waits for the registered editor integration after navigation.
All corrected local gates above pass. GitHub Actions run
[`32849753528`](https://github.com/MilanMiMeOnline/WP-Simple-Events/actions/runs/32849753528)
then passed all ten jobs for commit `b7c6a7e`, including strict official Plugin
Check, PHP 8.2–8.5, WordPress 6.9 and 7.1, the release archive and all twenty
browser journeys.

## Release gate result

The source and packaged WordPress/PHP compatibility matrix, dependency audits,
translation catalogue, reproducible archive, release notes, corrected-package
production journey and official strict Plugin Check are qualified. No known
security, privacy, compatibility or WordPress.org compliance blocker remains for
the 0.4.0 publication.
