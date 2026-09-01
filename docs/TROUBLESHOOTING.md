# Troubleshooting

Start with a backup on production. Use **Events > Settings** maintenance actions
only when their matching status reports a problem; they are not routine buttons.

## An event cannot be published

- Confirm a valid start date exists.
- For a timed event, confirm a start time exists.
- Confirm the end is not before the start.
- Use complete `https://` or `http://` location and external-action URLs.
- Save as a draft first if recurrence has not yet been created.

The editor may show a general WordPress save error while the detailed reason is
listed in the Event details or recurrence notice.

## The time or day looks wrong

Open **Settings > General** and verify timezone, date format and time format.
Then compare the captured timezone shown in the Event details panel. Existing
events intentionally retain their saved timezone after the site setting changes.

Browser time pickers may display 12-hour or 24-hour controls differently, but the
saved value is canonical. Public output follows WordPress' Time Format. Prefer a
named timezone over a fixed UTC offset when daylight-saving time matters.

## An event is missing from a list or calendar

- Confirm it is published and has no post password.
- Check whether the component shows upcoming, past or all events.
- Remove active filter choices or use **Clear all**.
- If configured defaults return, use **Restore defaults** only when that is the
  intended filter state.
- Check initial category/tag constraints in the block, widget, module or
  shortcode.
- Confirm the event date overlaps the visible calendar range.

Draft, private, password-protected, corrupt and ineligible recurring occurrences
are excluded by design.

## Recurrence preview or occurrences are missing

Save the event with valid dates first. Reopen the editor, select the intended
scope and request a fresh preview. Do not apply a broad change when the preview
does not match the expected dates.

Under **Events > Settings**, inspect **Occurrence index**. If WordPress
reports that derived occurrence data is missing or incomplete, use the provided
repair action. The index is rebuildable; the canonical event series remains in
WordPress metadata.

## Filters seem stuck

Active choices belong to the specific event component and may also be present in
its shareable URL. Remove a choice, use **Clear all**, or open the page without
that component's `wpse_*` query values. Several components deliberately keep
separate state.

If categories or tags are hidden in component settings, their configured initial
constraints remain fixed. Empty taxonomies do not produce filter controls.

## Calendar layout is wrong until a resize or click

- Clear page, optimization and CDN caches.
- Ensure JavaScript deferral or combination does not reorder the local calendar
  bundle after its configuration.
- Test with optimization disabled on a staging copy.
- Confirm the page contains only one copy of each intended calendar.

The server fallback should remain usable when JavaScript fails. If the problem
persists, include the page URL, browser, theme, builder and console error in a
private-safe bug report.

## Builder preview differs from the front end

- Save and reload the builder document.
- Confirm the event source is **Current event** only inside an event context; use
  an explicit published event on an ordinary page.
- Clear builder-generated CSS and page caches.
- Check whether a Theme Builder condition is overriding the native template.
- Confirm the supported builder version is active.

Editor previews are authenticated and bounded. A source that is not publicly
eligible can intentionally appear empty outside its editable preview context.

## Header or footer is missing

See [Native templates and conflicts](BUILDERS-AND-TEMPLATES.md#native-templates-and-conflicts).
The plugin fallback retains the theme shell; a builder condition or theme
override can deliberately replace it.

## Maintenance actions

Events settings can repair event capabilities, derived date indexes and the
recurring occurrence index. Each action is capability-checked and intended for a
reported inconsistency. Back up first, run one matching repair, and verify the
public event/archive afterward.

If a defect remains, open a reproducible
[GitHub issue](https://github.com/MilanMiMeOnline/WP-Simple-Events/issues) without
credentials or personal data. Use [the private security process](../SECURITY.md)
for a suspected vulnerability.
