<?php
/**
 * Tests for access-aware event presentation context resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\EventContextResolver;
use MiMe\WPSimpleEvents\Frontend\EventPresentation;
use MiMe\WPSimpleEvents\Frontend\EventPresentationFactory;
use MiMe\WPSimpleEvents\Tests\Support\WordPressState;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WP_Post;

#[CoversClass( EventContextResolver::class )]
#[CoversClass( EventPresentation::class )]
#[CoversClass( EventPresentationFactory::class )]
/**
 * Verifies current-context authorization, explicit public selection and reuse.
 */
final class EventContextResolverTest extends TestCase {
	/** Reset deterministic WordPress state. */
	protected function setUp(): void {
		WordPressState::reset();
	}

	/**
	 * Explicit selection accepts only public, password-free events.
	 */
	public function test_explicit_context_rejects_every_non_public_source(): void {
		$this->add_post( 10, EventPostType::POST_TYPE, 'publish' );
		$this->add_post( 11, EventPostType::POST_TYPE, 'draft' );
		$this->add_post( 12, EventPostType::POST_TYPE, 'private' );
		$this->add_post( 13, EventPostType::POST_TYPE, 'publish', 'secret' );
		$this->add_post( 14, 'page', 'publish' );
		$resolver = new EventContextResolver();

		self::assertSame( 10, $resolver->resolve_public( 10 )?->event->ID );
		self::assertNull( $resolver->resolve_public( 0 ) );
		self::assertNull( $resolver->resolve_public( 11 ) );
		self::assertNull( $resolver->resolve_public( 12 ) );
		self::assertNull( $resolver->resolve_public( 13 ) );
		self::assertNull( $resolver->resolve_public( 14 ) );
		self::assertNull( $resolver->resolve_public( 999 ) );
	}

	/**
	 * Current context permits an authorized editorial preview but not a leak.
	 */
	public function test_current_context_requires_read_access_for_non_public_events(): void {
		$this->add_post( 21, EventPostType::POST_TYPE, 'draft' );
		WordPressState::set_singular_event( true, 21 );
		$resolver = new EventContextResolver();

		self::assertNull( $resolver->resolve_current() );

		WordPressState::allow_current_user( true );

		self::assertSame( 21, $resolver->resolve_current()?->event->ID );
	}

	/**
	 * Password state remains available to the composite renderer in current context.
	 */
	public function test_current_context_preserves_password_protection(): void {
		$this->add_post( 31, EventPostType::POST_TYPE, 'publish', 'secret' );

		$presentation = ( new EventContextResolver() )->resolve_current( 31 );

		self::assertNotNull( $presentation );
		self::assertSame( 'secret', $presentation->event->post_password );
	}

	/**
	 * One resolver reuses a presentation snapshot only for the current request.
	 */
	public function test_resolved_presentation_is_reused_within_one_resolver(): void {
		$this->add_post( 41, EventPostType::POST_TYPE, 'publish' );
		WordPressState::update_post_meta( 41, EventMeta::VENUE, 'First venue' );
		$resolver = new EventContextResolver();
		$first    = $resolver->resolve_public( 41 );

		WordPressState::update_post_meta( 41, EventMeta::VENUE, 'Changed later' );
		$second = $resolver->resolve_public( 41 );
		$fresh  = ( new EventContextResolver() )->resolve_public( 41 );

		self::assertSame( $first, $second );
		self::assertSame( 'First venue', $second?->venue );
		self::assertSame( 'Changed later', $fresh?->venue );
	}

	/**
	 * Add one deterministic post fixture.
	 *
	 * @param int    $id       Post ID.
	 * @param string $type     Post type.
	 * @param string $status   Publication status.
	 * @param string $password Optional post password.
	 */
	private function add_post( int $id, string $type, string $status, string $password = '' ): void {
		WordPressState::add_post(
			new WP_Post(
				array(
					'ID'            => $id,
					'post_type'     => $type,
					'post_status'   => $status,
					'post_password' => $password,
					'post_title'    => 'Event ' . $id,
				)
			),
			'https://example.com/events/' . $id . '/'
		);
	}
}
