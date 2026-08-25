<?php
/**
 * Exact public occurrence presentation resolution.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Application\RecurrenceAggregateContentGuard;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIdentity;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadModel;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceReadRepository;
use MiMe\WPSimpleEvents\Recurrence\ConcurrentRecurrenceAggregateStore;
use MiMe\WPSimpleEvents\Recurrence\OccurrenceOverride;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceAggregate;
use MiMe\WPSimpleEvents\Recurrence\WordPressRecurrenceAggregateStore;
use RuntimeException;

/**
 * Joins one eligible active projection row to its canonical series inheritance.
 */
final class OccurrencePresentationResolver implements OccurrencePresentationProvider, ProjectedOccurrencePresentationProvider {
	/**
	 * Request-local exact contexts.
	 *
	 * @var array<string, OccurrencePresentationContext|null>
	 */
	private array $contexts = array();

	/**
	 * Request-local canonical aggregates, including negative lookups.
	 *
	 * @var array<int, RecurrenceAggregate|null>
	 */
	private array $aggregates = array();

	/**
	 * Create the resolver.
	 *
	 * @param OccurrenceReadRepository           $occurrences Public projection reader.
	 * @param ConcurrentRecurrenceAggregateStore $store       Protected canonical aggregate store.
	 * @param EventContextResolver               $events      Public series presentation boundary.
	 * @param RecurrenceAggregateContentGuard    $content     WordPress canonical-content guard.
	 */
	public function __construct(
		private readonly OccurrenceReadRepository $occurrences = new OccurrenceReadRepository(),
		private readonly ConcurrentRecurrenceAggregateStore $store = new WordPressRecurrenceAggregateStore(),
		private readonly EventContextResolver $events = new EventContextResolver(),
		private readonly RecurrenceAggregateContentGuard $content = new RecurrenceAggregateContentGuard()
	) {}

	/**
	 * Resolve one published, password-free occurrence or fail closed.
	 *
	 * @param int    $event_id   Canonical series post ID.
	 * @param string $public_key Stable lowercase occurrence key.
	 */
	public function resolve_public( int $event_id, string $public_key ): ?OccurrencePresentationContext {
		if ( $event_id <= 0 || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $public_key ) ) {
			return null;
		}

		$cache_key = $event_id . ':' . $public_key;

		if ( array_key_exists( $cache_key, $this->contexts ) ) {
			return $this->contexts[ $cache_key ];
		}

		try {
			$occurrence = $this->occurrences->find_public( $event_id, $public_key );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return $this->cache( $cache_key, null );
		}

		return null === $occurrence
			? $this->cache( $cache_key, null )
			: $this->resolve_projected( $occurrence );
	}

	/**
	 * Resolve an already-authorized recurring projection row without another SQL lookup.
	 *
	 * @param OccurrenceReadModel $occurrence Active public projection row.
	 */
	public function resolve_projected( OccurrenceReadModel $occurrence ): ?OccurrencePresentationContext {
		$cache_key = $occurrence->event_id . ':' . $occurrence->public_key;

		if ( array_key_exists( $cache_key, $this->contexts ) ) {
			return $this->contexts[ $cache_key ];
		}

		if ( $occurrence->event_id <= 0
			|| 'one-off' === $occurrence->recurrence_id
			|| 1 !== preg_match( '/^[a-f0-9]{32}$/D', $occurrence->public_key )
		) {
			return $this->cache( $cache_key, null );
		}

		try {
			$series = $this->events->resolve_public( $occurrence->event_id );

			if ( null === $series ) {
				return $this->cache( $cache_key, null );
			}

			$aggregate = $this->aggregate( $occurrence->event_id );

			if ( null === $aggregate ) {
				return $this->cache( $cache_key, null );
			}

			$identity = OccurrenceIdentity::from( $aggregate->series_uid, $occurrence->recurrence_id );

			if ( ! hash_equals( $identity->public_key(), $occurrence->public_key ) ) {
				return $this->cache( $cache_key, null );
			}

			return $this->cache( $cache_key, $this->context( $series, $occurrence, $aggregate ) );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return $this->cache( $cache_key, null );
		}
	}

	/**
	 * Load and validate one canonical aggregate at most once during this request.
	 *
	 * @param int $event_id Canonical series post ID.
	 */
	private function aggregate( int $event_id ): ?RecurrenceAggregate {
		if ( array_key_exists( $event_id, $this->aggregates ) ) {
			return $this->aggregates[ $event_id ];
		}

		try {
			$aggregate = $this->store->load( $event_id );

			if ( null !== $aggregate ) {
				$this->content->assert_canonical( $aggregate );
			}
		} catch ( InvalidArgumentException | RuntimeException ) {
			$aggregate = null;
		}

		$this->aggregates[ $event_id ] = $aggregate;

		return $aggregate;
	}

	/**
	 * Remember one positive or fail-closed result for the current request.
	 *
	 * @param string                             $cache_key Exact event and occurrence identity.
	 * @param OccurrencePresentationContext|null $context   Resolved context or a negative lookup.
	 */
	private function cache(
		string $cache_key,
		?OccurrencePresentationContext $context
	): ?OccurrencePresentationContext {
		$this->contexts[ $cache_key ] = $context;

		return $context;
	}

	/**
	 * Build named effective fields from one identity's sparse override.
	 *
	 * @param EventPresentation   $series     Normalized public series snapshot.
	 * @param OccurrenceReadModel $occurrence Exact active occurrence row.
	 * @param RecurrenceAggregate $aggregate Canonical recurrence aggregate.
	 */
	private function context(
		EventPresentation $series,
		OccurrenceReadModel $occurrence,
		RecurrenceAggregate $aggregate
	): OccurrencePresentationContext {
		$fields = array();

		foreach ( $aggregate->overrides as $override ) {
			if ( $override->recurrence_id === $occurrence->recurrence_id ) {
				$fields = $override->fields();
				break;
			}
		}

		return new OccurrencePresentationContext(
			$series,
			$occurrence,
			$this->string_field( $fields, OccurrenceOverride::TITLE, $series->title ),
			$this->string_field( $fields, OccurrenceOverride::NOTE, '' ),
			$this->image_field( $fields, max( 0, (int) get_post_thumbnail_id( $series->event ) ) ),
			$this->string_field( $fields, OccurrenceOverride::VENUE, $series->venue ),
			$this->string_field( $fields, OccurrenceOverride::ADDRESS, $series->address ),
			$this->string_field( $fields, OccurrenceOverride::LOCATION_URL, $series->location_url ),
			$this->string_field( $fields, OccurrenceOverride::EVENT_URL, $series->event_url ),
			$this->string_field( $fields, OccurrenceOverride::EVENT_URL_LABEL, $series->event_url_label )
		);
	}

	/**
	 * Return one already validated sparse string or its normalized series value.
	 *
	 * @param array<string, mixed> $fields    Sparse override fields.
	 * @param string               $field     Allowlisted field name.
	 * @param string               $inherited Normalized series value.
	 */
	private function string_field( array $fields, string $field, string $inherited ): string {
		$value = $fields[ $field ] ?? $inherited;

		return is_string( $value ) ? $value : $inherited;
	}

	/**
	 * Return one already validated sparse image identifier or its series value.
	 *
	 * @param array<string, mixed> $fields    Sparse override fields.
	 * @param int                  $inherited Normalized series image ID.
	 */
	private function image_field( array $fields, int $inherited ): int {
		$value = $fields[ OccurrenceOverride::FEATURED_IMAGE_ID ] ?? $inherited;

		return is_int( $value ) ? $value : $inherited;
	}
}
