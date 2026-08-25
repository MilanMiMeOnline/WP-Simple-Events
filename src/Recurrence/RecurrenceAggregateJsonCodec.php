<?php
/**
 * Canonical recurrence aggregate JSON storage.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Wraps the exact aggregate schema in one bounded revision-safe metadata string.
 */
final readonly class RecurrenceAggregateJsonCodec {
	public const MAX_ENCODED_BYTES = 2_097_152;

	/**
	 * Create the JSON persistence codec.
	 *
	 * @param RecurrenceAggregateCodec $aggregates Exact aggregate codec.
	 */
	public function __construct(
		private RecurrenceAggregateCodec $aggregates = new RecurrenceAggregateCodec()
	) {}

	/**
	 * Encode one validated aggregate to its canonical JSON representation.
	 *
	 * @param RecurrenceAggregate $aggregate Validated recurrence aggregate.
	 * @throws RuntimeException When canonical JSON cannot be encoded within its bound.
	 */
	public function encode( RecurrenceAggregate $aggregate ): string {
		try {
			$json = wp_json_encode(
				$this->aggregates->encode( $aggregate ),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
			);
		} catch ( JsonException ) {
			throw new RuntimeException( 'The recurrence aggregate could not be encoded.' );
		}

		if ( ! is_string( $json ) || strlen( $json ) > self::MAX_ENCODED_BYTES ) {
			throw new RuntimeException( 'The recurrence aggregate exceeds its storage bound.' );
		}

		return $json;
	}

	/**
	 * Decode one untrusted stored JSON string through the complete aggregate schema.
	 *
	 * @param mixed $value Untrusted metadata value.
	 * @throws InvalidArgumentException When the JSON or aggregate is invalid.
	 */
	public function decode( mixed $value ): RecurrenceAggregate {
		if ( ! is_string( $value ) || '' === $value || strlen( $value ) > self::MAX_ENCODED_BYTES ) {
			throw new InvalidArgumentException( 'The stored recurrence aggregate is not a bounded JSON string.' );
		}

		try {
			$decoded = json_decode( $value, true, 64, JSON_THROW_ON_ERROR );
		} catch ( JsonException ) {
			throw new InvalidArgumentException( 'The stored recurrence aggregate contains invalid JSON.' );
		}

		return $this->aggregates->decode( $decoded );
	}

	/**
	 * Sanitize internal metadata to canonical JSON, rejecting every invalid value.
	 *
	 * WordPress metadata sanitizers cannot return a validation error. The dedicated
	 * application service validates before writing; this callback is a final
	 * fail-closed guard for direct metadata calls by third-party code.
	 *
	 * @param mixed $value Untrusted metadata value.
	 */
	public function sanitize( mixed $value ): string {
		try {
			return $this->encode( $this->decode( $value ) );
		} catch ( InvalidArgumentException | RuntimeException ) {
			return '';
		}
	}
}
