<?php
/**
 * Calendar time presentation derived from WordPress settings.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Calendar;

/**
 * Maps the relevant PHP time-format tokens to bounded FullCalendar options.
 */
final class CalendarTimeFormat {
	/**
	 * Build a FullCalendar time presentation without changing stored values.
	 *
	 * @param string $wordpress_format WordPress/PHP time format.
	 * @return array{hour: '2-digit'|'numeric', minute?: '2-digit', hourCycle: 'h12'|'h23', meridiem: bool|'lowercase'}
	 */
	public function fullcalendar( string $wordpress_format ): array {
		$tokens = $this->unescaped_tokens( $wordpress_format );
		$hour   = $this->first_hour_token( $tokens );

		if ( null === $hour ) {
			$tokens = array( 'H', 'i' );
			$hour   = 'H';
		}

		$is_12_hour = in_array( $hour, array( 'g', 'h' ), true );
		$options    = array(
			'hour'      => in_array( $hour, array( 'h', 'H' ), true ) ? '2-digit' : 'numeric',
			'hourCycle' => $is_12_hour ? 'h12' : 'h23',
			'meridiem'  => false,
		);

		if ( in_array( 'i', $tokens, true ) ) {
			$options['minute'] = '2-digit';
		}

		if ( $is_12_hour && in_array( 'a', $tokens, true ) ) {
			$options['meridiem'] = 'lowercase';
		} elseif ( $is_12_hour && in_array( 'A', $tokens, true ) ) {
			$options['meridiem'] = true;
		}

		return $options;
	}

	/**
	 * Return meaningful format characters while respecting PHP escaping.
	 *
	 * @param string $format WordPress/PHP time format.
	 * @return list<string>
	 */
	private function unescaped_tokens( string $format ): array {
		$tokens  = array();
		$escaped = false;
		$length  = strlen( $format );

		for ( $index = 0; $index < $length; $index++ ) {
			$character = $format[ $index ];

			if ( $escaped ) {
				$escaped = false;
				continue;
			}

			if ( '\\' === $character ) {
				$escaped = true;
				continue;
			}

			$tokens[] = $character;
		}

		return $tokens;
	}

	/**
	 * Find the first unescaped PHP hour token.
	 *
	 * @param array $tokens Unescaped format characters.
	 * @phpstan-param list<string> $tokens
	 */
	private function first_hour_token( array $tokens ): ?string {
		foreach ( $tokens as $token ) {
			if ( in_array( $token, array( 'g', 'G', 'h', 'H' ), true ) ) {
				return $token;
			}
		}

		return null;
	}
}
