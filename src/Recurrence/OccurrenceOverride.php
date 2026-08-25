<?php
/**
 * Sparse occurrence overrides.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Holds only fields deliberately changed for one immutable occurrence identity.
 */
final readonly class OccurrenceOverride {
	public const TITLE             = 'title';
	public const NOTE              = 'note';
	public const FEATURED_IMAGE_ID = 'featured_image_id';
	public const DATE_RANGE        = 'date_range';
	public const STATUS            = 'status';
	public const VENUE             = 'venue';
	public const ADDRESS           = 'address';
	public const LOCATION_URL      = 'location_url';
	public const EVENT_URL         = 'event_url';
	public const EVENT_URL_LABEL   = 'event_url_label';

	public const MAX_TITLE_LENGTH   = 200;
	public const MAX_NOTE_LENGTH    = 1_000;
	public const MAX_VENUE_LENGTH   = 200;
	public const MAX_ADDRESS_LENGTH = 500;
	public const MAX_URL_LENGTH     = 2_048;
	public const MAX_LABEL_LENGTH   = 120;

	private const CONTROL_CHARACTERS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/D';

	/**
	 * Create one complete sparse override from normalized application values.
	 *
	 * Empty venue, address and URL values deliberately hide an inherited value.
	 * Removing a key restores inheritance.
	 *
	 * @param string $recurrence_id Original generated or manual recurrence identity.
	 * @param array  $fields        Sparse allowlisted field map.
	 * @phpstan-param array<array-key, EventDateRange|EventStatus|int|string> $fields
	 * @throws InvalidArgumentException When an identity, key or value is unsupported.
	 */
	public static function from_fields( string $recurrence_id, array $fields ): self {
		if ( 'one-off' === $recurrence_id || ! OccurrenceIdentity::valid_recurrence_id( $recurrence_id ) ) {
			throw new InvalidArgumentException( 'An occurrence override requires a generated or manual identity.' );
		}

		if ( array() === $fields ) {
			throw new InvalidArgumentException( 'An occurrence override must change at least one field.' );
		}

		$normalized = array();

		foreach ( $fields as $field => $value ) {
			if ( ! is_string( $field ) || array_key_exists( $field, $normalized ) ) {
				throw new InvalidArgumentException( 'An occurrence override field name is invalid.' );
			}

			$normalized[ $field ] = self::validate_field( $field, $value );
		}

		ksort( $normalized, SORT_STRING );

		return new self( $recurrence_id, $normalized );
	}

	/**
	 * Store one validated sparse override.
	 *
	 * @param string $recurrence_id Original recurrence identity.
	 * @param array  $fields        Canonically ordered field map.
	 * @phpstan-param array<string, EventDateRange|EventStatus|int|string> $fields
	 */
	private function __construct(
		public string $recurrence_id,
		private array $fields
	) {}

	/**
	 * Return the canonically ordered sparse fields.
	 *
	 * @return array<string, EventDateRange|EventStatus|int|string>
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Validate one field through its exact domain contract.
	 *
	 * @param string                                $field Allowlisted field name candidate.
	 * @param EventDateRange|EventStatus|int|string $value Candidate normalized value.
	 * @return EventDateRange|EventStatus|int|string
	 * @throws InvalidArgumentException When the field or value is unsupported.
	 */
	private static function validate_field(
		string $field,
		EventDateRange|EventStatus|int|string $value
	): EventDateRange|EventStatus|int|string {
		return match ( $field ) {
			self::TITLE             => self::text( $value, self::MAX_TITLE_LENGTH, false, false ),
			self::NOTE              => self::text( $value, self::MAX_NOTE_LENGTH, false, true ),
			self::FEATURED_IMAGE_ID => self::featured_image_id( $value ),
			self::DATE_RANGE        => self::date_range( $value ),
			self::STATUS            => self::status( $value ),
			self::VENUE             => self::text( $value, self::MAX_VENUE_LENGTH, true, false ),
			self::ADDRESS           => self::text( $value, self::MAX_ADDRESS_LENGTH, true, true ),
			self::LOCATION_URL,
			self::EVENT_URL         => self::url( $value ),
			self::EVENT_URL_LABEL   => self::text( $value, self::MAX_LABEL_LENGTH, false, false ),
			default                 => throw new InvalidArgumentException( 'The occurrence override field is unsupported.' ),
		};
	}

	/**
	 * Validate one bounded canonical text value.
	 *
	 * @param EventDateRange|EventStatus|int|string $value         Candidate value.
	 * @param int                                   $maximum       Maximum character count.
	 * @param bool                                  $allow_empty   Whether empty text hides inheritance.
	 * @param bool                                  $allow_newline Whether line breaks are meaningful.
	 * @throws InvalidArgumentException When text is not canonical or bounded.
	 */
	private static function text(
		EventDateRange|EventStatus|int|string $value,
		int $maximum,
		bool $allow_empty,
		bool $allow_newline
	): string {
		if ( ! is_string( $value ) || trim( $value ) !== $value ) {
			throw new InvalidArgumentException( 'Occurrence override text must be a canonical string.' );
		}

		if ( ( ! $allow_empty && '' === $value ) || self::length( $value ) > $maximum ) {
			throw new InvalidArgumentException( 'Occurrence override text is empty or exceeds its limit.' );
		}

		if ( 1 === preg_match( self::CONTROL_CHARACTERS, $value ) || ( ! $allow_newline && str_contains( $value, "\n" ) ) ) {
			throw new InvalidArgumentException( 'Occurrence override text contains unsupported control characters.' );
		}

		return $value;
	}

	/**
	 * Validate an attachment ID, using zero as an explicit no-image override.
	 *
	 * @param EventDateRange|EventStatus|int|string $value Candidate value.
	 * @throws InvalidArgumentException When the value is not non-negative.
	 */
	private static function featured_image_id( EventDateRange|EventStatus|int|string $value ): int {
		if ( ! is_int( $value ) || $value < 0 ) {
			throw new InvalidArgumentException( 'An occurrence featured-image override must be a non-negative integer.' );
		}

		return $value;
	}

	/**
	 * Validate a complete date-range override.
	 *
	 * @param EventDateRange|EventStatus|int|string $value Candidate value.
	 * @throws InvalidArgumentException When the value is not a date range.
	 */
	private static function date_range( EventDateRange|EventStatus|int|string $value ): EventDateRange {
		if ( ! $value instanceof EventDateRange ) {
			throw new InvalidArgumentException( 'An occurrence date override must be a validated range.' );
		}

		return $value;
	}

	/**
	 * Validate an effective occurrence status override.
	 *
	 * @param EventDateRange|EventStatus|int|string $value Candidate value.
	 * @throws InvalidArgumentException When the value is not a status enum.
	 */
	private static function status( EventDateRange|EventStatus|int|string $value ): EventStatus {
		if ( ! $value instanceof EventStatus ) {
			throw new InvalidArgumentException( 'An occurrence status override must be a validated status.' );
		}

		return $value;
	}

	/**
	 * Validate an optional external HTTP(S) destination.
	 *
	 * @param EventDateRange|EventStatus|int|string $value Candidate value.
	 * @throws InvalidArgumentException When the value is not an allowed URL.
	 */
	private static function url( EventDateRange|EventStatus|int|string $value ): string {
		if ( ! is_string( $value ) || trim( $value ) !== $value || self::length( $value ) > self::MAX_URL_LENGTH ) {
			throw new InvalidArgumentException( 'An occurrence URL override must be a bounded canonical string.' );
		}

		if ( '' === $value ) {
			return '';
		}

		if ( false === filter_var( $value, FILTER_VALIDATE_URL ) || 1 !== preg_match( '#^https?://#i', $value ) ) {
			throw new InvalidArgumentException( 'An occurrence URL override must use HTTP or HTTPS.' );
		}

		return $value;
	}

	/**
	 * Count characters without requiring the optional mbstring extension.
	 *
	 * @param string $value Canonical text.
	 */
	private static function length( string $value ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
