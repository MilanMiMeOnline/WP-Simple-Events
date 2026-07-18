<?php
/**
 * Public event repository.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use WP_Query;

/**
 * Owns all standalone public event WP_Query creation.
 */
final readonly class EventRepository {
	/**
	 * Create the repository.
	 *
	 * @param EventQueryArguments $arguments Query argument builder.
	 */
	public function __construct( private EventQueryArguments $arguments = new EventQueryArguments() ) {}

	/**
	 * Query public, non-password-protected events.
	 *
	 * @param EventQueryCriteria $criteria Validated query criteria.
	 */
	public function query( EventQueryCriteria $criteria ): WP_Query {
		return new WP_Query( $this->arguments->build( $criteria ) );
	}

	/**
	 * Query public events overlapping one bounded calendar window.
	 *
	 * @param EventWindowCriteria $criteria Validated calendar criteria.
	 */
	public function query_window( EventWindowCriteria $criteria ): WP_Query {
		return new WP_Query( $this->arguments->build_window( $criteria ) );
	}
}
