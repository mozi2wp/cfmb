<?php
/**
 * Post type definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, checks and register_post_type() arguments for post types made in CFMB.
 */
class Post_Type_Schema extends Content_Schema {

	/**
	 * Names WordPress keeps for itself.
	 */
	private const RESERVED = array(
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'action',
		'author',
		'order',
		'theme',
		'fields',
		'type',
	);

	/**
	 * Kind key.
	 *
	 * @return string
	 */
	public function kind(): string {
		return 'post_type';
	}

	/**
	 * Key prefix.
	 *
	 * @return string
	 */
	public function key_prefix(): string {
		return Key_Generator::POST_TYPE_PREFIX;
	}

	/**
	 * Storage post type.
	 *
	 * @return string
	 */
	public function storage_type(): string {
		return 'cfmb_post_type';
	}

	/**
	 * WordPress limits post type names to 20 characters.
	 *
	 * @return int
	 */
	public function name_max_length(): int {
		return 20;
	}

	/**
	 * Builder strings.
	 *
	 * @return array<string, string>
	 */
	public function names(): array {
		return array(
			'singular'      => __( 'Post Type', 'custom-fields-meta-box' ),
			'plural'        => __( 'Post Types', 'custom-fields-meta-box' ),
			'add'           => __( 'Add post type', 'custom-fields-meta-box' ),
			'title_label'   => __( 'Plural name', 'custom-fields-meta-box' ),
			'title_example' => __( 'For example: Books', 'custom-fields-meta-box' ),
			'name_label'    => __( 'Post type key', 'custom-fields-meta-box' ),
			'name_help'     => __( 'Lowercase letters, numbers, dashes and underscores; up to 20 characters. Stored with every post of this type.', 'custom-fields-meta-box' ),
			'title_missing' => __( 'Give the post type a plural name, such as Books.', 'custom-fields-meta-box' ),
			'name_missing'  => __( 'Give the post type a key, such as book.', 'custom-fields-meta-box' ),
			'empty_title'   => __( 'Create your first post type', 'custom-fields-meta-box' ),
			'empty_text'    => __( 'A post type is a kind of content with its own menu, like Books or Events. Add fields to it with a field group.', 'custom-fields-meta-box' ),
			'move'          => __( 'Move existing posts to the new key', 'custom-fields-meta-box' ),
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
			/* translators: %s: post type key */
			return sprintf( __( '"%s" is reserved by WordPress. Choose another key.', 'custom-fields-meta-box' ), $name );
		}

		// A post type registered by the theme or another plugin; ours are registered under their own name.
		if ( $name !== $previous && post_type_exists( $name ) && ! Registrar::owns( 'post_type', $name ) ) {
			/* translators: %s: post type key */
			return sprintf( __( 'A post type called "%s" already exists, added by WordPress, your theme or another plugin. Choose another key.', 'custom-fields-meta-box' ), $name );
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

		return array(
			$this->setting( 'singular', __( 'Singular name', 'custom-fields-meta-box' ), 'text', '', array( 'help' => __( 'For example: Book', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'description', __( 'Description', 'custom-fields-meta-box' ), 'textarea' ),
			$this->setting( 'public', __( 'Public', 'custom-fields-meta-box' ), 'toggle', true, array( 'help' => __( 'Visitors can see these posts on the site, and editors manage them in the admin.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'hierarchical', __( 'Parent and child posts', 'custom-fields-meta-box' ), 'toggle', false, array( 'help' => __( 'Like pages: each post can have a parent.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'supports', __( 'Editor features', 'custom-fields-meta-box' ), 'checkboxes', array( 'title', 'editor', 'thumbnail' ), array( 'options' => self::supports_options() ) ),
			$this->setting(
				'taxonomies',
				__( 'Taxonomies', 'custom-fields-meta-box' ),
				'checkboxes',
				array(),
				array(
					'options' => self::taxonomy_options(),
					'free'    => true,
				)
			),
			$this->setting( 'menu_icon', __( 'Menu icon', 'custom-fields-meta-box' ), 'dashicon', 'dashicons-admin-post', array( 'help' => __( 'A Dashicons name such as dashicons-book, an image URL, or an SVG data URL.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'has_archive', __( 'Archive page', 'custom-fields-meta-box' ), 'toggle', false, array( 'help' => __( 'A page that lists all posts of this type.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'show_in_rest', __( 'Block editor and REST API', 'custom-fields-meta-box' ), 'toggle', true, array( 'help' => __( 'Edit with the block editor and read the posts through the REST API. Off uses the classic editor.', 'custom-fields-meta-box' ) ) ),

			$this->setting( 'labels', __( 'Labels', 'custom-fields-meta-box' ), 'labels', array(), $this->advanced( 'labels', array( 'labels' => $this->label_templates() ) ) ),

			$this->setting( 'publicly_queryable', __( 'Has its own URLs', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'exclude_from_search', __( 'Hide from site search', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( __( 'Opposite of Public', 'custom-fields-meta-box' ) ) ) ) ),
			$this->setting( 'show_ui', __( 'Show in the admin', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_in_menu', __( 'Show in the admin menu', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'visibility' ) ),
			$this->setting( 'menu_parent', __( 'Place under another menu', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'visibility', array( 'help' => __( 'Optional. The parent menu page, such as tools.php or edit.php?post_type=page. Empty gives it its own menu.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'menu_position', __( 'Menu position', 'custom-fields-meta-box' ), 'number', '', $this->advanced( 'visibility', array( 'help' => __( 'Optional. 5 is below Posts, 20 below Pages, 60 below Appearance.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'show_in_nav_menus', __( 'Can be added to menus', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( $follows_public ) ) ) ),
			$this->setting( 'show_in_admin_bar', __( 'Show in the toolbar "New" menu', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'visibility', array( 'options' => self::tristate_options( __( 'Same as the admin menu', 'custom-fields-meta-box' ) ) ) ) ),

			$this->setting( 'rewrite', __( 'Pretty URLs', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'rewrite_slug', __( 'URL slug', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the post type key.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'rewrite_with_front', __( 'Start URLs with the permalink base', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls', array( 'help' => __( 'For a permalink structure like /blog/%postname%/, URLs start with /blog/.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'rewrite_feeds', __( 'Feed URLs', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'urls', array( 'options' => self::tristate_options( __( 'Same as Archive page', 'custom-fields-meta-box' ) ) ) ) ),
			$this->setting( 'rewrite_pages', __( 'Paged URLs', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls' ) ),
			$this->setting( 'archive_slug', __( 'Archive URL slug', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the URL slug.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'query_var', __( 'Query variable', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'urls', array( 'help' => __( 'Allows URLs like ?book=my-book.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'query_var_name', __( 'Query variable name', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'urls', array( 'help' => __( 'Optional. Defaults to the post type key.', 'custom-fields-meta-box' ) ) ) ),

			$this->setting( 'capability_type', __( 'Capability type', 'custom-fields-meta-box' ), 'text', 'post', $this->advanced( 'permissions', array( 'help' => __( 'Base for capability names. "post" uses the same permissions as posts; "book" creates edit_books, publish_books and so on.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'capability_plural', __( 'Plural capability type', 'custom-fields-meta-box' ), 'text', '', $this->advanced( 'permissions', array( 'help' => __( 'Optional, for irregular plurals.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'map_meta_cap', __( 'Map meta capabilities', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'permissions', array( 'help' => __( 'Leave on unless you manage every capability yourself.', 'custom-fields-meta-box' ) ) ) ),
			$this->setting( 'can_export', __( 'Include in Tools > Export', 'custom-fields-meta-box' ), 'toggle', true, $this->advanced( 'permissions' ) ),
			$this->setting( 'delete_with_user', __( 'Delete posts with their author', 'custom-fields-meta-box' ), 'tristate', '', $this->advanced( 'permissions', array( 'options' => self::tristate_options( __( 'If the post type supports authors', 'custom-fields-meta-box' ) ) ) ) ),

			$this->setting( 'rest_base', __( 'REST base', 'custom-fields-meta-box' ), 'slug', '', $this->advanced( 'rest', array( 'help' => __( 'Optional. Defaults to the post type key.', 'custom-fields-meta-box' ) ) ) ),
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
	 * Editor features WordPress knows.
	 *
	 * @return array<string, string>
	 */
	private static function supports_options(): array {
		return array(
			'title'           => __( 'Title', 'custom-fields-meta-box' ),
			'editor'          => __( 'Content editor', 'custom-fields-meta-box' ),
			'thumbnail'       => __( 'Featured image', 'custom-fields-meta-box' ),
			'excerpt'         => __( 'Excerpt', 'custom-fields-meta-box' ),
			'author'          => __( 'Author', 'custom-fields-meta-box' ),
			'comments'        => __( 'Comments', 'custom-fields-meta-box' ),
			'revisions'       => __( 'Revisions', 'custom-fields-meta-box' ),
			'page-attributes' => __( 'Page attributes (order and parent)', 'custom-fields-meta-box' ),
			'custom-fields'   => __( 'Core custom fields panel', 'custom-fields-meta-box' ),
			'trackbacks'      => __( 'Trackbacks', 'custom-fields-meta-box' ),
			'post-formats'    => __( 'Post formats', 'custom-fields-meta-box' ),
		);
	}

	/**
	 * Taxonomies posts can be tagged with.
	 *
	 * @return array<string, string>
	 */
	private static function taxonomy_options(): array {
		$options = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $name => $taxonomy ) {
			if ( 'link_category' !== $name && 0 !== strpos( $name, 'wp_' ) ) {
				$options[ $name ] = $taxonomy->labels->name;
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
			'name'                     => array( __( 'Name', 'custom-fields-meta-box' ), '%2$s' ),
			'singular_name'            => array( __( 'Singular name', 'custom-fields-meta-box' ), '%1$s' ),
			'menu_name'                => array( __( 'Menu name', 'custom-fields-meta-box' ), '%2$s' ),
			/* translators: %2$s: plural name */
			'all_items'                => array( __( 'All items', 'custom-fields-meta-box' ), __( 'All %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'add_new'                  => array( __( 'Add new', 'custom-fields-meta-box' ), __( 'Add New %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'add_new_item'             => array( __( 'Add new item', 'custom-fields-meta-box' ), __( 'Add New %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'edit_item'                => array( __( 'Edit item', 'custom-fields-meta-box' ), __( 'Edit %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'new_item'                 => array( __( 'New item', 'custom-fields-meta-box' ), __( 'New %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'view_item'                => array( __( 'View item', 'custom-fields-meta-box' ), __( 'View %1$s', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'view_items'               => array( __( 'View items', 'custom-fields-meta-box' ), __( 'View %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'search_items'             => array( __( 'Search items', 'custom-fields-meta-box' ), __( 'Search %2$s', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'not_found'                => array( __( 'Not found', 'custom-fields-meta-box' ), __( 'No %4$s found.', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'not_found_in_trash'       => array( __( 'Not found in Trash', 'custom-fields-meta-box' ), __( 'No %4$s found in Trash.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'parent_item_colon'        => array( __( 'Parent item', 'custom-fields-meta-box' ), __( 'Parent %1$s:', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'archives'                 => array( __( 'Archives', 'custom-fields-meta-box' ), __( '%1$s Archives', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'attributes'               => array( __( 'Attributes', 'custom-fields-meta-box' ), __( '%1$s Attributes', 'custom-fields-meta-box' ) ),
			/* translators: %3$s: singular name in lowercase */
			'insert_into_item'         => array( __( 'Insert into item', 'custom-fields-meta-box' ), __( 'Insert into %3$s', 'custom-fields-meta-box' ) ),
			/* translators: %3$s: singular name in lowercase */
			'uploaded_to_this_item'    => array( __( 'Uploaded to this item', 'custom-fields-meta-box' ), __( 'Uploaded to this %3$s', 'custom-fields-meta-box' ) ),
			/* translators: %4$s: plural name in lowercase */
			'filter_items_list'        => array( __( 'Filter list', 'custom-fields-meta-box' ), __( 'Filter %4$s list', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'items_list_navigation'    => array( __( 'List navigation', 'custom-fields-meta-box' ), __( '%2$s list navigation', 'custom-fields-meta-box' ) ),
			/* translators: %2$s: plural name */
			'items_list'               => array( __( 'List', 'custom-fields-meta-box' ), __( '%2$s list', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_published'           => array( __( 'Published', 'custom-fields-meta-box' ), __( '%1$s published.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_published_privately' => array( __( 'Published privately', 'custom-fields-meta-box' ), __( '%1$s published privately.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_reverted_to_draft'   => array( __( 'Reverted to draft', 'custom-fields-meta-box' ), __( '%1$s reverted to draft.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_scheduled'           => array( __( 'Scheduled', 'custom-fields-meta-box' ), __( '%1$s scheduled.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_updated'             => array( __( 'Updated', 'custom-fields-meta-box' ), __( '%1$s updated.', 'custom-fields-meta-box' ) ),
			/* translators: %1$s: singular name */
			'item_link'                => array( __( 'Navigation link block title', 'custom-fields-meta-box' ), __( '%1$s Link', 'custom-fields-meta-box' ) ),
			/* translators: %3$s: singular name in lowercase */
			'item_link_description'    => array( __( 'Navigation link block description', 'custom-fields-meta-box' ), __( 'A link to a %3$s.', 'custom-fields-meta-box' ) ),
		);
	}

	/**
	 * Arguments for register_post_type().
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
			'labels'              => $this->labels( $singular, $plural, (array) $s['labels'] ),
			'description'         => (string) $s['description'],
			'public'              => $public,
			'hierarchical'        => (bool) $s['hierarchical'],
			'exclude_from_search' => self::tristate( $s['exclude_from_search'], ! $public ),
			'publicly_queryable'  => self::tristate( $s['publicly_queryable'], $public ),
			'show_ui'             => $show_ui,
			'show_in_nav_menus'   => self::tristate( $s['show_in_nav_menus'], $public ),
			'show_in_rest'        => (bool) $s['show_in_rest'],
			'supports'            => array_values( (array) $s['supports'] ),
			'taxonomies'          => array_values( (array) $s['taxonomies'] ),
			'has_archive'         => (bool) $s['has_archive'],
			'can_export'          => (bool) $s['can_export'],
			'map_meta_cap'        => (bool) $s['map_meta_cap'],
			'menu_icon'           => (string) $s['menu_icon'],
		);

		// Without supports, WordPress would fall back to title and editor.
		if ( ! $args['supports'] ) {
			$args['supports'] = false;
		}

		$show_in_menu = (bool) $s['show_in_menu'];
		if ( $show_in_menu && '' !== (string) $s['menu_parent'] ) {
			$show_in_menu = (string) $s['menu_parent'];
		}
		$args['show_in_menu']      = $show_in_menu;
		$args['show_in_admin_bar'] = self::tristate( $s['show_in_admin_bar'], (bool) $show_in_menu );

		if ( '' !== $s['menu_position'] ) {
			$args['menu_position'] = (int) $s['menu_position'];
		}

		if ( $args['has_archive'] && '' !== (string) $s['archive_slug'] ) {
			$args['has_archive'] = (string) $s['archive_slug'];
		}

		$args['rewrite'] = (bool) $s['rewrite'] ? array(
			'slug'       => '' !== (string) $s['rewrite_slug'] ? (string) $s['rewrite_slug'] : (string) $item['name'],
			'with_front' => (bool) $s['rewrite_with_front'],
			'feeds'      => self::tristate( $s['rewrite_feeds'], (bool) $args['has_archive'] ),
			'pages'      => (bool) $s['rewrite_pages'],
		) : false;

		$args['query_var'] = (bool) $s['query_var'] && '' !== (string) $s['query_var_name'] ? (string) $s['query_var_name'] : (bool) $s['query_var'];

		$type                    = '' !== (string) $s['capability_type'] ? (string) $s['capability_type'] : 'post';
		$args['capability_type'] = '' !== (string) $s['capability_plural'] ? array( $type, (string) $s['capability_plural'] ) : $type;

		if ( '' !== $s['delete_with_user'] ) {
			$args['delete_with_user'] = '1' === (string) $s['delete_with_user'];
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
