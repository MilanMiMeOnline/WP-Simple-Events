# Getting started

This guide takes a new installation from activation to one public event and one
events page.

## 1. Check WordPress first

Before creating an event, open **Settings > General** and verify:

- the **Timezone** is the location whose local time you enter;
- **Date Format** and **Time Format** look the way visitors should see them.

New events capture the current WordPress timezone when first saved. Changing the
site timezone later does not silently move existing events. A named zone such as
`Europe/Brussels` follows daylight-saving time; a fixed offset such as `+02:00`
does not.

## 2. Install and activate

From WordPress.org, use **Plugins > Add New**, search for “MiMe Simple Events and
Calendar”, choose **Install Now**, and activate it. For a downloaded official
ZIP, use **Plugins > Add New > Upload Plugin** instead.

The plugin adds an **Events** menu. It does not require Elementor, Divi,
WooCommerce or another event plugin.

## 3. Create the first event

1. Open **Events > Add New**.
2. Add the title, normal WordPress content, excerpt and featured image you need.
3. In **Event details**, enter a start date. Enter a start time for a timed event,
   or enable **All-day event**.
4. Optionally add an end, venue, address, location link, event status and external
   action link. The external action label can be changed per event.
5. Assign event categories and tags if they help visitors browse or filter.
6. Save a draft and preview it. Publish only when the date and public content are
   correct.

A valid start is required for publication. Drafts may remain incomplete. An end
must not be before the start. Location and external links accept only HTTP or
HTTPS URLs and open in an isolated new tab on the public site.

## 4. Create an events page

Create a normal page and choose one of these approaches:

- insert the **Event List / Grid** Gutenberg block;
- insert the **Event Calendar** Gutenberg block;
- add `[wpse_events]` or `[wpse_calendar]` in a Shortcode block;
- use the equivalent optional Elementor or Divi component.

The native archive is also available at `/events/` by default. Its address can
be changed under **Events > Settings**, but changing it changes existing event
URLs and does not create redirects.

## 5. Verify as a visitor

Open the event and events page while logged out or in a private browser window.
Check the date, time, timezone intention, links, mobile layout and any filters.
Draft, private and password-protected event details are deliberately excluded
from public plugin collections.

## Sensible first settings

The defaults are intentionally safe. Most sites can leave them unchanged:

- native archive: enabled through WordPress at `/events/`;
- public timezone label: off;
- native Add to Calendar: off;
- Event structured data: on;
- delete plugin data on uninstall: off.

Enable the public timezone label for audiences that may interpret an event in a
different timezone. Disable Event structured data only if another SEO tool is
already producing Event JSON-LD for the same page.

Next: [display and style events](DISPLAYING-EVENTS.md) or
[make an event repeat](RECURRING-EVENTS.md).
