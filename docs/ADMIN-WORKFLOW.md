# Event administration workflow

## Events overview

The native **Events → All Events** table keeps WordPress' selection and title columns and adds:

- start and end in the event's captured timezone;
- all-day state;
- venue and address;
- event categories;
- scheduled, cancelled or postponed event status;
- the separate WordPress publication status.

Start and end headers are sortable through `_wpse_start_utc` and `_wpse_end_utc` as numeric values. Empty date and location cells contain both a visible dash and screen-reader text. The overview does not query arbitrary metadata.

The top controls provide:

- all events;
- upcoming and active events, using inclusive `_wpse_end_utc >= now` and ascending start;
- past events, using `_wpse_end_utc < now` and descending start;
- cancelled events;
- postponed events;
- one native event-category filter.

Controls are strict allowlists and affect only the main `wpse_event` query in wp-admin. Front-end, secondary, blog and WooCommerce queries remain untouched. Existing admin meta-query clauses from another extension are combined with the event clause instead of overwritten.

## Duplicate event

Authorized event editors receive **Duplicate event** in the row actions. The action requires:

- permission to edit the source event;
- permission to create events;
- permission to assign event terms;
- a nonce tied to the source event ID.

The result is always a new, password-free draft owned through WordPress' normal current-user insertion behaviour. It opens directly in the editor.

Copied values:

- title with “— Copy”, content and excerpt;
- featured image;
- canonical and derived start/end values, all-day state and timezone;
- venue, address and route/location URL;
- event status;
- event categories and event tags.

Deliberately not copied:

- external event/information/registration URL;
- source password;
- revisions;
- arbitrary custom metadata or third-party secrets;
- blog or third-party taxonomy terms.

Any required copy-step failure permanently removes only the newly created partial draft. The source event is never modified.

Copied dates set the internal `_wpse_dates_need_review` flag. The editor shows a prominent warning until a save passes the shared event validator and persistence gateway. This provides the fast workflow expected as the version-1 replacement for recurrence without silently treating an old date as confirmed.
