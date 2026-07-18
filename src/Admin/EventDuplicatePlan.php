<?php
/**
 * Explicit event duplication policy.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Admin;

use MiMe\WPSimpleEvents\Content\EventMeta;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use WP_Post;

/**
 * Defines the post, metadata and taxonomy values an event duplicate may copy.
 */
final class EventDuplicatePlan {
	/**
	 * Build a new draft from editorial source fields only.
	 *
	 * @param WP_Post $source Source event.
	 * @return array{
	 *     post_type: string,
	 *     post_status: string,
	 *     post_password: string,
	 *     post_title: string,
	 *     post_excerpt: string,
	 *     post_content: string
	 * }
	 */
	public function post_data( WP_Post $source ): array {
		return array(
			'post_type'     => EventPostType::POST_TYPE,
			'post_status'   => 'draft',
			'post_password' => '',
			/* translators: %s: Original event title. */
			'post_title'    => sprintf( __( '%s — Copy', 'wp-simple-events' ), $source->post_title ),
			'post_excerpt'  => $source->post_excerpt,
			'post_content'  => $source->post_content,
		);
	}

	/**
	 * Return the complete metadata copy allowlist.
	 *
	 * @return list<string>
	 */
	public function meta_keys(): array {
		return array(
			EventMeta::START_LOCAL,
			EventMeta::END_LOCAL,
			EventMeta::START_UTC,
			EventMeta::END_UTC,
			EventMeta::ALL_DAY,
			EventMeta::TIMEZONE,
			EventMeta::VENUE,
			EventMeta::ADDRESS,
			EventMeta::LOCATION_URL,
			EventMeta::STATUS,
			'_thumbnail_id',
		);
	}

	/**
	 * Return the event-only taxonomy copy allowlist.
	 *
	 * @return list<string>
	 */
	public function taxonomies(): array {
		return array( EventTaxonomies::CATEGORY, EventTaxonomies::TAG );
	}
}
