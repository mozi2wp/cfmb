<?php
/**
 * Reads ACF and Secure Custom Fields definitions.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

defined( 'ABSPATH' ) || exit;

/**
 * ACF and SCF store definitions the same way, so one reader covers both,
 * active or not:
 *
 * - Field Groups as acf-field-group posts, fields as acf-field posts
 *   (settings serialized in post_content)
 * - post types, taxonomies and options pages (ACF 6.1+ / SCF) as
 *   acf-post-type, acf-taxonomy and acf-ui-options-page posts
 * - local JSON files in the theme's acf-json folder
 * - with ACF or SCF active, groups and options pages registered in PHP
 *
 * The database copy wins over JSON and PHP for the same key, as in ACF.
 * Serialized settings are read with allowed_classes off, so no objects are
 * ever created from them.
 */
class Acf_Reader {

	/**
	 * Which plugin is active: acf, acf-pro, scf, or empty.
	 *
	 * @return string
	 */
	public static function active_plugin(): string {
		if ( defined( 'SCF_VERSION' ) || class_exists( 'SCF' ) ) {
			return 'scf';
		}

		if ( defined( 'ACF_PRO' ) && ACF_PRO ) {
			return 'acf-pro';
		}

		return class_exists( 'ACF' ) || function_exists( 'acf_get_field_groups' ) ? 'acf' : '';
	}

	/**
	 * Every Field Group with its fields nested, by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function groups(): array {
		$groups = array();

		foreach ( $this->local_groups() as $key => $group ) {
			$groups[ $key ] = $group;
		}

		foreach ( $this->json( 'group_' ) as $key => $group ) {
			$group['source'] = 'json';
			$groups[ $key ]  = $group;
		}

		foreach ( $this->stored_groups() as $key => $group ) {
			$groups[ $key ] = $group;
		}

		return $groups;
	}

	/**
	 * Post type definitions, by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function post_types(): array {
		return $this->stored_items( 'acf-post-type' ) + $this->json( 'post_type_' );
	}

	/**
	 * Taxonomy definitions, by key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function taxonomies(): array {
		return $this->stored_items( 'acf-taxonomy' ) + $this->json( 'taxonomy_' );
	}

	/**
	 * Options pages from the UI, JSON and, when ACF is active, PHP. By menu slug.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function options_pages(): array {
		$pages = array();

		if ( function_exists( 'acf_get_options_pages' ) ) {
			foreach ( (array) acf_get_options_pages() as $page ) {
				if ( is_array( $page ) && ! empty( $page['menu_slug'] ) ) {
					$pages[ (string) $page['menu_slug'] ] = $page + array( 'source' => 'php' );
				}
			}
		}

		foreach ( $this->stored_items( 'acf-ui-options-page' ) + $this->json( 'ui_options_page_' ) as $item ) {
			if ( ! empty( $item['menu_slug'] ) ) {
				$pages[ (string) $item['menu_slug'] ] = $item;
			}
		}

		return $pages;
	}

	/**
	 * Groups stored in the database.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function stored_groups(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off read for the Switch Assistant; ACF's own statuses aren't registered when it is inactive, so WP_Query can't find them.
		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_name, post_excerpt, post_content, post_parent, menu_order, post_status, post_type FROM {$wpdb->posts} WHERE post_type IN ('acf-field-group','acf-field') AND post_status IN ('publish','acf-disabled') ORDER BY menu_order ASC, ID ASC",
			ARRAY_A
		);

		$groups   = array();
		$fields   = array();
		$children = array();

		foreach ( (array) $rows as $row ) {
			$settings = self::unserialize( (string) $row['post_content'] );

			if ( 'acf-field-group' === $row['post_type'] ) {
				$groups[ (int) $row['ID'] ] = array_merge(
					$settings,
					array(
						'ID'     => (int) $row['ID'],
						'key'    => (string) $row['post_name'],
						'title'  => (string) $row['post_title'],
						'active' => 'publish' === $row['post_status'],
						'source' => 'db',
					)
				);
				continue;
			}

			$fields[ (int) $row['ID'] ] = array_merge(
				$settings,
				array(
					'ID'    => (int) $row['ID'],
					'key'   => (string) $row['post_name'],
					'label' => (string) $row['post_title'],
					'name'  => (string) $row['post_excerpt'],
				)
			);

			$children[ (int) $row['post_parent'] ][] = (int) $row['ID'];
		}

		$by_key = array();

		foreach ( $groups as $id => $group ) {
			$group['fields']          = $this->nest( $id, $fields, $children, 0 );
			$by_key[ $group['key'] ] = $group;
		}

		return $by_key;
	}

	/**
	 * Fields under a parent, with sub_fields and flexible content layouts nested.
	 *
	 * @param int                                $parent   Parent post ID.
	 * @param array<int, array<string, mixed>>   $fields   Fields by ID.
	 * @param array<int, int[]>                  $children Child IDs by parent ID.
	 * @param int                                $depth    Depth, to stop loops.
	 * @return array<int, array<string, mixed>>
	 */
	private function nest( int $parent, array $fields, array $children, int $depth ): array {
		if ( $depth > 20 ) {
			return array();
		}

		$list = array();

		foreach ( $children[ $parent ] ?? array() as $id ) {
			$field = $fields[ $id ];
			$subs  = $this->nest( $id, $fields, $children, $depth + 1 );

			if ( 'flexible_content' === ( $field['type'] ?? '' ) ) {
				$layouts = is_array( $field['layouts'] ?? null ) ? $field['layouts'] : array();

				foreach ( $layouts as $layout_key => $layout ) {
					$key                                  = (string) ( $layout['key'] ?? $layout_key );
					$layouts[ $layout_key ]['sub_fields'] = array_values(
						array_filter( $subs, static fn( array $sub ): bool => (string) ( $sub['parent_layout'] ?? '' ) === $key )
					);
				}

				$field['layouts'] = $layouts;
			} else {
				$field['sub_fields'] = $subs;
			}

			$list[] = $field;
		}

		return $list;
	}

	/**
	 * Groups registered in PHP while ACF or SCF is active.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function local_groups(): array {
		if ( ! function_exists( 'acf_get_local_field_groups' ) || ! function_exists( 'acf_get_local_fields' ) ) {
			return array();
		}

		$groups = array();

		foreach ( (array) acf_get_local_field_groups() as $group ) {
			if ( ! is_array( $group ) || empty( $group['key'] ) ) {
				continue;
			}

			$group['fields']                 = $this->local_fields( (string) $group['key'] );
			$group['source']                 = 'php';
			$groups[ (string) $group['key'] ] = $group;
		}

		return $groups;
	}

	/**
	 * Local fields under a parent key.
	 *
	 * @param string $parent Parent key.
	 * @return array<int, array<string, mixed>>
	 */
	private function local_fields( string $parent ): array {
		$fields = array();

		foreach ( (array) acf_get_local_fields( $parent ) as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( empty( $field['sub_fields'] ) && 'flexible_content' !== ( $field['type'] ?? '' ) && in_array( $field['type'] ?? '', array( 'group', 'repeater' ), true ) ) {
				$field['sub_fields'] = $this->local_fields( (string) $field['key'] );
			}

			$fields[] = $field;
		}

		return $fields;
	}

	/**
	 * Post type, taxonomy or options page posts.
	 *
	 * @param string $post_type ACF storage post type.
	 * @return array<string, array<string, mixed>>
	 */
	private function stored_items( string $post_type ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- See stored_groups().
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT ID, post_title, post_name, post_content, post_status FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ('publish','acf-disabled') ORDER BY menu_order ASC, ID ASC", $post_type ),
			ARRAY_A
		);

		$items = array();

		foreach ( (array) $rows as $row ) {
			$items[ (string) $row['post_name'] ] = array_merge(
				self::unserialize( (string) $row['post_content'] ),
				array(
					'key'    => (string) $row['post_name'],
					'title'  => (string) $row['post_title'],
					'active' => 'publish' === $row['post_status'],
					'source' => 'db',
				)
			);
		}

		return $items;
	}

	/**
	 * Local JSON definitions whose key starts with a prefix.
	 *
	 * @param string $prefix Key prefix: group_, post_type_, taxonomy_ or ui_options_page_.
	 * @return array<string, array<string, mixed>>
	 */
	private function json( string $prefix ): array {
		$items = array();

		foreach ( $this->json_paths() as $path ) {
			foreach ( (array) glob( trailingslashit( $path ) . '*.json' ) as $file ) {
				if ( ! is_string( $file ) || ! is_readable( $file ) || filesize( $file ) > 2 * MB_IN_BYTES ) {
					continue;
				}

				$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file in the theme.

				if ( ! is_array( $data ) || ! isset( $data['key'] ) || 0 !== strpos( (string) $data['key'], $prefix ) ) {
					continue;
				}

				if ( 'group_' === $prefix && 0 === strpos( (string) $data['key'], 'group_' ) && ! isset( $data['fields'] ) ) {
					continue;
				}

				$data['source']                  = 'json';
				$items[ (string) $data['key'] ] = $data;
			}
		}

		return $items;
	}

	/**
	 * Folders ACF loads JSON from.
	 *
	 * @return string[]
	 */
	private function json_paths(): array {
		$paths = array();

		if ( function_exists( 'acf_get_setting' ) ) {
			$paths = (array) acf_get_setting( 'load_json' );
		}

		$paths[] = get_stylesheet_directory() . '/acf-json';
		$paths[] = get_template_directory() . '/acf-json';

		return array_values( array_unique( array_filter( array_map( 'strval', $paths ), 'is_dir' ) ) );
	}

	/**
	 * Read serialized ACF settings without creating objects.
	 *
	 * @param string $data Serialized array.
	 * @return array<string, mixed>
	 */
	public static function unserialize( string $data ): array {
		$data = trim( $data );

		if ( '' === $data || ! is_serialized( $data ) ) {
			return array();
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- ACF's own storage format; objects are refused.
		$value = @unserialize( $data, array( 'allowed_classes' => false ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A broken row is skipped, not reported.

		return is_array( $value ) ? $value : array();
	}
}
