<?php
/**
 * Canonical recurrence aggregate revision tokens.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

/**
 * Produces non-secret deterministic tokens for optimistic editor concurrency.
 */
final readonly class RecurrenceAggregateRevision {
	private const CONTEXT = "mime-simple-events-calendar\0recurrence-revision\0";

	/**
	 * Create the revision service.
	 *
	 * @param RecurrenceAggregateJsonCodec $codec Canonical aggregate JSON codec.
	 */
	public function __construct(
		private RecurrenceAggregateJsonCodec $codec = new RecurrenceAggregateJsonCodec()
	) {}

	/**
	 * Return one canonical revision token, including for one-off state.
	 *
	 * @param RecurrenceAggregate|null $aggregate Current canonical aggregate.
	 */
	public function token( ?RecurrenceAggregate $aggregate ): string {
		$value = null === $aggregate ? 'one-off' : $this->codec->encode( $aggregate );

		return hash( 'sha256', self::CONTEXT . $value );
	}

	/**
	 * Validate an untrusted revision token without weak coercion.
	 *
	 * @param mixed $value Candidate token.
	 */
	public function valid( mixed $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $value );
	}
}
