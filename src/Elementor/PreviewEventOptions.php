<?php
/**
 * Bounded Elementor event preview choices.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;
use MiMe\WPSimpleEvents\Query\EventRepository;
use WP_Post;

/**
 * Supplies published, password-free events through the shared repository.
 */
final readonly class PreviewEventOptions {
	/**
	 * Create the bounded preview provider.
	 *
	 * @param EventRepository $events Shared permission-safe event repository.
	 */
	public function __construct( private EventRepository $events = new EventRepository() ) {}

	/**
	 * Return select-control options keyed by event ID.
	 *
	 * @return array<int, string>
	 */
	public function options(): array {
		$query   = $this->events->query(
			new EventQueryCriteria( EventPeriod::ALL, EventQueryCriteria::MAX_LIMIT, 1, array(), array(), time() )
		);
		$options = array();

		foreach ( $query->posts as $post ) {
			if ( ! $post instanceof WP_Post ) {
				continue;
			}

			$options[ $post->ID ] = get_the_title( $post );
		}

		return $options;
	}
}
