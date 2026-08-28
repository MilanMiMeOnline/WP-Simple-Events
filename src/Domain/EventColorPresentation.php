<?php
/**
 * Resolved event color presentation.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Domain;

/** Carries only normalized background/foreground values to public adapters. */
final readonly class EventColorPresentation {
	/**
	 * Store one already-normalized presentation result.
	 *
	 * @param string           $background  Six-digit background.
	 * @param string           $foreground  Derived black or white foreground.
	 * @param EventColorSource $source      Resolved source.
	 * @param int|null         $category_id Exact category source when unambiguous.
	 */
	public function __construct(
		public string $background,
		public string $foreground,
		public EventColorSource $source,
		public ?int $category_id = null
	) {}
}
