<?php
/**
 * Tests for the bounded WordPress occurrence coverage probe.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Occurrence\WordPressOccurrenceCoverageProbe;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/**
 * Ensures the readiness lookup is public-only, password-safe and strictly bounded.
 */
#[CoversClass( WordPressOccurrenceCoverageProbe::class )]
final class WordPressOccurrenceCoverageProbeTest extends TestCase {
	/**
	 * Reset deterministic WordPress state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Probe arguments target only public canonical events and one potential gap.
	 */
	public function test_probe_uses_bounded_public_gap_query(): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			)
		);

		self::assertTrue( ( new WordPressOccurrenceCoverageProbe() )->has_public_gap() );

		$arguments = WordPressState::last_get_posts_arguments();
		self::assertSame( EventPostType::POST_TYPE, $arguments['post_type'] );
		self::assertSame( 'publish', $arguments['post_status'] );
		self::assertFalse( $arguments['has_password'] );
		self::assertSame( 1, $arguments['posts_per_page'] );
		self::assertTrue( $arguments['no_found_rows'] );
		self::assertSame( EventMeta::START_LOCAL, $arguments['meta_query'][0]['key'] );
		self::assertSame( 'OR', $arguments['meta_query'][1]['relation'] );
		self::assertSame( EventMeta::ACTIVE_GENERATION, $arguments['meta_query'][1][0]['key'] );
		self::assertSame( EventMeta::INDEX_DIRTY, $arguments['meta_query'][1][1]['key'] );
	}
}
