<?php
/**
 * Tests for canonical series-level event color persistence.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Application\EventColorPersistence;
use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventColorPersistence::class )]
/** Ensures inactive, invalid and unassigned presentation metadata cannot linger. */
final class EventColorPersistenceTest extends TestCase {
	/** Reset deterministic metadata and terms. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** Automatic mode is represented by absence and removes inactive values. */
	public function test_automatic_mode_is_migration_free_and_clears_inactive_values(): void {
		$this->store_stale_values();

		( new EventColorPersistence() )->persist_admin(
			42,
			array( 'color_mode' => EventColorMode::AUTOMATIC->value )
		);

		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::COLOR_MODE ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::COLOR ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::DISPLAY_CATEGORY ) );
	}

	/** Custom and fallback choices keep only the fields their mode owns. */
	public function test_custom_and_fallback_modes_are_normalized_and_exclusive(): void {
		$persistence = new EventColorPersistence();
		$persistence->persist_admin(
			42,
			array(
				'color_mode'          => EventColorMode::CUSTOM->value,
				'event_color'         => '#AABBCC',
				'display_category_id' => '9',
			)
		);

		self::assertSame( EventColorMode::CUSTOM->value, WordPressState::post_meta( 42, EventMeta::COLOR_MODE ) );
		self::assertSame( '#aabbcc', WordPressState::post_meta( 42, EventMeta::COLOR ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::DISPLAY_CATEGORY ) );

		$persistence->persist_admin( 42, array( 'color_mode' => EventColorMode::FALLBACK->value ) );
		self::assertSame( EventColorMode::FALLBACK->value, WordPressState::post_meta( 42, EventMeta::COLOR_MODE ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::COLOR ) );
	}

	/** Explicit category selection persists only an assigned category with a valid color. */
	public function test_category_mode_requires_an_assigned_colored_event_category(): void {
		WordPressState::set_post_terms( 42, EventTaxonomies::CATEGORY, array( 7, 9 ) );
		WordPressState::update_term_meta( 7, EventCategoryMeta::COLOR, '#112233' );
		WordPressState::update_term_meta( 9, EventCategoryMeta::COLOR, 'invalid' );
		$persistence = new EventColorPersistence();

		$persistence->persist_admin(
			42,
			array(
				'color_mode'          => EventColorMode::CATEGORY->value,
				'display_category_id' => '7',
			)
		);
		self::assertSame( 7, WordPressState::post_meta( 42, EventMeta::DISPLAY_CATEGORY ) );

		$persistence->persist_admin(
			42,
			array(
				'color_mode'          => EventColorMode::CATEGORY->value,
				'display_category_id' => '9',
			)
		);
		self::assertSame( EventColorMode::CATEGORY->value, WordPressState::post_meta( 42, EventMeta::COLOR_MODE ) );
		self::assertFalse( WordPressState::has_post_meta( 42, EventMeta::DISPLAY_CATEGORY ) );
	}

	/** Missing REST color fields leave canonical color intent untouched. */
	public function test_rest_updates_are_partial_but_present_values_are_normalized(): void {
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::FALLBACK->value );
		$persistence = new EventColorPersistence();

		$persistence->persist_rest( 42, array( EventMeta::VENUE => 'Town Hall' ) );
		self::assertSame( EventColorMode::FALLBACK->value, WordPressState::post_meta( 42, EventMeta::COLOR_MODE ) );

		$persistence->persist_rest(
			42,
			array(
				EventMeta::COLOR_MODE => EventColorMode::CUSTOM->value,
				EventMeta::COLOR      => '#445566',
			)
		);
		self::assertSame( '#445566', WordPressState::post_meta( 42, EventMeta::COLOR ) );
	}

	/** Seed deliberately inconsistent values to prove cleanup. */
	private function store_stale_values(): void {
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::CUSTOM->value );
		WordPressState::update_post_meta( 42, EventMeta::COLOR, '#aabbcc' );
		WordPressState::update_post_meta( 42, EventMeta::DISPLAY_CATEGORY, 9 );
	}
}
