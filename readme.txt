=== Simple Events by MiMe ===
Contributors: mimeonline
Tags: events, calendar, event management, schedule, blocks
Requires at least: 6.9
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 0.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create and publish focused WordPress events with lists, calendars, blocks and optional Elementor widgets.

== Description ==

Simple Events by MiMe adds a native Events section to WordPress. It is designed for websites that need clear event publishing without recurrence, ticketing, interactive maps or the overhead of a large event suite.

= Event publishing =

* Create events in the familiar WordPress editor.
* Add a start, optional end, all-day state, venue, address and external links.
* Use separate event categories and tags without mixing them with blog posts.
* Mark events as scheduled, postponed or cancelled independently of their WordPress publication status.
* Keep each event's original timezone when the website timezone changes.
* Use WordPress revisions and a safe Duplicate event action.

= Display options =

* Native single-event and event-archive pages for classic and block themes.
* Responsive event list and grid with optional filters and pagination.
* Month and list calendar views with category and tag filters.
* An accessible upcoming-event fallback when JavaScript is unavailable or the calendar cannot load.
* Twelve dynamic Gutenberg blocks for individual event fields, plus a single-event pattern.
* Three complete shortcodes: `[wpse_events]`, `[wpse_calendar]` and `[wpse_event_details]`.
* Event JSON-LD on eligible single-event pages, with an administrator opt-out.

= Optional Elementor integration =

Elementor 3.35 or newer adds Event List / Grid, Event Calendar and Event Details widgets, together with twelve individual event-field widgets. Elementor is optional: the event editor, shortcodes, Gutenberg blocks and native templates work without it.

Individual widgets can select a published event on an ordinary Elementor page. In a dynamic event template they can use the current event context. Elementor Pro is only required when the site owner wants to use Elementor's Theme Builder; it is not required by this plugin.

= Privacy by design =

The plugin does not create visitor cookies, collect analytics or telemetry, load remote scripts, or send information to MiMe or another external service. Calendar requests stay on the same WordPress website.

Event content entered by site editors—including dates, venues, addresses and links—is stored in the website's own WordPress database. Published event information can be visible in page HTML, Event JSON-LD, the WordPress REST API and the plugin's bounded calendar feed. Do not publish private information as event content. Draft, private and password-protected event details are excluded from public plugin collections; protected event metadata is also removed from public core REST responses while the WordPress password is still required.

External location and event links are contacted only when a visitor chooses them. They open in an isolated new tab without passing a referrer. The destination website has its own privacy practices.

Deactivation never deletes event data. Plugin deletion also preserves events and settings by default. An administrator can explicitly opt into permanent cleanup under Events > Settings; shared media remains untouched.

The complete source, build instructions and security policy are available at https://github.com/MilanMiMeOnline/WP-Simple-Events.

== Installation ==

1. In WordPress, go to Plugins > Add New > Upload Plugin.
2. Select the official Simple Events by MiMe zip file and choose Install Now.
3. Activate the plugin.
4. Go to Events > Add New and create the first event. A valid start is required before publication.
5. Add `[wpse_calendar]` or `[wpse_events]` to a page, or insert the matching Gutenberg or Elementor component.

== Frequently Asked Questions ==

= Does the plugin support recurring events? =

No. Recurrence is deliberately outside the first version because a reliable recurrence system needs a separate occurrence model, editing rules and migration design. Duplicating a one-off event creates a draft and marks its dates for review.

= Does it include maps, geocoding or ticket sales? =

No. You can store an address and an HTTP or HTTPS location link, but the plugin does not load an interactive map, call a geocoding service or sell tickets.

= Is Elementor required? =

No. Elementor is an optional integration. Native templates, Gutenberg blocks and shortcodes provide the complete core experience.

= Can Elementor Free use the event widgets? =

Yes. The widgets can be placed on ordinary Elementor Free pages and can select a published event. Elementor Pro is only needed for Elementor's own Theme Builder functionality.

= Which timezone and time format are used? =

New events capture the timezone configured under WordPress Settings > General. Existing events keep their saved timezone if that website setting changes. Public times follow the WordPress time-format setting. Events > Settings can optionally show the captured timezone and applicable UTC offset.

= What happens to data when the plugin is removed? =

Nothing is deleted on deactivation. Deleting the plugin also keeps events, event categories, event tags and settings unless an administrator first enables the clearly warned Delete plugin data option. Uploaded media is never deleted by that cleanup.

= Does the plugin track visitors or contact external services? =

No. There is no telemetry, analytics, advertising, remote asset loading or MiMe service connection. The calendar reads its public data from the same WordPress installation. A visitor can still choose an external location or event link, after which the destination's own policies apply.

= Where can I report a bug or security issue? =

Ordinary bugs can be reported at https://github.com/MilanMiMeOnline/WP-Simple-Events/issues. Do not publish vulnerability details, secrets or personal data in an issue; follow the private process in the repository's SECURITY.md file.

== Screenshots ==

1. The native Events overview with event dates, location, categories and status filters.
2. The Event details panel in the familiar WordPress editor.
3. The responsive public month calendar and event list.
4. A single event with date, venue, location link, external action, categories and tags.
5. Event archive, display, timezone and data-retention settings.
6. The complete optional Simple Events by MiMe widget collection in Elementor.
7. Calendar and event-list configuration inside the Elementor editor.

== Changelog ==

= 0.2.2 =
* Protect password-locked event metadata in the public WordPress REST API while preserving authorized editor access.
* Add professional WordPress.org documentation, privacy guidance, icons, banners and product screenshots.
* Pin continuous-integration actions to reviewed immutable revisions.

= 0.2.1 =
* Adopt the final first-release name and slug: Simple Events by MiMe and simple-events-by-mime.
* Open location and external event actions safely in a new tab.
* Expose Edit with Elementor for individual Events on compatible Elementor installations.
