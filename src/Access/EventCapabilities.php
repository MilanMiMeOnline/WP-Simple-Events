<?php
/**
 * Event capability contract.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Access;

/**
 * Defines post-type, taxonomy and role capabilities for events.
 */
final class EventCapabilities {
	public const EDIT_POST              = 'edit_wpse_event';
	public const READ_POST              = 'read_wpse_event';
	public const DELETE_POST            = 'delete_wpse_event';
	public const EDIT_POSTS             = 'edit_wpse_events';
	public const EDIT_OTHERS_POSTS      = 'edit_others_wpse_events';
	public const PUBLISH_POSTS          = 'publish_wpse_events';
	public const READ_PRIVATE_POSTS     = 'read_private_wpse_events';
	public const DELETE_POSTS           = 'delete_wpse_events';
	public const DELETE_PRIVATE_POSTS   = 'delete_private_wpse_events';
	public const DELETE_PUBLISHED_POSTS = 'delete_published_wpse_events';
	public const DELETE_OTHERS_POSTS    = 'delete_others_wpse_events';
	public const EDIT_PRIVATE_POSTS     = 'edit_private_wpse_events';
	public const EDIT_PUBLISHED_POSTS   = 'edit_published_wpse_events';
	public const MANAGE_TERMS           = 'manage_wpse_event_terms';
	public const EDIT_TERMS             = 'edit_wpse_event_terms';
	public const DELETE_TERMS           = 'delete_wpse_event_terms';
	public const ASSIGN_TERMS           = 'assign_wpse_event_terms';

	/**
	 * Return the complete post-type capability map.
	 *
	 * @return array<string, string>
	 */
	public static function post_type_map(): array {
		return array(
			'edit_post'              => self::EDIT_POST,
			'read_post'              => self::READ_POST,
			'delete_post'            => self::DELETE_POST,
			'edit_posts'             => self::EDIT_POSTS,
			'edit_others_posts'      => self::EDIT_OTHERS_POSTS,
			'publish_posts'          => self::PUBLISH_POSTS,
			'read_private_posts'     => self::READ_PRIVATE_POSTS,
			'delete_posts'           => self::DELETE_POSTS,
			'delete_private_posts'   => self::DELETE_PRIVATE_POSTS,
			'delete_published_posts' => self::DELETE_PUBLISHED_POSTS,
			'delete_others_posts'    => self::DELETE_OTHERS_POSTS,
			'edit_private_posts'     => self::EDIT_PRIVATE_POSTS,
			'edit_published_posts'   => self::EDIT_PUBLISHED_POSTS,
			'create_posts'           => self::EDIT_POSTS,
		);
	}

	/**
	 * Return the taxonomy capability map.
	 *
	 * @return array<string, string>
	 */
	public static function taxonomy_map(): array {
		return array(
			'manage_terms' => self::MANAGE_TERMS,
			'edit_terms'   => self::EDIT_TERMS,
			'delete_terms' => self::DELETE_TERMS,
			'assign_terms' => self::ASSIGN_TERMS,
		);
	}

	/**
	 * Return primitive capabilities granted to editorial event roles.
	 *
	 * Meta capabilities are deliberately excluded because WordPress maps them.
	 *
	 * @return list<string>
	 */
	public static function editorial(): array {
		return array(
			self::EDIT_POSTS,
			self::EDIT_OTHERS_POSTS,
			self::PUBLISH_POSTS,
			self::READ_PRIVATE_POSTS,
			self::DELETE_POSTS,
			self::DELETE_PRIVATE_POSTS,
			self::DELETE_PUBLISHED_POSTS,
			self::DELETE_OTHERS_POSTS,
			self::EDIT_PRIVATE_POSTS,
			self::EDIT_PUBLISHED_POSTS,
			self::MANAGE_TERMS,
			self::EDIT_TERMS,
			self::DELETE_TERMS,
			self::ASSIGN_TERMS,
		);
	}

	/**
	 * Return role names that receive editorial event capabilities.
	 *
	 * @return list<string>
	 */
	public static function editorial_roles(): array {
		return array( 'administrator', 'editor' );
	}
}
