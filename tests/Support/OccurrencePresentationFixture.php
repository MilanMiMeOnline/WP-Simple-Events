<?php
/**
 * Deterministic effective occurrence context fixture.
 *
 * @package MiMe\WPSimpleEvents\Tests\Support
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Support;

use MiMe\WPSimpleEvents\Domain\EventDateRange;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationContext;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;

/** Builds one deliberately different occurrence over a supplied series. */
final class OccurrencePresentationFixture {
	/**
	 * Build an effective occurrence context.
	 *
	 * @param EventPresentation $series     Public series presentation.
	 * @param string            $public_key Stable lowercase occurrence key.
	 */
	public static function create( EventPresentation $series, string $public_key ): OccurrencePresentationContext {
		$range      = EventDateRange::from_local(
			'2027-01-05T19:00:00',
			'2027-01-05T21:00:00',
			false,
			'Europe/Brussels'
		);
		$occurrence = OccurrenceReadModel::from_row(
			array(
				'event_id'      => $series->event->ID,
				'public_key'    => $public_key,
				'recurrence_id' => '2027-01-05T19:00:00',
				'generation'    => 7,
				'segment_id'    => 0,
				'source'        => 'rule',
				'start_local'   => $range->start_local(),
				'end_local'     => $range->end_local(),
				'start_utc'     => $range->start_utc(),
				'end_utc'       => $range->end_utc(),
				'timezone'      => $range->timezone(),
				'all_day'       => 0,
				'event_status'  => EventStatus::POSTPONED->value,
			)
		);

		return new OccurrencePresentationContext(
			$series,
			$occurrence,
			'Occurrence block title',
			'Occurrence block note',
			0,
			'Occurrence block venue',
			'Occurrence block address',
			'https://example.com/occurrence-location',
			'https://example.com/occurrence-action',
			'Occurrence block action'
		);
	}
}
