# Public event query contract

This document freezes the public query and event-list shortcode contract introduced in the first native front-end increment. Storage remains defined in `DATA-CONTRACT.md`.

## Visibility and periods

Every standalone list and the main event archive query (default `/events/`) uses `EventQueryArguments`. Queries are built with WordPress APIs and expose only:

- `wpse_event` posts;
- publication status `publish`;
- events without a post password;
- at most 50 results per page;
- at most page 1000;
- at most 20 category slugs and 20 tag slugs per filter.

Draft, pending, private, trashed and password-protected events are excluded. The explicit event status `cancelled` or `postponed` remains informational and does not unpublish an event.

Period boundaries use the inclusive `_wpse_end_utc` index:

- `upcoming`: `_wpse_end_utc >= now`, ordered by `_wpse_start_utc` ascending;
- `past`: `_wpse_end_utc < now`, ordered by `_wpse_start_utc` descending;
- `all`: no end boundary, ordered by `_wpse_start_utc` ascending.

Consequently, an event that started earlier but has not ended remains active/upcoming. Category and tag clauses use only `wpse_event_category` and `wpse_event_tag`; when both are supplied they use `AND` semantics between the taxonomies and `IN` semantics within each selected slug list.

## Native archive

The main `wpse_event` archive query uses the archive settings documented in `ARCHIVE-SETTINGS-CONTRACT.md`. Until an explicit override is saved, it defaults to `upcoming` and inherits the bounded site `posts_per_page` value. Administrators may choose `upcoming` or `all` as the default and 1 through 50 events per page. It accepts only the registered public query variables `wpse_period`, `wpse_category` and `wpse_tag`. A visitor's valid period filter overrides the site default; invalid period or pagination input falls back safely.

The adapter changes only the front-end main event archive. It does not alter admin, blog, WooCommerce, secondary or unrelated taxonomy queries.

The native archive renderer consumes this main query directly and adds an accessible filter form for `wpse_period`, `wpse_category` and `wpse_tag`. It does not create a duplicate query. Classic and block-theme presentation details are frozen in `TEMPLATE-CONTRACT.md`.

## `[wpse_events]` shortcode

Default attributes are:

| Attribute | Default | Contract |
|---|---:|---|
| `view` | `grid` | `list` or `grid` |
| `period` | `upcoming` | `upcoming`, `past` or `all` |
| `limit` | `12` | integer from 1 through 50 |
| `columns` | `3` | integer from 1 through 4 |
| `category` | empty | comma-separated event category slugs |
| `tag` | empty | comma-separated event tag slugs |
| `filters` | `false` | strict boolean string |
| `pagination` | `true` | strict boolean string |
| `show_excerpt` | `true` | strict boolean string |
| `show_image` | `true` | strict boolean string |
| `show_location` | `true` | strict boolean string |

Unknown attributes are ignored. Invalid enum, integer and boolean values use documented safe defaults; no raw meta query, SQL, callback or post-status argument is accepted.

Each rendered shortcode receives a deterministic request namespace based on render order, such as `wpse_1_period` and `wpse_1_page`. Filter forms preserve only allowlisted state belonging to other WP Simple Events instances. This prevents one list from reading or overwriting another list's filters or pagination.

The shortcode returns HTML and does not mutate the global WordPress loop. It uses the shared repository, date formatter and card renderer also consumed by native templates and Elementor adapters.

## `[wpse_event_details]` shortcode

Without attributes this shortcode renders the current queried event through the shared complete event-details renderer. Its optional `id` attribute accepts only a positive base-ten event post ID. Explicit selection is public-only: the target must be a published `wpse_event` without a post password. Invalid, non-event, draft, private and password-protected targets return no output.

The renderer itself accepts an explicit event ID for later Elementor previews, enforces WordPress password protection and guards against recursion through event content. The full template, shortcode and security contract is documented in `TEMPLATE-CONTRACT.md`.

## Rendering and assets

Cards omit missing optional values, use the stored event timezone, expose machine-readable start/end boundaries, label cancelled or postponed events, and use semantic article, heading, link and time markup. Corrupt date metadata produces no public card.

The `wpse-frontend` stylesheet is component-scoped, has no global reset, inherits typography and foreground colour, and provides only layout, spacing, image ratio, neutral states and visible focus indicators. It is enqueued for discoverable event views and by dynamic renderers.

## Calendar feed

`GET /wp-json/wpse/v1/events` is a public, read-only representation owned by
`CalendarFeedController`. Its required `start` and `end` parameters are strict
ISO 8601 date-times with an explicit timezone. `start` is inclusive and `end`
is exclusive. The non-empty request window may span at most 400 days.

Optional `categories` and `tags` values contain at most 20 comma-separated
slugs per taxonomy; each normalized slug is at most 200 bytes. `per_page` is
bounded from 1 through 100 and `page` from 1 through 1000. Invalid standalone
or relational input returns HTTP 400 before WordPress is queried.

The feed reuses `EventRepository` and selects only published, non-password
events with this exact overlap condition:

```text
_wpse_end_utc >= requested.start
AND _wpse_start_utc < requested.end
```

Results are ordered by `_wpse_start_utc` ascending and expose only ID, plain
text title, formatted start/end, all-day flag, visible event status, permalink,
venue and category slugs. Draft/private state, content, addresses, internal
metadata keys and write capabilities are not exposed. Corrupt event date
records are omitted. `X-WP-Total` and `X-WP-TotalPages` describe the bounded
public query.

For timed events, start and end retain the captured event timezone offset.
For all-day events, stored inclusive end dates are converted to FullCalendar's
exclusive end date.

## `[wpse_calendar]` shortcode

Default attributes are:

| Attribute | Default | Contract |
|---|---:|---|
| `initial_view` | `month` | `month` or `list` |
| `mobile_view` | `list` | `month` or `list` |
| `category` | empty | comma-separated event category slugs |
| `tag` | empty | comma-separated event tag slugs |
| `filters` | `true` | strict boolean string |

Each calendar receives an isolated `wpse_calendar_N` request namespace. Native
GET controls work without JavaScript and preserve only allowlisted state from
other calendar instances. The visitor form is omitted completely when neither
event taxonomy has a non-empty public term; when only one taxonomy has choices,
only that selector is shown. Configured category and tag constraints remain
authoritative for both the server fallback and JavaScript feed when visitor
controls are disabled or unavailable. The fallback is a bounded upcoming event list. After
the first successful feed response, JavaScript replaces that list with the
calendar. Feed failure leaves the list visible.

The `wpse-calendar` script handle points to the locally built production bundle
and is enqueued only when the shortcode renders. It contains exactly
FullCalendar core, day-grid and list modules; no CDN, recurrence, drag/drop,
resource, map or premium code is included. The calendar uses real event links,
translated controls, a live status region, keyboard-operable native controls,
visible focus and text labels for cancelled or postponed states.
