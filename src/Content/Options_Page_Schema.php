<?php
/**
 * Options page definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Core\Plugin;
use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, checks and registry arguments for options pages made in CFMB.
 */
class Options_Page_Schema extends Content_Schema {

	/**
	 * Kind key.
	 *
	 * @return string
	 */
	public function kind(): string {
		return 'options_page';
	}

	/**
	 * Key prefix.
	 *
	 * @return string
	 */
	public function key_prefix(): string {
		return Key_Generator::OPTIONS_PAGE_PREFIX;
	}

	/**
	 * Storage post type.
	 *
	 * @return string
	 */
	public function storage_type(): string {
		return 'cfmb_options_page';
	}

	/**
	 * Values are saved as cfmb_{page}_{field}; this keeps option names within 191 characters.
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
			'singular'      => __( 'Options Page', 'custom-fields-meta-box' ),
			'plural'        => __( 'Options Pages', 'custom-fields-meta-box' ),
			'add'           => __( 'Add options page', 'custom-fields-meta-box' ),
			'title_label'   => __( 'Page title', 'custom-fields-meta-box' ),
			'title_example' => __( 'For example: Site settings', 'custom-fields-meta-box' ),
			'name_label'    => __( 'Page ID', 'custom-fields-meta-box' ),
			'name_help'     => __( 'Used in cfmb_get_field( \'name\', \'option:ID\' ) and in saved option names. Lowercase letters, numbers, dashes and underscores.', 'custom-fields-meta-box' ),
			'title_missing' => __( 'Give the options page a title.', 'custom-fields-meta-box' ),
			'name_missing'  => __( 'Give the options page an ID, such as site_settings.', 'custom-fields-meta-box' ),
			'empty_title'   => __( 'Create your first options page', 'custom-fields-meta-box' ),
			'empty_text'    => __( 'An options page holds site-wide fields, like a phone number or social links, on its own admin page.', 'custom-fields-meta-box' ),
			'move'          => '',
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
		if ( $name === $previous ) {
			return null;
		}

		$existing = Plugin::instance()->get( Options_Pages::class )->get( $name );

		if ( $existing && empty( $existing['cfmb_key'] ) ) {
			/* translators: %s: options page ID */
			return sprintf( __( 'An options page with the ID "%s" is already registered in code. Choose another ID.', 'custom-fields-meta-box' ), $name );
		}

		return null;
	}

	/**
	 * Setting descriptors.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		return array(
			$this->setting( 'menu_title', __( 'Menu title', 'custom-fields-meta-box' ), 'text', '', array( 'help' => __( 'Optional. Defaults to the page title.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'parent', __( 'Menu placement', 'custom-fields-meta-box' ), 'select', '', array( 'options' => self::parent_options() ) ),
			$this->setting( 'icon', __( 'Menu icon', 'custom-fields-meta-box' ), 'dashicon', 'dashicons-admin-generic', array( 'help' => __( 'For top-level pages. A Dashicons name, an image URL, or an SVG data URL.', 'custom-fields-meta-box' ) ) ),
			$this->setting( 'description', __( 'Description', 'custom-fields-meta-box' ), 'textarea', '', array( 'help' => __( 'Shown at the top of the page.', 'custom-fields-meta-box' ) ) ),
			$this->setting(
				'parent_slug',
				__( 'Custom parent menu', 'custom-fields-meta-box' ),
				'text',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
					'help'     => __( 'Optional. The menu slug of another parent page, for menus added by other plugins. Overrides Menu placement.', 'custom-fields-meta-box' ),
				)
			),
			$this->setting(
				'position',
				__( 'Menu position', 'custom-fields-meta-box' ),
				'number',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
				)
			),
			$this->setting(
				'capability',
				__( 'Capability', 'custom-fields-meta-box' ),
				'text',
				'manage_options',
				array(
					'advanced' => true,
					'section'  => 'permissions',
					'help'     => __( 'Who can see and save the page. Default: manage_options (administrators).', 'custom-fields-meta-box' ),
				)
			),
			$this->setting(
				'button_label',
				__( 'Save button label', 'custom-fields-meta-box' ),
				'text',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
					'help'     => __( 'Optional. Defaults to "Save changes".', 'custom-fields-meta-box' ),
				)
			),
		);
	}

	/**
	 * Where the page can sit in the admin menu.
	 *
	 * @return array<string, string>
	 */
	private static function parent_options(): array {
		$options = array(
			''                        => __( 'Its own top-level menu', 'custom-fields-meta-box' ),
			'index.php'               => __( 'Under Dashboard', 'custom-fields-meta-box' ),
			'edit.php'                => __( 'Under Posts', 'custom-fields-meta-box' ),
			'upload.php'              => __( 'Under Media', 'custom-fields-meta-box' ),
			'edit.php?post_type=page' => __( 'Under Pages', 'custom-fields-meta-box' ),
			'themes.php'              => __( 'Under Appearance', 'custom-fields-meta-box' ),
			'plugins.php'             => __( 'Under Plugins', 'custom-fields-meta-box' ),
			'users.php'               => __( 'Under Users', 'custom-fields-meta-box' ),
			'tools.php'               => __( 'Under Tools', 'custom-fields-meta-box' ),
			'options-general.php'     => __( 'Under Settings', 'custom-fields-meta-box' ),
		);

		foreach ( get_post_types(
			array(
				'show_ui'  => true,
				'_builtin' => false,
			),
			'objects'
		) as $name => $type ) {
			if ( 0 !== strpos( $name, 'wp_' ) && 0 !== strpos( $name, 'cfmb_' ) ) {
				/* translators: %s: post type name, e.g. Books */
				$options[ 'edit.php?post_type=' . $name ] = sprintf( __( 'Under %s', 'custom-fields-meta-box' ), $type->labels->name );
			}
		}

		foreach ( Plugin::instance()->get( Options_Pages::class )->all() as $page ) {
			if ( '' === $page['parent_slug'] ) {
				/* translators: %s: options page title */
				$options[ $page['menu_slug'] ] = sprintf( __( 'Under %s', 'custom-fields-meta-box' ), $page['menu_title'] );
			}
		}

		return $options;
	}

	/**
	 * Arguments for the options page registry, see cfmb_add_options_page().
	 *
	 * @param array<string, mixed> $item Normalized definition.
	 * @return array<string, mixed>
	 */
	public function build_args( array $item ): array {
		$s      = $item['settings'];
		$parent = '' !== (string) $s['parent_slug'] ? (string) $s['parent_slug'] : (string) $s['parent'];

		$args = array(
			'id'           => (string) $item['name'],
			'page_title'   => (string) $item['title'],
			'menu_title'   => '' !== (string) $s['menu_title'] ? (string) $s['menu_title'] : (string) $item['title'],
			'parent_slug'  => $parent,
			'capability'   => '' !== (string) $s['capability'] ? (string) $s['capability'] : 'manage_options',
			'icon_url'     => (string) $s['icon'],
			'description'  => (string) $s['description'],
			'button_label' => (string) $s['button_label'],
			'cfmb_key'     => (string) $item['key'],
		);

		if ( '' !== $s['position'] ) {
			$args['position'] = (int) $s['position'];
		}

		return $args;
	}
}
