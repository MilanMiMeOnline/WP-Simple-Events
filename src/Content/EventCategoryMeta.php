<?php
/**
 * Event-category presentation metadata.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Content;

use MiMe\WPSimpleEvents\Access\EventCapabilities;
use MiMe\WPSimpleEvents\Domain\HexColor;

/** Registers one optional, private and strictly bounded category color. */
final class EventCategoryMeta {
	public const COLOR = '_wpse_category_color';

	/** Register all category metadata fields. */
	public function register(): void {
		foreach ( $this->definitions() as $meta_key => $arguments ) {
			register_term_meta( EventTaxonomies::CATEGORY, $meta_key, $arguments );
		}
	}

	/**
	 * Build typed category metadata definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function definitions(): array {
		return array(
			self::COLOR => array(
				'type'              => 'string',
				'label'             => __( 'Event category color', 'mime-simple-events-calendar' ),
				'description'       => __( 'Optional normalized calendar background color.', 'mime-simple-events-calendar' ),
				'single'            => true,
				'default'           => '',
				'sanitize_callback' => array( HexColor::class, 'normalize' ),
				'auth_callback'     => array( $this, 'authorize' ),
				'show_in_rest'      => false,
			),
		);
	}

	/**
	 * Authorize term metadata mutations through the event taxonomy capability.
	 *
	 * @param bool   $allowed Existing authorization result.
	 * @param string $meta_key Registered meta key.
	 * @param int    $term_id Event category ID.
	 */
	public function authorize( bool $allowed, string $meta_key, int $term_id ): bool {
		unset( $allowed, $meta_key, $term_id );

		return current_user_can( EventCapabilities::MANAGE_TERMS );
	}
}
