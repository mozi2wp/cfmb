<?php
/**
 * Options page menus and screens.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Content\Options_Pages;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Group;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Adds menus for registered options pages and prints their forms.
 */
class Options_Screen {

	/**
	 * Registered pages.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Group output.
	 *
	 * @var Group_Renderer
	 */
	private Group_Renderer $renderer;

	/**
	 * Page ID by admin hook suffix.
	 *
	 * @var array<string, string>
	 */
	private array $hooks = array();

	/**
	 * Groups for the page being viewed.
	 *
	 * @var Group[]
	 */
	private array $groups = array();

	/**
	 * Constructor.
	 *
	 * @param Options_Pages  $pages    Registered pages.
	 * @param Matcher        $matcher  Location matcher.
	 * @param Group_Renderer $renderer Group output.
	 */
	public function __construct( Options_Pages $pages, Matcher $matcher, Group_Renderer $renderer ) {
		$this->pages    = $pages;
		$this->matcher  = $matcher;
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Late, so parent menus from other plugins already exist.
		add_action( 'admin_menu', array( $this, 'add_menus' ), 99 );
	}

	/**
	 * Add a menu item per options page.
	 *
	 * @return void
	 */
	public function add_menus(): void {
		foreach ( $this->pages->all() as $id => $page ) {
			if ( '' !== $page['parent_slug'] ) {
				$hook = add_submenu_page( $page['parent_slug'], $page['page_title'], $page['menu_title'], $page['capability'], $page['menu_slug'], array( $this, 'render' ), $page['position'] );
			} else {
				$hook = add_menu_page( $page['page_title'], $page['menu_title'], $page['capability'], $page['menu_slug'], array( $this, 'render' ), $page['icon_url'], $page['position'] );
			}

			if ( $hook ) {
				$this->hooks[ $hook ] = $id;
				add_action( "load-{$hook}", array( $this, 'prepare' ) );
			}
		}
	}

	/**
	 * Find the groups for the page being loaded and enqueue assets.
	 *
	 * @return void
	 */
	public function prepare(): void {
		$id = $this->current_page_id();

		if ( '' === $id ) {
			return;
		}

		$this->groups = $this->matcher->groups_for( Context::for_options_page( $id ) );
		$this->renderer->enqueue( $this->groups );
	}

	/**
	 * Print the options page.
	 *
	 * @return void
	 */
	public function render(): void {
		$id   = $this->current_page_id();
		$page = $this->pages->get( $id );

		if ( ! $page ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::OPTION, 0, $id );

		echo '<div class="wrap cfmb-options-page">';
		printf( '<h1>%s</h1>', esc_html( $page['page_title'] ) );

		if ( '' !== (string) ( $page['description'] ?? '' ) ) {
			printf( '<p class="cfmb-options-page__description">%s</p>', esc_html( (string) $page['description'] ) );
		}

		// Set by Form_Saver after a nonce-checked save.
		if ( isset( $_GET['cfmb-updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html__( 'Changes saved.', 'cfmb' ) );
		}

		if ( isset( $_GET['cfmb-errors'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Some fields were not saved. Check the messages below each field.', 'cfmb' ) );
		}

		if ( ! $this->groups ) {
			$this->render_empty_state( $id );
			echo '</div>';
			return;
		}

		printf( '<form method="post" action="%s" class="cfmb-options-page__form">', esc_url( admin_url( 'admin-post.php' ) ) );
		echo '<input type="hidden" name="action" value="cfmb_save_options" />';
		printf( '<input type="hidden" name="cfmb_options_page" value="%s" />', esc_attr( $id ) );

		foreach ( $this->groups as $group ) {
			echo '<div class="postbox cfmb-options-page__box">';
			printf( '<div class="postbox-header"><h2 class="hndle">%s</h2></div>', esc_html( $group->title() ) );
			echo '<div class="inside">';
			$this->renderer->render( $group, $ref, 'box' );
			echo '</div></div>';
		}

		submit_button( '' !== (string) ( $page['button'] ?? '' ) ? (string) $page['button'] : __( 'Save changes', 'cfmb' ) );
		echo '</form></div>';
	}

	/**
	 * Explain how to put fields on an empty options page.
	 *
	 * @param string $id Page ID.
	 * @return void
	 */
	private function render_empty_state( string $id ): void {
		echo '<div class="cfmb-empty-state">';
		printf( '<p>%s</p>', esc_html__( 'No fields are on this page yet.', 'cfmb' ) );

		if ( current_user_can( cfmb_admin_capability() ) ) {
			printf(
				'<p>%1$s <a href="%2$s">%3$s</a></p>',
				esc_html(
					sprintf(
						/* translators: %s: options page ID */
						__( 'Create a field group and set its location to "Options page is %s".', 'cfmb' ),
						$id
					)
				),
				esc_url( admin_url( 'admin.php?page=cfmb&group=new&location=options_page:' . rawurlencode( $id ) ) ),
				esc_html__( 'Add a field group', 'cfmb' )
			);
		}

		echo '</div>';
	}

	/**
	 * Options page ID for the current request.
	 *
	 * @return string
	 */
	private function current_page_id(): string {
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen context.

		foreach ( $this->pages->all() as $id => $page ) {
			if ( $page['menu_slug'] === $slug ) {
				return $id;
			}
		}

		return '';
	}
}
