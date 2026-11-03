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
			'singular'      => __( 'Options Page', 'cfmb' ),
			'plural'        => __( 'Options Pages', 'cfmb' ),
			'add'           => __( 'Add options page', 'cfmb' ),
			'title_label'   => __( 'Page title', 'cfmb' ),
			'title_example' => __( 'For example: Site settings', 'cfmb' ),
			'name_label'    => __( 'Page ID', 'cfmb' ),
			'name_help'     => __( 'Used in cfmb_get_field( \'name\', \'option:ID\' ) and in saved option names. Lowercase letters, numbers, dashes and underscores.', 'cfmb' ),
			'title_missing' => __( 'Give the options page a title.', 'cfmb' ),
			'name_missing'  => __( 'Give the options page an ID, such as site_settings.', 'cfmb' ),
			'empty_title'   => __( 'Create your first options page', 'cfmb' ),
			'empty_text'    => __( 'An options page holds site-wide fields, like a phone number or social links, on its own admin page.', 'cfmb' ),
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
			return sprintf( __( 'An options page with the ID "%s" is already registered in code. Choose another ID.', 'cfmb' ), $name );
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
			$this->setting( 'menu_title', __( 'Menu title', 'cfmb' ), 'text', '', array( 'help' => __( 'Optional. Defaults to the page title.', 'cfmb' ) ) ),
			$this->setting( 'parent', __( 'Menu placement', 'cfmb' ), 'select', '', array( 'options' => self::parent_options() ) ),
			$this->setting( 'icon', __( 'Menu icon', 'cfmb' ), 'dashicon', 'dashicons-admin-generic', array( 'help' => __( 'For top-level pages. A Dashicons name, an image URL, or an SVG data URL.', 'cfmb' ) ) ),
			$this->setting( 'description', __( 'Description', 'cfmb' ), 'textarea', '', array( 'help' => __( 'Shown at the top of the page.', 'cfmb' ) ) ),
			$this->setting(
				'parent_slug',
				__( 'Custom parent menu', 'cfmb' ),
				'text',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
					'help'     => __( 'Optional. The menu slug of another parent page, for menus added by other plugins. Overrides Menu placement.', 'cfmb' ),
				)
			),
			$this->setting(
				'position',
				__( 'Menu position', 'cfmb' ),
				'number',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
				)
			),
			$this->setting(
				'capability',
				__( 'Capability', 'cfmb' ),
				'text',
				'manage_options',
				array(
					'advanced' => true,
					'section'  => 'permissions',
					'help'     => __( 'Who can see and save the page. Default: manage_options (administrators).', 'cfmb' ),
				)
			),
			$this->setting(
				'button_label',
				__( 'Save button label', 'cfmb' ),
				'text',
				'',
				array(
					'advanced' => true,
					'section'  => 'menu',
					'help'     => __( 'Optional. Defaults to "Save changes".', 'cfmb' ),
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
			''                        => __( 'Its own top-level menu', 'cfmb' ),
			'index.php'               => __( 'Under Dashboard', 'cfmb' ),
			'edit.php'                => __( 'Under Posts', 'cfmb' ),
			'upload.php'              => __( 'Under Media', 'cfmb' ),
			'edit.php?post_type=page' => __( 'Under Pages', 'cfmb' ),
			'themes.php'              => __( 'Under Appearance', 'cfmb' ),
			'plugins.php'             => __( 'Under Plugins', 'cfmb' ),
			'users.php'               => __( 'Under Users', 'cfmb' ),
			'tools.php'               => __( 'Under Tools', 'cfmb' ),
			'options-general.php'     => __( 'Under Settings', 'cfmb' ),
		);

		foreach ( get_post_types( array( 'show_ui' => true, '_builtin' => false ), 'objects' ) as $name => $type ) {
			if ( 0 !== strpos( $name, 'wp_' ) && 0 !== strpos( $name, 'cfmb_' ) ) {
				/* translators: %s: post type name, e.g. Books */
				$options[ 'edit.php?post_type=' . $name ] = sprintf( __( 'Under %s', 'cfmb' ), $type->labels->name );
			}
		}

		foreach ( Plugin::instance()->get( Options_Pages::class )->all() as $page ) {
			if ( '' === $page['parent_slug'] ) {
				/* translators: %s: options page title */
				$options[ $page['menu_slug'] ] = sprintf( __( 'Under %s', 'cfmb' ), $page['menu_title'] );
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
