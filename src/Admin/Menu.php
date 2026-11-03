<?php
/**
 * CFMB admin menu.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the CFMB menu: Field Groups, Post Types, Taxonomies, Options Pages,
 * Connections, Sync, Switch Assistant, Settings and Go Pro. All but Settings
 * and Go Pro are views of the builder app.
 */
class Menu {

	public const SLUG = 'cfmb';

	/**
	 * Builder views by menu slug.
	 */
	public const VIEWS = array(
		'cfmb'               => 'groups',
		'cfmb-post-types'    => 'post_type',
		'cfmb-taxonomies'    => 'taxonomy',
		'cfmb-options-pages' => 'options_page',
		'cfmb-connections'   => 'connection',
		'cfmb-sync'          => 'sync',
		'cfmb-switch'        => 'switch',
	);

	/**
	 * Field Group builder screen.
	 *
	 * @var Builder_Screen
	 */
	private Builder_Screen $builder;

	/**
	 * Settings screen.
	 *
	 * @var Settings_Screen
	 */
	private Settings_Screen $settings;

	/**
	 * Go Pro page.
	 *
	 * @var Go_Pro_Screen
	 */
	private Go_Pro_Screen $go_pro;

	/**
	 * Script and style handles, for the PHP screens' stylesheet.
	 *
	 * @var Assets|null
	 */
	private ?Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Builder_Screen     $builder  Builder screen.
	 * @param Settings_Screen    $settings Settings screen.
	 * @param Go_Pro_Screen|null $go_pro   Go Pro page.
	 * @param Assets|null        $assets   Script and style handles.
	 */
	public function __construct( Builder_Screen $builder, Settings_Screen $settings, ?Go_Pro_Screen $go_pro = null, ?Assets $assets = null ) {
		$this->builder  = $builder;
		$this->settings = $settings;
		$this->go_pro   = $go_pro ? $go_pro : new Go_Pro_Screen();
		$this->assets   = $assets;
	}

	/**
	 * Load the PHP screens' stylesheet.
	 *
	 * @return void
	 */
	public function load_screen_styles(): void {
		if ( $this->assets ) {
			$this->assets->enqueue_style( 'screens' );
		}
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add menu items.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$capability = cfmb_admin_capability();

		add_menu_page(
			__( 'Field Groups', 'custom-fields-meta-box' ),
			__( 'CFMB', 'custom-fields-meta-box' ),
			$capability,
			self::SLUG,
			array( $this->builder, 'render' ),
			self::menu_icon(),
			80
		);

		$pages = array(
			'cfmb'               => __( 'Field Groups', 'custom-fields-meta-box' ),
			'cfmb-post-types'    => __( 'Post Types', 'custom-fields-meta-box' ),
			'cfmb-taxonomies'    => __( 'Taxonomies', 'custom-fields-meta-box' ),
			'cfmb-options-pages' => __( 'Options Pages', 'custom-fields-meta-box' ),
			'cfmb-connections'   => __( 'Connections', 'custom-fields-meta-box' ),
			'cfmb-sync'          => __( 'Sync', 'custom-fields-meta-box' ),
			'cfmb-switch'        => __( 'Switch Assistant', 'custom-fields-meta-box' ),
		);

		foreach ( $pages as $slug => $title ) {
			$hook = add_submenu_page( self::SLUG, $title, $title, $capability, $slug, array( $this->builder, 'render' ) );

			if ( $hook ) {
				add_action( "load-{$hook}", array( $this->builder, 'load' ) );
			}
		}

		$hook = add_submenu_page(
			self::SLUG,
			__( 'CFMB Settings', 'custom-fields-meta-box' ),
			__( 'Settings', 'custom-fields-meta-box' ),
			$capability,
			Settings_Screen::SLUG,
			array( $this->settings, 'render' )
		);

		if ( $hook ) {
			add_action( "load-{$hook}", array( $this, 'load_screen_styles' ) );
		}

		if ( ! Go_Pro_Screen::pro_active() ) {
			$hook = add_submenu_page(
				self::SLUG,
				__( 'CFMB Pro', 'custom-fields-meta-box' ),
				__( 'Go Pro', 'custom-fields-meta-box' ),
				$capability,
				Go_Pro_Screen::SLUG,
				array( $this->go_pro, 'render' )
			);

			if ( $hook ) {
				add_action( "load-{$hook}", array( $this, 'load_screen_styles' ) );
			}
		}
	}

	/**
	 * The CFMB mark for the admin menu, as a data URI. WordPress paints it in
	 * the admin colour scheme's icon colours.
	 *
	 * @return string
	 */
	public static function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M3 4.5A1.5 1.5 0 0 1 4.5 3h11A1.5 1.5 0 0 1 17 4.5v2A1.5 1.5 0 0 1 15.5 8h-11A1.5 1.5 0 0 1 3 6.5v-2Zm1.5 0v2h11v-2h-11ZM3 10.5A1.5 1.5 0 0 1 4.5 9h6a1.5 1.5 0 0 1 1.5 1.5v2a1.5 1.5 0 0 1-1.5 1.5h-6A1.5 1.5 0 0 1 3 12.5v-2Zm1.5 0v2h6v-2h-6ZM3 16.25A.75.75 0 0 1 3.75 15.5h5.5a.75.75 0 0 1 0 1.5h-5.5a.75.75 0 0 1-.75-.75ZM13.47 13.22a.75.75 0 0 1 1.06 0l.97.97 2.22-2.22a.75.75 0 1 1 1.06 1.06l-2.75 2.75a.75.75 0 0 1-1.06 0l-1.5-1.5a.75.75 0 0 1 0-1.06Z"/></svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- WordPress needs menu icons as a base64 data URI.
	}
}
