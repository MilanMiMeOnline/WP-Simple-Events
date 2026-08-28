<?php
/**
 * Public add-to-calendar snapshot resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventMetaSanitizer;
use MiMe\WPSimpleEvents\Domain\EventStatus;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationProvider;
use MiMe\WPSimpleEvents\Frontend\OccurrencePresentationResolver;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSource;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteUrlBuilder;
use RuntimeException;

/**
 * Resolves one immutable public one-off or exact-occurrence snapshot.
 */
final readonly class CalendarExportSnapshotResolver implements CalendarExportSnapshotProvider {
	/**
	 * Create the resolver around existing public visibility boundaries.
	 *
	 * @param EventContextResolver           $events      Public canonical-event resolver.
	 * @param OccurrencePresentationProvider $recurring   Exact recurring-occurrence resolver.
	 * @param OccurrenceReadRepository       $occurrences Active public projection reader.
	 * @param RecurrenceAggregateStore       $aggregates  Canonical recurrence-state reader.
	 * @param EventMetaSanitizer             $sanitizer   Stored identity normalizer.
	 * @param OccurrenceRouteUrlBuilder      $routes      Canonical occurrence URL builder.
	 */
	public function __construct(
		private EventContextResolver $events = new EventContextResolver(),
		private OccurrencePresentationProvider $recurring = new OccurrencePresentationResolver(),
		private OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private RecurrenceAggregateStore $aggregates = new WordPressRecurrenceAggregateStore(),
		private EventMetaSanitizer $sanitizer = new EventMetaSanitizer(),
		private OccurrenceRouteUrlBuilder $routes = new OccurrenceRouteUrlBuilder()
	) {}

	/**
	 * Resolve one public selection without falling back across contexts.
	 *
	 * @param int         $event_id   Canonical event post ID.
	 * @param string|null $public_key Exact occurrence key, or null for one-off.
	 */
	public function resolve( int $event_id, ?string $public_key = null ): ?CalendarExportSnapshot {
		if ( $event_id <= 0 ) {
			return null;
		}

		if ( null !== $public_key ) {
			return 1 === preg_match( '/^[a-f0-9]{32}$/D', $public_key )
				? $this->recurring( $event_id, $public_key )
				: null;
		}

		return $this->one_off( $event_id );
	}

	/**
	 * Resolve one exact active one-off projection and canonical event.
	 *
	 * @param int $event_id Canonical event post ID.
	 */
	private function one_off( int $event_id ): ?CalendarExportSnapshot {
		try {
			$series = $this->events->resolve_public( $event_id );

			if ( null === $series || null !== $this->aggregates->load( $event_id ) ) {
				return null;
			}

			$identity   = $this->identity( $event_id, 'one-off' );
			$occurrence = null === $identity
				? null
				: $this->occurrences->find_public( $event_id, $identity->public_key() );

			if ( null === $identity
				|| null === $occurrence
				|| OccurrenceSource::ONE_OFF !== $occurrence->source
				|| 'one-off' !== $occurrence->recurrence_id
			) {
				return null;
			}

			return $this->snapshot(
				$series,
				$occurrence,
				$identity,
				$series->title,
				'',
				$series->venue,
				$series->address,
				$series->permalink
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			return null;
		}
	}

	/**
	 * Resolve one exact recurring occurrence and never its series fallback.
	 *
	 * @param int    $event_id   Canonical series post ID.
	 * @param string $public_key Exact stable occurrence key.
	 */
	private function recurring( int $event_id, string $public_key ): ?CalendarExportSnapshot {
		try {
			$context = $this->recurring->resolve_public( $event_id, $public_key );

			if ( null === $context
				|| $context->occurrence->event_id !== $event_id
				|| ! hash_equals( $context->occurrence->public_key, $public_key )
				|| 'one-off' === $context->occurrence->recurrence_id
			) {
				return null;
			}

			$identity = $this->identity( $event_id, $context->occurrence->recurrence_id );
			$url      = $this->routes->build( $context->series->permalink, $public_key );

			if ( null === $identity || ! hash_equals( $identity->public_key(), $public_key ) || '' === $url ) {
				return null;
			}

			return $this->snapshot(
				$context->series,
				$context->occurrence,
				$identity,
				$context->title,
				$context->note,
				$context->venue,
				$context->address,
				$url
			);
		} catch ( InvalidArgumentException | RuntimeException ) {
			return null;
		}
	}

	/**
	 * Build one stable identity from protected canonical metadata.
	 *
	 * @param int    $event_id     Canonical event post ID.
	 * @param string $recurrence_id One-off or exact recurrence identity.
	 */
	private function identity( int $event_id, string $recurrence_id ): ?OccurrenceIdentity {
		$series_uid = $this->sanitizer->uuid( get_post_meta( $event_id, EventMeta::SERIES_UID, true ) );

		return '' === $series_uid ? null : OccurrenceIdentity::from( $series_uid, $recurrence_id );
	}

	/**
	 * Normalize one already-authorized public context into bounded provider data.
	 *
	 * @param EventPresentation   $series      Canonical series presentation.
	 * @param OccurrenceReadModel $occurrence Exact active projection.
	 * @param OccurrenceIdentity  $identity    Verified stable identity.
	 * @param string              $title       Effective title.
	 * @param string              $note        Effective occurrence note.
	 * @param string              $venue       Effective venue.
	 * @param string              $address     Effective address.
	 * @param string              $url         Canonical public URL.
	 */
	private function snapshot(
		EventPresentation $series,
		OccurrenceReadModel $occurrence,
		OccurrenceIdentity $identity,
		string $title,
		string $note,
		string $venue,
		string $address,
		string $url
	): ?CalendarExportSnapshot {
		if ( EventStatus::CANCELLED === $occurrence->status ) {
			return null;
		}

		$modified = get_post_modified_time( 'U', true, $series->event, false );

		if ( ! is_int( $modified ) || $modified <= 0 ) {
			return null;
		}

		$title       = $this->plain_text( $title, CalendarExportSnapshot::MAX_TITLE_BYTES, false );
		$description = '' !== trim( $note ) ? $note : get_the_excerpt( $series->event );
		$description = $this->description( $description, $url );
		$location    = $this->plain_text(
			implode( "\n", array_filter( array( $venue, $address ), static fn( string $value ): bool => '' !== trim( $value ) ) ),
			CalendarExportSnapshot::MAX_LOCATION_BYTES,
			true
		);

		if ( '' === $title || '' === $description ) {
			return null;
		}

		return new CalendarExportSnapshot(
			$series->event->ID,
			$identity,
			$title,
			$url,
			$occurrence->date_range,
			$occurrence->status,
			$description,
			$location,
			$modified,
			$this->filename( $title, $occurrence->date_range->start_local() )
		);
	}

	/**
	 * Build a bounded plain-text description that always retains its public URL.
	 *
	 * @param string $value Plain-text source candidate.
	 * @param string $url   Canonical public URL.
	 */
	private function description( string $value, string $url ): string {
		$suffix  = '' === trim( $value ) ? $url : "\n\n" . $url;
		$maximum = CalendarExportSnapshot::MAX_DESCRIPTION_BYTES - strlen( $suffix );
		$value   = $this->plain_text( $value, max( 0, $maximum ), true );

		return '' === $value ? $url : $value . $suffix;
	}

	/**
	 * Strip markup and controls before truncating at a UTF-8-safe byte boundary.
	 *
	 * @param string $value       Untrusted presentation text.
	 * @param int    $maximum     Maximum output bytes.
	 * @param bool   $allow_lines Whether normalized line breaks may remain.
	 */
	private function plain_text( string $value, int $maximum, bool $allow_lines ): string {
		$value = wp_strip_all_tags( strip_shortcodes( $value ) );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value ) ?? '';
		$value = $allow_lines
			? ( preg_replace( '/[\t ]+/', ' ', $value ) ?? '' )
			: ( preg_replace( '/\s+/', ' ', $value ) ?? '' );
		$value = trim( $value );

		if ( $maximum <= 0 || '' === $value ) {
			return '';
		}

		$cut = substr( $value, 0, $maximum );

		while ( '' !== $cut && 1 !== preg_match( '//u', $cut ) ) {
			$cut = substr( $cut, 0, -1 );
		}

		return rtrim( $cut );
	}

	/**
	 * Build a strict bounded attachment basename from title and local date.
	 *
	 * @param string $title       Normalized public title.
	 * @param string $start_local Canonical local start value.
	 */
	private function filename( string $title, string $start_local ): string {
		$slug = sanitize_title( $title );
		$slug = preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $slug ) ) ?? '';
		$slug = trim( preg_replace( '/-+/', '-', $slug ) ?? '', '-' );
		$slug = '' === $slug ? 'event' : substr( $slug, 0, 80 );

		return rtrim( $slug, '-' ) . '-' . substr( $start_local, 0, 10 );
	}
}
