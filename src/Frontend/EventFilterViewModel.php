<?php
/**
 * Shared immutable public event-filter state.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Frontend;

use WP_Term;

/**
 * Carries normalized filter choices and navigation without editor-specific state.
 */
final readonly class EventFilterViewModel {
	/**
	 * Store one normalized component state snapshot.
	 *
	 * @param string                         $action              Base page URL.
	 * @param array<string, string|string[]> $preserved           Other component state.
	 * @param array<string, string|string[]> $current             Current component state.
	 * @param string                         $category_key        Category request key.
	 * @param string                         $tag_key             Tag request key.
	 * @param WP_Term[]                      $categories          Available categories.
	 * @param WP_Term[]                      $tags                Available tags.
	 * @param string[]                       $selected_categories Selected category slugs.
	 * @param string[]                       $selected_tags       Selected tag slugs.
	 * @param string                         $clear_url           Clear-all URL.
	 * @param string                         $restore_url         Optional restore-default URL.
	 * @param bool                           $submitted           Whether visitor state was submitted.
	 */
	public function __construct(
		public string $action,
		public array $preserved,
		public array $current,
		public string $category_key,
		public string $tag_key,
		public array $categories,
		public array $tags,
		public array $selected_categories,
		public array $selected_tags,
		public string $clear_url,
		public string $restore_url,
		public bool $submitted
	) {}
}
