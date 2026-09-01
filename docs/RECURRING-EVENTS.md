# Recurring events

Recurring events are one event series with generated occurrences. Shared content
stays in one place, while an individual occurrence stores only what differs.

## Create a repeating schedule

1. Create the event and enter a valid start and optional end in **Event details**.
2. **Save the draft first.** Recurrence needs a saved event ID and valid dates.
3. Open the **Repeating event** panel in the WordPress editor sidebar.
4. Choose **Daily**, **Weekly**, **Monthly**, **Yearly** or **Selected dates**.
5. Configure the interval and end condition: **Never**, **On a date** or **After
   a number of events**.
6. Choose **Preview recurrence** or **Review impact**. Read the summary and sample
   dates before applying it.
7. Choose **Apply** only when the preview matches your intention, then save the
   event.

An open-ended schedule uses a bounded rolling public window that renews
automatically. It does not create an unlimited number of database rows at once.
Selected dates must include the first event date.

## Monthly behavior

- **Day of month** keeps the same calendar number. Months without that date are
  skipped; the event is never silently moved to the last day.
- **Weekday position** keeps a pattern such as the second Tuesday.
- A last-weekday pattern remains the last matching weekday when applicable.

Always inspect a preview for dates near month ends and daylight-saving changes.
The event keeps its captured local timezone.

## Understand the three edit scopes

### Complete series

Use this for information or schedule changes that should apply to the complete
series. Shared title, content, featured image, location and action information
remain owned by the series unless an occurrence has an explicit override.

This is the broadest choice. Review its impact before applying it.

### Edit one occurrence

Select the occurrence first, then override only what is different: date/time,
status, title, note, featured image, venue, address, location link or external
action. You can cancel one occurrence without deleting the series.

Choose **Use series value** for a field to remove its override and inherit future
series changes again. Individual occurrence changes are sparse and reversible.
Calendar color remains series-owned and cannot differ per occurrence.

### This and following

Use this when the schedule changes from one generated occurrence onward. Earlier
occurrences remain part of the old segment; the selected boundary and later dates
follow the replacement schedule. Existing individual changes are preserved where
possible and may become standalone occurrences when they no longer fit the new
generated pattern.

The boundary must be a generated occurrence other than the root. Review the
listed impact before confirming.

## Stop repeating

**Stop repeating** is not the same as editing the schedule. It converts exactly
one selected occurrence into a normal one-off event and removes the other
occurrences from that series.

1. Select the occurrence you want to keep.
2. Review the destructive impact summary.
3. Confirm only if all other occurrences should disappear.

The normal event title, content, image, location and action remain. Recurrence and
individual occurrence data are removed. Back up the site before a broad
production change.

## A low-confusion workflow

- Save ordinary event edits before opening recurrence review.
- Decide the scope before changing fields.
- Prefer **Edit one occurrence** for a single cancellation, move or venue change.
- Prefer **This and following** only for a genuine schedule transition.
- Use **Complete series** for intentionally shared changes.
- Reopen the public calendar after applying a broad change and verify earlier,
  boundary and later dates.

If the preview is unavailable or appears stale, do not apply the change. See
[Troubleshooting](TROUBLESHOOTING.md#recurrence-preview-or-occurrences-are-missing).
