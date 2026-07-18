<?php
/**
 * Tests for the event list-table adapter.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\EventListTable;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventListTable::class )]
/**
 * Freezes list-table columns and post-type-specific hook registration.
 */
final class EventListTableTest extends TestCase {
	/**
	 * Reset hook state before each test.
	 */
	protected function setUp(): void {
		HookRecorder::reset();
	}

	/**
	 * Hooks remain scoped to the event list table and admin main query.
	 */
	public function test_registers_event_admin_hooks(): void {
		$table = new EventListTable();

		$table->register();

		self::assertSame(
			array( $table, 'columns' ),
			HookRecorder::action( 'manage_' . EventPostType::POST_TYPE . '_posts_columns' )
		);
		self::assertSame(
			array( $table, 'render_column' ),
			HookRecorder::action( 'manage_' . EventPostType::POST_TYPE . '_posts_custom_column' )
		);
		self::assertSame(
			array( $table, 'sortable_columns' ),
			HookRecorder::action( 'manage_edit-' . EventPostType::POST_TYPE . '_sortable_columns' )
		);
		self::assertSame( array( $table, 'render_filters' ), HookRecorder::action( 'restrict_manage_posts' ) );
	}

	/**
	 * The compact overview exposes every required event decision field.
	 */
	public function test_defines_required_columns_in_workflow_order(): void {
		$columns = ( new EventListTable() )->columns(
			array(
				'cb'                                    => 'Select',
				'title'                                 => 'Title',
				'author'                                => 'Author',
				'taxonomy-' . EventTaxonomies::CATEGORY => 'Categories',
				'date'                                  => 'Date',
			)
		);

		self::assertSame(
			array(
				'cb',
				'title',
				'wpse_start',
				'wpse_end',
				'wpse_all_day',
				'wpse_location',
				'taxonomy-' . EventTaxonomies::CATEGORY,
				'wpse_event_status',
				'wpse_publication_status',
			),
			array_keys( $columns )
		);
	}

	/**
	 * Only start and end advertise server-side sorting.
	 */
	public function test_registers_date_sort_columns(): void {
		$sortable = ( new EventListTable() )->sortable_columns( array( 'title' => 'title' ) );

		self::assertSame( 'title', $sortable['title'] );
		self::assertSame( 'wpse_start', $sortable['wpse_start'] );
		self::assertSame( 'wpse_end', $sortable['wpse_end'] );
	}
}
