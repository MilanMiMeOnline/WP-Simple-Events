<?php
/**
 * Tests for canonical occurrence URL construction.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Routing\OccurrenceRouteUrlBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( OccurrenceRouteUrlBuilder::class )]
/** Proves pretty, plain and unsafe occurrence destinations share one contract. */
final class OccurrenceRouteUrlBuilderTest extends TestCase {
	private const KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

	/** Pretty and query-style parents retain their exact permalink semantics. */
	public function test_builds_pretty_and_query_style_urls(): void {
		$builder = new OccurrenceRouteUrlBuilder();

		self::assertSame(
			'https://example.com/events/series/occurrence/' . self::KEY . '/',
			$builder->build( 'https://example.com/events/series/', self::KEY )
		);
		self::assertSame(
			'https://example.com/?wpse_event=series&wpse_occurrence=' . self::KEY,
			$builder->build( 'https://example.com/?wpse_event=series', self::KEY )
		);
	}

	/** Unsafe parents and malformed identities fail closed. */
	public function test_rejects_unsafe_inputs(): void {
		$builder = new OccurrenceRouteUrlBuilder();

		self::assertSame( '', $builder->build( 'javascript:alert(1)', self::KEY ) );
		self::assertSame( '', $builder->build( 'https://example.com/events/series/', 'INVALID' ) );
	}
}
