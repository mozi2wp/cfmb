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
	 * Constructor.
	 *
	 * @param Builder_Screen     $builder  Builder screen.
	 * @param Settings_Screen    $settings Settings screen.
	 * @param Go_Pro_Screen|null $go_pro   Go Pro page.
	 */
	public function __construct( Builder_Screen $builder, Settings_Screen $settings, ?Go_Pro_Screen $go_pro = null ) {
		$this->builder  = $builder;
		$this->settings = $settings;
		$this->go_pro   = $go_pro ? $go_pro : new Go_Pro_Screen();
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
			__( 'Field Groups', 'cfmb' ),
			__( 'CFMB', 'cfmb' ),
			$capability,
			self::SLUG,
			array( $this->builder, 'render' ),
			'dashicons-database',
			80
		);

		$pages = array(
			'cfmb'               => __( 'Field Groups', 'cfmb' ),
			'cfmb-post-types'    => __( 'Post Types', 'cfmb' ),
			'cfmb-taxonomies'    => __( 'Taxonomies', 'cfmb' ),
			'cfmb-options-pages' => __( 'Options Pages', 'cfmb' ),
			'cfmb-connections'   => __( 'Connections', 'cfmb' ),
			'cfmb-sync'          => __( 'Sync', 'cfmb' ),
			'cfmb-switch'        => __( 'Switch Assistant', 'cfmb' ),
		);

		foreach ( $pages as $slug => $title ) {
			$hook = add_submenu_page( self::SLUG, $title, $title, $capability, $slug, array( $this->builder, 'render' ) );

			if ( $hook ) {
				add_action( "load-{$hook}", array( $this->builder, 'load' ) );
			}
		}

		add_submenu_page(
			self::SLUG,
			__( 'CFMB Settings', 'cfmb' ),
			__( 'Settings', 'cfmb' ),
			$capability,
			Settings_Screen::SLUG,
			array( $this->settings, 'render' )
		);

		if ( ! Go_Pro_Screen::pro_active() ) {
			add_submenu_page(
				self::SLUG,
				__( 'CFMB Pro', 'cfmb' ),
				__( 'Go Pro', 'cfmb' ),
				$capability,
				Go_Pro_Screen::SLUG,
				array( $this->go_pro, 'render' )
			);
		}
	}
}
