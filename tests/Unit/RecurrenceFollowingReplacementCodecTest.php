<?php
/**
 * Tests for this-and-following replacement decoding.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingReplacement;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceFollowingReplacementCodec;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceRule;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves exact keys, strict scalars and plugin-owned definition decoding.
 */
#[CoversClass( RecurrenceFollowingReplacementCodec::class )]
#[CoversClass( RecurrenceFollowingReplacement::class )]
final class RecurrenceFollowingReplacementCodecTest extends TestCase {
	/**
	 * One exact daily replacement decodes without accepting timezone input.
	 */
	public function test_exact_replacement_decodes(): void {
		$replacement = ( new RecurrenceFollowingReplacementCodec() )->decode( $this->value() );

		self::assertSame( '2027-01-06T20:00:00', $replacement->start_local );
		self::assertSame( '2027-01-06T22:00:00', $replacement->end_local );
		self::assertFalse( $replacement->all_day );
		self::assertInstanceOf( RecurrenceRule::class, $replacement->definition );
	}

	/**
	 * Unknown top-level or template fields fail closed.
	 */
	public function test_unknown_fields_are_rejected(): void {
		$value             = $this->value();
		$value['timezone'] = 'UTC';

		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceFollowingReplacementCodec() )->decode( $value );
	}

	/**
	 * Weak all-day scalars do not pass the REST boundary.
	 */
	public function test_weak_boolean_is_rejected(): void {
		$value                        = $this->value();
		$value['template']['all_day'] = '0';

		$this->expectException( InvalidArgumentException::class );
		( new RecurrenceFollowingReplacementCodec() )->decode( $value );
	}

	/**
	 * Return one exact replacement input.
	 *
	 * @return array<string, mixed>
	 */
	private function value(): array {
		return array(
			'template'   => array(
				'start_local' => '2027-01-06T20:00:00',
				'end_local'   => '2027-01-06T22:00:00',
				'all_day'     => false,
			),
			'definition' => array(
				'type'      => 'rule',
				'frequency' => 'daily',
				'interval'  => 2,
				'end'       => array( 'mode' => 'never' ),
			),
		);
	}
}
