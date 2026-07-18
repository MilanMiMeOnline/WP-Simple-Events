# Event structured data

WP Simple Events emits one Schema.org `Event` JSON-LD graph in `wp_head` only on the canonical singular page of a published, password-free event. Archives, taxonomies, shortcodes, Elementor previews, drafts, private events and password-protected events never receive this output.

The graph can contain `name`, `startDate`, `endDate`, `eventStatus`, `url`, `image`, `description` and `location`. Empty optional properties are omitted. The plugin deliberately emits no offers or pricing. All-day values are ISO dates; timed values use the event's captured timezone and include the applicable UTC offset.

Output is enabled by default. The global `wpse_structured_data_enabled` option and the `wpse_structured_data_enabled` filter control it. The filter receives the current boolean decision and event ID:

```php
add_filter(
	'wpse_structured_data_enabled',
	static fn ( bool $enabled, int $event_id ): bool => false,
	10,
	2
);
```

Disable the plugin output when an SEO plugin already owns Event schema. The settings-page checkbox is the user-facing control for the same option.

All schema is derived at request time from public post data and validated event metadata. JSON encoding escapes HTML-significant characters before the graph enters its script element.
