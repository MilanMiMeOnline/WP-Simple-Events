<?php
/**
 * WordPress recurrence override input boundary.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Application;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;

/**
 * Requires stored and proposed override strings to be WordPress-canonical.
 */
final readonly class RecurrenceAggregateContentGuard {
	/**
	 * Reject an aggregate whose external text still requires sanitization.
	 *
	 * @param RecurrenceAggregate $aggregate Complete decoded aggregate.
	 * @throws InvalidArgumentException When override input is not canonical.
	 */
	public function assert_canonical( RecurrenceAggregate $aggregate ): void {
		foreach ( $aggregate->overrides as $override ) {
			foreach ( $override->fields() as $field => $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}

				$sanitized = match ( $field ) {
					OccurrenceOverride::TITLE,
					OccurrenceOverride::VENUE,
					OccurrenceOverride::EVENT_URL_LABEL => sanitize_text_field( $value ),
					OccurrenceOverride::NOTE,
					OccurrenceOverride::ADDRESS         => sanitize_textarea_field( $value ),
					OccurrenceOverride::LOCATION_URL,
					OccurrenceOverride::EVENT_URL       => '' === $value ? '' : esc_url_raw( $value, array( 'http', 'https' ) ),
					default                             => $value,
				};

				if ( ! hash_equals( $value, $sanitized ) ) {
					throw new InvalidArgumentException( 'Occurrence override content is not WordPress-canonical.' );
				}
			}
		}
	}
}
