<?php
/**
 * Tests for the production occurrence repair window.
 *
 * @package MiMe\WPSimpleEvents\Tests\Unit
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Tests\Unit;

use MiMe\WPSimpleEvents\Admin\RecurrenceEditorAssets;
use MiMe\WPSimpleEvents\Occurrence\OccurrenceRepairWindowFactory;
use MiMe\WPSimpleEvents\Recurrence\RecurrenceGenerationWindow;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Proves repair uses exactly the editor's bounded production horizon.
 */
#[CoversClass( OccurrenceRepairWindowFactory::class )]
final class OccurrenceRepairWindowFactoryTest extends TestCase {
	/**
	 * The window spans 540 calendar days and uses the engine row cap.
	 */
	public function test_builds_editor_production_horizon(): void {
		$window = ( new OccurrenceRepairWindowFactory() )->from_date( '2026-08-24' );

		self::assertSame( 540, RecurrenceEditorAssets::HORIZON_DAYS );
		self::assertSame( '2026-08-24', $window->from_date() );
		self::assertSame( '2028-02-15', $window->through_date() );
		self::assertSame( RecurrenceGenerationWindow::MAX_ROWS, $window->max_rows() );
	}
}
