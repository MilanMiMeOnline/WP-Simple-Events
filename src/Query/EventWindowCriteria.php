<?php
/**
 * Validated public calendar overlap criteria.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Query;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\CalendarWindow;

/**
 * Carries one bounded and permission-safe calendar feed query.
 */
final readonly class EventWindowCriteria {
	public const MAX_LIMIT = 100;
	public const MAX_PAGE  = 1000;

	/**
	 * Store validated overlap criteria.
	 *
	 * @param CalendarWindow $window         Half-open requested interval.
	 * @param int            $limit          Results per page.
	 * @param int            $page           One-based page number.
	 * @param string[]       $category_slugs Event category slugs.
	 * @param string[]       $tag_slugs      Event tag slugs.
	 * @throws InvalidArgumentException When a query boundary is invalid.
	 */
	public function __construct(
		public CalendarWindow $window,
		public int $limit,
		public int $page,
		public array $category_slugs,
		public array $tag_slugs
	) {
		if ( $limit < 1 || $limit > self::MAX_LIMIT ) {
			throw new InvalidArgumentException( 'The calendar query limit is outside the supported range.' );
		}

		if ( $page < 1 || $page > self::MAX_PAGE ) {
			throw new InvalidArgumentException( 'The calendar query page is outside the supported range.' );
		}

		$this->validate_slugs( $category_slugs );
		$this->validate_slugs( $tag_slugs );
	}

	/**
	 * Ensure filter lists remain normalized and bounded.
	 *
	 * @param string[] $slugs Normalized term slugs.
	 * @throws InvalidArgumentException When a slug list is invalid.
	 */
	private function validate_slugs( array $slugs ): void {
		if ( count( $slugs ) > 20 ) {
			throw new InvalidArgumentException( 'A calendar query accepts at most twenty term slugs.' );
		}

		foreach ( $slugs as $slug ) {
			if ( '' === $slug || strlen( $slug ) > 200 ) {
				throw new InvalidArgumentException( 'The calendar query contains an invalid term slug.' );
			}
		}
	}
}
