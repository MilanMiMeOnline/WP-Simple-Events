# Displaying events

Use the native archive for the fastest setup, a composite block or builder
component for visual control, and atomic event fields for custom templates.

## Native pages

The plugin provides safe single-event and archive fallbacks that retain the
active theme header and footer. The archive defaults to `/events/` and can show
upcoming or all events according to **Events > Settings**.

Themes may override these templates. A builder Theme Builder assignment may also
replace them deliberately.

## Event List / Grid

The list/grid component can control:

- list or grid view, columns and number of events;
- upcoming, past or all events;
- initial category and tag constraints;
- pagination, image, title, date, excerpt and location visibility;
- title heading level and excerpt length;
- optional visitor filters and their labels, layout, disclosure, active choices
  and result status;
- card, image, content, pagination and filter presentation in supported builders.

Visitor filters use ordinary checkboxes. Active choices can be removed
individually. **Clear all** removes the visitor's choices, while **Restore
defaults** returns to configured initial choices when those exist. Filter URLs are
shareable, work without JavaScript and remain isolated when several event
components share a page.

## Event Calendar

The calendar supports month and list views, different desktop/mobile defaults,
an initial date, optional Previous/Next, Today and Month/List controls, optional
visitor filters and an Auto/Show/Hide category-color legend.

If JavaScript cannot load, visitors still receive a bounded upcoming-event list.
The calendar library and its styles are bundled locally; no CDN is contacted.

## Filters

Enable filters only when visitors benefit from categories or tags. Empty filter
groups are omitted automatically.

- **Auto layout** responds to the component width, not only the browser width.
- **Horizontal** and **Stacked** force a presentation when the design needs it.
- **Auto disclosure** becomes compact when space is limited; Open or Closed can
  set the initial state explicitly.
- More than ten options receive a local search field. Checked choices remain
  visible while searching.
- Hiding a category or tag group keeps configured initial constraints fixed;
  visitor URL parameters cannot override a hidden group.

Gutenberg exposes content choices. Elementor and Divi additionally expose
component-scoped design controls for panel, trigger, checkbox, choice chip,
action, status, spacing, border, radius and typography. Theme styles remain the
default unless you deliberately override them.

## Event colors

Add an optional category color under **Events > Event Categories**. Then choose
the event's **Calendar color** in its Event details:

- **Automatic — use one unambiguous category color** resolves one safe assigned
  category;
- **Use the calendar or website default** keeps the component fallback;
- **Use one assigned category color** lets you select an eligible assigned
  category;
- **Use a custom event color** uses the event's own color.

Save or assign a category before choosing it explicitly. If several assigned
categories have different colors, Auto falls back instead of choosing an
arbitrary category. Recurring occurrences inherit the series color.

Spanning and all-day calendar items use a colored block. Ordinary timed month
items use a colored dot so compact text stays readable. List cards and fallback
output use the same color as an accent. Text, title, time and status remain
visible, so color is never the only meaning.

## Add to Calendar

Place the separate **Add to Calendar** block, widget, module or shortcode exactly
where you want it. Omit it when the design should not offer an export.

The default local ICS option creates a one-time snapshot, not a subscription or
sync. Google and Outlook actions are optional and must be enabled deliberately by
the template author. Exact recurring occurrence pages export that occurrence;
the recurring series page does not imply a whole-series export.

## Shortcode examples

```text
[wpse_events]
[wpse_events view="list" period="all" limit="20" filters="true"]
[wpse_events category="music,workshops" filter_layout="stacked"]

[wpse_calendar]
[wpse_calendar initial_view="list" filters="false"]
[wpse_calendar category="music" legend="show"]

[wpse_event_details]
[wpse_event_details id="123"]

[wpse_add_to_calendar]
[wpse_add_to_calendar id="123" providers="ics,google" layout="list"]
```

Lists are bounded to 1–50 events and grids to 1–4 columns. Categories and tags
use comma-separated slugs. Boolean shortcode values use `true` or `false`.
Unknown attributes are ignored and invalid values use safe defaults. The full
attribute contract is in [Public query contract](PUBLIC-QUERY-CONTRACT.md).

Next: [use Gutenberg, Elementor or Divi templates](BUILDERS-AND-TEMPLATES.md).
