<?php
/**
 * Public event list shortcode.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Shortcode;

use MiMe\WPSimpleEvents\Frontend\EventListRenderer;
use MiMe\WPSimpleEvents\Frontend\FrontendAssets;
use MiMe\WPSimpleEvents\Frontend\RenderInstanceIds;
use MiMe\WPSimpleEvents\Query\EventRepository;
use WP_Post;

/**
 * Adapts allowlisted shortcode input to shared query and rendering services.
 */
final class EventListShortcode implements ShortcodeRenderer {
	/**
	 * Create the shortcode adapter.
	 *
	 * @param EventRepository   $events   Public event repository.
	 * @param EventListRenderer $renderer Shared collection renderer.
	 * @param EventListControls $controls Filter and pagination renderer.
	 * @param FrontendAssets    $assets   Scoped front-end assets.
	 */
	public function __construct(
		private readonly EventRepository $events = new EventRepository(),
		private readonly EventListRenderer $renderer = new EventListRenderer(),
		private readonly EventListControls $controls = new EventListControls(),
		private readonly FrontendAssets $assets = new FrontendAssets()
	) {}

	/**
	 * Register the public shortcode.
	 */
	public function register(): void {
		add_shortcode( 'wpse_events', array( $this, 'render' ) );
	}

	/**
	 * Return one isolated event list or grid.
	 *
	 * @param array<string, mixed>|string $attributes Raw shortcode attributes.
	 */
	public function render( array|string $attributes = array() ): string {
		$instance    = RenderInstanceIds::next( RenderInstanceIds::EVENT_LIST );
		$instance_id = 'wpse-events-' . $instance;
		$results_id  = $instance_id . '-results';
		$prefix      = 'wpse_' . $instance;
		$request     = $this->request_values();
		$normalized  = EventListAttributes::from_shortcode( is_array( $attributes ) ? $attributes : array() )
			->with_request( $request, $prefix );
		$query       = $this->events->query( $normalized->criteria( time() ) );
		$posts       = array_values(
			array_filter( $query->posts, static fn ( mixed $post ): bool => $post instanceof WP_Post )
		);

		$this->assets->enqueue();

		$output = '<div id="' . esc_attr( $instance_id ) . '" class="wpse-events">';

		if ( $normalized->filters ) {
			$output .= $this->controls->filters( $normalized, $prefix, $results_id, $request );
		}

		$output .= $this->renderer->render(
			$posts,
			$normalized->view,
			$normalized->columns,
			$normalized->card_options(),
			$results_id
		);

		if ( $normalized->pagination ) {
			$output .= $this->controls->pagination(
				$normalized->page,
				(int) $query->max_num_pages,
				$prefix . '_page'
			);
		}

		return $output . '</div>';
	}

	/**
	 * Normalize only string-keyed public query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function request_values(): array {
		$request = array();

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only public filters; all values are allowlisted later.
		foreach ( $_GET as $key => $value ) {
			if ( is_string( $key ) ) {
				$request[ $key ] = wp_unslash( $value );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $request;
	}
}
