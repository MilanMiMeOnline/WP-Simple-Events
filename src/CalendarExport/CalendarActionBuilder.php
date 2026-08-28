<?php
/**
 * Shared add-to-calendar action construction.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\CalendarExport;

use InvalidArgumentException;

/** Maps one immutable snapshot to the author's allowlisted provider choices. */
final readonly class CalendarActionBuilder {
	/**
	 * Create the action builder.
	 *
	 * @param CalendarExportUrlBuilder   $local    Same-origin ICS URL builder.
	 * @param CalendarProviderUrlBuilder $external External compose-link builder.
	 */
	public function __construct(
		private CalendarExportUrlBuilder $local = new CalendarExportUrlBuilder(),
		private CalendarProviderUrlBuilder $external = new CalendarProviderUrlBuilder()
	) {}

	/**
	 * Build provider actions in stable display order.
	 *
	 * @param CalendarExportSnapshot $snapshot Eligible snapshot.
	 * @param CalendarProvider[]     $providers Unique allowlisted providers.
	 * @return CalendarActionLink[]
	 * @throws InvalidArgumentException When provider input is not unique and allowlisted.
	 */
	public function build( CalendarExportSnapshot $snapshot, array $providers ): array {
		$actions = array();
		$seen    = array();

		foreach ( $providers as $provider ) {
			if ( isset( $seen[ $provider->value ] ) ) {
				throw new InvalidArgumentException( 'Calendar providers must be unique allowlisted values.' );
			}

			$seen[ $provider->value ] = true;
			$url                      = CalendarProvider::ICS === $provider
				? $this->local->build( $snapshot )
				: $this->external->build( $provider, $snapshot );

			if ( '' !== $url ) {
				$actions[] = new CalendarActionLink( $provider, $url, CalendarProvider::ICS !== $provider );
			}
		}

		return $actions;
	}
}
