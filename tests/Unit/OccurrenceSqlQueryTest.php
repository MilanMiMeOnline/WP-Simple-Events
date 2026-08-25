<?php
/**
 * Tests for the internal occurrence SQL contract.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceSqlQuery;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Proves that the WordPress adapter receives only strictly typed placeholders.
 */
#[CoversClass( OccurrenceSqlQuery::class )]
final class OccurrenceSqlQueryTest extends TestCase {
	/**
	 * Supported placeholders accept only their exact scalar shapes.
	 */
	public function test_supported_placeholders_are_accepted(): void {
		$query = new OccurrenceSqlQuery(
			'SELECT * FROM wp_table WHERE title = %s AND event_id = %d AND score = %f',
			array( 'Event', 42, 4.5 )
		);

		self::assertSame( array( 'Event', 42, 4.5 ), $query->parameters );
	}

	/**
	 * Invalid placeholder contracts fail before reaching WordPress.
	 *
	 * @param string                 $sql        Invalid SQL template.
	 * @param list<int|float|string> $parameters Invalid values.
	 */
	#[DataProvider( 'invalid_query_provider' )]
	public function test_invalid_placeholder_contract_is_rejected( string $sql, array $parameters ): void {
		$this->expectException( InvalidArgumentException::class );

		new OccurrenceSqlQuery( $sql, $parameters );
	}

	/**
	 * Return unsupported, incomplete and mismatched query contracts.
	 *
	 * @return iterable<string, array{0: string, 1: list<int|float|string>}>
	 */
	public static function invalid_query_provider(): iterable {
		yield 'unsupported identifier placeholder' => array( 'SELECT * FROM %i', array( 'wp_posts' ) );
		yield 'too few values' => array( 'SELECT * FROM wp_posts WHERE ID = %d', array() );
		yield 'too many values' => array( 'SELECT * FROM wp_posts', array( 1 ) );
		yield 'wrong integer type' => array( 'SELECT * FROM wp_posts WHERE ID = %d', array( '1' ) );
		yield 'wrong string type' => array( 'SELECT * FROM wp_posts WHERE post_type = %s', array( 1 ) );
		yield 'wrong float type' => array( 'SELECT * FROM wp_posts WHERE score = %f', array( '1.2' ) );
	}
}
