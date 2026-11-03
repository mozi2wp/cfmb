<?php
/**
 * Turns ACF and SCF definitions into CFMB definitions.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Produces raw CFMB definitions for the usual sanitizers, plus the value
 * rules Value_Converter needs, plus plain-language notes about anything
 * that doesn't carry over.
 *
 * Keys keep ACF's random part (field_5f2a… becomes fld_5f2a…), so running
 * the import twice finds the groups it made before.
 *
 * Value rules match flattened meta keys. "value" rules list every stored
 * value, so options page values can move to CFMB's option names. Most
 * values already match CFMB's storage (Repeater rows as items_0_title,
 * Group sub-fields as hero_title, IDs, text). Two kinds change:
 *
 * - list: ACF keeps checkbox, multi-select, relationship, gallery and other
 *   lists, and Flexible Content layouts, as one serialized array; CFMB
 *   keeps one meta row per item
 * - date: ACF's date picker saves Ymd; CFMB saves Y-m-d
 */
class Acf_Mapper {

	/**
	 * ACF type => CFMB type. Types not listed don't carry over.
	 */
	private const TYPES = array(
		'text'             => 'text',
		'textarea'         => 'textarea',
		'number'           => 'number',
		'range'            => 'range',
		'email'            => 'email',
		'url'              => 'url',
		'password'         => 'password',
		'wysiwyg'          => 'wysiwyg',
		'oembed'           => 'oembed',
		'image'            => 'image',
		'file'             => 'file',
		'gallery'          => 'gallery',
		'select'           => 'select',
		'checkbox'         => 'checkbox',
		'radio'            => 'radio',
		'button_group'     => 'button_group',
		'true_false'       => 'toggle',
		'link'             => 'link',
		'post_object'      => 'post',
		'page_link'        => 'page_link',
		'relationship'     => 'relationship',
		'taxonomy'         => 'taxonomy',
		'user'             => 'user',
		'google_map'       => 'map',
		'date_picker'      => 'date',
		'date_time_picker' => 'datetime',
		'time_picker'      => 'time',
		'color_picker'     => 'color',
		'icon_picker'      => 'icon',
		'message'          => 'message',
		'tab'              => 'tab',
		'accordion'        => 'accordion',
		'group'            => 'group',
		'repeater'         => 'repeater',
		'flexible_content' => 'stacks',
	);

	/**
	 * ACF location params => CFMB rule params.
	 */
	private const RULES = array(
		'post_type'     => 'post_type',
		'post_template' => 'page_template',
		'page_template' => 'page_template',
		'post_status'   => 'post_status',
		'post_format'   => 'post_format',
		'post_category' => 'post_term',
		'post_taxonomy' => 'post_term',
		'taxonomy'      => 'taxonomy',
		'user_role'     => 'user_role',
		'user_form'     => 'user_form',
		'options_page'  => 'options_page',
		'attachment'    => 'attachment',
		'comment'       => 'comment',
		'nav_menu'      => 'menu',
		'nav_menu_item' => 'menu_item',
		'widget'        => 'widget',
	);

	/**
	 * Every ACF group by key, for clone fields.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $all_groups = array();

	/**
	 * ACF options page slug => CFMB options page ID.
	 *
	 * @var array<string, string>
	 */
	private array $pages = array();

	/**
	 * Notes for the group being mapped.
	 *
	 * @var string[]
	 */
	private array $notes = array();

	/**
	 * Value rules for the group being mapped.
	 *
	 * @var array<int, array{pattern: string, kind: string}>
	 */
	private array $rules = array();

	/**
	 * Keys used in the group being mapped.
	 *
	 * @var array<string, bool>
	 */
	private array $keys = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $all_groups Every ACF group by key, for clone fields.
	 * @param array<string, string>               $pages      ACF options page slug => CFMB page ID.
	 */
	public function __construct( array $all_groups = array(), array $pages = array() ) {
		$this->all_groups = $all_groups;
		$this->pages      = $pages;
	}

	/**
	 * Map one Field Group.
	 *
	 * @param array<string, mixed> $acf ACF group with nested fields.
	 * @return array{group: array<string, mixed>, rules: array<int, array{pattern: string, kind: string}>, notes: string[], pages: string[]}
	 */
	public function group( array $acf ): array {
		$this->notes = array();
		$this->rules = array();
		$this->keys  = array();

		$fields   = $this->fields( is_array( $acf['fields'] ?? null ) ? $acf['fields'] : array(), '', 0 );
		$location = $this->location( is_array( $acf['location'] ?? null ) ? $acf['location'] : array() );
		$position = (string) ( $acf['position'] ?? 'normal' );

		$group = array(
			'key'      => self::key( (string) ( $acf['key'] ?? '' ), 'group_', Key_Generator::GROUP_PREFIX ),
			'title'    => '' !== (string) ( $acf['title'] ?? '' ) ? (string) $acf['title'] : __( 'Imported field group', 'cfmb' ),
			'active'   => ! array_key_exists( 'active', $acf ) || ! empty( $acf['active'] ),
			'order'    => (int) ( $acf['menu_order'] ?? 0 ),
			'fields'   => $fields,
			'location' => $location,
			'settings' => array(
				'position'        => 'acf_after_title' === $position ? 'high' : ( 'side' === $position ? 'side' : 'normal' ),
				'label_placement' => 'left' === ( $acf['label_placement'] ?? 'top' ) ? 'left' : 'top',
				'description'     => (string) ( $acf['description'] ?? '' ),
				'show_in_rest'    => ! empty( $acf['show_in_rest'] ),
			),
		);

		if ( ! $location ) {
			$this->notes[] = __( 'None of its location rules carry over, so it is imported inactive. Set where it shows in the builder.', 'cfmb' );
			$group['active'] = false;
		}

		return array(
			'group' => $group,
			'rules' => $this->rules,
			'notes' => array_values( array_unique( $this->notes ) ),
			'pages' => $this->option_pages( $location ),
		);
	}

	/**
	 * Map a post type definition (ACF 6.1+, SCF).
	 *
	 * @param array<string, mixed> $acf ACF post type settings.
	 * @return array<string, mixed> Raw CFMB definition for Post_Type_Schema::sanitize().
	 */
	public function post_type( array $acf ): array {
		$labels  = is_array( $acf['labels'] ?? null ) ? $acf['labels'] : array();
		$rewrite = is_array( $acf['rewrite'] ?? null ) ? $acf['rewrite'] : array();

		return array(
			'key'      => self::key( (string) ( $acf['key'] ?? '' ), 'post_type_', Key_Generator::POST_TYPE_PREFIX ),
			'name'     => (string) ( $acf['post_type'] ?? '' ),
			'title'    => (string) ( $labels['name'] ?? ( $acf['title'] ?? '' ) ),
			'active'   => ! array_key_exists( 'active', $acf ) || ! empty( $acf['active'] ),
			'settings' => array(
				'singular'            => (string) ( $labels['singular_name'] ?? '' ),
				'description'         => (string) ( $acf['description'] ?? '' ),
				'public'              => ! empty( $acf['public'] ),
				'hierarchical'        => ! empty( $acf['hierarchical'] ),
				'supports'            => array_values( array_filter( (array) ( $acf['supports'] ?? array() ), 'is_string' ) ),
				'taxonomies'          => array_values( array_filter( (array) ( $acf['taxonomies'] ?? array() ), 'is_string' ) ),
				'menu_icon'           => is_string( $acf['menu_icon'] ?? null ) ? $acf['menu_icon'] : '',
				'has_archive'         => ! empty( $acf['has_archive'] ),
				'archive_slug'        => (string) ( $acf['has_archive_slug'] ?? '' ),
				'show_in_rest'        => ! array_key_exists( 'show_in_rest', $acf ) || ! empty( $acf['show_in_rest'] ),
				'rest_base'           => (string) ( $acf['rest_base'] ?? '' ),
				'rest_namespace'      => (string) ( $acf['rest_namespace'] ?? '' ),
				'labels'              => self::labels( $labels ),
				'publicly_queryable'  => self::tristate( $acf, 'publicly_queryable' ),
				'exclude_from_search' => self::tristate( $acf, 'exclude_from_search' ),
				'show_ui'             => self::tristate( $acf, 'show_ui' ),
				'show_in_menu'        => ! array_key_exists( 'show_in_menu', $acf ) || ! empty( $acf['show_in_menu'] ),
				'menu_parent'         => (string) ( $acf['admin_menu_parent'] ?? '' ),
				'menu_position'       => is_numeric( $acf['menu_position'] ?? null ) ? (int) $acf['menu_position'] : '',
				'show_in_nav_menus'   => self::tristate( $acf, 'show_in_nav_menus' ),
				'show_in_admin_bar'   => self::tristate( $acf, 'show_in_admin_bar' ),
				'rewrite'             => 'no_permalink' !== ( $rewrite['permalink_rewrite'] ?? '' ),
				'rewrite_slug'        => 'custom_permalink' === ( $rewrite['permalink_rewrite'] ?? '' ) ? (string) ( $rewrite['slug'] ?? '' ) : '',
				'rewrite_with_front'  => ! array_key_exists( 'with_front', $rewrite ) || ! empty( $rewrite['with_front'] ),
				'rewrite_feeds'       => ! empty( $rewrite['feeds'] ),
				'rewrite_pages'       => ! array_key_exists( 'pages', $rewrite ) || ! empty( $rewrite['pages'] ),
				'query_var'           => 'none' !== ( $acf['query_var'] ?? '' ),
				'query_var_name'      => (string) ( $acf['query_var_name'] ?? '' ),
				'can_export'          => ! array_key_exists( 'can_export', $acf ) || ! empty( $acf['can_export'] ),
				'delete_with_user'    => ! empty( $acf['delete_with_user'] ),
			),
		);
	}

	/**
	 * Map a taxonomy definition (ACF 6.1+, SCF).
	 *
	 * @param array<string, mixed> $acf ACF taxonomy settings.
	 * @return array<string, mixed> Raw CFMB definition for Taxonomy_Schema::sanitize().
	 */
	public function taxonomy( array $acf ): array {
		$labels  = is_array( $acf['labels'] ?? null ) ? $acf['labels'] : array();
		$rewrite = is_array( $acf['rewrite'] ?? null ) ? $acf['rewrite'] : array();
		$default = is_array( $acf['default_term'] ?? null ) ? $acf['default_term'] : array();

		return array(
			'key'      => self::key( (string) ( $acf['key'] ?? '' ), 'taxonomy_', Key_Generator::TAXONOMY_PREFIX ),
			'name'     => (string) ( $acf['taxonomy'] ?? '' ),
			'title'    => (string) ( $labels['name'] ?? ( $acf['title'] ?? '' ) ),
			'active'   => ! array_key_exists( 'active', $acf ) || ! empty( $acf['active'] ),
			'settings' => array(
				'singular'             => (string) ( $labels['singular_name'] ?? '' ),
				'description'          => (string) ( $acf['description'] ?? '' ),
				'post_types'           => array_values( array_filter( (array) ( $acf['object_type'] ?? array() ), 'is_string' ) ),
				'public'               => ! array_key_exists( 'public', $acf ) || ! empty( $acf['public'] ),
				'hierarchical'         => ! empty( $acf['hierarchical'] ),
				'show_in_rest'         => ! array_key_exists( 'show_in_rest', $acf ) || ! empty( $acf['show_in_rest'] ),
				'show_admin_column'    => ! empty( $acf['show_admin_column'] ),
				'labels'               => self::labels( $labels ),
				'publicly_queryable'   => self::tristate( $acf, 'publicly_queryable' ),
				'show_ui'              => self::tristate( $acf, 'show_ui' ),
				'show_in_menu'         => self::tristate( $acf, 'show_in_menu' ),
				'show_in_nav_menus'    => self::tristate( $acf, 'show_in_nav_menus' ),
				'show_tagcloud'        => self::tristate( $acf, 'show_tagcloud' ),
				'show_in_quick_edit'   => self::tristate( $acf, 'show_in_quick_edit' ),
				'rewrite'              => 'no_permalink' !== ( $rewrite['permalink_rewrite'] ?? '' ),
				'rewrite_slug'         => 'custom_permalink' === ( $rewrite['permalink_rewrite'] ?? '' ) ? (string) ( $rewrite['slug'] ?? '' ) : '',
				'rewrite_with_front'   => ! array_key_exists( 'with_front', $rewrite ) || ! empty( $rewrite['with_front'] ),
				'rewrite_hierarchical' => ! empty( $rewrite['rewrite_hierarchical'] ),
				'query_var'            => 'none' !== ( $acf['query_var'] ?? '' ),
				'query_var_name'       => (string) ( $acf['query_var_name'] ?? '' ),
				'default_term_name'    => ! empty( $default['default_term_enabled'] ) ? (string) ( $default['default_term_name'] ?? '' ) : '',
				'default_term_slug'    => ! empty( $default['default_term_enabled'] ) ? (string) ( $default['default_term_slug'] ?? '' ) : '',
				'sort'                 => ! empty( $acf['sort'] ),
				'rest_base'            => (string) ( $acf['rest_base'] ?? '' ),
				'rest_namespace'       => (string) ( $acf['rest_namespace'] ?? '' ),
			),
		);
	}

	/**
	 * Map an options page.
	 *
	 * @param array<string, mixed> $acf  ACF options page.
	 * @param string               $name CFMB page ID to use.
	 * @return array<string, mixed> Raw CFMB definition for Options_Page_Schema::sanitize().
	 */
	public function options_page( array $acf, string $name ): array {
		$key = (string) ( $acf['key'] ?? '' );

		return array(
			'key'      => '' !== $key ? self::key( $key, 'ui_options_page_', Key_Generator::OPTIONS_PAGE_PREFIX ) : Key_Generator::OPTIONS_PAGE_PREFIX . substr( md5( 'acf-options-' . $name ), 0, 12 ),
			'name'     => $name,
			'title'    => (string) ( $acf['page_title'] ?? ( $acf['title'] ?? $name ) ),
			'active'   => ! array_key_exists( 'active', $acf ) || ! empty( $acf['active'] ),
			'settings' => array(
				'menu_title'   => (string) ( $acf['menu_title'] ?? '' ),
				'parent_slug'  => (string) ( $acf['parent_slug'] ?? '' ),
				'icon'         => is_string( $acf['icon_url'] ?? null ) && '' !== $acf['icon_url'] ? $acf['icon_url'] : 'dashicons-admin-generic',
				'description'  => (string) ( $acf['description'] ?? '' ),
				'position'     => is_numeric( $acf['position'] ?? null ) ? (int) $acf['position'] : '',
				'capability'   => (string) ( $acf['capability'] ?? 'edit_posts' ),
				'button_label' => (string) ( $acf['update_button'] ?? '' ),
			),
		);
	}

	/**
	 * CFMB page ID for an ACF options page slug.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function page_name( string $slug ): string {
		$name = sanitize_key( (string) preg_replace( '/^acf-options-?/', '', strtolower( $slug ) ) );
		$name = '' !== $name ? $name : 'options';

		return substr( $name, 0, 32 );
	}

	/**
	 * Map a list of fields.
	 *
	 * @param array<int, array<string, mixed>> $fields ACF fields.
	 * @param string                           $prefix Regex prefix of their flattened keys.
	 * @param int                              $depth  Depth, to stop clone loops.
	 * @return array<int, array<string, mixed>>
	 */
	private function fields( array $fields, string $prefix, int $depth ): array {
		$mapped = array();

		foreach ( $fields as $acf ) {
			if ( ! is_array( $acf ) ) {
				continue;
			}

			if ( 'clone' === ( $acf['type'] ?? '' ) ) {
				$mapped = array_merge( $mapped, $this->clone_fields( $acf, $prefix, $depth ) );
				continue;
			}

			$field = $this->field( $acf, $prefix, $depth );

			if ( $field ) {
				$mapped[] = $field;
			}
		}

		return $this->conditions( $mapped );
	}

	/**
	 * Map one field.
	 *
	 * @param array<string, mixed> $acf    ACF field.
	 * @param string               $prefix Regex prefix of its flattened key.
	 * @param int                  $depth  Depth.
	 * @return array<string, mixed>|null
	 */
	private function field( array $acf, string $prefix, int $depth ): ?array {
		$acf_type = (string) ( $acf['type'] ?? '' );
		$type     = self::TYPES[ $acf_type ] ?? '';
		$label    = (string) ( $acf['label'] ?? '' );
		$name     = (string) ( $acf['name'] ?? '' );

		if ( '' === $type ) {
			/* translators: 1: field label, 2: ACF field type */
			$this->notes[] = sprintf( __( '"%1$s" is a %2$s field, which CFMB doesn\'t have. It was left out; its values stay in the database.', 'cfmb' ), '' !== $label ? $label : $name, $acf_type );
			return null;
		}

		$path  = $prefix . preg_quote( $name, '/' );
		$field = array(
			'key'          => $this->field_key( (string) ( $acf['key'] ?? '' ) ),
			'name'         => $name,
			'label'        => $label,
			'type'         => $type,
			'instructions' => (string) ( $acf['instructions'] ?? '' ),
			'required'     => ! empty( $acf['required'] ),
			'wrapper'      => array(
				'width' => (int) ( $acf['wrapper']['width'] ?? 0 ),
				'class' => (string) ( $acf['wrapper']['class'] ?? '' ),
			),
			'settings'     => $this->settings( $type, $acf ),
			'_acf_key'     => (string) ( $acf['key'] ?? '' ),
			'_acf_logic'   => $acf['conditional_logic'] ?? 0,
		);

		// ACF Multilingual keeps WPML's per-field choice: 0 ignore, 1 copy, 2 translate, 3 copy once.
		$wpml = array( 0 => 'ignore', 1 => 'copy', 2 => 'translate', 3 => 'copy_once' );
		if ( isset( $acf['wpml_cf_preferences'] ) && is_numeric( $acf['wpml_cf_preferences'] ) && isset( $wpml[ (int) $acf['wpml_cf_preferences'] ] ) ) {
			$field['integrations'] = array( 'translation' => $wpml[ (int) $acf['wpml_cf_preferences'] ] );
		}

		$this->value_rule( $type, $acf, $path );

		if ( 'group' === $type ) {
			$field['fields'] = $this->fields( (array) ( $acf['sub_fields'] ?? array() ), $path . '_', $depth + 1 );
		} elseif ( 'repeater' === $type ) {
			$field['fields'] = $this->fields( (array) ( $acf['sub_fields'] ?? array() ), $path . '_\d+_', $depth + 1 );
		} elseif ( 'stacks' === $type ) {
			$field['layouts'] = $this->layouts( (array) ( $acf['layouts'] ?? array() ), $path . '_\d+_', $depth );
		}

		return $field;
	}

	/**
	 * Flexible Content layouts as Stacks layouts.
	 *
	 * @param array<int|string, mixed> $layouts ACF layouts.
	 * @param string                   $prefix  Regex prefix of row keys.
	 * @param int                      $depth   Depth.
	 * @return array<int, array<string, mixed>>
	 */
	private function layouts( array $layouts, string $prefix, int $depth ): array {
		$mapped = array();

		foreach ( $layouts as $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$mapped[] = array(
				'key'    => self::key( (string) ( $layout['key'] ?? '' ), 'layout_', Key_Generator::LAYOUT_PREFIX ),
				'name'   => (string) ( $layout['name'] ?? '' ),
				'label'  => (string) ( $layout['label'] ?? '' ),
				'min'    => (int) ( $layout['min'] ?? 0 ),
				'max'    => (int) ( $layout['max'] ?? 0 ),
				'fields' => $this->fields( (array) ( $layout['sub_fields'] ?? array() ), $prefix, $depth + 1 ),
			);
		}

		return $mapped;
	}

	/**
	 * Copies of the fields a clone field shows. Seamless clones sit inline;
	 * prefixed clones become a Group named after the clone, which matches
	 * how ACF saved them ({clone}_{field}).
	 *
	 * @param array<string, mixed> $acf    Clone field.
	 * @param string               $prefix Regex prefix.
	 * @param int                  $depth  Depth.
	 * @return array<int, array<string, mixed>>
	 */
	private function clone_fields( array $acf, string $prefix, int $depth ): array {
		if ( $depth > 5 ) {
			return array();
		}

		$targets = array();

		foreach ( (array) ( $acf['clone'] ?? array() ) as $target ) {
			$targets = array_merge( $targets, $this->clone_targets( (string) $target ) );
		}

		if ( ! $targets ) {
			/* translators: %s: field label */
			$this->notes[] = sprintf( __( 'The clone field "%s" points at fields that were not found, so it was left out.', 'cfmb' ), (string) ( $acf['label'] ?? '' ) );
			return array();
		}

		// Cloned keys repeat the originals; give the copies their own.
		$seed    = (string) ( $acf['key'] ?? '' );
		$targets = self::rekey( $targets, $seed );

		/* translators: %s: field label */
		$this->notes[] = sprintf( __( 'The clone field "%s" was turned into a copy of the fields it showed. Changes to the original fields no longer reach it.', 'cfmb' ), (string) ( $acf['label'] ?? '' ) );

		$prefixed = ! empty( $acf['prefix_name'] ) || 'group' === ( $acf['display'] ?? 'seamless' );

		if ( ! $prefixed ) {
			return $this->fields( $targets, $prefix, $depth + 1 );
		}

		$group = $this->field(
			array(
				'key'        => $seed,
				'name'       => (string) ( $acf['name'] ?? '' ),
				'label'      => (string) ( $acf['label'] ?? '' ),
				'type'       => 'group',
				'sub_fields' => $targets,
			),
			$prefix,
			$depth + 1
		);

		return $group ? array( $group ) : array();
	}

	/**
	 * Fields a clone target names: a group key (all its fields) or a field key.
	 *
	 * @param string $target Key.
	 * @return array<int, array<string, mixed>>
	 */
	private function clone_targets( string $target ): array {
		if ( isset( $this->all_groups[ $target ] ) ) {
			return (array) ( $this->all_groups[ $target ]['fields'] ?? array() );
		}

		foreach ( $this->all_groups as $group ) {
			$found = self::find_field( (array) ( $group['fields'] ?? array() ), $target );

			if ( $found ) {
				return array( $found );
			}
		}

		return array();
	}

	/**
	 * Find a field by key in a tree.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param string                           $key    Key.
	 * @return array<string, mixed>|null
	 */
	private static function find_field( array $fields, string $key ): ?array {
		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( ( $field['key'] ?? '' ) === $key ) {
				return $field;
			}

			$children = (array) ( $field['sub_fields'] ?? array() );

			foreach ( (array) ( $field['layouts'] ?? array() ) as $layout ) {
				$children = array_merge( $children, (array) ( $layout['sub_fields'] ?? array() ) );
			}

			$found = self::find_field( $children, $key );

			if ( $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Give copied fields new keys derived from the clone's.
	 *
	 * @param array<int, array<string, mixed>> $fields Fields.
	 * @param string                           $seed   Clone key.
	 * @return array<int, array<string, mixed>>
	 */
	private static function rekey( array $fields, string $seed ): array {
		foreach ( $fields as $index => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$fields[ $index ]['key'] = 'field_' . substr( md5( $seed . '|' . (string) ( $field['key'] ?? $index ) ), 0, 13 );

			if ( isset( $field['sub_fields'] ) ) {
				$fields[ $index ]['sub_fields'] = self::rekey( (array) $field['sub_fields'], $seed );
			}

			if ( isset( $field['layouts'] ) ) {
				foreach ( (array) $field['layouts'] as $layout_key => $layout ) {
					$fields[ $index ]['layouts'][ $layout_key ]['key']        = 'layout_' . substr( md5( $seed . '|' . (string) ( $layout['key'] ?? $layout_key ) ), 0, 13 );
					$fields[ $index ]['layouts'][ $layout_key ]['sub_fields'] = self::rekey( (array) ( $layout['sub_fields'] ?? array() ), $seed );
				}
			}
		}

		return $fields;
	}

	/**
	 * Type-specific settings.
	 *
	 * @param string               $type CFMB type.
	 * @param array<string, mixed> $acf  ACF field.
	 * @return array<string, mixed>
	 */
	private function settings( string $type, array $acf ): array {
		$copy = static function ( array $names ) use ( $acf ): array {
			$out = array();
			foreach ( $names as $from => $to ) {
				$from = is_int( $from ) ? $to : $from;

				if ( array_key_exists( $from, $acf ) && null !== $acf[ $from ] && '' !== $acf[ $from ] ) {
					$out[ $to ] = $acf[ $from ];
				}
			}

			return $out;
		};

		switch ( $type ) {
			case 'text':
			case 'email':
			case 'url':
				return $copy( array( 'default_value', 'placeholder', 'prepend', 'append', 'maxlength' ) );

			case 'password':
				return $copy( array( 'placeholder' ) );

			case 'textarea':
				$settings = $copy( array( 'default_value', 'placeholder', 'rows', 'maxlength' ) );
				$lines    = (string) ( $acf['new_lines'] ?? '' );

				$settings['new_lines'] = in_array( $lines, array( 'wpautop', 'br' ), true ) ? $lines : 'none';

				return $settings;

			case 'number':
			case 'range':
				return $copy( array( 'default_value', 'placeholder', 'min', 'max', 'step', 'prepend', 'append' ) );

			case 'wysiwyg':
				$settings                 = $copy( array( 'default_value' ) );
				$settings['media_upload'] = ! array_key_exists( 'media_upload', $acf ) || ! empty( $acf['media_upload'] );

				return $settings;

			case 'oembed':
				return $copy( array( 'width', 'height' ) );

			case 'image':
				return $copy( array( 'return_format', 'preview_size', 'mime_types' ) );

			case 'file':
				return $copy( array( 'return_format', 'mime_types' ) );

			case 'gallery':
				return $copy( array( 'min', 'max', 'return_format', 'preview_size' ) );

			case 'select':
			case 'checkbox':
			case 'radio':
			case 'button_group':
				$settings            = $copy( array( 'return_format', 'allow_null', 'placeholder', 'toggle' => 'toggle_all', 'layout' ) );
				$settings['choices'] = self::choices( $acf['choices'] ?? array() );
				$default             = $acf['default_value'] ?? '';

				if ( 'select' === $type ) {
					$settings['multiple'] = ! empty( $acf['multiple'] );
				}

				$settings['default_value'] = in_array( $type, array( 'checkbox' ), true ) || ! empty( $settings['multiple'] )
					? array_values( array_map( 'strval', (array) $default ) )
					: ( is_array( $default ) ? (string) reset( $default ) : (string) $default );

				return $settings;

			case 'toggle':
				$settings                  = $copy( array( 'message' ) );
				$settings['default_value'] = ! empty( $acf['default_value'] );

				return $settings;

			case 'link':
				return $copy( array( 'return_format' ) );

			case 'post':
				$settings              = $copy( array( 'return_format' ) );
				$settings['post_type'] = array_values( array_filter( (array) ( $acf['post_type'] ?? array() ), 'is_string' ) );
				$settings['multiple']  = ! empty( $acf['multiple'] );

				return $settings;

			case 'page_link':
				return array( 'multiple' => ! empty( $acf['multiple'] ) );

			case 'relationship':
				$settings              = $copy( array( 'min', 'max', 'return_format' ) );
				$settings['post_type'] = array_values( array_filter( (array) ( $acf['post_type'] ?? array() ), 'is_string' ) );

				return $settings;

			case 'taxonomy':
				$settings = $copy( array( 'taxonomy', 'return_format' ) );
				$look     = (string) ( $acf['field_type'] ?? 'checkbox' );

				$settings['appearance'] = in_array( $look, array( 'checkbox', 'multi_select', 'radio', 'select' ), true ) ? $look : 'checkbox';
				$settings['load_terms'] = ! empty( $acf['load_terms'] ) || ! empty( $acf['save_terms'] );

				if ( ! empty( $acf['save_terms'] ) || ! empty( $acf['load_terms'] ) ) {
					/* translators: %s: field label */
					$this->notes[] = sprintf( __( '"%s" saved and loaded post terms in ACF. Check its term settings in the builder.', 'cfmb' ), (string) ( $acf['label'] ?? '' ) );
				}

				return $settings;

			case 'user':
				$settings             = $copy( array( 'return_format' ) );
				$settings['role']     = array_values( array_filter( (array) ( $acf['role'] ?? array() ), 'is_string' ) );
				$settings['multiple'] = ! empty( $acf['multiple'] );

				return $settings;

			case 'map':
				return $copy( array( 'center_lat', 'center_lng', 'zoom', 'height' ) );

			case 'date':
			case 'datetime':
			case 'time':
				return $copy( array( 'display_format', 'return_format', 'first_day' ) );

			case 'color':
				$settings = $copy( array( 'default_value' ) );

				$settings['alpha'] = ! empty( $acf['enable_opacity'] );

				return $settings;

			case 'message':
				return array( 'message' => (string) ( $acf['message'] ?? '' ) );

			case 'accordion':
				return array( 'open' => ! empty( $acf['open'] ) );

			case 'repeater':
			case 'stacks':
				$settings = $copy( array( 'min', 'max', 'button_label' ) );

				if ( ! empty( $acf['pagination'] ) ) {
					$settings['pagination'] = true;
					$settings['per_page']   = (int) ( $acf['rows_per_page'] ?? 20 );
				}

				return $settings;
		}

		return array();
	}

	/**
	 * Note how this field's values have to change, if at all.
	 *
	 * @param string               $type CFMB type.
	 * @param array<string, mixed> $acf  ACF field.
	 * @param string               $path Regex of its flattened key.
	 * @return void
	 */
	private function value_rule( string $type, array $acf, string $path ): void {
		if ( ! in_array( $type, array( 'message', 'tab', 'accordion', 'group' ), true ) ) {
			// Every stored value, for moving options page values to their new names.
			$this->rules[] = array(
				'pattern' => '/^' . $path . '$/',
				'kind'    => 'value',
			);
		}

		$list = in_array( $type, array( 'checkbox', 'gallery', 'relationship', 'stacks' ), true )
			|| ( in_array( $type, array( 'select', 'post', 'user', 'page_link' ), true ) && ! empty( $acf['multiple'] ) )
			|| ( 'taxonomy' === $type && in_array( (string) ( $acf['field_type'] ?? 'checkbox' ), array( 'checkbox', 'multi_select' ), true ) );

		if ( $list ) {
			$this->rules[] = array(
				'pattern' => '/^' . $path . '$/',
				'kind'    => 'list',
			);
		}

		if ( 'date' === $type ) {
			$this->rules[] = array(
				'pattern' => '/^' . $path . '$/',
				'kind'    => 'date',
			);
		}
	}

	/**
	 * ACF conditional logic as CFMB conditions, for fields on the same level.
	 *
	 * @param array<int, array<string, mixed>> $mapped Mapped fields.
	 * @return array<int, array<string, mixed>>
	 */
	private function conditions( array $mapped ): array {
		$keys = array();
		foreach ( $mapped as $field ) {
			if ( isset( $field['_acf_key'] ) ) {
				$keys[ (string) $field['_acf_key'] ] = (string) $field['key'];
			}
		}

		$operators = array(
			'=='         => '==',
			'!='         => '!=',
			'==empty'    => 'empty',
			'!=empty'    => 'not_empty',
			'==contains' => 'contains',
			'>'          => '>',
			'<'          => '<',
		);

		foreach ( $mapped as $index => $field ) {
			// Fields copied in by a clone were handled on their own level.
			if ( ! array_key_exists( '_acf_logic', $field ) ) {
				continue;
			}

			$sets  = array();
			$lost  = false;
			$logic = $field['_acf_logic'];

			foreach ( is_array( $logic ) ? $logic : array() as $rules ) {
				$set = array();

				foreach ( (array) $rules as $rule ) {
					$target   = (string) ( $rule['field'] ?? '' );
					$operator = (string) ( $rule['operator'] ?? '' );

					if ( ! isset( $keys[ $target ], $operators[ $operator ] ) ) {
						$lost = true;
						continue;
					}

					$set[] = array(
						'field'    => $keys[ $target ],
						'operator' => $operators[ $operator ],
						'value'    => is_scalar( $rule['value'] ?? '' ) ? (string) ( $rule['value'] ?? '' ) : '',
					);
				}

				if ( $set ) {
					$sets[] = $set;
				}
			}

			if ( $lost ) {
				/* translators: %s: field label */
				$this->notes[] = sprintf( __( 'Some conditions on "%s" point at fields in another group or row, or use an operator CFMB doesn\'t have. Check them in the builder.', 'cfmb' ), (string) $field['label'] );
			}

			$mapped[ $index ]['conditions'] = $sets;
			unset( $mapped[ $index ]['_acf_key'], $mapped[ $index ]['_acf_logic'] );
		}

		return $mapped;
	}

	/**
	 * Location rules.
	 *
	 * @param array<int, mixed> $location ACF location groups.
	 * @return array<int, array<int, array<string, string>>>
	 */
	private function location( array $location ): array {
		$mapped = array();

		foreach ( $location as $rules ) {
			$set  = array();
			$skip = false;

			foreach ( (array) $rules as $rule ) {
				$param = (string) ( $rule['param'] ?? '' );
				$value = (string) ( $rule['value'] ?? '' );

				if ( ! isset( self::RULES[ $param ] ) || ! in_array( (string) ( $rule['operator'] ?? '==' ), array( '==', '!=' ), true ) ) {
					/* translators: %s: ACF location rule name */
					$this->notes[] = sprintf( __( 'The location rule "%s" has no CFMB equivalent and was left out.', 'cfmb' ), $param );
					$skip          = true;
					continue;
				}

				if ( 'options_page' === $param ) {
					$value = $this->pages[ $value ] ?? self::page_name( $value );
				}

				if ( 'user_form' === $param && 'register' === $value ) {
					$value = 'add';
				}

				$set[] = array(
					'param'    => self::RULES[ $param ],
					'operator' => (string) ( $rule['operator'] ?? '==' ),
					'value'    => $value,
				);
			}

			// A rule group with a dropped rule would match more than before, so it goes.
			if ( $set && ! $skip ) {
				$mapped[] = $set;
			}
		}

		return $mapped;
	}

	/**
	 * Options page IDs the location points at.
	 *
	 * @param array<int, array<int, array<string, string>>> $location Mapped location.
	 * @return string[]
	 */
	private function option_pages( array $location ): array {
		$pages = array();

		foreach ( $location as $rules ) {
			foreach ( $rules as $rule ) {
				if ( 'options_page' === $rule['param'] && '==' === $rule['operator'] ) {
					$pages[] = $rule['value'];
				}
			}
		}

		return array_values( array_unique( $pages ) );
	}

	/**
	 * Field key, unique within the group.
	 *
	 * @param string $acf_key ACF key.
	 * @return string
	 */
	private function field_key( string $acf_key ): string {
		$key = self::key( $acf_key, 'field_', Key_Generator::FIELD_PREFIX );

		while ( isset( $this->keys[ $key ] ) ) {
			$key = Key_Generator::field();
		}

		$this->keys[ $key ] = true;

		return $key;
	}

	/**
	 * CFMB key for an ACF key, keeping its random part when it fits.
	 *
	 * @param string $acf_key    ACF key, e.g. field_5f2a1b3c4d5e6.
	 * @param string $acf_prefix ACF prefix, e.g. field_.
	 * @param string $prefix     CFMB prefix, e.g. fld_.
	 * @return string
	 */
	public static function key( string $acf_key, string $acf_prefix, string $prefix ): string {
		$rest = 0 === strpos( $acf_key, $acf_prefix ) ? substr( $acf_key, strlen( $acf_prefix ) ) : $acf_key;
		$rest = (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $rest ) );

		if ( strlen( $rest ) < 6 || strlen( $rest ) > 32 ) {
			$rest = '' !== $acf_key ? substr( md5( $acf_key ), 0, 12 ) : bin2hex( random_bytes( 5 ) );
		}

		return $prefix . $rest;
	}

	/**
	 * ACF choices (value => label) as CFMB pairs.
	 *
	 * @param mixed $choices ACF choices.
	 * @return array<int, array{value: string, label: string}>
	 */
	private static function choices( $choices ): array {
		$list = array();

		foreach ( is_array( $choices ) ? $choices : array() as $value => $label ) {
			$list[] = array(
				'value' => (string) $value,
				'label' => is_scalar( $label ) ? (string) $label : (string) $value,
			);
		}

		return $list;
	}

	/**
	 * ACF labels CFMB knows, without empty ones.
	 *
	 * @param array<string, mixed> $labels ACF labels.
	 * @return array<string, string>
	 */
	private static function labels( array $labels ): array {
		$clean = array();

		foreach ( $labels as $key => $label ) {
			if ( is_string( $label ) && '' !== $label && ! in_array( $key, array( 'name', 'singular_name' ), true ) ) {
				$clean[ (string) $key ] = $label;
			}
		}

		return $clean;
	}

	/**
	 * An ACF boolean as a CFMB tristate ('' follows the default).
	 *
	 * @param array<string, mixed> $acf  Settings.
	 * @param string               $name Setting.
	 * @return string
	 */
	private static function tristate( array $acf, string $name ): string {
		if ( ! array_key_exists( $name, $acf ) || '' === $acf[ $name ] || null === $acf[ $name ] ) {
			return '';
		}

		return ! empty( $acf[ $name ] ) ? '1' : '0';
	}
}
