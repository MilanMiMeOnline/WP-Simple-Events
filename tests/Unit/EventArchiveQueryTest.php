<?php
/**
 * Tests for native archive setting application.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Query\EventArchiveQuery;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Query;

#[CoversClass( EventArchiveQuery::class )]
/**
 * Verifies that native archive settings control only the public main archive.
 */
final class EventArchiveQueryTest extends TestCase {
	/**
	 * Reset deterministic options.
	 */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * The initial main query uses both configured archive defaults.
	 */
	public function test_applies_configured_page_size_and_default_period(): void {
		WordPressState::set_option( EventArchiveSettings::PER_PAGE_OPTION, 7 );
		WordPressState::set_option( EventArchiveSettings::DEFAULT_PERIOD_OPTION, 'all' );
		$query = new WP_Query();

		( new EventArchiveQuery() )->apply( $query );

		self::assertSame( 7, $query->get( 'posts_per_page' ) );
		self::assertSame( 'all', $query->get( 'wpse_period' ) );
		self::assertSame( '', $query->get( 'meta_query' ) );
	}

	/**
	 * A visitor's allowlisted period wins while invalid input fails safe.
	 */
	public function test_explicit_valid_filter_overrides_default_and_invalid_filter_does_not(): void {
		WordPressState::set_option( EventArchiveSettings::DEFAULT_PERIOD_OPTION, 'all' );
		$past    = new WP_Query( array( 'wpse_period' => 'past' ) );
		$invalid = new WP_Query( array( 'wpse_period' => 'private' ) );

		( new EventArchiveQuery() )->apply( $past );
		( new EventArchiveQuery() )->apply( $invalid );

		self::assertSame( 'past', $past->get( 'wpse_period' ) );
		self::assertSame( 'all', $invalid->get( 'wpse_period' ) );
	}

	/**
	 * Event taxonomy archives are public event collections ordered by start date.
	 */
	public function test_event_taxonomy_archive_uses_all_event_chronology_without_replacing_term_scope(): void {
		$query = new WP_Query(
			array(
				'wpse_test_request'       => 'taxonomy',
				'taxonomy'                => EventTaxonomies::CATEGORY,
				EventTaxonomies::CATEGORY => 'concerts',
			)
		);

		( new EventArchiveQuery() )->apply( $query );

		self::assertSame( 'wpse_event', $query->get( 'post_type' ) );
		self::assertSame( 'publish', $query->get( 'post_status' ) );
		self::assertFalse( $query->get( 'has_password' ) );
		self::assertSame( EventMeta::START_UTC, $query->get( 'meta_key' ) );
		self::assertSame( 'ASC', $query->get( 'order' ) );
		self::assertSame( 'all', $query->get( 'wpse_period' ) );
		self::assertSame( 'concerts', $query->get( EventTaxonomies::CATEGORY ) );
		self::assertSame( '', $query->get( 'tax_query' ) );
	}

	/**
	 * Query changes remain isolated from blog, product and mixed-search queries.
	 */
	public function test_unrelated_main_query_is_not_modified(): void {
		$query = new WP_Query(
			array(
				'wpse_test_request' => 'unrelated',
				'post_type'         => array( 'post', 'product', 'wpse_event' ),
				'orderby'           => 'relevance',
			)
		);

		( new EventArchiveQuery() )->apply( $query );

		self::assertSame( array( 'post', 'product', 'wpse_event' ), $query->get( 'post_type' ) );
		self::assertSame( 'relevance', $query->get( 'orderby' ) );
		self::assertSame( '', $query->get( 'meta_key' ) );
	}
}
