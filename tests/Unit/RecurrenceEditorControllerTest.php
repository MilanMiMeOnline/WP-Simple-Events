<?php
/**
 * Tests for recurrence editor REST validation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Rest\RecurrenceEditorController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact aggregate, identity, date and revision request boundaries.
 */
#[CoversClass( RecurrenceEditorController::class )]
final class RecurrenceEditorControllerTest extends TestCase {
	/**
	 * Complete canonical aggregates pass while partial or unknown shapes fail.
	 */
	public function test_aggregate_validation_uses_exact_codec(): void {
		$controller           = new RecurrenceEditorController();
		$shape                = ( new RecurrenceAggregateCodec() )->encode( $this->aggregate() );
		$unknown              = $shape;
		$unknown['raw_rrule'] = 'FREQ=DAILY';

		self::assertTrue( $controller->valid_aggregate( $shape ) );
		self::assertFalse( $controller->valid_aggregate( $unknown ) );
		self::assertFalse( $controller->valid_aggregate( array( 'schema_version' => 1 ) ) );
	}

	/**
	 * Scalar validators reject weak, malformed and one-off mutation identities.
	 */
	public function test_scalar_request_validators_are_strict(): void {
		$controller = new RecurrenceEditorController();
		$revision   = ( new RecurrenceAggregateRevision() )->token( null );

		self::assertTrue( $controller->valid_revision( $revision ) );
		self::assertFalse( $controller->valid_revision( strtoupper( $revision ) ) );
		self::assertTrue( $controller->valid_target( '' ) );
		self::assertTrue( $controller->valid_target( '2027-01-04T19:00:00' ) );
		self::assertFalse( $controller->valid_target( 'one-off' ) );
		self::assertTrue( $controller->valid_required_target( '2027-01-04T19:00:00' ) );
		self::assertTrue( $controller->valid_required_target( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320' ) );
		self::assertFalse( $controller->valid_required_target( '' ) );
		self::assertFalse( $controller->valid_required_target( 'one-off' ) );
		self::assertTrue( $controller->valid_generated_target( '2027-01-04T19:00:00' ) );
		self::assertFalse( $controller->valid_generated_target( 'manual:019c1d83-1798-4fac-a66d-ae8d67c46320' ) );
		self::assertTrue( $controller->valid_date( '2027-01-04' ) );
		self::assertFalse( $controller->valid_date( '04/01/2027' ) );
	}

	/**
	 * Following replacements accept exact objects and reject client timezone data.
	 */
	public function test_following_replacement_validation_is_exact(): void {
		$controller                      = new RecurrenceEditorController();
		$value                           = array(
			'template'   => array(
				'start_local' => '2027-01-05T20:00:00',
				'end_local'   => '2027-01-05T22:00:00',
				'all_day'     => false,
			),
			'definition' => array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => 2,
				'end'       => array( 'mode' => 'never' ),
			),
		);
		$unknown                         = $value;
		$unknown['template']['timezone'] = 'UTC';

		self::assertTrue( $controller->valid_following_replacement( $value ) );
		self::assertFalse( $controller->valid_following_replacement( $unknown ) );
	}

	/**
	 * Return one complete recurrence aggregate.
	 */
	private function aggregate(): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04T19:00:00', '2027-01-04T21:00:00', false, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, RecurrenceRule::daily() ) )
		);
	}
}
