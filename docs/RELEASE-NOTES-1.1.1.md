## MiMe Simple Events and Calendar 1.1.1

Version 1.1.1 keeps an unknown event end unknown, without publishing an invented end time.

### Highlights

- Leave both end fields blank and they stay blank after saving, including recurring events.
- Events without an end time remain in upcoming lists until the end of their local event day.
- Calendar displays and event structured data omit unknown end times. Structured data also omits old timed ends that equal the start.

### Safety and compatibility

- Existing event dates, recurring schedules, settings and saved layouts are preserved. This update does not automatically rewrite old event dates.
- All-day events and events with a known end keep their existing behavior.

For an older event with an automatically filled end, clear both the end date and end time and save if you want to mark its end as unknown. No bulk cleanup is required.

See [CHANGELOG.md](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/v1.1.1/CHANGELOG.md) for the complete technical changelog.
