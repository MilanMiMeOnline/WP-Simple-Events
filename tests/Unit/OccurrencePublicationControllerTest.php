<?php
/**
 * Tests for occurrence projection repair on publication.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Occurrence\OccurrencePublicationController;
use MiMe\WPSimpleEvents\Tests\Support\FakeOccurrenceIndexRepairer;
use MiMe\WPSimpleEvents\Tests\Support\HookRecorder;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

/** Proves every event publication path establishes a public-ready projection. */
#[CoversClass( OccurrencePublicationController::class )]
final class OccurrencePublicationControllerTest extends TestCase {
	/** Reset deterministic hooks before every test. */
	protected function setUp(): void {
		HookRecorder::reset();
		WordPressState::reset();
	}

	/** The controller registers the canonical WordPress status-transition hook. */
	public function test_registers_publication_transition_hook(): void {
		( new OccurrencePublicationController() )->register();

		self::assertIsCallable( HookRecorder::action( 'transition_post_status' ) );
	}

	/** A draft event becoming public is repaired synchronously. */
	public function test_event_publication_repairs_projection(): void {
		$repairer = new FakeOccurrenceIndexRepairer();
		$post     = new WP_Post(
			array(
				'ID'        => 42,
				'post_type' => EventPostType::POST_TYPE,
			)
		);

		( new OccurrencePublicationController( $repairer ) )->transition( 'publish', 'draft', $post );

		self::assertSame( array( 42 ), $repairer->event_ids );
	}

	/** A clean indexed one-off event is not rebuilt merely because it is published. */
	public function test_clean_indexed_one_off_publication_is_unchanged(): void {
		$repairer = new FakeOccurrenceIndexRepairer();
		WordPressState::update_post_meta( 42, EventMeta::ACTIVE_GENERATION, 7 );

		( new OccurrencePublicationController( $repairer ) )->transition(
			'publish',
			'draft',
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		self::assertSame( array(), $repairer->event_ids );
	}

	/** An explicit dirty marker always repairs before the event becomes public. */
	public function test_dirty_projection_is_repaired_on_publication(): void {
		$repairer = new FakeOccurrenceIndexRepairer();
		WordPressState::update_post_meta( 42, EventMeta::ACTIVE_GENERATION, 7 );
		WordPressState::update_post_meta( 42, EventMeta::INDEX_DIRTY, true );

		( new OccurrencePublicationController( $repairer ) )->transition(
			'publish',
			'draft',
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		self::assertSame( array( 42 ), $repairer->event_ids );
	}

	/** Ordinary updates to an already-public event do not rebuild the projection. */
	public function test_existing_public_event_update_is_ignored(): void {
		$repairer = new FakeOccurrenceIndexRepairer();

		( new OccurrencePublicationController( $repairer ) )->transition(
			'publish',
			'publish',
			new WP_Post(
				array(
					'ID'        => 42,
					'post_type' => EventPostType::POST_TYPE,
				)
			)
		);

		self::assertSame( array(), $repairer->event_ids );
	}

	/** Publishing unrelated WordPress content never touches event projections. */
	public function test_unrelated_post_publication_is_ignored(): void {
		$repairer = new FakeOccurrenceIndexRepairer();

		( new OccurrencePublicationController( $repairer ) )->transition(
			'publish',
			'draft',
			new WP_Post(
				array(
					'ID'        => 9,
					'post_type' => 'post',
				)
			)
		);

		self::assertSame( array(), $repairer->event_ids );
	}
}
