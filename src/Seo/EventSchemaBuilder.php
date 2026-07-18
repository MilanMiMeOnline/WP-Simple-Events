<?php
/**
 * Event structured-data builder.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Domain\EventStatus;

/**
 * Converts validated public event values into a Schema.org Event graph.
 */
final class EventSchemaBuilder {
	/**
	 * Build one event graph without inventing missing required values.
	 *
	 * @param EventSchemaInput $input Validated public input.
	 * @return array<string, mixed>|null
	 */
	public function build( EventSchemaInput $input ): ?array {
		$name       = trim( $input->name );
		$start_date = trim( $input->start_date );
		$end_date   = trim( $input->end_date );
		$url        = trim( $input->url );

		if ( '' === $name || '' === $start_date || '' === $end_date || '' === $url ) {
			return null;
		}

		$schema = array(
			'@context'    => 'https://schema.org',
			'@type'       => 'Event',
			'name'        => $name,
			'startDate'   => $start_date,
			'endDate'     => $end_date,
			'eventStatus' => $this->schema_status( $input->status ),
			'url'         => $url,
		);

		$description = trim( $input->description );
		$image_url   = trim( $input->image_url );
		$venue       = trim( $input->venue );
		$address     = trim( $input->address );

		if ( '' !== $description ) {
			$schema['description'] = $description;
		}

		if ( '' !== $image_url ) {
			$schema['image'] = array( $image_url );
		}

		if ( '' !== $venue || '' !== $address ) {
			$location = array( '@type' => 'Place' );

			if ( '' !== $venue ) {
				$location['name'] = $venue;
			}

			if ( '' !== $address ) {
				$location['address'] = $address;
			}

			$schema['location'] = $location;
		}

		return $schema;
	}

	/**
	 * Map an internal event status to its Schema.org URL.
	 *
	 * @param EventStatus $status Public event status.
	 */
	private function schema_status( EventStatus $status ): string {
		return match ( $status ) {
			EventStatus::SCHEDULED => 'https://schema.org/EventScheduled',
			EventStatus::CANCELLED => 'https://schema.org/EventCancelled',
			EventStatus::POSTPONED => 'https://schema.org/EventPostponed',
		};
	}
}
