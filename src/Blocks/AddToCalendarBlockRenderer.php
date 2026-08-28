<?php
/**
 * Dynamic Gutenberg add-to-calendar block rendering.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarOptions;
use MiMe\WPSimpleEvents\CalendarExport\AddToCalendarRenderer;
use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Block;

/** Selects one safe block context and delegates the complete action contract. */
final readonly class AddToCalendarBlockRenderer {
	/**
	 * Create the thin block adapter.
	 *
	 * @param AddToCalendarRenderer $renderer Shared occurrence-aware renderer.
	 */
	public function __construct(
		private AddToCalendarRenderer $renderer = new AddToCalendarRenderer()
	) {}

	/**
	 * Render one explicit public one-off event or the exact current context.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param string               $content    Saved dynamic content.
	 * @param WP_Block|null        $block      Runtime block and inherited context.
	 */
	public function render( array $attributes = array(), string $content = '', ?WP_Block $block = null ): string {
		unset( $content );

		if ( ! $block instanceof WP_Block ) {
			return '';
		}

		$options  = AddToCalendarOptions::from_input(
			$attributes['providers'] ?? null,
			$attributes['layout'] ?? null,
			$attributes['label'] ?? null
		);
		$explicit = array_key_exists( 'eventId', $attributes ) && 0 !== $attributes['eventId'];

		if ( $explicit ) {
			$event_id = EventFieldBlockSettings::event_id( $attributes['eventId'] );
			$output   = null === $event_id ? '' : $this->renderer->render_public( $event_id, $options );
		} else {
			$event_id = $this->current_event_id( $block );
			$output   = $event_id > 0 ? $this->renderer->render_current( $event_id, $options ) : '';
		}

		if ( '' === $output ) {
			return '';
		}

		$extra = array( 'class' => 'wpse-add-to-calendar-block' );
		$style = AddToCalendarBlockSettings::style( $attributes );

		if ( '' !== $style ) {
			$extra['style'] = $style;
		}

		return '<div ' . get_block_wrapper_attributes( $extra ) . '>' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core owns wrapper escaping and the shared renderer owns action escaping.
	}

	/**
	 * Resolve only a real event context; never fall back from another post type.
	 *
	 * @param WP_Block $block Runtime block and inherited context.
	 */
	private function current_event_id( WP_Block $block ): int {
		$context = $block->context;

		if ( array_key_exists( 'postId', $context ) || array_key_exists( 'postType', $context ) ) {
			$event_id  = EventFieldBlockSettings::event_id( $context['postId'] ?? null );
			$post_type = is_string( $context['postType'] ?? null ) ? $context['postType'] : '';

			return null !== $event_id && EventPostType::POST_TYPE === $post_type ? $event_id : 0;
		}

		$event_id = get_queried_object_id();

		return $event_id > 0 && EventPostType::POST_TYPE === get_post_type( $event_id ) ? $event_id : 0;
	}
}
