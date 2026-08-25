<?php
/**
 * Validated public occurrence read model.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Carries one occurrence independently from its canonical series post.
 */
final readonly class OccurrenceReadModel {
	/**
	 * Create a validated read model from a database result row.
	 *
	 * @param array<string, mixed> $row Projection result row.
	 * @throws InvalidArgumentException When persisted projection data is corrupt.
	 */
	public static function from_row( array $row ): self {
		$event_id   = self::positive_integer( $row['event_id'] ?? null );
		$generation = self::positive_integer( $row['generation'] ?? null );
		$segment_id = self::non_negative_integer( $row['segment_id'] ?? null );
		$start_utc  = self::integer( $row['start_utc'] ?? null );
		$end_utc    = self::integer( $row['end_utc'] ?? null );
		$all_day    = self::boolean( $row['all_day'] ?? null );
		$source     = OccurrenceSource::tryFrom( self::scalar_string( $row['source'] ?? null ) );
		$status     = EventStatus::tryFrom( self::scalar_string( $row['event_status'] ?? null ) );

		if ( null === $source || null === $status ) {
			throw new InvalidArgumentException( 'The occurrence projection contains an unsupported enum value.' );
		}

		$date_range = EventDateRange::from_local(
			self::scalar_string( $row['start_local'] ?? null ),
			self::scalar_string( $row['end_local'] ?? null ),
			$all_day,
			self::scalar_string( $row['timezone'] ?? null )
		);

		if ( $date_range->start_utc() !== $start_utc || $date_range->end_utc() !== $end_utc ) {
			throw new InvalidArgumentException( 'The occurrence projection timestamps do not match its canonical local range.' );
		}

		return new self(
			$event_id,
			self::identity_string( $row['public_key'] ?? null, 32 ),
			self::identity_string( $row['recurrence_id'] ?? null, 64 ),
			$generation,
			$segment_id,
			$source,
			$date_range,
			$status
		);
	}

	/**
	 * Store one fully validated occurrence result.
	 *
	 * @param int              $event_id      Canonical series post ID.
	 * @param string           $public_key    Stable public occurrence key.
	 * @param string           $recurrence_id Immutable recurrence identity.
	 * @param int              $generation    Active projection generation.
	 * @param int              $segment_id    Owning schedule segment.
	 * @param OccurrenceSource $source        Occurrence source classification.
	 * @param EventDateRange   $date_range    Canonical effective date range.
	 * @param EventStatus      $status        Effective event status.
	 */
	private function __construct(
		public int $event_id,
		public string $public_key,
		public string $recurrence_id,
		public int $generation,
		public int $segment_id,
		public OccurrenceSource $source,
		public EventDateRange $date_range,
		public EventStatus $status
	) {}

	/**
	 * Parse a positive database integer.
	 *
	 * @param mixed $value Untrusted database value.
	 * @throws InvalidArgumentException When the value is not positive.
	 */
	private static function positive_integer( mixed $value ): int {
		$integer = self::integer( $value );

		if ( $integer <= 0 ) {
			throw new InvalidArgumentException( 'A positive occurrence identifier is required.' );
		}

		return $integer;
	}

	/**
	 * Parse a non-negative database integer.
	 *
	 * @param mixed $value Untrusted database value.
	 * @throws InvalidArgumentException When the value is negative.
	 */
	private static function non_negative_integer( mixed $value ): int {
		$integer = self::integer( $value );

		if ( $integer < 0 ) {
			throw new InvalidArgumentException( 'A non-negative occurrence identifier is required.' );
		}

		return $integer;
	}

	/**
	 * Parse an integer without accepting floats or partial numeric strings.
	 *
	 * @param mixed $value Untrusted database value.
	 * @throws InvalidArgumentException When the value is not a supported integer.
	 */
	private static function integer( mixed $value ): int {
		if ( is_int( $value ) ) {
			return $value;
		}

		if ( ! is_string( $value ) || 1 !== preg_match( '/^-?\d+$/D', $value ) ) {
			throw new InvalidArgumentException( 'The occurrence projection contains an invalid integer.' );
		}

		$integer = filter_var( $value, FILTER_VALIDATE_INT );

		if ( false === $integer ) {
			throw new InvalidArgumentException( 'The occurrence projection integer is outside the supported range.' );
		}

		return $integer;
	}

	/**
	 * Parse a strict database boolean.
	 *
	 * @param mixed $value Untrusted database value.
	 * @throws InvalidArgumentException When the value is not a supported boolean.
	 */
	private static function boolean( mixed $value ): bool {
		return match ( $value ) {
			true, 1, '1'  => true,
			false, 0, '0' => false,
			default        => throw new InvalidArgumentException( 'The occurrence all-day value is invalid.' ),
		};
	}

	/**
	 * Return one scalar database value as a string.
	 *
	 * @param mixed $value Untrusted database value.
	 * @throws InvalidArgumentException When the value is not scalar.
	 */
	private static function scalar_string( mixed $value ): string {
		if ( ! is_scalar( $value ) ) {
			throw new InvalidArgumentException( 'The occurrence projection contains a non-scalar value.' );
		}

		return (string) $value;
	}

	/**
	 * Validate one ASCII identity field.
	 *
	 * @param mixed $value          Untrusted database value.
	 * @param int   $maximum_length Maximum accepted byte length.
	 * @throws InvalidArgumentException When the identity is invalid.
	 */
	private static function identity_string( mixed $value, int $maximum_length ): string {
		$value = self::scalar_string( $value );

		if ( '' === $value || strlen( $value ) > $maximum_length || 1 !== preg_match( '/^[A-Za-z0-9:_-]+$/D', $value ) ) {
			throw new InvalidArgumentException( 'The occurrence projection identity is invalid.' );
		}

		return $value;
	}
}
