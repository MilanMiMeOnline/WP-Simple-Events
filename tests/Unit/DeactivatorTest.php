<?php
/**
 * Tests for rewrite-safe plugin deactivation.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Lifecycle\Deactivator;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceGenerationCleanupController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceIndexMigrationController;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceProjectionRenewalController;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( Deactivator::class )]
/**
 * Verifies that stale event routes are removed without touching persistent data.
 */
final class DeactivatorTest extends TestCase {
	/**
	 * Reset deterministic post type and rewrite state.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * The event post type disappears before one soft rewrite flush is generated.
	 */
	public function test_unregisters_the_event_post_type_before_flushing(): void {
		( new EventPostType() )->register();
		( new EventTaxonomies() )->register();
		WordPressState::schedule_hook( OccurrenceIndexMigrationController::HOOK, time() + 30 );
		WordPressState::schedule_hook( OccurrenceGenerationCleanupController::HOOK, time() + 30 );
		WordPressState::schedule_hook( OccurrenceProjectionRenewalController::HOOK, time() + 30 );
		WordPressState::set_option( OccurrenceProjectionRenewalController::OFFSET_OPTION, 2 );

		Deactivator::deactivate();

		self::assertFalse( WordPressState::post_type_exists( EventPostType::POST_TYPE ) );
		self::assertSame( array( EventPostType::POST_TYPE ), WordPressState::unregistered_post_types() );
		self::assertSame(
			array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG ),
			WordPressState::unregistered_taxonomies()
		);
		self::assertSame( 1, WordPressState::rewrite_flushes() );
		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceIndexMigrationController::HOOK ) );
		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceGenerationCleanupController::HOOK ) );
		self::assertSame( 0, WordPressState::scheduled_count( OccurrenceProjectionRenewalController::HOOK ) );
		self::assertFalse( WordPressState::has_option( OccurrenceProjectionRenewalController::OFFSET_OPTION ) );
	}

	/**
	 * Deactivation remains safe when the content type was not registered.
	 */
	public function test_still_flushes_once_when_the_post_type_is_absent(): void {
		Deactivator::deactivate();

		self::assertSame( array(), WordPressState::unregistered_post_types() );
		self::assertSame( array(), WordPressState::unregistered_taxonomies() );
		self::assertSame( 1, WordPressState::rewrite_flushes() );
	}
}
