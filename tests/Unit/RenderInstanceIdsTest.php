<?php
/**
 * Tests for request-wide rendered instance identifiers.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Frontend\RenderInstanceIds;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( RenderInstanceIds::class )]
/**
 * Prevents duplicate IDs when Elementor reconstructs separate widget objects.
 */
final class RenderInstanceIdsTest extends TestCase {
	/**
	 * Separate renderer instances share one sequence for the same component.
	 */
	public function test_component_sequence_is_request_wide(): void {
		self::assertSame( 1, RenderInstanceIds::next( 'unit-events' ) );
		self::assertSame( 2, RenderInstanceIds::next( 'unit-events' ) );
	}

	/**
	 * Different component types retain independent deterministic sequences.
	 */
	public function test_component_sequences_are_isolated(): void {
		self::assertSame( 1, RenderInstanceIds::next( 'unit-calendar' ) );
		self::assertSame( 1, RenderInstanceIds::next( 'unit-details' ) );
	}

	/**
	 * An empty internal component key is rejected explicitly.
	 */
	public function test_empty_component_key_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		RenderInstanceIds::next( '' );
	}
}
