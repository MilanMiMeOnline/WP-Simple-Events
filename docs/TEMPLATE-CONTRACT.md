# Native template and event-details contract

This document freezes the presentation boundary used by classic themes, block themes, shortcodes and Elementor adapters.

## Template priority

Event requests receive a fallback candidate through `template_include` at priority `0`. This deliberately leaves later builder filters free to replace it. The selected fallback hierarchy is:

1. an applicable Elementor Pro `single` or `archive` Theme Builder location;
2. an active theme or child-theme override at `mime-simple-events-calendar/single-wpse_event.php`, `mime-simple-events-calendar/archive-wpse_event.php` or a specific `mime-simple-events-calendar/taxonomy-wpse_event_category.php` / `taxonomy-wpse_event_tag.php` file;
3. a matching block template customized by the active full block theme or Site Editor;
4. the plugin-owned block template for full block themes;
5. the bundled PHP template for classic themes.

The PHP and block fallbacks both delegate to `NativeTemplateRenderer`. The controller checks Elementor through its public theme-location function before rendering native output. Theme files contain no event business logic.

Block templates are registered with the WordPress block-template registry under `mime-simple-events-calendar//single-wpse_event`, `mime-simple-events-calendar//archive-wpse_event`, `mime-simple-events-calendar//taxonomy-wpse_event_category` and `mime-simple-events-calendar//taxonomy-wpse_event_tag`. Their dynamic `wpse/native-single` and `wpse/native-archive` blocks are internal presentation bridges, not editor widgets. They use the same renderers as the PHP fallbacks and do not issue their own archive query. Classic themes may reuse the generic PHP event-archive override for taxonomy requests; full block themes use the taxonomy-specific template slug so WordPress can preserve their block header and footer deterministically.

`wp_is_block_theme()` is the boundary for full-page block-template selection.
A classic theme with `theme.json` may expose WordPress' `block-templates`
feature without providing block header or footer template parts; it must still
use the PHP fallback. An explicit PHP override is returned before block-template
discovery so a theme or child theme cannot be displaced by a plugin template at
the same slug.

## Single event output

`EventDetailsRenderer::render()` always receives an explicit event ID. This makes the service usable with the current query, an Elementor preview ID or another trusted adapter without relying on global loop state.

The complete native output order is title, featured image, date/time, exceptional status, venue/address/location link, post content, external action, categories and tags. Empty optional values are omitted. The renderer applies the core `the_content` pipeline once and has a per-event recursion guard, so an accidental `[wpse_event_details]` inside event content cannot duplicate the event indefinitely.

Password protection is enforced inside the renderer. Until the WordPress password cookie is valid, it returns only the core password form and does not render date, status, location, content, action or taxonomy metadata.

## `[wpse_event_details]` shortcode

The shortcode returns the shared complete renderer output. With no attributes it uses the current queried event. It supports one optional allowlisted attribute:

| Attribute | Contract |
|---|---|
| `id` | positive base-ten event post ID |

An explicit `id` renders only a published event with an empty post password. Draft, pending, private, trashed, password-protected, non-event and malformed IDs return an empty string. This lets a normal page embed a selected public event without turning the shortcode into a private-content lookup. An implicit current event can render an authorized WordPress preview; password protection remains enforced by the renderer.

## Native archive output

`EventArchiveRenderer` consumes the already-bounded main `WP_Query`; it never creates a second archive query. On the post-type archive it renders the archive title, period/category/tag form, shared event cards, empty state and native pagination. On an event category or tag archive it uses the term archive title, shared cards and pagination but omits the cross-archive filter form so the fixed term scope cannot be replaced. Filters use only `wpse_period`, `wpse_category` and `wpse_tag`, which are normalized by the existing public-query contract.

## Stable extension points

- `wpse_render_single_template`: renders the builder location or shared native single event inside the bundled PHP template.
- `wpse_render_archive_template`: renders the builder location or shared native archive inside the bundled PHP template.
- `wpse/native-single`: internal server-rendered block used by the plugin block template.
- `wpse/native-archive`: internal server-rendered block used by the plugin block template.

Theme overrides may own the complete page markup. Elementor widgets call the existing shortcode/render services rather than copying metadata, query or date logic. Their specific host and control contract is documented in `ELEMENTOR-INTEGRATION.md`.
