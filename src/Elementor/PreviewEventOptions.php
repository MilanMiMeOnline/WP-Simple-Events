<?php
/**
 * Bounded Elementor event preview choices.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Elementor;

use MiMe\WPSimpleEvents\Query\EventRepository;
use MiMe\WPSimpleEvents\Query\PublicEventOptions;

/**
 * Supplies published, password-free events through the shared repository.
 */
final class PreviewEventOptions {
	/**
	 * Shared host-neutral public choice provider.
	 *
	 * @var PublicEventOptions
	 */
	private PublicEventOptions $options;

	/**
	 * Create the bounded preview provider.
	 *
	 * @param EventRepository|PublicEventOptions $events Shared public event provider or repository.
	 */
	public function __construct( EventRepository|PublicEventOptions $events = new EventRepository() ) {
		$this->options = $events instanceof PublicEventOptions ? $events : new PublicEventOptions( $events );
	}

	/**
	 * Return select-control options keyed by event ID.
	 *
	 * @return array<int, string>
	 */
	public function options(): array {
		return $this->options->options();
	}
}
