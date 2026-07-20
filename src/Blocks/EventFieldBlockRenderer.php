<?php
/**
 * Server renderer for atomic Gutenberg event-field blocks.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventFieldRenderer;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use WP_Block;

/** Resolves one safe block context and renders exactly one named shared field. */
final readonly class EventFieldBlockRenderer {
	/**
	 * Create a request-shared block adapter.
	 *
	 * @param EventContextResolver $contexts Shared event context resolver.
	 * @param EventFieldRenderer   $fields   Shared named-field renderer.
	 */
	public function __construct(
		private EventContextResolver $contexts = new EventContextResolver(),
		private EventFieldRenderer $fields = new EventFieldRenderer()
	) {}

	/**
	 * Render a dynamic event field.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param string               $content    Saved dynamic block content.
	 * @param WP_Block|null        $block      Runtime block and inherited context.
	 */
	public function render( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $content );

		if ( ! $block instanceof WP_Block ) {
			return '';
		}

		$slug = EventFieldBlockDefinitions::slug( is_string( $block->name ) ? $block->name : '' );

		if ( null === $slug ) {
			return '';
		}

		$presentation = $this->resolve_event( $attributes, $block );
		$output       = null === $presentation ? '' : $this->render_field( $slug, $presentation, $attributes );

		if ( '' === $output ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes(
			array( 'class' => 'wpse-event-field-block wpse-event-field-block-' . $slug )
		);

		return '<div ' . $wrapper . '>' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core creates wrapper attributes and the named renderer owns field escaping.
	}

	/**
	 * Resolve explicit selections strictly and empty selections from block context.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param WP_Block             $block      Runtime block and inherited context.
	 */
	private function resolve_event( array $attributes, WP_Block $block ): ?EventPresentation {
		if ( array_key_exists( 'eventId', $attributes ) && 0 !== $attributes['eventId'] ) {
			$event_id = EventFieldBlockSettings::event_id( $attributes['eventId'] );

			return null === $event_id ? null : $this->contexts->resolve_public( $event_id );
		}

		$context = $block->context;

		if ( array_key_exists( 'postId', $context ) || array_key_exists( 'postType', $context ) ) {
			$post_id   = EventFieldBlockSettings::event_id( $context['postId'] ?? null );
			$post_type = is_string( $context['postType'] ?? null ) ? $context['postType'] : '';

			return null !== $post_id && EventPostType::POST_TYPE === $post_type
				? $this->contexts->resolve_current( $post_id )
				: null;
		}

		$queried_id = get_queried_object_id();

		return $queried_id > 0 && EventPostType::POST_TYPE === get_post_type( $queried_id )
			? $this->contexts->resolve_current( $queried_id )
			: null;
	}

	/**
	 * Render one allowlisted field.
	 *
	 * @param string               $slug         Allowlisted field block slug.
	 * @param EventPresentation    $presentation Resolved event presentation.
	 * @param array<string, mixed> $attributes   Parsed block attributes.
	 */
	private function render_field( string $slug, EventPresentation $presentation, array $attributes ): string {
		return match ( $slug ) {
			'event-title' => $this->fields->title(
				$presentation,
				EventFieldBlockSettings::choice( $attributes['heading'] ?? null, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'h2' ),
				'',
				EventFieldBlockSettings::boolean( $attributes, 'link', false )
			),
			'event-featured-image' => $this->fields->featured_image(
				$presentation,
				EventFieldBlockSettings::choice( $attributes['imageSize'] ?? null, array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'large' ),
				EventFieldBlockSettings::boolean( $attributes, 'link', false ),
				EventFieldBlockSettings::choice( $attributes['altMode'] ?? null, array( 'attachment', 'decorative' ), 'attachment' )
			),
			'event-date-time' => $this->fields->date_time(
				$presentation,
				EventFieldBlockSettings::boolean( $attributes, 'showLabel', true ),
				EventFieldBlockSettings::text( $attributes['label'] ?? null )
			),
			'event-status' => $this->fields->status( $presentation ),
			'event-venue' => $this->fields->venue(
				$presentation,
				EventFieldBlockSettings::boolean( $attributes, 'showLabel', true ),
				EventFieldBlockSettings::text( $attributes['label'] ?? null )
			),
			'event-address'         => $this->fields->address( $presentation ),
			'event-location-link'   => $this->fields->location_action( $presentation, EventFieldBlockSettings::text( $attributes['linkText'] ?? null ) ),
			'event-content'         => $this->fields->content( $presentation ),
			'event-excerpt'         => $this->fields->excerpt( $presentation ),
			'event-external-action' => $this->fields->external_action( $presentation, EventFieldBlockSettings::text( $attributes['linkText'] ?? null ) ),
			'event-categories' => $this->fields->categories(
				$presentation,
				EventFieldBlockSettings::boolean( $attributes, 'showLabel', true ),
				EventFieldBlockSettings::text( $attributes['label'] ?? null )
			),
			'event-tags' => $this->fields->tags(
				$presentation,
				EventFieldBlockSettings::boolean( $attributes, 'showLabel', true ),
				EventFieldBlockSettings::text( $attributes['label'] ?? null )
			),
			default => '',
		};
	}
}
