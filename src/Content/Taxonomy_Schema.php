<?php
/**
 * Taxonomy definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, checks and register_taxonomy() arguments for taxonomies made in CFMB.
 */
class Taxonomy_Schema extends Content_Schema {

	/**
	 * Query variables WordPress reserves; a taxonomy with one of these names breaks URLs.
	 */
	private const RESERVED = array(
		'attachment', 'attachment_id', 'author', 'author_name', 'calendar', 'cat', 'category', 'category__and',
		'category__in', 'category__not_in', 'category_name', 'comments_per_page', 'comments_popup', 'custom',
		'customize_messenger_channel', 'customized', 'cpage', 'day', 'debug', 'embed', 'error', 'exact', 'feed',
		'fields', 'hour', 'link_category', 'm', 'minute', 'monthnum', 'more', 'name', 'nav_menu', 'nonce',
		'nopaging', 'offset', 'order', 'orderby', 'p', 'page', 'page_id', 'paged', 'pagename', 'pb', 'perm',
		'post', 'post__in', 'post__not_in', 'post_format', 'post_mime_type', 'post_status', 'post_tag',
		'post_type', 'posts', 'posts_per_archive_page', 'posts_per_page', 'preview', 'robots', 's', 'search',
		'second', 'sentence', 'showposts', 'static', 'status', 'subpost', 'subpost_id', 'tag', 'tag__and',
		'tag__in', 'tag__not_in', 'tag_id', 'tag_slug__and', 'tag_slug__in', 'taxonomy', 'tb', 'term', 'terms',
		'theme', 'title', 'type', 'types', 'w', 'withcomments', 'withoutcomments', 'year',
	);

	/**
	 * Kind key.
	 *
	 * @return string
	 */
	public function kind(): string {
		return 'taxonomy';
	}

	/**
	 * Key prefix.
	 *
	 * @return string
	 */
	public function key_prefix(): string {
		return Key_Generator::TAXONOMY_PREFIX;
	}

	/**
	 * Storage post type.
	 *
	 * @return string
	 */
	public function storage_type(): string {
		return 'cfmb_taxonomy';
	}

	/**
	 * WordPress limits taxonomy names to 32 characters.
	 *
	 * @return int
	 */
	public function name_max_length(): int {
		return 32;
	}

	/**
	 * Builder strings.
	 *
	 * @return array<string, string>
	 */
	public function names(): array {
		return array(
			'singular'      => __( 'Taxonomy', 'cfmb' ),
			'plural'        => __( 'Taxonomies', 'cfmb' ),
			'add'           => __( 'Add taxonomy', 'cfmb' ),
			'title_label'   => __( 'Plural name', 'cfmb' ),
			'title_example' => __( 'For example: Genres', 'cfmb' ),
			'name_label'    => __( 'Taxonomy key', 'cfmb' ),
			'name_help'     => __( 'Lowercase letters, numbers, dashes and underscores; up to 32 characters. Stored with every term.', 'cfmb' ),
			'title_missing' => __( 'Give the taxonomy a plural name, such as Genres.', 'cfmb' ),
			'name_missing'  => __( 'Give the taxonomy a key, such as genre.', 'cfmb' ),
			'empty_title'   => __( 'Create your first taxonomy', 'cfmb' ),
			'empty_text'    => __( 'A taxonomy groups content, like categories and tags do. For example, Genres for Books.', 'cfmb' ),
			'move'          => __( 'Move existing terms to the new key', 'cfmb' ),
		);
	}

	/**
	 * Explain what is wrong with a name.
	 *
	 * @param string $name     Sanitized name.
	 * @param string $previous Previous name.
	 * @return string|null
	 */
	protected function name_error( string $name, string $previous ): ?string {
		if ( in_array( $name, self::RESERVED, true ) || 0 === strpos( $name, 'wp_' ) || 0 === strpos( $name, 'cfmb_' ) ) {
			/* translators: %s: taxonomy key */
			return sprintf( __( '"%s" is reserved by WordPress. Choose another key, for example by adding a prefix.', 'cfmb' ), $name );
		}

		if ( $name !== $previous && taxonomy_exists( $name ) && ! Registrar::owns( 'taxonomy', $name ) ) {
			/* translators: %s: taxonomy key */
			return sprintf( __( 'A taxonomy called "%s" already exists, added by WordPress, your theme or another plugin. Choose another key.', 'cfmb' ), $name );
		}

		return null;
	}

	/**
	 * Setting descriptors.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		$follows_public = __( 'Same as Public', 'cfmb' );
		$follows_ui     = __( 'Same as Show in the admin', 'cfmb' );

		return array(
			$this->setting( 'singular', __( 'Singular name', 'cfmb' ), 'text', '', array( 'help' => __( 'For example: Genre', 'cfmb' ) ) ),
			$this->setting( 'description', __( 'Description', 'cfmb' ), 'textarea' ),
			$this->setting(
				'post_types',
				__( 'Post types', 'cfmb' ),
				'checkboxes',
				array( 'post' ),
				array(
					'options' => self::post_type_options(),
					'free'    => true,
				)
			),
			$this->setting( 'public', __( 'Public', 'cfmb' ), 'toggle', true, array( 'help' => __( 'Visitors can see term archives on the site, and editors manage terms in the admin.', 'cfmb' ) ) ),
			$this->setting( 'hierarchical', __( 'Parent and child terms', 'cfmb' ), 'toggle', false, array( 'help' => __( 'Like categories, picked from a checklist. Off works like tags.', 'cfmb' ) ) ),
			$this->setting( 'show_in_rest', __( 'Block editor and REST API', 'cfmb' ), 'toggle', true, array( 'help' => __( 'Needed for the term panel in the block editor.', 'cfmb' ) ) ),
			$this->setting( 'show_admin_column', __( 'Column on post lists', 'cfmb' ), 'toggle', false ),

			$this->setting( 'labels', __( 'Labels', 'cfmb' ), 'labels', array(), $this->advanced( 'labels', array( 'labels' => $this->label_templates() ) ) ),

			$this->setting( 'publicly_queryable', __( 'Has its own URLs', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_ui', __( 'Show in the admin', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_in_menu', __( 'Show in the admin menu', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting( 'show_in_nav_menus', __( 'Can be added to menus', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_tagcloud', __( 'Tag Cloud widget', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting( 'show_in_quick_edit', __( 'Quick Edit', 'cfmb' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting(
				'meta_box',
				__( 'Box on the classic edit screen', 'cfmb' ),
				'select',
				'',
				$this->advanced(
					'visibility',
					array(
						'options' => array(
							''     => __( 'Default', 'cfmb' ),
							'none' => __( 'Hidden', 'cfmb' ),
						),
					)
				)
			),

			$this->setting( 'rewrite', __( 'Pretty URLs', 'cfmb' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'rewrite_slug', __( 'URL slug', 'cfmb' ), 'slug', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'cfmb' ) ) ) ),
			$this->setting( 'rewrite_with_front', __( 'Start URLs with the permalink base', 'cfmb' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'rewrite_hierarchical', __( 'Include parent terms in URLs', 'cfmb' ), 'toggle', false, $this->advanced( 'urls' ) ),
			$this->setting( 'query_var', __( 'Query variable', 'cfmb' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'query_var_name', __( 'Query variable name', 'cfmb' ), 'text', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'cfmb' ) ) ) ),

			$this->setting( 'default_term_name', __( 'Default term name', 'cfmb' ), 'text', '', $this->advanced( 'terms', array( 'help' => __( 'Optional. Given to posts saved without a term, like "Uncategorized".', 'cfmb' ) ) ) ),
			$this->setting( 'default_term_slug', __( 'Default term slug', 'cfmb' ), 'slug', '', $this->advanced( 'terms' ) ),
			$this->setting( 'sort', __( 'Keep the order terms were added in', 'cfmb' ), 'toggle', false, $this->advanced( 'terms' ) ),

			$this->setting( 'manage_terms', __( 'Capability to manage terms', 'cfmb' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'cfmb' ) ) ) ),
			$this->setting( 'edit_terms', __( 'Capability to edit terms', 'cfmb' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'cfmb' ) ) ) ),
			$this->setting( 'delete_terms', __( 'Capability to delete terms', 'cfmb' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'cfmb' ) ) ) ),
			$this->setting( 'assign_terms', __( 'Capability to assign terms', 'cfmb' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to edit_posts.', 'cfmb' ) ) ) ),

			$this->setting( 'rest_base', __( 'REST base', 'cfmb' ), 'slug', '', $this->advanced( 'rest', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'cfmb' ) ) ) ),
			$this->setting( 'rest_namespace', __( 'REST namespace', 'cfmb' ), 'slug', '', $this->advanced( 'rest', array( 'help' => __( 'Optional. Defaults to wp/v2.', 'cfmb' ) ) ) ),
		);
	}

	/**
	 * Mark a descriptor as advanced and give it a section.
	 *
	 * @param string               $section Section key.
	 * @param array<string, mixed> $extra   More descriptor keys.
	 * @return array<string, mixed>
	 */
	private function advanced( string $section, array $extra = array() ): array {
		return array_merge(
			array(
				'advanced' => true,
				'section'  => $section,
			),
			$extra
		);
	}

	/**
	 * Post types a taxonomy can be attached to.
	 *
	 * @return array<string, string>
	 */
	private static function post_type_options(): array {
		$options = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $type ) {
			if ( 0 !== strpos( $name, 'wp_' ) && 0 !== strpos( $name, 'cfmb_' ) ) {
				$options[ $name ] = $type->labels->name;
			}
		}

		return $options;
	}

	/**
	 * Label templates.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function label_templates(): array {
		return array(
			// Names are used as typed, so they are not translated.
			'name'                       => array( __( 'Name', 'cfmb' ), '%2$s' ),
			'singular_name'              => array( __( 'Singular name', 'cfmb' ), '%1$s' ),
			'menu_name'                  => array( __( 'Menu name', 'cfmb' ), '%2$s' ),
			/* translators: %2$s: plural name, e.g. Genres */
			'all_items'                  => array( __( 'All items', 'cfmb' ), __( 'All %2$s', 'cfmb' ) ),
			/* translators: %1$s: singular name, e.g. Genre */
			'edit_item'                  => array( __( 'Edit item', 'cfmb' ), __( 'Edit %1$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'view_item'                  => array( __( 'View item', 'cfmb' ), __( 'View %1$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'update_item'                => array( __( 'Update item', 'cfmb' ), __( 'Update %1$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'add_new_item'               => array( __( 'Add new item', 'cfmb' ), __( 'Add New %1$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'new_item_name'              => array( __( 'New item name', 'cfmb' ), __( 'New %1$s Name', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'parent_item'                => array( __( 'Parent item', 'cfmb' ), __( 'Parent %1$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'parent_item_colon'          => array( __( 'Parent item with colon', 'cfmb' ), __( 'Parent %1$s:', 'cfmb' ) ),
			/* translators: %2$s: plural name */
			'search_items'               => array( __( 'Search items', 'cfmb' ), __( 'Search %2$s', 'cfmb' ) ),
			/* translators: %2$s: plural name */
			'popular_items'              => array( __( 'Popular items', 'cfmb' ), __( 'Popular %2$s', 'cfmb' ) ),
			/* translators: %4$s: plural name in lowercase */
			'separate_items_with_commas' => array( __( 'Separate with commas', 'cfmb' ), __( 'Separate %4$s with commas', 'cfmb' ) ),
			/* translators: %4$s: plural name in lowercase */
			'add_or_remove_items'        => array( __( 'Add or remove', 'cfmb' ), __( 'Add or remove %4$s', 'cfmb' ) ),
			/* translators: %4$s: plural name in lowercase */
			'choose_from_most_used'      => array( __( 'Choose from the most used', 'cfmb' ), __( 'Choose from the most used %4$s', 'cfmb' ) ),
			/* translators: %4$s: plural name in lowercase */
			'not_found'                  => array( __( 'Not found', 'cfmb' ), __( 'No %4$s found.', 'cfmb' ) ),
			/* translators: %4$s: plural name in lowercase */
			'no_terms'                   => array( __( 'No terms', 'cfmb' ), __( 'No %4$s', 'cfmb' ) ),
			/* translators: %3$s: singular name in lowercase */
			'filter_by_item'             => array( __( 'Filter by', 'cfmb' ), __( 'Filter by %3$s', 'cfmb' ) ),
			/* translators: %2$s: plural name */
			'items_list_navigation'      => array( __( 'List navigation', 'cfmb' ), __( '%2$s list navigation', 'cfmb' ) ),
			/* translators: %2$s: plural name */
			'items_list'                 => array( __( 'List', 'cfmb' ), __( '%2$s list', 'cfmb' ) ),
			/* translators: %2$s: plural name */
			'back_to_items'              => array( __( 'Back link', 'cfmb' ), __( '&larr; Go to %2$s', 'cfmb' ) ),
			/* translators: %1$s: singular name */
			'item_link'                  => array( __( 'Navigation link block title', 'cfmb' ), __( '%1$s Link', 'cfmb' ) ),
			/* translators: %3$s: singular name in lowercase */
			'item_link_description'      => array( __( 'Navigation link block description', 'cfmb' ), __( 'A link to a %3$s.', 'cfmb' ) ),
		);
	}

	/**
	 * Post types the taxonomy is attached to.
	 *
	 * @param array<string, mixed> $item Normalized definition.
	 * @return string[]
	 */
	public function object_types( array $item ): array {
		return array_values( array_map( 'strval', (array) $item['settings']['post_types'] ) );
	}

	/**
	 * Arguments for register_taxonomy().
	 *
	 * @param array<string, mixed> $item Normalized definition.
	 * @return array<string, mixed>
	 */
	public function build_args( array $item ): array {
		$s        = $item['settings'];
		$plural   = (string) $item['title'];
		$singular = '' !== (string) $s['singular'] ? (string) $s['singular'] : $plural;
		$public   = (bool) $s['public'];
		$show_ui  = self::tristate( $s['show_ui'], $public );

		$args = array(
			'labels'             => $this->labels( $singular, $plural, (array) $s['labels'] ),
			'description'        => (string) $s['description'],
			'public'             => $public,
			'hierarchical'       => (bool) $s['hierarchical'],
			'publicly_queryable' => self::tristate( $s['publicly_queryable'], $public ),
			'show_ui'            => $show_ui,
			'show_in_menu'       => self::tristate( $s['show_in_menu'], $show_ui ),
			'show_in_nav_menus'  => self::tristate( $s['show_in_nav_menus'], $public ),
			'show_tagcloud'      => self::tristate( $s['show_tagcloud'], $show_ui ),
			'show_in_quick_edit' => self::tristate( $s['show_in_quick_edit'], $show_ui ),
			'show_admin_column'  => (bool) $s['show_admin_column'],
			'show_in_rest'       => (bool) $s['show_in_rest'],
			'sort'               => (bool) $s['sort'],
		);

		if ( 'none' === $s['meta_box'] ) {
			$args['meta_box_cb'] = false;
		}

		$args['rewrite'] = (bool) $s['rewrite'] ? array(
			'slug'         => '' !== (string) $s['rewrite_slug'] ? (string) $s['rewrite_slug'] : (string) $item['name'],
			'with_front'   => (bool) $s['rewrite_with_front'],
			'hierarchical' => (bool) $s['rewrite_hierarchical'],
		) : false;

		$args['query_var'] = (bool) $s['query_var'] && '' !== (string) $s['query_var_name'] ? (string) $s['query_var_name'] : (bool) $s['query_var'];

		$capabilities = array();
		foreach ( array( 'manage_terms', 'edit_terms', 'delete_terms', 'assign_terms' ) as $cap ) {
			if ( '' !== (string) $s[ $cap ] ) {
				$capabilities[ $cap ] = (string) $s[ $cap ];
			}
		}
		if ( $capabilities ) {
			$args['capabilities'] = $capabilities;
		}

		if ( '' !== (string) $s['default_term_name'] ) {
			$args['default_term'] = array(
				'name' => (string) $s['default_term_name'],
				'slug' => '' !== (string) $s['default_term_slug'] ? (string) $s['default_term_slug'] : sanitize_title( (string) $s['default_term_name'] ),
			);
		}

		if ( '' !== (string) $s['rest_base'] ) {
			$args['rest_base'] = (string) $s['rest_base'];
		}

		if ( '' !== (string) $s['rest_namespace'] ) {
			$args['rest_namespace'] = (string) $s['rest_namespace'];
		}

		return $args;
	}
}
