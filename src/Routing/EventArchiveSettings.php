<?php
/**
 * Native event archive settings.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Routing;

use MiMe\WPSimpleEvents\Domain\EventPeriod;
use MiMe\WPSimpleEvents\Query\EventQueryCriteria;

/**
 * Resolves and sanitizes the small bounded archive configuration surface.
 */
final class EventArchiveSettings {
	public const SLUG_OPTION           = 'wpse_archive_slug';
	public const PER_PAGE_OPTION       = 'wpse_archive_per_page';
	public const DEFAULT_PERIOD_OPTION = 'wpse_archive_default_period';
	public const DEFAULT_SLUG          = 'events';
	public const DEFAULT_PER_PAGE      = 10;
	public const MAX_SLUG_LENGTH       = 200;

	/**
	 * Return the validated single-segment archive slug.
	 */
	public function slug(): string {
		return $this->sanitize_slug( get_option( self::SLUG_OPTION, self::DEFAULT_SLUG ) );
	}

	/**
	 * Return the bounded number of events per archive page.
	 */
	public function per_page(): int {
		$site_default = $this->bounded_integer( get_option( 'posts_per_page', self::DEFAULT_PER_PAGE ) );
		$value        = get_option( self::PER_PAGE_OPTION, $site_default );

		return $this->bounded_integer( $value );
	}

	/**
	 * Return the allowlisted default archive period.
	 */
	public function default_period(): EventPeriod {
		$value = $this->sanitize_default_period( get_option( self::DEFAULT_PERIOD_OPTION, EventPeriod::UPCOMING->value ) );

		return EventPeriod::from( $value );
	}

	/**
	 * Normalize one user-controlled archive path segment.
	 *
	 * @param mixed $value Submitted setting value.
	 */
	public function sanitize_slug( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return self::DEFAULT_SLUG;
		}

		$slug = sanitize_title( sanitize_text_field( $value ) );

		if ( '' === $slug || strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			return self::DEFAULT_SLUG;
		}

		return $slug;
	}

	/**
	 * Normalize one archive page-size setting.
	 *
	 * @param mixed $value Submitted setting value.
	 */
	public function sanitize_per_page( mixed $value ): int {
		return $this->bounded_integer( $value );
	}

	/**
	 * Allow upcoming or all events as the initial archive view.
	 *
	 * @param mixed $value Submitted setting value.
	 */
	public function sanitize_default_period( mixed $value ): string {
		return in_array( $value, array( EventPeriod::UPCOMING->value, EventPeriod::ALL->value ), true )
			? $value
			: EventPeriod::UPCOMING->value;
	}

	/**
	 * Parse an integer inside the public query limit.
	 *
	 * @param mixed $value Candidate integer.
	 */
	private function bounded_integer( mixed $value ): int {
		if ( is_int( $value ) ) {
			$integer = $value;
		} elseif ( is_string( $value ) && '' !== $value && ctype_digit( $value ) ) {
			$integer = (int) $value;
		} else {
			return self::DEFAULT_PER_PAGE;
		}

		return $integer >= 1 && $integer <= EventQueryCriteria::MAX_LIMIT
			? $integer
			: self::DEFAULT_PER_PAGE;
	}
}
