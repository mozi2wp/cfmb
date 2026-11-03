<?php
/**
 * Options page registry.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Holds options pages registered with cfmb_add_options_page(), with
 * cfmb_register_options_page(), or made under CFMB > Options Pages.
 * Admin\Options_Screen adds the menus and renders them.
 */
class Options_Pages {

	/**
	 * Pages by ID.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $pages = array();

	/**
	 * Register an options page.
	 *
	 * @param array<string, mixed> $args See cfmb_add_options_page().
	 * @return bool
	 */
	public function add( array $args ): bool {
		$id    = sanitize_key( (string) ( $args['id'] ?? Object_Ref::DEFAULT_OPTIONS_PAGE ) );
		$title = sanitize_text_field( (string) ( $args['page_title'] ?? '' ) );

		if ( '' === $id || '' === $title ) {
			_doing_it_wrong( 'cfmb_add_options_page', esc_html__( 'An options page needs an id and a page_title.', 'custom-fields-meta-box' ), '0.1.0' );
			return false;
		}

		if ( isset( $this->pages[ $id ] ) ) {
			/* translators: %s: options page ID */
			_doing_it_wrong( 'cfmb_add_options_page', esc_html( sprintf( __( 'An options page with the id "%s" is already registered.', 'custom-fields-meta-box' ), $id ) ), '0.1.0' );
			return false;
		}

		$this->pages[ $id ] = array(
			'id'          => $id,
			'page_title'  => $title,
			'menu_title'  => sanitize_text_field( (string) ( $args['menu_title'] ?? $title ) ),
			'parent_slug' => (string) ( $args['parent_slug'] ?? '' ),
			'capability'  => (string) ( $args['capability'] ?? 'manage_options' ),
			'icon_url'    => (string) ( $args['icon_url'] ?? 'dashicons-admin-generic' ),
			'position'    => isset( $args['position'] ) ? (int) $args['position'] : null,
			'menu_slug'   => 'cfmb-options-' . $id,
			'description' => sanitize_textarea_field( (string) ( $args['description'] ?? '' ) ),
			'button'      => sanitize_text_field( (string) ( $args['button_label'] ?? '' ) ),
			// Set for pages defined under CFMB > Options Pages (or synced from a file); empty for code.
			'cfmb_key'    => (string) ( $args['cfmb_key'] ?? '' ),
		);

		return true;
	}

	/**
	 * All pages, by ID.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all(): array {
		return $this->pages;
	}

	/**
	 * One page.
	 *
	 * @param string $id Page ID.
	 * @return array<string, mixed>|null
	 */
	public function get( string $id ): ?array {
		return $this->pages[ $id ] ?? null;
	}
}
