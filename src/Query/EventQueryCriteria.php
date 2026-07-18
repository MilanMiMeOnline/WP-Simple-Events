<?php
/**
 * Validated public event query criteria.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventPeriod;

/**
 * Carries a bounded, permission-safe event query contract.
 */
final readonly class EventQueryCriteria {
	public const MAX_LIMIT = 50;
	public const MAX_PAGE  = 1000;

	/**
	 * Create validated event query criteria.
	 *
	 * @param EventPeriod $period         Upcoming, past or all events.
	 * @param int         $limit          Results per page.
	 * @param int         $page           One-based page number.
	 * @param string[]    $category_slugs Event category slugs.
	 * @param string[]    $tag_slugs      Event tag slugs.
	 * @param int         $now_utc        Current Unix timestamp.
	 * @throws InvalidArgumentException When a boundary is invalid.
	 */
	public function __construct(
		public EventPeriod $period,
		public int $limit,
		public int $page,
		public array $category_slugs,
		public array $tag_slugs,
		public int $now_utc
	) {
		if ( $limit < 1 || $limit > self::MAX_LIMIT ) {
			throw new InvalidArgumentException( 'The event query limit is outside the supported range.' );
		}

		if ( $page < 1 || $page > self::MAX_PAGE ) {
			throw new InvalidArgumentException( 'The event query page is outside the supported range.' );
		}

		if ( $now_utc < 0 ) {
			throw new InvalidArgumentException( 'The event query timestamp cannot be negative.' );
		}

		$this->validate_slugs( $category_slugs );
		$this->validate_slugs( $tag_slugs );
	}

	/**
	 * Ensure filter lists remain small and contain normalized scalar values.
	 *
	 * @param string[] $slugs Normalized term slugs.
	 * @throws InvalidArgumentException When a slug list is invalid.
	 */
	private function validate_slugs( array $slugs ): void {
		if ( count( $slugs ) > 20 ) {
			throw new InvalidArgumentException( 'An event query accepts at most twenty term slugs.' );
		}

		foreach ( $slugs as $slug ) {
			if ( '' === $slug || strlen( $slug ) > 200 ) {
				throw new InvalidArgumentException( 'The event query contains an invalid term slug.' );
			}
		}
	}
}
