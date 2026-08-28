<?php
/**
 * Tests for deterministic event color resolution.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Domain\EventColorMode;
use MiMe\WPSimpleEvents\Domain\EventColorPresentation;
use MiMe\WPSimpleEvents\Domain\EventColorResolver;
use MiMe\WPSimpleEvents\Domain\EventColorSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass( EventColorMode::class )]
#[CoversClass( EventColorPresentation::class )]
#[CoversClass( EventColorResolver::class )]
#[CoversClass( EventColorSource::class )]
/** Prevents category order, corrupt metadata or inaccessible colors becoming presentation. */
final class EventColorResolverTest extends TestCase {
	/** Custom mode uses only a normalized custom background and derived foreground. */
	public function test_custom_mode_resolves_the_event_override(): void {
		$result = ( new EventColorResolver() )->resolve(
			EventColorMode::CUSTOM->value,
			'#112233',
			0,
			array( 7 => '#abcdef' ),
			'#eeeeee'
		);

		self::assertInstanceOf( EventColorPresentation::class, $result );
		self::assertSame( '#112233', $result->background );
		self::assertSame( '#ffffff', $result->foreground );
		self::assertSame( EventColorSource::CUSTOM, $result->source );
		self::assertNull( $result->category_id );
	}

	/** An explicit category must still be assigned and carry a valid color. */
	public function test_explicit_category_requires_a_current_valid_assignment(): void {
		$resolver = new EventColorResolver();
		$valid    = $resolver->resolve(
			EventColorMode::CATEGORY->value,
			'',
			9,
			array(
				4 => '#112233',
				9 => '#abcdef',
			),
			'#eeeeee'
		);

		self::assertInstanceOf( EventColorPresentation::class, $valid );
		self::assertSame( '#abcdef', $valid->background );
		self::assertSame( EventColorSource::CATEGORY, $valid->source );
		self::assertSame( 9, $valid->category_id );

		$removed = $resolver->resolve(
			EventColorMode::CATEGORY->value,
			'',
			9,
			array( 4 => '#112233' ),
			'#eeeeee'
		);

		self::assertInstanceOf( EventColorPresentation::class, $removed );
		self::assertSame( EventColorSource::FALLBACK, $removed->source );
	}

	/** Automatic mode accepts one distinct color but never an arbitrary first category. */
	public function test_automatic_mode_is_deterministic_for_zero_one_and_many_colors(): void {
		$resolver = new EventColorResolver();

		$one = $resolver->resolve(
			EventColorMode::AUTOMATIC->value,
			'',
			0,
			array(
				9 => '#AABBCC',
				4 => '#aabbcc',
				2 => 'invalid',
			),
			'#eeeeee'
		);

		self::assertInstanceOf( EventColorPresentation::class, $one );
		self::assertSame( '#aabbcc', $one->background );
		self::assertSame( EventColorSource::CATEGORY, $one->source );
		self::assertNull( $one->category_id );

		$many = $resolver->resolve(
			EventColorMode::AUTOMATIC->value,
			'',
			0,
			array(
				9 => '#aabbcc',
				4 => '#112233',
			),
			'#eeeeee'
		);

		self::assertInstanceOf( EventColorPresentation::class, $many );
		self::assertSame( EventColorSource::FALLBACK, $many->source );

		$none = $resolver->resolve( '', '', 0, array(), '' );
		self::assertNull( $none );
	}

	/** Forced fallback and corrupt values cannot reactivate stale inactive metadata. */
	public function test_fallback_and_corrupt_modes_ignore_stale_values(): void {
		$resolver = new EventColorResolver();
		$fallback = $resolver->resolve(
			EventColorMode::FALLBACK->value,
			'#ff0000',
			9,
			array( 9 => '#00ff00' ),
			'#123456'
		);

		self::assertInstanceOf( EventColorPresentation::class, $fallback );
		self::assertSame( '#123456', $fallback->background );
		self::assertSame( EventColorSource::FALLBACK, $fallback->source );

		$corrupt = $resolver->resolve(
			'javascript:alert(1)',
			'#ff0000',
			9,
			array( 9 => '#00ff00' ),
			'#123456'
		);

		self::assertInstanceOf( EventColorPresentation::class, $corrupt );
		self::assertSame( EventColorSource::FALLBACK, $corrupt->source );
	}

	/** Missing mode is the migration-free automatic default; non-string mode fails safe. */
	public function test_absent_mode_is_automatic_but_non_string_mode_falls_back(): void {
		$resolver  = new EventColorResolver();
		$automatic = $resolver->resolve( '', '', 0, array( 5 => '#445566' ), '#eeeeee' );
		$corrupt   = $resolver->resolve( array( 'automatic' ), '', 0, array( 5 => '#445566' ), '#eeeeee' );

		self::assertInstanceOf( EventColorPresentation::class, $automatic );
		self::assertSame( EventColorSource::CATEGORY, $automatic->source );
		self::assertInstanceOf( EventColorPresentation::class, $corrupt );
		self::assertSame( EventColorSource::FALLBACK, $corrupt->source );
	}
}
