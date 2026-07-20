# Elementor integration contract

Elementor is an optional presentation host. WP Simple Events registers no Elementor class until the public `elementor/loaded` action has fired and the detected version is 3.35.0 or newer. Missing or older Elementor versions never disable event management, native templates, REST endpoints, feeds or shortcodes.

The event post type declares WordPress' public `elementor` feature support. A compatible Elementor installation therefore exposes **Edit with Elementor** for individual Events without WP Simple Events modifying the user's `elementor_cpt_support` option. Event dates, locations and other native Event details remain managed by the WordPress Event details panel; Elementor edits the event's normal content and layout.

The initial compatibility matrix is Elementor 3.35.9 and 4.1.5 on WordPress 7.0.1 with PHP 8.3. The main plugin header records the current tested Elementor release. The matrix must be rerun and the header updated before a release; a passing minimum-version comparison alone is not release evidence for a future major version.

## Widget category and widgets

All widgets appear under **WP Simple Events**:

- **Event List / Grid** (`wpse-event-list`) controls layout, period, results per page, responsive columns, categories, tags, visitor filters, pagination, image, excerpt and location. Style controls cover text, secondary text, spacing, card borders/radius, title and button typography, and button colors/borders.
- **Event Calendar** (`wpse-event-calendar`) controls desktop/mobile month or list view, initial categories/tags and visitor filters. `Initial categories` and `Initial tags` constrain both the server fallback and interactive feed even when visitor controls are hidden. `Show visitor filters` remains enabled by default for compatibility, but the complete form is omitted when no non-empty public category or tag can be selected. It exposes text, accent, on-accent and border colors plus distinctly labelled calendar/button typography controls, and declares the local `wpse-calendar` script dependency. Accent/on-accent colors deterministically control hover, focus, pressed and selected toolbar states; all stable saved control identifiers remain backward compatible.
- **Event Details** (`wpse-event-details`) renders the current event or an explicitly selected public preview event. It exposes text, border and typography controls. Empty fields remain omitted by the native details renderer.

The original three widget names, settings and render contracts remain stable. In addition, twelve atomic widgets expose the complete named presentation palette: Event Title, Featured Image, Date & Time, Event Status, Venue, Address, Location Link, Event Content, Event Excerpt, External Event Action, Event Categories and Event Tags. They are dedicated, discoverable widgets rather than a generic metadata widget.

Every atomic widget has the same optional **Event source** selector. Selecting an event uses that published, password-free event as the actual source, which makes the widgets usable on ordinary Elementor Free pages. Leaving it empty consumes the current event context supplied by the page or by a host such as Elementor Pro Theme Builder. Template assignment remains Elementor's responsibility; WP Simple Events does not require Pro and does not change widget output based on the Elementor edition.

Field-specific controls stay intentionally small. Meaningful labels can be shown, hidden or customized. The title has an allowlisted heading and optional permalink. The image has an allowlisted WordPress image size, attachment-alt/decorative behaviour and optional permalink. Location and external actions can override their visible link text without changing their destination; both external destinations open in a new tab with `noopener noreferrer` isolation. Date and time continue to inherit WordPress' `date_format` and `time_format`, plus the plugin's global timezone-label choice; a duplicate widget-level clock-format setting is not introduced. Scoped typography, color and spacing controls inherit the theme until explicitly set.

Every widget declares the shared `wpse-frontend` style. Style selectors use Elementor's `{{WRAPPER}}` token and WP Simple Events component classes. They do not rely on `.elementor-widget-container`; `has_widget_inner_wrapper()` returns false for Elementor's optimized markup.

## Rendering and security boundary

Elementor settings are treated as untrusted stored input. `WidgetSettings` validates choices, bounds integers, accepts only documented switcher values and sanitizes at most twenty unique term slugs. The resulting values pass through the existing shortcode normalization again before querying or rendering.

The original widgets do not instantiate `WP_Query`, read event metadata or reproduce event HTML. They delegate to the same list, calendar and details shortcode render contracts used outside Elementor. Atomic widgets use the shared `EventContextResolver` and named `EventFieldRenderer`; they never accept a metadata key. Those services enforce public status, empty passwords, query limits, contextual escaping and the existing accessibility markup.

Elementor constructs a new PHP object for every placed widget. `RenderInstanceIds` therefore owns one request-wide counter per rendered component type. This keeps DOM IDs, filter names and pagination namespaces unique even when a page mixes shortcodes and multiple Elementor widgets.

The shared details/atomic event selector queries through `EventRepository` and lists at most fifty published, password-free events. Taxonomy selectors list at most one hundred terms per taxonomy. These deliberate editor-query bounds keep the first version predictable; searchable remote controls can replace them later without changing public render contracts.

When details or atomic output is empty, a clear instruction is shown only inside the Elementor editor. Public requests return an empty string instead of a random event, empty plugin wrapper or editor message. An explicit malformed, private, draft, protected or non-event ID never falls back to current context.

## Assets and compatibility

Widgets use the current `elementor/widgets/register` hook and the official `get_style_depends()` / `get_script_depends()` methods. Deprecated widget registration and dependency properties are not used. The local calendar bundle remains on-demand and no remote asset, map, geocoder or Elementor Pro dependency is introduced.

Elementor Pro dynamic tags are optional and deferred. Theme Builder continues to take precedence over native single/archive fallbacks independently of all Free-compatible widgets.
