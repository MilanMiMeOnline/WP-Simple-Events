<?php
/**
 * Singular event structured-data output.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Seo;

use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Frontend\OccurrenceEventPresentationFactory;
use MiMe\WPSimpleEvents\Routing\OccurrenceRouteController;

/**
 * Adds safe JSON-LD to eligible singular event requests.
 */
final class StructuredDataController {
	/**
	 * Create the output controller.
	 *
	 * @param EventSchemaProvider                $provider                 Schema provider.
	 * @param StructuredDataDocument             $document                 Safe document renderer.
	 * @param StructuredDataSettings             $settings                 Output setting resolver.
	 * @param OccurrenceRouteController          $occurrences              Current exact occurrence route.
	 * @param OccurrenceEventPresentationFactory $occurrence_presentations Effective occurrence adapter.
	 */
	public function __construct(
		private readonly EventSchemaProvider $provider = new EventSchemaProvider(),
		private readonly StructuredDataDocument $document = new StructuredDataDocument(),
		private readonly StructuredDataSettings $settings = new StructuredDataSettings(),
		private readonly OccurrenceRouteController $occurrences = new OccurrenceRouteController(),
		private readonly OccurrenceEventPresentationFactory $occurrence_presentations = new OccurrenceEventPresentationFactory()
	) {}

	/**
	 * Register the front-end head callback.
	 */
	public function register(): void {
		add_action( 'wp_head', array( $this, 'render' ), 20 );
	}

	/**
	 * Output one schema graph on an eligible singular event request.
	 */
	public function render(): void {
		if ( ! is_singular( EventPostType::POST_TYPE ) ) {
			return;
		}

		$event_id = get_queried_object_id();

		if ( $event_id <= 0 || ! $this->settings->enabled( $event_id ) ) {
			return;
		}

		$occurrence = $this->occurrences->current();
		$schema     = null !== $occurrence
			? $this->provider->provide_presentation(
				$this->occurrence_presentations->create(
					$occurrence,
					$this->occurrences->canonical_url( $occurrence )
				)
			)
			: $this->provider->provide( $event_id );

		if ( null === $schema ) {
			return;
		}

		$output = $this->document->render( $schema );

		if ( '' !== $output ) {
			echo $output . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StructuredDataDocument applies JSON_HEX encoding at the script boundary.
		}
	}
}
