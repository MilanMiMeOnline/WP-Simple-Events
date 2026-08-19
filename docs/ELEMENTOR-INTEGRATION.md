# Elementor integration contract

Elementor is an optional presentation host. MiMe Simple Events and Calendar registers no Elementor class until the public `elementor/loaded` action has fired and the detected version is 3.35.0 or newer. Missing or older Elementor versions never disable event management, native templates, REST endpoints, feeds or shortcodes.

The event post type declares WordPress' public `elementor` feature support. A compatible Elementor installation therefore exposes **Edit with Elementor** for individual Events without MiMe Simple Events and Calendar modifying the user's `elementor_cpt_support` option. Event dates, locations and other native Event details remain managed by the WordPress Event details panel; Elementor edits the event's normal content and layout.

The current compatibility matrix is Elementor 3.35.9 and 4.2.2 on WordPress 7.0.1 with PHP 8.2. The main plugin header records the current tested Elementor release. The matrix must be rerun and the header updated before a release; a passing minimum-version comparison alone is not release evidence for a future major version.

## Widget category and widgets

All widgets appear under **MiMe Simple Events and Calendar**:

- **Event List / Grid** (`wpse-event-list`) controls layout, period, results per page, responsive columns, categories, tags, visitor filters, pagination, card sections, a bounded excerpt length and an H2–H6 title. Style controls cover card background/border/radius, content padding, image ratio, independent responsive row/column gaps, title and button presentation, the filter panel and its form controls, and the pagination container. Columns, excerpt length, title heading, filter and pagination controls appear only while their related feature is active.
- **Event Calendar** (`wpse-event-calendar`) controls desktop/mobile month or list view, a validated initial date, initial categories/tags, visitor filters, navigation, Today, the month/list switcher and the server-fallback heading. `Initial categories` and `Initial tags` constrain both the server fallback and interactive feed even when visitor controls are hidden. `Show visitor filters` remains enabled by default for compatibility, but the complete form is omitted when no non-empty public category or tag can be selected. It exposes separate calendar canvas, today, list-hover, event-chip and normal/hover button colors, plus padding, button border/radius, mobile toolbar gap and distinctly labelled typography controls. It declares the local `wpse-calendar` script dependency. All previously saved control identifiers and their defaults remain backward compatible.
- **Event Details** (`wpse-event-details`) renders the current event or an explicitly selected public preview event. It can hide established field groups, select an H1–H6 title and override bounded field labels. It also exposes text and typography controls plus shared summary, featured-image and external-action presentation controls. Empty fields and an all-hidden component remain omitted by the native details renderer.

The original three widget names, settings and render contracts remain stable. In addition, twelve atomic widgets expose the complete named presentation palette: Event Title, Featured Image, Date & Time, Event Status, Venue, Address, Location Link, Event Content, Event Excerpt, External Event Action, Event Categories and Event Tags. They are dedicated, discoverable widgets rather than a generic metadata widget.

Every atomic widget has the same optional **Event source** selector. Selecting an event uses that published, password-free event as the actual source, which makes the widgets usable on ordinary Elementor Free pages. Leaving it empty consumes the current event context supplied by the page or by a host such as Elementor Pro Theme Builder. Template assignment remains Elementor's responsibility; MiMe Simple Events and Calendar does not require Pro and does not change widget output based on the Elementor edition.

Field-specific controls stay intentionally small. Meaningful labels can be shown, hidden or customized. The title has an allowlisted heading and optional permalink. The image has an allowlisted WordPress image size, attachment-alt/decorative behaviour, optional permalink and responsive width, ratio and radius controls. Location and external actions can override their visible link text without changing their destination; both external destinations open in a new tab with `noopener noreferrer` isolation. The external action also exposes scoped button background, hover, padding, border and radius controls. Date and time continue to inherit WordPress' `date_format` and `time_format`, plus the plugin's global timezone-label choice; a duplicate widget-level clock-format setting is not introduced. Scoped typography, color and spacing controls inherit the theme until explicitly set.

New visual controls deliberately have no saved default. Elementor emits their
wrapper-scoped CSS only after a user chooses a value. Installing an update
therefore preserves theme inheritance and existing page output, while allowing
common background, border and pagination adjustments without page-level CSS.

Every widget declares the shared `wpse-frontend` style. Style selectors use Elementor's `{{WRAPPER}}` token and MiMe Simple Events and Calendar component classes. They do not rely on `.elementor-widget-container`; `has_widget_inner_wrapper()` returns false for Elementor's optimized markup.

## Rendering and security boundary

Elementor settings are treated as untrusted stored input. `WidgetSettings` validates choices, bounds integers, accepts only documented switcher values and sanitizes at most twenty unique term slugs. The resulting values pass through the existing shortcode normalization again before querying or rendering.

The original widgets do not instantiate `WP_Query`, read event metadata or reproduce event HTML. They delegate to the same list, calendar and details shortcode render contracts used outside Elementor. Atomic widgets use the shared `EventContextResolver` and named `EventFieldRenderer`; they never accept a metadata key. Those services enforce public status, empty passwords, query limits, contextual escaping and the existing accessibility markup.

Elementor constructs a new PHP object for every placed widget. `RenderInstanceIds` therefore owns one request-wide counter per rendered component type. This keeps DOM IDs, filter names and pagination namespaces unique even when a page mixes shortcodes and multiple Elementor widgets.

The shared details/atomic event selector queries through `EventRepository` and lists at most fifty published, password-free events. Taxonomy selectors list at most one hundred terms per taxonomy. These deliberate editor-query bounds keep the first version predictable; searchable remote controls can replace them later without changing public render contracts.

When details or atomic output is empty, a clear instruction is shown only inside the Elementor editor. Public requests return an empty string instead of a random event, empty plugin wrapper or editor message. An explicit malformed, private, draft, protected or non-event ID never falls back to current context.

## Assets and compatibility

Widgets use the current `elementor/widgets/register` hook and the official `get_style_depends()` / `get_script_depends()` methods. Deprecated widget registration and dependency properties are not used. The local calendar bundle remains on-demand and no remote asset, map, geocoder or Elementor Pro dependency is introduced.

The calendar bundle progressively enhances calendars present during the normal
document load and registers once with Elementor's supported
`frontend/element_ready/wpse-event-calendar.default` lifecycle. Initialization is
idempotent per rendered root: a repeated hook updates geometry without creating a
second FullCalendar instance, while a replaced widget root receives one fresh
instance. Native pages and sites without Elementor do not load or depend on an
Elementor runtime.

Elementor Pro dynamic tags are optional and deferred. Theme Builder continues to take precedence over native single/archive fallbacks independently of all Free-compatible widgets.
