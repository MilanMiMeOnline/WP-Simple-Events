<?php
/**
 * Prepared public event color tests.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventCategoryMeta;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Frontend\EventColorCollection;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Term;

#[CoversClass( EventColorCollection::class )]
/** Verifies bounded cache priming and canonical color resolution. */
final class EventColorCollectionTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** A collection primes each relevant cache once before resolving events. */
	public function test_it_primes_each_collection_cache_once_and_resolves_series_color(): void {
		$term = new WP_Term(
			array(
				'term_id'  => 7,
				'name'     => 'Music',
				'slug'     => 'music',
				'taxonomy' => EventTaxonomies::CATEGORY,
			)
		);
		WordPressState::add_term( $term, '' );
		WordPressState::set_post_terms( 42, EventTaxonomies::CATEGORY, array( 7 ) );
		WordPressState::update_term_meta( 7, EventCategoryMeta::COLOR, '#336699' );
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::AUTOMATIC->value );

		$colors = ( new EventColorCollection() )->prepare( array( 42, 42 ) );

		self::assertSame( '#336699', $colors[42]->background );
		self::assertSame(
			array(
				array(
					'type' => 'post',
					'ids'  => array( 42 ),
				),
				array(
					'type' => 'term',
					'ids'  => array( 7 ),
				),
			),
			WordPressState::meta_cache_calls()
		);
		self::assertSame(
			array(
				array(
					'ids'       => array( 42 ),
					'post_type' => 'wpse_event',
				),
			),
			WordPressState::object_term_cache_calls()
		);
	}

	/** Component-specific fallbacks remain outside shared REST data. */
	public function test_component_fallback_is_not_baked_into_shared_feed_data(): void {
		WordPressState::update_post_meta( 42, EventMeta::COLOR_MODE, EventColorMode::FALLBACK->value );

		self::assertSame( array(), ( new EventColorCollection() )->prepare( array( 42 ) ) );
	}
}
