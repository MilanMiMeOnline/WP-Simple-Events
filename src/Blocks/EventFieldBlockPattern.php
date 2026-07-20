<?php
/**
 * Opt-in single-event atomic block pattern.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Blocks;

/** Supplies a neutral complete event layout without replacing templates. */
final class EventFieldBlockPattern {
	/** Return serialized pattern content containing the complete atomic palette. */
	public function content(): string {
		$blocks = array_map(
			static fn ( string $slug ): string => '<!-- wp:wpse/' . $slug . ' /-->',
			EventFieldBlockDefinitions::slugs()
		);

		return '<!-- wp:group {"tagName":"article","layout":{"type":"constrained"}} -->'
			. '<article class="wp-block-group">'
			. implode( '', $blocks )
			. '</article><!-- /wp:group -->';
	}
}
