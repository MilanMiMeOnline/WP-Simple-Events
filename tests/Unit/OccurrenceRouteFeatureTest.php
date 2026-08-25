<?php
/**
 * Tests for the occurrence-route feature decision.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteFeature;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( OccurrenceRouteFeature::class )]
/**
 * Proves public routing is active by default and remains test-isolatable.
 */
final class OccurrenceRouteFeatureTest extends TestCase {
	/** Production enables public occurrence routing without a hidden constant. */
	public function test_defaults_to_enabled(): void {
		self::assertTrue( ( new OccurrenceRouteFeature() )->enabled() );
	}

	/** Explicit test decisions are preserved without weak coercion. */
	public function test_accepts_an_explicit_boolean_test_decision(): void {
		self::assertTrue( ( new OccurrenceRouteFeature( true ) )->enabled() );
		self::assertFalse( ( new OccurrenceRouteFeature( false ) )->enabled() );
	}
}
