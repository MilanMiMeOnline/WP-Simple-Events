<?php
/**
 * Tests for native event archive headings.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventArchiveTitle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Query;
use WP_Term;

#[CoversClass( EventArchiveTitle::class )]
/** Freezes plugin-owned, plain-text taxonomy archive headings. */
final class EventArchiveTitleTest extends TestCase {
	/** Categories and tags receive distinct translated plain-text headings. */
	public function test_builds_plain_text_headings_for_both_event_taxonomies(): void {
		$title    = new EventArchiveTitle();
		$category = new WP_Query(
			array(
				'taxonomy'       => EventTaxonomies::CATEGORY,
				'queried_object' => $this->term( 'Rock & <span>Roll</span>', EventTaxonomies::CATEGORY ),
			)
		);
		$tag      = new WP_Query(
			array(
				'taxonomy'       => EventTaxonomies::TAG,
				'queried_object' => $this->term( 'Inside', EventTaxonomies::TAG ),
			)
		);

		self::assertSame( 'Events in “Rock & Roll”', $title->taxonomy( $category ) );
		self::assertSame( 'Events tagged “Inside”', $title->taxonomy( $tag ) );
	}

	/** Malformed, empty and substituted queried objects fail to a neutral heading. */
	public function test_rejects_malformed_or_mismatched_query_objects(): void {
		$title = new EventArchiveTitle();

		self::assertSame( 'Events', $title->taxonomy( new WP_Query() ) );
		self::assertSame(
			'Events',
			$title->taxonomy(
				new WP_Query(
					array(
						'taxonomy'       => EventTaxonomies::CATEGORY,
						'queried_object' => $this->term( 'Inside', EventTaxonomies::TAG ),
					)
				)
			)
		);
		self::assertSame(
			'Events',
			$title->taxonomy(
				new WP_Query(
					array(
						'taxonomy'       => EventTaxonomies::TAG,
						'queried_object' => $this->term( '<span></span>', EventTaxonomies::TAG ),
					)
				)
			)
		);
	}

	/**
	 * Build one deterministic term object.
	 *
	 * @param string $name     Public term name.
	 * @param string $taxonomy Taxonomy identifier.
	 */
	private function term( string $name, string $taxonomy ): WP_Term {
		return new WP_Term(
			array(
				'term_id'  => 42,
				'name'     => $name,
				'slug'     => 'term',
				'taxonomy' => $taxonomy,
			)
		);
	}
}
