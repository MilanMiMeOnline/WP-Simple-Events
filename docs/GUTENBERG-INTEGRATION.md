# Gutenberg integration contract

Simple Events by MiMe registers twelve public dynamic blocks under **Simple Events by MiMe**: Event Title, Featured Image, Date & Time, Event Status, Venue, Address, Location Link, Event Content, Event Excerpt, External Event Action, Event Categories and Event Tags. Each block has dedicated `block.json` metadata and is discoverable independently; there is no raw-meta block.

## Sources and rendering

Blocks save attributes but no event HTML. WordPress calls the shared PHP renderer on every frontend or editor preview request, so event edits, access checks, date/time formatting and translations remain current without block deprecations or content migrations.

`eventId: 0` means current event context. The renderer first consumes `postId` and `postType` inherited through `WP_Block`, which supports single templates and Query Loop descendants without mutable global-loop assumptions. If those context keys are absent, only an actual queried `wpse_event` may be used. A positive `eventId` is the real source for an ordinary static page and must resolve to a published, password-free event. Invalid values, pages/posts, drafts, private events and protected events return nothing and never fall back.

The blocks delegate to `EventContextResolver` and `EventFieldRenderer`. They do not query posts, read metadata or construct field HTML independently. Request-local positive and negative presentation snapshots are therefore reused across a composed layout. The Event Content block inherits the shared request-wide recursion guard.

## Editor interface

One shared `wpse-event-fields-editor` bundle registers all client interfaces. It is registered with core WordPress script dependencies and enqueued only for block editors. At most fifty published, password-free events are queried through `EventRepository` and localized for the optional source selector; no public request performs that editor query.

Inspector controls mirror the Elementor allowlists:

- title heading and optional event permalink;
- featured-image size, Media Library/decorative alt behaviour and optional permalink;
- label visibility and custom text for date/time, venue, categories and tags;
- visible-text overrides for location and external actions.

ServerSideRender supplies the live preview. Empty fields receive an editor-only placeholder; saved block content and visitor output remain empty. Loading and server errors have distinct editor placeholders.

## Styling and markup

Text fields opt into native text/link colors, typography, margin and wide/full alignment. The image block opts into margin and alignment without irrelevant text controls. WordPress applies these supports to a `.wpse-event-field-block` host wrapper only when the shared field renderer returns content. Inner semantic classes and accessibility behaviour are identical to Elementor and native output.

The editor bundle is never a frontend dependency. The existing `wpse-frontend` component stylesheet remains shared, local and theme-inheriting.

## Patterns and fallback compatibility

The **Single Event Fields** pattern inserts the complete atomic palette as an opt-in starting layout. It neither assigns a template nor replaces customized content. The established `wpse/native-single` and `wpse/native-archive` internal fallback blocks retain their identifiers and composite rendering contracts.
