<?php
/**
 * Tests for the calendar time-presentation adapter.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Calendar\CalendarTimeFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the bounded translation from PHP to FullCalendar time options.
 */
#[CoversClass( CalendarTimeFormat::class )]
final class CalendarTimeFormatTest extends TestCase {
	/**
	 * Common WordPress formats retain their hour cycle, zero padding and meridiem.
	 *
	 * @param string                                                                                                   $format   WordPress time format.
	 * @param array{hour: '2-digit'|'numeric', minute?: '2-digit', hourCycle: 'h12'|'h23', meridiem: bool|'lowercase'} $expected Expected calendar contract.
	 */
	#[DataProvider( 'format_provider' )]
	public function test_maps_supported_wordpress_tokens( string $format, array $expected ): void {
		self::assertSame( $expected, ( new CalendarTimeFormat() )->fullcalendar( $format ) );
	}

	/**
	 * Provide common WordPress time formats and their calendar equivalents.
	 *
	 * @return iterable<string, array{string, array<string, string|bool>}>
	 */
	public static function format_provider(): iterable {
		yield 'zero-padded 24 hour' => array(
			'H:i',
			array(
				'hour'      => '2-digit',
				'hourCycle' => 'h23',
				'meridiem'  => false,
				'minute'    => '2-digit',
			),
		);
		yield 'unpadded 24 hour' => array(
			'G:i',
			array(
				'hour'      => 'numeric',
				'hourCycle' => 'h23',
				'meridiem'  => false,
				'minute'    => '2-digit',
			),
		);
		yield 'zero-padded uppercase 12 hour' => array(
			'h:i A',
			array(
				'hour'      => '2-digit',
				'hourCycle' => 'h12',
				'meridiem'  => true,
				'minute'    => '2-digit',
			),
		);
		yield 'unpadded lowercase 12 hour' => array(
			'g:i a',
			array(
				'hour'      => 'numeric',
				'hourCycle' => 'h12',
				'meridiem'  => 'lowercase',
				'minute'    => '2-digit',
			),
		);
		yield 'format without minutes or meridiem' => array(
			'g',
			array(
				'hour'      => 'numeric',
				'hourCycle' => 'h12',
				'meridiem'  => false,
			),
		);
	}

	/**
	 * Escaped PHP tokens are literals and malformed values receive a safe default.
	 */
	public function test_ignores_escaped_tokens_and_falls_back_safely(): void {
		$formatter = new CalendarTimeFormat();

		self::assertSame(
			array(
				'hour'      => 'numeric',
				'hourCycle' => 'h12',
				'meridiem'  => 'lowercase',
				'minute'    => '2-digit',
			),
			$formatter->fullcalendar( '\\H g:i a' )
		);
		self::assertSame(
			array(
				'hour'      => '2-digit',
				'hourCycle' => 'h23',
				'meridiem'  => false,
				'minute'    => '2-digit',
			),
			$formatter->fullcalendar( '' )
		);
		self::assertSame(
			array(
				'hour'      => '2-digit',
				'hourCycle' => 'h23',
				'meridiem'  => false,
				'minute'    => '2-digit',
			),
			$formatter->fullcalendar( '\\g xyz' )
		);
	}
}
