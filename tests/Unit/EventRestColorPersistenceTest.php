<?php
/**
 * Tests for color persistence through the authoritative REST event boundary.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Rest\EventRestController;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use WP_Post;
use WP_REST_Request;

#[CoversClass( EventRestController::class )]
/** Confirms Gutenberg/REST writes use the same canonical color cleanup as native saves. */
final class EventRestColorPersistenceTest extends TestCase {
	/** Reset deterministic metadata before each REST journey. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/** A validated REST save persists normalized custom series color intent. */
	public function test_validated_rest_save_persists_bounded_color_intent(): void {
		$request = new WP_REST_Request();
		$request->set_param( 'id', 42 );
		$request->set_param(
			'meta',
			array(
				EventMeta::START_LOCAL => '2027-02-03T18:30:00',
				EventMeta::END_LOCAL   => '2027-02-03T21:00:00',
				EventMeta::ALL_DAY     => false,
				EventMeta::TIMEZONE    => 'Europe/Brussels',
				EventMeta::STATUS      => 'scheduled',
				EventMeta::COLOR_MODE  => EventColorMode::CUSTOM->value,
				EventMeta::COLOR       => '#AABBCC',
			)
		);
		$prepared              = new stdClass();
		$prepared->post_status = 'publish';
		$controller            = new EventRestController();

		self::assertSame( $prepared, $controller->validate( $prepared, $request ) );
		$controller->persist(
			new WP_Post(
				array(
					'ID'          => 42,
					'post_type'   => EventPostType::POST_TYPE,
					'post_status' => 'publish',
				)
			),
			$request,
			false
		);

		self::assertSame( EventColorMode::CUSTOM->value, WordPressState::post_meta( 42, EventMeta::COLOR_MODE ) );
		self::assertSame( '#aabbcc', WordPressState::post_meta( 42, EventMeta::COLOR ) );
	}
}
