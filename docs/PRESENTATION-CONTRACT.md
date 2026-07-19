# Event presentation contract

This contract defines the shared WP4 boundary used by native templates and the existing composite shortcode/Elementor output. Future Elementor widgets and Gutenberg blocks must consume the same named fields rather than reading event metadata directly.

## Event source and access

`EventContextResolver` is the single event-source boundary:

| Source | Method | Access contract |
|---|---|---|
| Current page or template context | `resolve_current()` | Published events are available. Draft, private and scheduled previews require WordPress `read_post` permission. A protected event remains available only so the composite renderer can return WordPress' password form. |
| Explicit static-page or editor selection | `resolve_public()` | Only published, password-free `wpse_event` posts are returned. Invalid IDs, other post types, drafts, private events and protected events return no context. |

Resolution never falls back to another event. A resolver stores positive and negative normalized lookups only in its PHP object for the current request. Integrations should share one resolver instance across their field components. Nothing is cached across requests.

`EventPresentationFactory` is the only WP4 class that reads event metadata and taxonomies. Stored values are treated as untrusted and pass through the existing metadata sanitizers before entering `EventPresentation`. Host adapters receive named presentation data and rendered fields; they do not receive arbitrary metadata keys.

## Named fields

`EventFieldRenderer` exposes one method for every supported field:

| Field | Method | Stable primary class |
|---|---|---|
| Title | `title()` | `.wpse-single-event-title` |
| Featured image | `featured_image()` | `.wpse-single-event-image` |
| Date, time and optional timezone | `date_time()` | `.wpse-event-date`, `.wpse-event-timezone` |
| Exceptional event status | `status()` | `.wpse-event-status`, `.wpse-event-status-{status}` |
| Venue | `venue()` | `.wpse-event-venue` |
| Address | `address()` | `.wpse-event-address` |
| Location action | `location_action()` | `.wpse-event-location-link` |
| Content | `content()` | `.wpse-single-event-content` |
| Excerpt | `excerpt()` | `.wpse-event-excerpt` |
| External event action | `external_action()` | `.wpse-event-action`, `.wpse-event-action-link` |
| Categories | `categories()` | `.wpse-event-categories` |
| Tags | `tags()` | `.wpse-event-tags` |

`.wpse-event-label` remains the shared visible-label class. Optional linked images use `.wpse-event-image-link`. The composite renderer retains `.wpse-single-event`, `.wpse-single-event-header`, `.wpse-event-summary`, `.wpse-event-location` and `.wpse-event-taxonomies` as grouping classes.

Missing or corrupt optional values produce an empty string and therefore no frontend wrapper or spacing. Scheduled status is the normal state and remains visually omitted; cancelled and postponed render explicit status markup. All returned URLs are restricted to normalized HTTP(S) values and escaped at output. Text and attributes are escaped for context, while featured-image, content and excerpt HTML pass through WordPress' public rendering and KSES pipelines.

## Passwords and recursion

Atomic fields always return an empty string while `post_password_required()` is true. The complete `EventDetailsRenderer` instead returns WordPress' full password form and does not expose event fields.

Event content uses the core `the_content` pipeline. A request-wide guard prevents separate renderer instances or nested shortcodes from recursively rendering the same event. The same protection applies to the complete composite renderer and is always released with `finally`, including when a content callback fails.

## Composite compatibility and extension boundary

`EventDetailsRenderer::render()` composes a current-context event and `render_public()` composes an explicit public event. Both use the named field renderer. The existing native template, `[wpse_event_details]` shortcode and Elementor Event Details widget therefore keep one markup and access contract.

Custom presentation adapters may depend on `EventContextResolver` plus `EventFieldRenderer` and request only the named methods above. They should share those service instances for request-local reuse and enqueue the existing `wpse-frontend` stylesheet only after non-empty output. New public fields, changed semantic classes or additional extension hooks require a documented contract decision; raw-meta renderers are not supported.
