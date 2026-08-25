<?php
/**
 * Tests for the occurrence projection schema.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use InvalidArgumentException;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceTableSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Protects dbDelta compatibility, identity uniqueness and query indexes.
 */
#[CoversClass( OccurrenceTableSchema::class )]
final class OccurrenceTableSchemaTest extends TestCase {
	/**
	 * The schema supports safe generations and bounded chronological queries.
	 */
	public function test_schema_contains_required_projection_keys(): void {
		$sql = ( new OccurrenceTableSchema() )->create_sql(
			'wp_wpse_event_occurrences',
			'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
		);

		self::assertStringContainsString( 'PRIMARY KEY  (id)', $sql );
		self::assertStringContainsString( 'UNIQUE KEY event_generation_key (event_id,generation,public_key)', $sql );
		self::assertStringContainsString( 'KEY event_generation_start (event_id,generation,start_utc)', $sql );
		self::assertStringContainsString( 'created_utc bigint(20) NOT NULL DEFAULT 0', $sql );
		self::assertStringContainsString( 'KEY generation_cleanup (created_utc,event_id,generation)', $sql );
		self::assertStringContainsString( 'KEY status_start (event_status,start_utc)', $sql );
		self::assertStringEndsWith( 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;', $sql );
	}

	/**
	 * A caller cannot inject SQL through a table identifier.
	 */
	public function test_invalid_table_name_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );

		( new OccurrenceTableSchema() )->create_sql( 'wp_events; DROP TABLE wp_posts', '' );
	}
}
