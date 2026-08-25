<?php
/**
 * Tests for recurrence editor REST serialization.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\RecurrenceEditorContext;
use MiMe\WPSimpleEvents\Application\RecurrenceDisablePreview;
use MiMe\WPSimpleEvents\Application\RecurrenceOccurrenceEditContext;
use MiMe\WPSimpleEvents\Application\RecurrenceOccurrenceInheritedFields;
use MiMe\WPSimpleEvents\Application\RecurrenceEditorPreview;
use MiMe\WPSimpleEvents\Application\RecurrenceFollowingEditorPreview;
use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateRevision;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceEditScope;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingMutator;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceImpactPreviewer;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use MiMe\WPSimpleEvents\Recurrence\ScheduleSegment;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusion;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceExclusionAction;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Occurrence\RecurrenceOccurrenceBuilder;
use MiMe\WPSimpleEvents\Rest\RecurrenceEditorSerializer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves bounded scalar response output and exact impact summaries.
 */
#[CoversClass( RecurrenceEditorSerializer::class )]
final class RecurrenceEditorSerializerTest extends TestCase {
	/**
	 * Preview serialization contains canonical context, counts and occurrence sides.
	 */
	public function test_preview_is_serialized_to_bounded_scalars(): void {
		$current  = $this->aggregate( 2 );
		$proposed = $this->aggregate();
		$revision = ( new RecurrenceAggregateRevision() )->token( $current );
		$window   = RecurrenceGenerationWindow::between( '2027-01-04', '2027-01-07', 20 );
		$context  = new RecurrenceEditorContext( $current, true, $revision, EventStatus::SCHEDULED );
		$impact   = ( new RecurrenceImpactPreviewer() )->preview(
			$current,
			$proposed,
			EventStatus::SCHEDULED,
			$window,
			RecurrenceEditScope::COMPLETE_SERIES
		);
		$data     = ( new RecurrenceEditorSerializer() )->preview(
			new RecurrenceEditorPreview( $context, $impact, str_repeat( 'a', 64 ) )
		);

		self::assertTrue( $data['context']['recurring'] );
		self::assertSame( $revision, $data['context']['revision'] );
		self::assertSame( 2, $data['impact']['added'] );
		self::assertSame( 0, $data['impact']['removed'] );
		self::assertSame( '2027-01-05T19:00:00', $data['impact']['items'][0]['recurrence_id'] );
		self::assertNull( $data['impact']['items'][0]['before'] );
		self::assertSame( '2027-01-05T19:00:00', $data['impact']['items'][0]['after']['start_local'] );
	}

	/**
	 * A following preview returns the exact complete proposal required by save.
	 */
	public function test_following_preview_serializes_server_built_proposal(): void {
		$current  = $this->aggregate();
		$target   = '2027-01-05T19:00:00';
		$template = EventDateRange::from_local( '2027-01-05T20:00:00', '2027-01-05T22:00:00', false, 'Europe/Brussels' );
		$proposal = ( new RecurrenceFollowingMutator() )->replace_from(
			$current,
			$target,
			$template,
			RecurrenceRule::daily( 2 )
		);
		$revision = ( new RecurrenceAggregateRevision() )->token( $current );
		$window   = RecurrenceGenerationWindow::between( '2027-01-04', '2027-01-10', 20 );
		$context  = new RecurrenceEditorContext( $current, true, $revision, EventStatus::SCHEDULED );
		$impact   = ( new RecurrenceImpactPreviewer() )->preview(
			$current,
			$proposal,
			EventStatus::SCHEDULED,
			$window,
			RecurrenceEditScope::THIS_AND_FOLLOWING,
			$target
		);
		$data     = ( new RecurrenceEditorSerializer() )->following_preview(
			new RecurrenceFollowingEditorPreview(
				$context,
				$proposal,
				$impact,
				str_repeat( 'a', 64 )
			)
		);

		self::assertSame( 'this_and_following', $data['impact']['scope'] );
		self::assertSame( $target, $data['impact']['target'] );
		self::assertCount( 2, $data['proposal']['segments'] );
		self::assertSame( $target, $data['proposal']['segments'][1]['anchor'] );
		self::assertSame( $revision, $data['context']['revision'] );
	}

	/**
	 * Disable serialization names the survivor and is honest beyond the window.
	 */
	public function test_disable_preview_serializes_survivor_and_destructive_scope(): void {
		$current     = $this->aggregate();
		$revision    = ( new RecurrenceAggregateRevision() )->token( $current );
		$context     = new RecurrenceEditorContext( $current, true, $revision, EventStatus::SCHEDULED );
		$occurrences = ( new RecurrenceOccurrenceBuilder() )->build(
			42,
			$current,
			EventStatus::SCHEDULED,
			RecurrenceGenerationWindow::between( '2027-01-04', '2027-01-06', 20 ),
			1
		);
		$data        = ( new RecurrenceEditorSerializer() )->disable_preview(
			new RecurrenceDisablePreview(
				$context,
				$occurrences[1],
				array( $occurrences[0], $occurrences[2] ),
				0,
				str_repeat( 'a', 64 )
			)
		);

		self::assertSame( '2027-01-05T19:00:00', $data['survivor']['recurrence_id'] );
		self::assertSame( 2, $data['impact']['removed'] );
		self::assertTrue( $data['impact']['outside_window_removed'] );
		self::assertSame( array( 'removed' ), $data['impact']['items'][0]['changes'] );
		self::assertNull( $data['impact']['items'][0]['after'] );
	}

	/**
	 * Occurrence edit serialization preserves exact sparse values and baseline.
	 */
	public function test_occurrence_context_serializes_current_inherited_and_sparse_state(): void {
		$target             = '2027-01-05T19:00:00';
		$moved              = EventDateRange::from_local( '2027-01-05T20:00:00', '2027-01-05T22:00:00', false, 'Europe/Brussels' );
		$override           = OccurrenceOverride::from_fields(
			$target,
			array(
				OccurrenceOverride::DATE_RANGE        => $moved,
				OccurrenceOverride::EVENT_URL_LABEL   => 'Special tickets',
				OccurrenceOverride::FEATURED_IMAGE_ID => 123,
				OccurrenceOverride::STATUS            => EventStatus::POSTPONED,
			)
		);
		$current            = $this->aggregate(
			1,
			array( $override ),
			array( new OccurrenceExclusion( $target, OccurrenceExclusionAction::CANCEL ) )
		);
		$baseline           = $this->aggregate();
		$window             = RecurrenceGenerationWindow::between( '2027-01-05', '2027-01-05', 20 );
		$context            = new RecurrenceEditorContext(
			$current,
			true,
			( new RecurrenceAggregateRevision() )->token( $current ),
			EventStatus::SCHEDULED
		);
		$current_occurrence = ( new RecurrenceOccurrenceBuilder() )->build( 42, $current, EventStatus::SCHEDULED, $window, 1 )[0];
		$inherited          = ( new RecurrenceOccurrenceBuilder() )->build( 42, $baseline, EventStatus::SCHEDULED, $window, 1 )[0];
		$data               = ( new RecurrenceEditorSerializer() )->occurrence_context(
			new RecurrenceOccurrenceEditContext(
				$context,
				$window,
				$current_occurrence,
				$inherited,
				new RecurrenceOccurrenceInheritedFields(
					'Series title',
					'',
					77,
					'Main hall',
					'Main Street 1',
					'https://example.com/location',
					'https://example.com/event',
					'Register'
				),
				$override,
				OccurrenceExclusionAction::CANCEL
			)
		);

		self::assertSame( $target, $data['target'] );
		self::assertSame( '2027-01-05T20:00:00', $data['current']['start_local'] );
		self::assertSame( 'cancelled', $data['current']['status'] );
		self::assertSame( '2027-01-05T19:00:00', $data['inherited']['start_local'] );
		self::assertSame( 'cancel', $data['exclusion_action'] );
		self::assertSame( 123, $data['override_fields']['featured_image_id'] );
		self::assertSame( 'Special tickets', $data['override_fields']['event_url_label'] );
		self::assertSame( 'postponed', $data['override_fields']['status'] );
		self::assertSame(
			array(
				'title'             => 'Series title',
				'note'              => '',
				'featured_image_id' => 77,
				'venue'             => 'Main hall',
				'address'           => 'Main Street 1',
				'location_url'      => 'https://example.com/location',
				'event_url'         => 'https://example.com/event',
				'event_url_label'   => 'Register',
			),
			$data['inherited_fields']
		);
		self::assertSame(
			array(
				'start_local' => '2027-01-05T20:00:00',
				'end_local'   => '2027-01-05T22:00:00',
				'all_day'     => false,
			),
			$data['override_fields']['date_range']
		);
		self::assertSame(
			array(
				'from_date'    => '2027-01-05',
				'through_date' => '2027-01-05',
				'max_rows'     => 20,
			),
			$data['window']
		);
	}

	/**
	 * Return one daily aggregate.
	 *
	 * @param int   $interval   Daily interval.
	 * @param array $overrides  Optional sparse overrides.
	 * @param array $exclusions Optional exclusions.
	 * @phpstan-param list<OccurrenceOverride> $overrides
	 * @phpstan-param list<OccurrenceExclusion> $exclusions
	 */
	private function aggregate(
		int $interval = 1,
		array $overrides = array(),
		array $exclusions = array()
	): RecurrenceAggregate {
		$range = EventDateRange::from_local( '2027-01-04T19:00:00', '2027-01-04T21:00:00', false, 'Europe/Brussels' );

		return RecurrenceAggregate::create(
			'019c1d83-1798-4fac-a66d-ae8d67c46319',
			'Europe/Brussels',
			array( new ScheduleSegment( 0, $range->start_local(), $range, RecurrenceRule::daily( $interval ) ) ),
			array(),
			$exclusions,
			$overrides
		);
	}
}
