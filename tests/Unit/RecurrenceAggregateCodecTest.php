<?php
/**
 * Tests for versioned recurrence aggregate storage.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\ManualOccurrence;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEnd;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves canonical round trips and fail-closed nested schema parsing.
 */
#[CoversClass( RecurrenceAggregateCodec::class )]
final class RecurrenceAggregateCodecTest extends TestCase {
	private const SERIES_UID = '019c1d83-1798-4fac-a66d-ae8d67c46319';
	private const MANUAL_ID  = 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320';

	/**
	 * A complete aggregate has one stable nested version-one representation.
	 */
	public function test_complete_aggregate_round_trips_canonically(): void {
		$root_range   = $this->range( '2027-01-04T19:00:00', '2027-01-04T21:00:00' );
		$moved_range  = $this->range( '2027-01-11T20:00:00', '2027-01-11T22:00:00' );
		$manual_range = $this->range( '2027-02-14T18:00:00', '2027-02-14T20:00:00' );
		$aggregate    = RecurrenceAggregate::create(
			self::SERIES_UID,
			'Europe/Brussels',
			array(
				new ScheduleSegment(
					0,
					'2027-01-04T19:00:00',
					$root_range,
					RecurrenceRule::weekly( array( 1 ), 1, RecurrenceEnd::after( 20 ) )
				),
			),
			array( new ManualOccurrence( self::MANUAL_ID, $manual_range ) ),
			array( new OccurrenceExclusion( '2027-01-18T19:00:00', OccurrenceExclusionAction::CANCEL ) ),
			array(
				OccurrenceOverride::from_fields(
					'2027-01-11T19:00:00',
					array(
						OccurrenceOverride::TITLE      => 'Moved workshop',
						OccurrenceOverride::DATE_RANGE => $moved_range,
						OccurrenceOverride::STATUS     => EventStatus::POSTPONED,
					)
				),
			)
		);
		$codec        = new RecurrenceAggregateCodec();
		$encoded      = $codec->encode( $aggregate );

		self::assertSame( RecurrenceAggregate::SCHEMA_VERSION, $encoded['schema_version'] );
		self::assertSame( 'weekly', $encoded['segments'][0]['definition']['frequency'] );
		self::assertSame( '2027-01-11T20:00:00', $encoded['overrides'][0]['fields']['date_range']['start_local'] );
		self::assertSame( $encoded, $codec->encode( $codec->decode( $encoded ) ) );
	}

	/**
	 * Broad reconciliation may persist a detached slot under its generated identity.
	 */
	public function test_detached_generated_manual_identity_round_trips(): void {
		$shape                                = self::valid_shape();
		$shape['manuals']                     = array( self::manual_shape() );
		$shape['manuals'][0]['recurrence_id'] = '2027-02-14T18:00:00';
		$codec                                = new RecurrenceAggregateCodec();

		self::assertSame( $shape, $codec->encode( $codec->decode( $shape ) ) );
	}

	/**
	 * Invalid nested values are never partially accepted as version one.
	 *
	 * @param array<string, mixed> $value Invalid aggregate shape.
	 */
	#[DataProvider( 'invalid_aggregate_provider' )]
	public function test_invalid_aggregate_storage_is_rejected( array $value ): void {
		$this->expectException( InvalidArgumentException::class );

		( new RecurrenceAggregateCodec() )->decode( $value );
	}

	/**
	 * Return malformed root, collection, schedule, exception and override shapes.
	 *
	 * @return iterable<string, array{0: array<string, mixed>}>
	 */
	public static function invalid_aggregate_provider(): iterable {
		$valid = self::valid_shape();

		$wrong_version                   = $valid;
		$wrong_version['schema_version'] = '1';
		yield 'weak schema version' => array( $wrong_version );

		$unknown_root              = $valid;
		$unknown_root['raw_rrule'] = 'FREQ=DAILY';
		yield 'unknown root field' => array( $unknown_root );

		$keyed_segments             = $valid;
		$keyed_segments['segments'] = array( 'root' => $valid['segments'][0] );
		yield 'non-list segments' => array( $keyed_segments );

		$unknown_segment                           = $valid;
		$unknown_segment['segments'][0]['enabled'] = true;
		yield 'unknown segment field' => array( $unknown_segment );

		$raw_rule                              = $valid;
		$raw_rule['segments'][0]['definition'] = array(
			'type'  => 'rrule',
			'value' => 'FREQ=WEEKLY',
		);
		yield 'raw rule definition' => array( $raw_rule );

		$weak_segment_id                      = $valid;
		$weak_segment_id['segments'][0]['id'] = '0';
		yield 'weak segment ID' => array( $weak_segment_id );

		$weak_all_day                                       = $valid;
		$weak_all_day['segments'][0]['template']['all_day'] = 0;
		yield 'weak date boolean' => array( $weak_all_day );

		$invalid_status                         = $valid;
		$invalid_status['manuals']              = array( self::manual_shape() );
		$invalid_status['manuals'][0]['status'] = 'deleted';
		yield 'invalid manual status' => array( $invalid_status );

		$invalid_action               = $valid;
		$invalid_action['exclusions'] = array(
			array(
				'recurrence_id' => '2027-01-11T19:00:00',
				'action'        => 'delete',
			),
		);
		yield 'invalid exclusion action' => array( $invalid_action );

		$unknown_override              = $valid;
		$unknown_override['overrides'] = array(
			array(
				'recurrence_id' => '2027-01-11T19:00:00',
				'fields'        => array( 'content' => 'Not allowed' ),
			),
		);
		yield 'unknown override field' => array( $unknown_override );

		$too_many_manuals            = $valid;
		$too_many_manuals['manuals'] = array_fill( 0, RecurrenceAggregate::MAX_MANUALS + 1, self::manual_shape() );
		yield 'manual collection bound' => array( $too_many_manuals );
	}

	/**
	 * Return one minimal canonical root aggregate shape.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid_shape(): array {
		return array(
			'schema_version' => 1,
			'series_uid'     => self::SERIES_UID,
			'timezone'       => 'Europe/Brussels',
			'segments'       => array(
				array(
					'id'         => 0,
					'anchor'     => '2027-01-04T19:00:00',
					'template'   => array(
						'start_local' => '2027-01-04T19:00:00',
						'end_local'   => '2027-01-04T21:00:00',
						'all_day'     => false,
					),
					'definition' => array(
						'type'      => 'rule',
						'frequency' => 'weekly',
						'interval'  => 1,
						'end'       => array( 'mode' => 'never' ),
						'weekdays'  => array( 1 ),
					),
				),
			),
			'manuals'        => array(),
			'exclusions'     => array(),
			'overrides'      => array(),
		);
	}

	/**
	 * Return one canonical manual occurrence shape.
	 *
	 * @return array<string, mixed>
	 */
	private static function manual_shape(): array {
		return array(
			'recurrence_id' => self::MANUAL_ID,
			'date_range'    => array(
				'start_local' => '2027-02-14T18:00:00',
				'end_local'   => '2027-02-14T20:00:00',
				'all_day'     => false,
			),
			'status'        => 'scheduled',
		);
	}

	/**
	 * Return one canonical timed range in the test timezone.
	 *
	 * @param string $start Canonical local start.
	 * @param string $end   Canonical local end.
	 */
	private function range( string $start, string $end ): EventDateRange {
		return EventDateRange::from_local( $start, $end, false, 'Europe/Brussels' );
	}
}
