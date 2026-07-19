<?php
/**
 * Explicit per-site plugin data cleanup.
 *
 * @package MiMe\WPSimpleEvents
 */

declare(strict_types=1);

namespace MiMe\WPSimpleEvents\Lifecycle;

use MiMe\WPSimpleEvents\Access\RoleManager;
use MiMe\WPSimpleEvents\Content\EventPostType;
use MiMe\WPSimpleEvents\Content\EventTaxonomies;
use MiMe\WPSimpleEvents\Frontend\EventTimezoneDisplaySettings;
use MiMe\WPSimpleEvents\Seo\StructuredDataSettings;
use MiMe\WPSimpleEvents\Routing\EventArchiveRewriteManager;
use MiMe\WPSimpleEvents\Routing\EventArchiveSettings;

/**
 * Deletes allowlisted plugin-owned data after an explicit uninstall opt-in.
 */
final class SiteDataCleaner {
	private const BATCH_SIZE = 100;

	/**
	 * Delete current-site plugin data through WordPress APIs.
	 *
	 * Shared media is deliberately retained. Options are deleted last so an
	 * interrupted content cleanup does not hide its incomplete state.
	 */
	public function clean(): bool {
		$this->register_content_types();

		$events_deleted = $this->delete_events();
		$terms_deleted  = $events_deleted && $this->delete_taxonomy_terms( EventTaxonomies::CATEGORY );
		$terms_deleted  = $terms_deleted && $this->delete_taxonomy_terms( EventTaxonomies::TAG );

		( new RoleManager() )->revoke();

		if ( ! $events_deleted || ! $terms_deleted ) {
			return false;
		}

		foreach ( $this->option_names() as $option_name ) {
			delete_option( $option_name );
		}

		return true;
	}

	/**
	 * Register the inactive plugin's post type and taxonomies for API cleanup.
	 */
	private function register_content_types(): void {
		( new EventPostType() )->register();
		( new EventTaxonomies() )->register();
	}

	/**
	 * Permanently delete all event posts in bounded batches.
	 */
	private function delete_events(): bool {
		while ( true ) {
			$post_ids = get_posts(
				array(
					'post_type'              => EventPostType::POST_TYPE,
					'post_status'            => array_keys( get_post_stati() ),
					'fields'                 => 'ids',
					'posts_per_page'         => self::BATCH_SIZE,
					'orderby'                => 'ID',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'suppress_filters'       => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			if ( array() === $post_ids ) {
				return true;
			}

			$deleted_in_batch = 0;

			foreach ( $post_ids as $post_id ) {
				$deleted = wp_delete_post( $post_id, true );

				if ( false !== $deleted && null !== $deleted ) {
					++$deleted_in_batch;
				}
			}

			if ( 0 === $deleted_in_batch ) {
				return false;
			}
		}
	}

	/**
	 * Permanently delete all terms from one plugin-owned taxonomy.
	 *
	 * @param string $taxonomy Taxonomy key.
	 */
	private function delete_taxonomy_terms( string $taxonomy ): bool {
		while ( true ) {
			$term_ids = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
					'number'     => self::BATCH_SIZE,
					'orderby'    => 'term_id',
					'order'      => 'ASC',
				)
			);

			if ( is_wp_error( $term_ids ) ) {
				return false;
			}

			if ( array() === $term_ids ) {
				return true;
			}

			$deleted_in_batch = 0;

			foreach ( $term_ids as $term_id ) {
				$deleted = wp_delete_term( $term_id, $taxonomy );

				if ( ! is_wp_error( $deleted ) && false !== $deleted ) {
					++$deleted_in_batch;
				}
			}

			if ( 0 === $deleted_in_batch ) {
				return false;
			}
		}
	}

	/**
	 * Return the exhaustive current plugin option allowlist.
	 *
	 * @return list<string>
	 */
	private function option_names(): array {
		return array(
			Installer::VERSION_OPTION,
			EventArchiveSettings::SLUG_OPTION,
			EventArchiveSettings::PER_PAGE_OPTION,
			EventArchiveSettings::DEFAULT_PERIOD_OPTION,
			EventArchiveRewriteManager::PENDING_OPTION,
			StructuredDataSettings::OPTION,
			EventTimezoneDisplaySettings::OPTION,
			UninstallSettings::OPTION,
		);
	}
}
