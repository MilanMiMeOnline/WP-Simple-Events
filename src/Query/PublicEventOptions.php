<?php
/**
 * Bounded public event choices for visual editors.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use MiMe\WPSimpleEvents\Domain\EventPeriod;
use WP_Post;

/** Supplies published, password-free events through the shared repository. */
final class PublicEventOptions {
	/**
	 * Request-local cached choices.
	 *
	 * @var array<int, string>|null
	 */
	private ?array $options = null;

	/**
	 * Create the bounded provider.
	 *
	 * @param EventRepository $events Shared permission-safe event repository.
	 */
	public function __construct( private readonly EventRepository $events = new EventRepository() ) {}

	/**
	 * Return select-control options keyed by event ID.
	 *
	 * @return array<int, string>
	 */
	public function options(): array {
		if ( null !== $this->options ) {
			return $this->options;
		}

		$query   = $this->events->query(
			new EventQueryCriteria( EventPeriod::ALL, EventQueryCriteria::MAX_LIMIT, 1, array(), array(), time() )
		);
		$options = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post || '' !== $post->post_password ) {
				continue;
			}

			$options[ $post->ID ] = sanitize_text_field( get_the_title( $post ) );
		}

		$this->options = $options;

		return $this->options;
	}
}
