<?php
/**
 * This-and-following replacement request codec.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Recurrence;

use InvalidArgumentException;

/**
 * Decodes one strict replacement shape without trusting client timezone data.
 */
final readonly class RecurrenceFollowingReplacementCodec {
	/**
	 * Create the replacement codec.
	 *
	 * @param RecurrenceDefinitionCodec $definitions Canonical definition codec.
	 */
	public function __construct(
		private RecurrenceDefinitionCodec $definitions = new RecurrenceDefinitionCodec()
	) {}

	/**
	 * Decode one untrusted replacement request.
	 *
	 * @param mixed $value Untrusted REST value.
	 * @throws InvalidArgumentException When the shape or a scalar is invalid.
	 */
	public function decode( mixed $value ): RecurrenceFollowingReplacement {
		$value    = $this->exact_object( $value, array( 'template', 'definition' ) );
		$template = $this->exact_object(
			$value['template'] ?? null,
			array( 'start_local', 'end_local', 'all_day' )
		);
		$start    = $template['start_local'] ?? null;
		$end      = $template['end_local'] ?? null;
		$all_day  = $template['all_day'] ?? null;

		if ( ! is_string( $start ) || ! is_string( $end ) || ! is_bool( $all_day ) ) {
			throw new InvalidArgumentException( 'A following replacement template is invalid.' );
		}

		return new RecurrenceFollowingReplacement(
			$start,
			$end,
			$all_day,
			$this->definitions->decode( $value['definition'] ?? null )
		);
	}

	/**
	 * Require one associative array with an exact key set.
	 *
	 * @param mixed    $value Untrusted value.
	 * @param string[] $keys Exact allowed keys.
	 * @return array<string, mixed>
	 * @throws InvalidArgumentException When the value is not one exact object.
	 */
	private function exact_object( mixed $value, array $keys ): array {
		if ( ! is_array( $value ) || ( array() !== $value && array_is_list( $value ) ) ) {
			throw new InvalidArgumentException( 'A following replacement requires an object.' );
		}

		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $keys, SORT_STRING );

		if ( $actual !== $keys ) {
			throw new InvalidArgumentException( 'A following replacement contains incomplete or unknown fields.' );
		}

		return $value;
	}
}
