<?php
/**
 * Access-aware event presentation context resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use MiMe\WPSimpleEvents\Content\EventPostType;
use WP_Post;

/**
 * Resolves current previews and explicit public selections through one boundary.
 */
final class EventContextResolver {
	/**
	 * Request-local normalized presentations, including negative lookups.
	 *
	 * @var array<int, EventPresentation|null>
	 */
	private array $presentations = array();

	/**
	 * Create the context resolver.
	 *
	 * @param EventPresentationFactory $factory Presentation snapshot factory.
	 */
	public function __construct( private readonly EventPresentationFactory $factory = new EventPresentationFactory() ) {}

	/**
	 * Resolve a current page/template event, allowing authorized editorial previews.
	 *
	 * Password handling remains the renderer's responsibility so the composite
	 * native output can return WordPress' complete password form.
	 *
	 * @param int|null $event_id Explicit context ID or the queried object.
	 */
	public function resolve_current( ?int $event_id = null ): ?EventPresentation {
		$event_id   ??= get_queried_object_id();
		$presentation = $this->load( $event_id );

		if ( null === $presentation ) {
			return null;
		}

		return 'publish' === $presentation->event->post_status
			|| current_user_can( 'read_post', $event_id )
			? $presentation
			: null;
	}

	/**
	 * Resolve an explicit static-page/editor selection without private leakage.
	 *
	 * @param int $event_id Explicit event post ID.
	 */
	public function resolve_public( int $event_id ): ?EventPresentation {
		$presentation = $this->load( $event_id );

		if ( null === $presentation
			|| 'publish' !== $presentation->event->post_status
			|| '' !== $presentation->event->post_password
		) {
			return null;
		}

		return $presentation;
	}

	/**
	 * Load and normalize one event only once for this resolver/request.
	 *
	 * @param int $event_id Event post ID.
	 */
	private function load( int $event_id ): ?EventPresentation {
		if ( $event_id < 1 ) {
			return null;
		}

		if ( array_key_exists( $event_id, $this->presentations ) ) {
			return $this->presentations[ $event_id ];
		}

		$event = get_post( $event_id );

		$this->presentations[ $event_id ] = $event instanceof WP_Post
			&& EventPostType::POST_TYPE === $event->post_type
			? $this->factory->create( $event )
			: null;

		return $this->presentations[ $event_id ];
	}
}
