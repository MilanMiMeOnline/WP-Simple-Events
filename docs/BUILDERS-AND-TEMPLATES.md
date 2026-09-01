# Builders and templates

MiMe Simple Events and Calendar keeps the same data and public rendering rules
across native WordPress, Gutenberg, Elementor and Divi. A builder is optional.

## Two kinds of components

- **Composite components** render Event List / Grid, Event Calendar or a complete
  Event Details group.
- **Atomic components** render one field: title, featured image, date and time,
  status, venue, address, location link, content, excerpt, external action,
  categories, tags or Add to Calendar.

Use composites for speed. Use atomic fields when building a custom event layout.
Add to Calendar is deliberately independent so it can be placed or omitted.

## Event source

On an ordinary page, an atomic details component can select one published event.
In an event template or exact recurring occurrence context, choose the current
event context. Draft, private, password-protected and invalid explicit sources
produce no public details.

## Gutenberg

The block editor includes the three composite blocks, all atomic event fields,
Add to Calendar and a single-event pattern. Settings appear in the block
Inspector. Dynamic blocks render current data on the front end; saved post
content stores configuration rather than copied private event fields.

You can use the blocks on normal pages without a Site Editor template. In a
block-theme template or query context, use the current event.

## Elementor

Elementor 3.35 or newer exposes the same composite and atomic palette. Elementor
Free can place widgets on ordinary pages and select a public event. Elementor Pro
is only needed for Elementor's own Theme Builder workflow.

Creating a Theme Builder single template for the event post type lets you arrange
the complete page with current-event widgets. Editing an individual event with
Elementor edits that event's content area; it does not make the theme's shared
header, footer or plugin fallback layout part of that document.

Keep event dates, recurrence and canonical event details in the WordPress event
editor. Use Elementor for presentation.

## Divi 5

Divi 5.11.1 or newer exposes the equivalent native modules. Ordinary Divi pages
can select one public event; Theme Builder templates use the current event or
exact occurrence. The modules use Divi's normal responsive, design, preset and
copy/paste systems.

The plugin never installs, licenses or modifies Divi and never creates Theme
Builder assignments automatically.

## Native templates and conflicts

Without a builder assignment, the plugin's native fallbacks retain the active
theme header and footer. If an event page unexpectedly loses them:

1. check whether Elementor or Divi Theme Builder has a condition for events;
2. check for a theme override of the event single/archive template;
3. temporarily preview with a standard theme on a staging site;
4. clear page/cache-builder output after changing template conditions.

Avoid placing a complete Event Details composite and the same atomic fields in
one template unless the duplicate output is intentional. If an SEO plugin already
outputs Event structured data, disable the plugin's Event JSON-LD under
**Events > Settings** to avoid duplicate schema.
