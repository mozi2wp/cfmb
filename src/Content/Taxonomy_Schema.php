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
		'attachment',
		'attachment_id',
		'author',
		'author_name',
		'calendar',
		'cat',
		'category',
		'category__and',
		'category__in',
		'category__not_in',
		'category_name',
		'comments_per_page',
		'comments_popup',
		'custom',
		'customize_messenger_channel',
		'customized',
		'cpage',
		'day',
		'debug',
		'embed',
		'error',
		'exact',
		'feed',
		'fields',
		'hour',
		'link_category',
		'm',
		'minute',
		'monthnum',
		'more',
		'name',
		'nav_menu',
		'nonce',
		'nopaging',
		'offset',
		'order',
		'orderby',
		'p',
		'page',
		'page_id',
		'paged',
		'pagename',
		'pb',
		'perm',
		'post',
		'post__in',
		'post__not_in',
		'post_format',
		'post_mime_type',
		'post_status',
		'post_tag',
		'post_type',
		'posts',
		'posts_per_archive_page',
		'posts_per_page',
		'preview',
		'robots',
		's',
		'search',
		'second',
		'sentence',
		'showposts',
		'static',
		'status',
		'subpost',
		'subpost_id',
		'tag',
		'tag__and',
		'tag__in',
		'tag__not_in',
		'tag_id',
		'tag_slug__and',
		'tag_slug__in',
		'taxonomy',
		'tb',
		'term',
		'terms',
		'theme',
		'title',
		'type',
		'types',
		'w',
		'withcomments',
		'withoutcomments',
		'year',
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
			'singular'      => __( 'Taxonomy', 'custom-fields-meta-box' ),
			'plural'        => __( 'Taxonomies', 'custom-fields-meta-box' ),
			'add'           => __( 'Add taxonomy', 'custom-fields-meta-box' ),
			'title_label'   => __( 'Plural name', 'custom-fields-meta-box' ),
			'title_example' => __( 'For example: Genres', 'custom-fields-meta-box' ),
			'name_label'    => __( 'Taxonomy key', 'custom-fields-meta-box' ),
			'name_help'     => __( 'Lowercase letters, numbers, dashes and underscores; up to 32 characters. Stored with every term.', 'custom-fields-meta-box' ),
			'title_missing' => __( 'Give the taxonomy a plural name, such as Genres.', 'custom-fields-meta-box' ),
			'name_missing'  => __( 'Give the taxonomy a key, such as genre.', 'custom-fields-meta-box' ),
			'empty_title'   => __( 'Create your first taxonomy', 'custom-fields-meta-box' ),
			'empty_text'    => __( 'A taxonomy groups content, like categories and tags do. For example, Genres for Books.', 'custom-fields-meta-box' ),
			'move'          => __( 'Move existing terms to the new key', 'custom-fields-meta-box' ),
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
			return sprintf( __( '"%s" is reserved by WordPress. Choose another key, for example by adding a prefix.', 'custom-fields-meta-box' ), $name );
		}

		if ( $name !== $previous && taxonomy_exists( $name ) && ! Registrar::owns( 'taxonomy', $name ) ) {
			/* translators: %s: taxonomy key */
			return sprintf( __( 'A taxonomy called "%s" already exists, added by WordPress, your theme or another plugin. Choose another key.', 'custom-fields-meta-box' ), $name );
		}

		return null;
	}

	/**
	 * Setting descriptors.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		$follows_public = __( 'Same as Public', 'custom-fields-meta-box' );
		$follows_ui     = __( 'Same as Show in the admin', 'custom-fields-meta-box' );

		return array(
			$this->setting( 'singular', __( 'Singular name', 'custom-fields-meta-box' ), 'text', '', array( 'help' => __( 'For example: Genre', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'description', __( 'Description', 'custom-fields-meta-box' ), 'textarea' ),
			$this->setting(
				'post_types',
				__( 'Post types', 'custom-fields-meta-box' ),
				'checkboxes',
				array( 'post' ),
				array(
					'options' => self::post_type_options(),
					'free'    => true,
				)
			),
			$this->setting( 'public', __( 'Public', 'custom-fields-meta-box' ), 'toggle', true, array( 'help' => __( 'Visitors can see term archives on the site, and editors manage terms in the admin.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'hierarchical', __( 'Parent and child terms', 'custom-fields-meta-box' ), 'toggle', false, array( 'help' => __( 'Like categories, picked from a checklist. Off works like tags.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'show_in_rest', __( 'Block editor and REST API', 'custom-fields-meta-box' ), 'toggle', true, array( 'help' => __( 'Needed for the term panel in the block editor.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'show_admin_column', __( 'Column on post lists', 'custom-fields-meta-box' ), 'toggle', false ),

			$this->setting( 'labels', __( 'Labels', 'custom-fields-meta-box' ), 'labels', array(), $this->advanced( 'labels', array( 'labels' => $this->label_templates() ) ) ),

			$this->setting( 'publicly_queryable', __( 'Has its own URLs', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_ui', __( 'Show in the admin', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_in_menu', __( 'Show in the admin menu', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting( 'show_in_nav_menus', __( 'Can be added to menus', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_tagcloud', __( 'Tag Cloud widget', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting( 'show_in_quick_edit', __( 'Quick Edit', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_ui ) ) ) ),
			$this->setting(
				'meta_box',
				__( 'Box on the classic edit screen', 'custom-fields-meta-box' ),
				'select',
				'',
				$this->advanced(
					'visibility',
					array(
						'options' => array(
							''     => __( 'Default', 'custom-fields-meta-box' ),
							'none' => __( 'Hidden', 'custom-fields-meta-box' ),
						),
					)
				)
			),

			$this->setting( 'rewrite', __( 'Pretty URLs', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'rewrite_slug', __( 'URL slug', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'rewrite_with_front', __( 'Start URLs with the permalink base', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'rewrite_hierarchical', __( 'Include parent terms in URLs', 'custom-fields-meta-box' ), 'toggle', false, $this->advanced( 'urls' ) ),
			$this->setting( 'query_var', __( 'Query variable', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'query_var_name', __( 'Query variable name', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'custom-fields-meta-box' ) ) ) ),

			$this->setting( 'default_term_name', __( 'Default term name', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'terms', array( 'help' => __( 'Optional. Given to posts saved without a term, like "Uncategorized".', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'default_term_slug', __( 'Default term slug', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'terms' ) ),
			$this->setting( 'sort', __( 'Keep the order terms were added in', 'custom-fields-meta-box' ), 'toggle', false, $this->advanced( 'terms' ) ),

			$this->setting( 'manage_terms', __( 'Capability to manage terms', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'edit_terms', __( 'Capability to edit terms', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'delete_terms', __( 'Capability to delete terms', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to manage_categories.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'assign_terms', __( 'Capability to assign terms', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional. Defaults to edit_posts.', 'custom-fields-meta-box' ) ) ) ),

			$this->setting( 'rest_base', __( 'REST base', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'rest', array( 'help' => __( 'Optional. Defaults to the taxonomy key.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'rest_namespace', __( 'REST namespace', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'rest', array( 'help' => __( 'Optional. Defaults to wp/v2.', 'custom-fields-meta-box' ) ) ) ),
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
			'name'                       => array( __( 'Name', 'custom-fields-meta-box' ), '%2$s' ),
			'singular_name'              => array( __( 'Singular name', 'custom-fields-meta-box' ), '%1$s' ),
			'menu_name'                  => array( __( 'Menu name', 'custom-fields-meta-box' ), '%2$s' ),
			/* translators: %2$s: plural name, e.g. Genres */
			'all_items'                  => array( __( 'All items', 'custom-fields-meta-box' ), __( 'All %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name, e.g. Genre */
			'edit_item'                  => array( __( 'Edit item', 'custom-fields-meta-box' ), __( 'Edit %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'view_item'                  => array( __( 'View item', 'custom-fields-meta-box' ), __( 'View %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'update_item'                => array( __( 'Update item', 'custom-fields-meta-box' ), __( 'Update %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'add_new_item'               => array( __( 'Add new item', 'custom-fields-meta-box' ), __( 'Add New %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'new_item_name'              => array( __( 'New item name', 'custom-fields-meta-box' ), __( 'New %1$s Name', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'parent_item'                => array( __( 'Parent item', 'custom-fields-meta-box' ), __( 'Parent %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'parent_item_colon'          => array( __( 'Parent item with colon', 'custom-fields-meta-box' ), __( 'Parent %1$s:', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'search_items'               => array( __( 'Search items', 'custom-fields-meta-box' ), __( 'Search %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'popular_items'              => array( __( 'Popular items', 'custom-fields-meta-box' ), __( 'Popular %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'separate_items_with_commas' => array( __( 'Separate with commas', 'custom-fields-meta-box' ), __( 'Separate %4$s with commas', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'add_or_remove_items'        => array( __( 'Add or remove', 'custom-fields-meta-box' ), __( 'Add or remove %4$s', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'choose_from_most_used'      => array( __( 'Choose from the most used', 'custom-fields-meta-box' ), __( 'Choose from the most used %4$s', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'not_found'                  => array( __( 'Not found', 'custom-fields-meta-box' ), __( 'No %4$s found.', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'no_terms'                   => array( __( 'No terms', 'custom-fields-meta-box' ), __( 'No %4$s', 'custom-fields-meta-box' ) ),
			/* translators: %3$s: singular name in lowercase */
			'filter_by_item'             => array( __( 'Filter by', 'custom-fields-meta-box' ), __( 'Filter by %3$s', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'items_list_navigation'      => array( __( 'List navigation', 'custom-fields-meta-box' ), __( '%2$s list navigation', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'items_list'                 => array( __( 'List', 'custom-fields-meta-box' ), __( '%2$s list', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'back_to_items'              => array( __( 'Back link', 'custom-fields-meta-box' ), __( '&larr; Go to %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_link'                  => array( __( 'Navigation link block title', 'custom-fields-meta-box' ), __( '%1$s Link', 'custom-fields-meta-box' ) ),
			/* translators: %3$s: singular name in lowercase */
			'item_link_description'      => array( __( 'Navigation link block description', 'custom-fields-meta-box' ), __( 'A link to a %3$s.', 'custom-fields-meta-box' ) ),
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
