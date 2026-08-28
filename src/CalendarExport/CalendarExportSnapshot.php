<?php
/**
 * Immutable public event snapshot for one calendar action.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;

/**
 * Carries only bounded, already-public values into calendar providers.
 */
final readonly class CalendarExportSnapshot {
	public const MAX_TITLE_BYTES       = 800;
	public const MAX_DESCRIPTION_BYTES = 8_000;
	public const MAX_LOCATION_BYTES    = 1_600;
	public const MAX_URL_BYTES         = 2_048;
	public const MAX_FILENAME_BYTES    = 100;

	/**
	 * Store one validated public snapshot.
	 *
	 * @param int                $event_id         Canonical event post ID.
	 * @param OccurrenceIdentity $identity         Stable one-off or exact-occurrence identity.
	 * @param string             $title            Bounded plain-text title.
	 * @param string             $canonical_url    Public HTTP(S) event or occurrence URL.
	 * @param EventDateRange     $date_range        Exact validated event boundaries.
	 * @param EventStatus        $status            Scheduled or postponed status.
	 * @param string             $description       Optional bounded plain-text description.
	 * @param string             $location          Optional bounded plain-text location.
	 * @param int                $last_modified_utc Deterministic positive UTC timestamp.
	 * @param string             $filename          Safe lowercase attachment basename without extension.
	 * @throws InvalidArgumentException When one provider boundary value is unsafe.
	 */
	public function __construct(
		public int $event_id,
		public OccurrenceIdentity $identity,
		public string $title,
		public string $canonical_url,
		public EventDateRange $date_range,
		public EventStatus $status,
		public string $description,
		public string $location,
		public int $last_modified_utc,
		public string $filename
	) {
		if ( $event_id <= 0 ) {
			throw new InvalidArgumentException( 'A positive calendar event ID is required.' );
		}

		$this->assert_text( $title, self::MAX_TITLE_BYTES, false );
		$this->assert_text( $description, self::MAX_DESCRIPTION_BYTES, true );
		$this->assert_text( $location, self::MAX_LOCATION_BYTES, true );

		if ( EventStatus::CANCELLED === $status ) {
			throw new InvalidArgumentException( 'Cancelled events cannot create an add-to-calendar snapshot.' );
		}

		if ( $last_modified_utc <= 0 ) {
			throw new InvalidArgumentException( 'A deterministic calendar modification timestamp is required.' );
		}

		if ( '' === $canonical_url
			|| strlen( $canonical_url ) > self::MAX_URL_BYTES
			|| false === filter_var( $canonical_url, FILTER_VALIDATE_URL )
			|| ! in_array( strtolower( (string) wp_parse_url( $canonical_url, PHP_URL_SCHEME ) ), array( 'http', 'https' ), true )
		) {
			throw new InvalidArgumentException( 'A bounded public HTTP(S) calendar URL is required.' );
		}

		if ( '' === $filename
			|| strlen( $filename ) > self::MAX_FILENAME_BYTES
			|| 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $filename )
		) {
			throw new InvalidArgumentException( 'The calendar attachment filename is invalid.' );
		}
	}

	/**
	 * Validate one bounded UTF-8 provider value.
	 *
	 * @param string $value       Candidate value.
	 * @param int    $maximum     Maximum bytes.
	 * @param bool   $allow_empty Whether an empty value is valid.
	 * @throws InvalidArgumentException When the value is malformed or oversized.
	 */
	private function assert_text( string $value, int $maximum, bool $allow_empty ): void {
		if ( ( ! $allow_empty && '' === trim( $value ) )
			|| strlen( $value ) > $maximum
			|| 1 !== preg_match( '//u', $value )
			|| str_contains( $value, "\0" )
		) {
			throw new InvalidArgumentException( 'The calendar text value is invalid.' );
		}
	}
}
