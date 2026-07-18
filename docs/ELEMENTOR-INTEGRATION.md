# Elementor integration contract

Elementor is an optional presentation host. WP Simple Events registers no Elementor class until the public `elementor/loaded` action has fired and the detected version is 3.35.0 or newer. Missing or older Elementor versions never disable event management, native templates, REST endpoints, feeds or shortcodes.

The initial compatibility matrix is Elementor 3.35.9 and 4.1.5 on WordPress 7.0.1 with PHP 8.3. The main plugin header records the current tested Elementor release. The matrix must be rerun and the header updated before a release; a passing minimum-version comparison alone is not release evidence for a future major version.

## Widget category and widgets

All widgets appear under **WP Simple Events**:

- **Event List / Grid** (`wpse-event-list`) controls layout, period, results per page, responsive columns, categories, tags, visitor filters, pagination, image, excerpt and location. Style controls cover text, secondary text, spacing, card borders/radius, title and button typography, and button colors/borders.
- **Event Calendar** (`wpse-event-calendar`) controls desktop/mobile month or list view, categories, tags and visitor filters. It exposes calendar colors and typography and declares the local `wpse-calendar` script dependency.
- **Event Details** (`wpse-event-details`) renders the current event or an explicitly selected public preview event. It exposes text, border and typography controls. Empty fields remain omitted by the native details renderer.

Every widget declares the shared `wpse-frontend` style. Style selectors use Elementor's `{{WRAPPER}}` token and WP Simple Events component classes. They do not rely on `.elementor-widget-container`; `has_widget_inner_wrapper()` returns false for Elementor's optimized markup.

## Rendering and security boundary

Elementor settings are treated as untrusted stored input. `WidgetSettings` validates choices, bounds integers, accepts only documented switcher values and sanitizes at most twenty unique term slugs. The resulting values pass through the existing shortcode normalization again before querying or rendering.

Widgets do not instantiate `WP_Query`, read event metadata or reproduce event HTML. They delegate to the same list, calendar and details shortcode render contracts used outside Elementor. Those services enforce public status, empty passwords, query limits, contextual escaping and the existing accessibility markup.

Elementor constructs a new PHP object for every placed widget. `RenderInstanceIds` therefore owns one request-wide counter per rendered component type. This keeps DOM IDs, filter names and pagination namespaces unique even when a page mixes shortcodes and multiple Elementor widgets.

The details preview selector queries through `EventRepository` and lists at most fifty published, password-free events. Taxonomy selectors list at most one hundred terms per taxonomy. These deliberate editor-query bounds keep the first version predictable; searchable remote controls can replace them later without changing public render contracts.

When details output is empty, a clear instruction is shown only inside the Elementor editor. Public requests return an empty string instead of a random event or editor message.

## Assets and compatibility

Widgets use the current `elementor/widgets/register` hook and the official `get_style_depends()` / `get_script_depends()` methods. Deprecated widget registration and dependency properties are not used. The local calendar bundle remains on-demand and no remote asset, map, geocoder or Elementor Pro dependency is introduced.

Elementor Pro dynamic tags are optional and deferred. Theme Builder continues to take precedence over native single/archive fallbacks independently of the three Free widgets.
