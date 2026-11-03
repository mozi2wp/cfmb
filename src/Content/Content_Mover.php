<?php
/**
 * Moves content when a post type or taxonomy key changes.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Renaming a post type or taxonomy key would leave its posts or terms under
 * the old key, invisible in the admin. This moves them along, and points
 * CFMB's own location rules and links between definitions at the new key.
 */
class Content_Mover {

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Post type definitions.
	 *
	 * @var Post_Type_Repository
	 */
	private Post_Type_Repository $post_types;

	/**
	 * Taxonomy definitions.
	 *
	 * @var Taxonomy_Repository
	 */
	private Taxonomy_Repository $taxonomies;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository     $groups     Field Groups.
	 * @param Post_Type_Repository $post_types Post type definitions.
	 * @param Taxonomy_Repository  $taxonomies Taxonomy definitions.
	 */
	public function __construct( Group_Repository $groups, Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies ) {
		$this->groups     = $groups;
		$this->post_types = $post_types;
		$this->taxonomies = $taxonomies;
	}

	/**
	 * Point CFMB at a new post type key, and move its posts along.
	 *
	 * @param string $from       Old key.
	 * @param string $to         New key.
	 * @param bool   $move_posts Whether to move the posts too.
	 * @return int Number of posts moved.
	 */
	public function move_post_type( string $from, string $to, bool $move_posts = true ): int {
		global $wpdb;

		if ( '' === $from || '' === $to || $from === $to ) {
			return 0;
		}

		$ids = array();

		if ( $move_posts ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off move; caches are cleaned below.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", $from ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->posts, array( 'post_type' => $to ), array( 'post_type' => $from ), array( '%s' ), array( '%s' ) );

			foreach ( $ids as $id ) {
				clean_post_cache( (int) $id );
			}
		}

		$this->rewrite_group_rules(
			static function ( array $rule ) use ( $from, $to ): array {
				if ( in_array( $rule['param'], array( 'post_type', 'comment' ), true ) && $rule['value'] === $from ) {
					$rule['value'] = $to;
				}

				return $rule;
			}
		);

		foreach ( $this->taxonomies->all() as $taxonomy ) {
			$types = (array) $taxonomy['settings']['post_types'];

			if ( 'db' === $taxonomy['source'] && in_array( $from, $types, true ) ) {
				$taxonomy['settings']['post_types'] = array_values( array_unique( array_map( static fn( $type ) => $type === $from ? $to : $type, $types ) ) );
				$this->taxonomies->save( $taxonomy );
			}
		}

		return count( $ids );
	}

	/**
	 * Point CFMB at a new taxonomy key, and move its terms along.
	 *
	 * @param string $from       Old key.
	 * @param string $to         New key.
	 * @param bool   $move_terms Whether to move the terms too.
	 * @return int Number of terms moved.
	 */
	public function move_taxonomy( string $from, string $to, bool $move_terms = true ): int {
		global $wpdb;

		if ( '' === $from || '' === $to || $from === $to ) {
			return 0;
		}

		$ids = array();

		if ( $move_terms ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off move; caches are cleaned below.
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $from ) ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => $to ), array( 'taxonomy' => $from ), array( '%s' ), array( '%s' ) );

			if ( $ids ) {
				clean_term_cache( $ids, $from );
				clean_term_cache( $ids, $to );
			}
		}

		$this->rewrite_group_rules(
			static function ( array $rule ) use ( $from, $to ): array {
				if ( 'taxonomy' === $rule['param'] && $rule['value'] === $from ) {
					$rule['value'] = $to;
				}

				if ( 'post_term' === $rule['param'] && 0 === strpos( $rule['value'], $from . ':' ) ) {
					$rule['value'] = $to . substr( $rule['value'], strlen( $from ) );
				}

				return $rule;
			}
		);

		foreach ( $this->post_types->all() as $post_type ) {
			$list = (array) $post_type['settings']['taxonomies'];

			if ( 'db' === $post_type['source'] && in_array( $from, $list, true ) ) {
				$post_type['settings']['taxonomies'] = array_values( array_unique( array_map( static fn( $tax ) => $tax === $from ? $to : $tax, $list ) ) );
				$this->post_types->save( $post_type );
			}
		}

		return count( $ids );
	}

	/**
	 * Point "Options page is …" rules at a new page ID. Saved values stay
	 * under the old option names.
	 *
	 * @param string $from Old page ID.
	 * @param string $to   New page ID.
	 * @return void
	 */
	public function rename_options_page( string $from, string $to ): void {
		if ( '' === $from || '' === $to || $from === $to ) {
			return;
		}

		$this->rewrite_group_rules(
			static function ( array $rule ) use ( $from, $to ): array {
				if ( 'options_page' === $rule['param'] && $rule['value'] === $from ) {
					$rule['value'] = $to;
				}

				return $rule;
			}
		);
	}

	/**
	 * Apply a change to every location rule of every stored group, saving
	 * only the groups that changed.
	 *
	 * @param callable $change Receives a rule, returns it.
	 * @return void
	 */
	private function rewrite_group_rules( callable $change ): void {
		foreach ( $this->groups->all() as $group ) {
			if ( 'db' !== $group->source() ) {
				continue;
			}

			$location = $group->location();
			$changed  = false;

			foreach ( $location as $or => $rules ) {
				foreach ( (array) $rules as $and => $rule ) {
					$rule = array_merge(
						array(
							'param' => '',
							'value' => '',
						),
						(array) $rule
					);
					$new  = $change( $rule );

					if ( $new !== $rule ) {
						$location[ $or ][ $and ] = $new;
						$changed                 = true;
					}
				}
			}

			if ( $changed ) {
				$this->groups->save( array_merge( $group->to_array(), array( 'location' => $location ) ) );
			}
		}
	}
}
