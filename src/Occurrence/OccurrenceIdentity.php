<?php
/**
 * Immutable occurrence identity.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Occurrence;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Keeps an occurrence URL stable when its effective date changes.
 */
final readonly class OccurrenceIdentity {
	private const PUBLIC_KEY_CONTEXT = "mime-simple-events-calendar\0occurrence\0";
	private const UUID_PATTERN       = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';
	private const MANUAL_PATTERN     = '/^manual:[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D';

	/**
	 * Build an identity for one generated local recurrence slot.
	 *
	 * @param string $series_uid    Immutable series UUID.
	 * @param string $recurrence_id Original local slot, `one-off`, or manual UUID identity.
	 * @throws InvalidArgumentException When either identity component is invalid.
	 */
	public static function from( string $series_uid, string $recurrence_id ): self {
		$series_uid    = strtolower( trim( $series_uid ) );
		$recurrence_id = trim( $recurrence_id );

		if ( str_starts_with( strtolower( $recurrence_id ), 'manual:' ) ) {
			$recurrence_id = strtolower( $recurrence_id );
		}

		if ( 1 !== preg_match( self::UUID_PATTERN, $series_uid ) ) {
			throw new InvalidArgumentException( 'The occurrence series UID must be a canonical UUID.' );
		}

		if ( ! self::valid_recurrence_id( $recurrence_id ) ) {
			throw new InvalidArgumentException( 'The occurrence recurrence identity is invalid.' );
		}

		$public_key = substr(
			hash( 'sha256', self::PUBLIC_KEY_CONTEXT . $series_uid . "\0" . $recurrence_id ),
			0,
			32
		);

		return new self( $series_uid, $recurrence_id, $public_key );
	}

	/**
	 * Store validated identity fields.
	 *
	 * @param string $series_uid    Canonical immutable series UUID.
	 * @param string $recurrence_id Original recurrence slot or manual identity.
	 * @param string $public_key    Deterministic public occurrence key.
	 */
	private function __construct(
		private string $series_uid,
		private string $recurrence_id,
		private string $public_key
	) {}

	/**
	 * Return the immutable series UUID.
	 */
	public function series_uid(): string {
		return $this->series_uid;
	}

	/**
	 * Return the original recurrence slot or manual identity.
	 */
	public function recurrence_id(): string {
		return $this->recurrence_id;
	}

	/**
	 * Return the deterministic public occurrence key.
	 */
	public function public_key(): string {
		return $this->public_key;
	}

	/**
	 * Validate a canonical generated or manual recurrence identity.
	 *
	 * @param string $value Candidate recurrence identity.
	 */
	public static function valid_recurrence_id( string $value ): bool {
		if ( 'one-off' === $value || 1 === preg_match( self::MANUAL_PATTERN, $value ) ) {
			return true;
		}

		$format = 10 === strlen( $value ) ? 'Y-m-d' : 'Y-m-d\TH:i:s';
		$date   = DateTimeImmutable::createFromFormat( '!' . $format, $value, new DateTimeZone( 'UTC' ) );

		return false !== $date && $date->format( $format ) === $value;
	}

	/**
	 * Determine whether a value identifies a generated local recurrence slot.
	 *
	 * @param string $value Candidate generated identity.
	 */
	public static function is_generated_recurrence_id( string $value ): bool {
		return 'one-off' !== $value
			&& ! str_starts_with( strtolower( $value ), 'manual:' )
			&& self::valid_recurrence_id( $value );
	}

	/**
	 * Determine whether a value is one canonical manual-occurrence identity.
	 *
	 * @param string $value Candidate manual identity.
	 */
	public static function is_manual_recurrence_id( string $value ): bool {
		return 1 === preg_match( self::MANUAL_PATTERN, $value );
	}
}
