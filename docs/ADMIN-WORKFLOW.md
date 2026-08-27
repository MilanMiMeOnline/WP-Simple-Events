# Event administration workflow

## Event time controls

The native date and time fields save canonical local values. Browsers and operating systems may present an HTML time picker differently, including a 12-hour or 24-hour control, but that appearance does not change the saved value.

Public event details, cards and calendars follow **Settings → General → Time Format**. MiMe Simple Events and Calendar does not add a second global time-format setting. Changing the WordPress format changes presentation only; it does not alter event dates, durations, captured timezones, query indexes or machine-readable output.

## Event timezone

**Events → Settings → Site timezone** reports WordPress' authoritative timezone and links administrators to **Settings → General**. MiMe Simple Events and Calendar deliberately does not add a second timezone selector.

New events capture the configured site timezone when they are first saved. Existing events retain their captured timezone if the site setting later changes, keeping the intended local wall time stable. A named IANA zone such as `Europe/Brussels` follows daylight-saving transitions; a numeric fixed offset such as `+02:00` does not.

The optional **Public event timezone** setting is off by default. When enabled, timed native and Elementor event details add the captured zone and the UTC offset applicable on the event date. A range crossing an offset transition shows both offsets. All-day events omit the label. This is presentation only and does not affect cards, calendars, feeds, structured data or stored event values.

## Events overview

The native **Events → All Events** table keeps WordPress' selection and title columns and adds:

- a concise recurrence summary that distinguishes one-off events, selected
  dates, generated schedules and unavailable recurrence state;
- start and end in the event's captured timezone;
- all-day state;
- venue and address;
- event categories;
- scheduled, cancelled or postponed event status;
- the separate WordPress publication status.

The recurrence column describes the canonical series rule and its meaningful end
condition. It never expands occurrences in wp-admin, performs unbounded public
queries or treats corrupt recurrence state as a one-off event.

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

- external event/information/registration URL and its optional link label;
- source password;
- revisions;
- arbitrary custom metadata or third-party secrets;
- blog or third-party taxonomy terms.

Any required copy-step failure permanently removes only the newly created partial draft. The source event is never modified.

Copied dates set the internal `_wpse_dates_need_review` flag. The editor shows a prominent warning until a save passes the shared event validator and persistence gateway. This prevents a copied historical date from being treated as deliberately confirmed.

## Event colors (0.6 contract)

An event category may store one optional normalized hexadecimal background color.
Term creation and editing use the normal taxonomy capability and nonce boundaries;
invalid values fail validation and removing the value deletes the term metadata.
The category list shows a labelled swatch in addition to text.

An event may keep automatic color resolution, force the component fallback,
select one assigned colored category as its display source, or save one custom
normalized color. An unassigned/deleted category, corrupt color or ambiguous set
of differently colored categories falls back safely. The choice belongs to the
complete canonical series. An individual recurring occurrence cannot receive a
different color in 0.6.

Public black or white foreground text is derived from the normalized background;
editors do not store arbitrary text colors or CSS. Existing events receive no new
appearance until a category/event color is deliberately configured.
