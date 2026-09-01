# MiMe Simple Events and Calendar

![MiMe Simple Events and Calendar](.wordpress-org/banner-1544x500.png)

MiMe Simple Events and Calendar is a focused WordPress event plugin for one-off
and recurring events. It adds the familiar **Events** publishing area, a
responsive calendar, lists and grids, visitor filters, event colors, Gutenberg
blocks and optional Elementor and Divi 5 components—without ticketing, maps,
tracking or a large event-management suite.

[Get started](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/GETTING-STARTED.md) ·
[Read the user guide](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/USER-GUIDE.md) ·
[Report an issue](https://github.com/MilanMiMeOnline/WP-Simple-Events/issues)

## Highlights

- Publish timed, all-day, same-day and multi-day events in the normal WordPress
  editor.
- Add venue, address, location link, event status, external action, categories,
  tags and optional event/category colors.
- Create bounded daily, weekly, monthly, yearly and selected-date recurrence.
- Safely edit one occurrence, this-and-following or the complete series after an
  impact preview.
- Show events through the native archive, list/grid, month/list calendar,
  shortcodes or editor components.
- Offer semantic category/tag filters with removable choices, clear/restore
  actions, shareable URLs and a no-JavaScript fallback.
- Build with Gutenberg, optional Elementor 3.35+ or optional Divi 5.11.1+ using
  complete components or individual event fields.
- Place or omit Add to Calendar independently; local ICS is the safe default,
  with optional Google and Outlook compose actions.
- Preserve the active theme shell through native classic, hybrid and block-theme
  fallbacks.
- Keep visitor privacy simple: no cookies, telemetry, remote assets or background
  provider requests.

Interactive maps, geocoding, ticket sales and external calendar synchronization
are deliberate non-goals.

## Requirements

- WordPress 6.9 or newer
- PHP 8.2 or newer
- Elementor 3.35 or newer only for the optional Elementor integration
- Divi 5.11.1 or newer only for the optional Divi integration

Elementor, Divi and WooCommerce are optional and never core dependencies.

## Five-minute setup

1. Install and activate an official release.
2. Check timezone, date format and time format under **Settings > General**.
3. Open **Events > Add New**, enter a valid start and publish the event.
4. Add the **Event Calendar** or **Event List / Grid** block to a page.
5. Preview that page as a logged-out visitor and check its mobile layout.

The native event archive is available at `/events/` by default. New events
capture the WordPress site timezone; public dates and times follow WordPress'
format settings.

For the complete first-event workflow, see
[Getting started](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/GETTING-STARTED.md).

## Recurring events without guesswork

Save the event dates first, choose a recurrence rule, and review the generated
dates before applying it. Later edits always use an explicit scope:

- **Edit one occurrence** for one cancellation, move or venue change;
- **This and following** for a schedule transition from a chosen occurrence;
- **Complete series** for intentionally shared changes.

Individual occurrence values remain sparse and reversible: choosing **Use series
value** restores inheritance. Open-ended recurrence uses a bounded rolling public
window that renews automatically.

Read
[Recurring events](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/RECURRING-EVENTS.md)
before the first broad production change.

## Display and editor options

Gutenberg, Elementor and Divi expose the same three complete components:

- Event List / Grid;
- Event Calendar;
- Event Details.

They also expose individual event title, image, date/time, status, venue, address,
location link, content, excerpt, external action, category, tag and Add to
Calendar components. Ordinary Elementor Free pages can select a published event;
Elementor Pro is only relevant for Elementor's own Theme Builder.

Shortcodes remain available:

```text
[wpse_events]
[wpse_calendar]
[wpse_event_details]
[wpse_add_to_calendar]
```

See
[Displaying events](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/DISPLAYING-EVENTS.md)
for practical filters, colors and shortcode examples, and
[Builders and templates](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/BUILDERS-AND-TEMPLATES.md)
for current-event contexts and theme ownership.

## Privacy, security and data ownership

The runtime plugin creates no visitor cookies, collects no analytics or telemetry,
loads no remote scripts, fonts, images or pixels, and sends no information to
MiMe. Event data remains in the site's WordPress database. The local recurrence
index is bounded, rebuildable and contains no event body, password or visitor
data.

Published event information can appear in public HTML, Event JSON-LD, WordPress
REST responses, the bounded calendar feed and visitor-requested calendar
snapshots. Draft, private and password-protected event details are excluded from
public plugin collections.

Deactivation never deletes data. Plugin deletion preserves data by default; an
administrator must deliberately enable destructive cleanup. Read
[Privacy, data and updates](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/PRIVACY-DATA-AND-UPDATES.md)
before uninstalling or upgrading an early private build.

## Documentation

- [User guide](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/USER-GUIDE.md)
- [Getting started](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/GETTING-STARTED.md)
- [Displaying events](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/DISPLAYING-EVENTS.md)
- [Recurring events](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/RECURRING-EVENTS.md)
- [Builders and templates](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/BUILDERS-AND-TEMPLATES.md)
- [Troubleshooting](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/TROUBLESHOOTING.md)
- [Privacy, data and updates](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/PRIVACY-DATA-AND-UPDATES.md)
- [Developer and release documentation](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/README.md)

## Contributing and releases

Read [AGENTS.md](AGENTS.md) before changing the project and use
[CONTRIBUTING.md](CONTRIBUTING.md) for setup, testing and review commands. The
functional source of truth is the
[product specification](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/PRODUCT-SPECIFICATION.md).

Release archives use an explicit production allowlist, a minimal optimized
autoloader and reproducible checksum verification. See the
[release process](https://github.com/MilanMiMeOnline/WP-Simple-Events/blob/main/docs/RELEASE-PROCESS.md).

## Support and security reports

Use [GitHub Issues](https://github.com/MilanMiMeOnline/WP-Simple-Events/issues)
for ordinary defects and reproducible feature requests. Never publish exploit
details, credentials, nonces or personal data; use [SECURITY.md](SECURITY.md) for
private vulnerability reporting.

## Licence

[GPL-2.0-or-later](LICENSE). Bundled third-party notices are in
[THIRD-PARTY-NOTICES.txt](THIRD-PARTY-NOTICES.txt).
