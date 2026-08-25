<?php
/**
 * Server renderer for primary Gutenberg event components.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\CurrentEventPresentationResolver;
use MiMe\WPSimpleEvents\Frontend\EventDetailsRenderer;
use MiMe\WPSimpleEvents\Shortcode\CalendarShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventListShortcode;
use MiMe\WPSimpleEvents\Shortcode\EventDetailsAttributes;
use MiMe\WPSimpleEvents\Shortcode\ShortcodeRenderer;
use WP_Block;

/** Delegates block output to the existing native public presentation services. */
final readonly class EventCompositeBlockRenderer {
	/**
	 * Create the adapter with request-shared native renderers.
	 *
	 * @param ShortcodeRenderer                $event_list Native event-list adapter.
	 * @param ShortcodeRenderer                $calendar   Native calendar adapter.
	 * @param EventDetailsRenderer             $details    Shared complete-event renderer.
	 * @param CurrentEventPresentationResolver $current Current event or occurrence resolver.
	 */
	public function __construct(
		private ShortcodeRenderer $event_list = new EventListShortcode(),
		private ShortcodeRenderer $calendar = new CalendarShortcode(),
		private EventDetailsRenderer $details = new EventDetailsRenderer(),
		private CurrentEventPresentationResolver $current = new CurrentEventPresentationResolver()
	) {}

	/**
	 * Render one dynamic primary component.
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

		$component = EventCompositeBlockDefinitions::component( is_string( $block->name ) ? $block->name : '' );

		if ( null === $component ) {
			return '';
		}

		$output = match ( $component ) {
			'list'     => $this->event_list->render( EventCompositeBlockSettings::event_list( $attributes ) ),
			'calendar' => $this->calendar->render( EventCompositeBlockSettings::calendar( $attributes ) ),
			'details'  => $this->render_details( $attributes, $block ),
			default    => '',
		};

		if ( '' === $output ) {
			return '';
		}

		$wrapper = get_block_wrapper_attributes(
			array( 'class' => 'wpse-event-composite-block wpse-event-composite-block-' . $component )
		);

		return '<div ' . $wrapper . '>' . $output . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core creates wrapper attributes and shared renderers own contextual escaping.
	}

	/**
	 * Render explicit public details or current event block context.
	 *
	 * @param array<string, mixed> $attributes Parsed block attributes.
	 * @param WP_Block             $block      Runtime block context.
	 */
	private function render_details( array $attributes, WP_Block $block ): string {
		$details = EventDetailsAttributes::from_shortcode( EventCompositeBlockSettings::details( $attributes ) );

		if ( array_key_exists( 'eventId', $attributes ) && 0 !== $attributes['eventId'] ) {
			$event_id = EventCompositeBlockSettings::event_id( $attributes['eventId'] );
			$output   = null === $event_id ? '' : $this->details->render_public( $event_id, $details->options );
		} else {
			$event_id = $this->context_event_id( $block );
			$output   = null === $event_id
				? ''
				: $this->details->render_presentation( $this->current->resolve( $event_id ), $details->options );
		}

		return $output;
	}

	/**
	 * Resolve an event only from valid block context or the event request route.
	 *
	 * @param WP_Block $block Runtime block context.
	 */
	private function context_event_id( WP_Block $block ): ?int {
		$context = $block->context;

		if ( array_key_exists( 'postId', $context ) || array_key_exists( 'postType', $context ) ) {
			$event_id  = EventCompositeBlockSettings::event_id( $context['postId'] ?? null );
			$post_type = is_string( $context['postType'] ?? null ) ? $context['postType'] : '';

			return null !== $event_id && EventPostType::POST_TYPE === $post_type ? $event_id : null;
		}

		$queried_id = get_queried_object_id();

		return $queried_id > 0 && EventPostType::POST_TYPE === get_post_type( $queried_id )
			? $queried_id
			: null;
	}
}
