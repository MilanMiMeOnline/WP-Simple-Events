# Event presentation contract

This contract defines the shared presentation boundary used by native templates, composite shortcode output, atomic Elementor widgets and atomic Gutenberg blocks.

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

Optional renderer arguments remain presentation-only: title heading/link, featured-image size/link/attachment-alt versus decorative alt, visible/custom labels for date, venue and terms, and visible text overrides for the two actions. Their defaults preserve composite markup. Date and time continue to inherit WordPress formatting and the global timezone-label choice; presentation controls never alter canonical dates, UTC indexes, feeds or structured data.

Missing or corrupt optional values produce an empty string and therefore no frontend wrapper or spacing. Scheduled status is the normal state and remains visually omitted; cancelled and postponed render explicit status markup. All returned URLs are restricted to normalized HTTP(S) values and escaped at output. Text and attributes are escaped for context, while featured-image, content and excerpt HTML pass through WordPress' public rendering and KSES pipelines.

## Passwords and recursion

Atomic fields always return an empty string while `post_password_required()` is true. The complete `EventDetailsRenderer` instead returns WordPress' full password form and does not expose event fields.

Event content uses the core `the_content` pipeline. A request-wide guard prevents separate renderer instances or nested shortcodes from recursively rendering the same event. The same protection applies to the complete composite renderer and is always released with `finally`, including when a content callback fails.

## Composite compatibility and extension boundary

`EventDetailsRenderer::render()` composes a current-context event and `render_public()` composes an explicit public event. Both use the named field renderer. The existing native template, `[wpse_event_details]` shortcode and Elementor Event Details widget therefore keep one markup and access contract.

Custom presentation adapters may depend on `EventContextResolver` plus `EventFieldRenderer` and request only the named methods above. They should share those service instances for request-local reuse and enqueue the existing `wpse-frontend` stylesheet only after non-empty output. New public fields, changed semantic classes or additional extension hooks require a documented contract decision; raw-meta renderers are not supported.

Elementor's twelve atomic widgets use one request-local runtime service set even when the host reconstructs separate widget objects. Their optional `event_id` is strictly validated: empty means current context, while any non-empty value must resolve through `resolve_public()` and never falls back. Missing values render only an editor placeholder; public output contains no plugin placeholder or inner wrapper.

Gutenberg's twelve dynamic blocks are registered from dedicated `block.json` metadata and share one request-local PHP adapter. Their integer `eventId` is zero for current context or a positive explicit public source. Current context consumes `postId` and `postType` from `WP_Block`; only when those keys are absent may it use an event queried object. A host wrapper is emitted only for non-empty output and carries native block supports. ServerSideRender owns loading, error and empty placeholders in the editor, so no editor message can enter saved content or public HTML.
