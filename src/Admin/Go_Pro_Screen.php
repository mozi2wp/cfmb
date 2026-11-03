<?php
/**
 * The one "Go Pro" page.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * A plain list of what CFMB Pro adds, under CFMB > Go Pro. It loads nothing
 * from outside the site, shows no notices elsewhere, and disappears when
 * CFMB Pro is active. Nothing in CFMB is locked or limited without Pro.
 */
class Go_Pro_Screen {

	public const SLUG = 'cfmb-go-pro';

	public const URL = 'https://mozi2wp.com/cfmb-pro/';

	/**
	 * Whether CFMB Pro is active.
	 *
	 * @return bool
	 */
	public static function pro_active(): bool {
		return defined( 'CFMB_PRO_VERSION' );
	}

	/**
	 * Print the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$sections = array(
			__( 'Storage and safety', 'custom-fields-meta-box' ) => array(
				__( 'Table Storage: values in typed, indexed tables, Repeater rows included, so queries stay fast on large sites.', 'custom-fields-meta-box' ),
				__( 'Safe Rename: rename or retype a field and its data moves with it.', 'custom-fields-meta-box' ),
				__( 'Field Inspector: see where each field is used and find orphaned data.', 'custom-fields-meta-box' ),
				__( 'Field History: a change log per field, with diffs and single-field restore.', 'custom-fields-meta-box' ),
				__( 'Field Access: who can view and edit each field, and what the REST API shows.', 'custom-fields-meta-box' ),
			),
			__( 'Query and display', 'custom-fields-meta-box' ) => array(
				__( 'Query Studio: build queries over posts, terms, users, Connections and tables without code.', 'custom-fields-meta-box' ),
				__( 'Loop Designer: visual listing templates that output core blocks.', 'custom-fields-meta-box' ),
				__( 'Field Blocks built visually, without PHP templates.', 'custom-fields-meta-box' ),
				__( 'Block Bindings for repeaters, galleries and Connections, and loops in the shortcode.', 'custom-fields-meta-box' ),
			),
			__( 'Connections', 'custom-fields-meta-box' ) => array(
				__( 'Fields on the link itself, such as a role or a date.', 'custom-fields-meta-box' ),
				__( 'Front-end controls and automatic cleanup when connected items are deleted.', 'custom-fields-meta-box' ),
			),
			__( 'Front end', 'custom-fields-meta-box' )   => array(
				__( 'Front Forms: create and edit content from the site, with every field type.', 'custom-fields-meta-box' ),
				__( 'Member Pages: registration, account area and profile editing.', 'custom-fields-meta-box' ),
				__( 'Collections: favorites, compare lists and recently viewed.', 'custom-fields-meta-box' ),
			),
			__( 'More field types', 'custom-fields-meta-box' ) => array(
				__( 'Formula, unique value and auto-increment ID.', 'custom-fields-meta-box' ),
				__( 'Money, units, phone with country, address with geocoding.', 'custom-fields-meta-box' ),
				__( 'Date range and recurring dates.', 'custom-fields-meta-box' ),
				__( 'Dependent selects and choices from a query or REST source.', 'custom-fields-meta-box' ),
				__( 'Table, JSON and code editor, rating and signature.', 'custom-fields-meta-box' ),
			),
			__( 'Bulk work and switching', 'custom-fields-meta-box' ) => array(
				__( 'Grid Edit: spreadsheet-style editing in the admin.', 'custom-fields-meta-box' ),
				__( 'CSV import and export of content, Repeaters and Connections included.', 'custom-fields-meta-box' ),
				__( 'Switch Assistant for Meta Box, JetEngine, Pods and Toolset.', 'custom-fields-meta-box' ),
			),
			__( 'Developers and AI', 'custom-fields-meta-box' ) => array(
				__( 'PHP stubs and TypeScript types generated from your schema.', 'custom-fields-meta-box' ),
				__( 'A typed GraphQL and REST schema.', 'custom-fields-meta-box' ),
				__( 'Schema Assistant: describe what you need and get a Field Group.', 'custom-fields-meta-box' ),
				__( 'Abilities API and MCP access.', 'custom-fields-meta-box' ),
			),
		);

		echo '<div class="wrap cfmb-go-pro">';
		printf( '<h1>%s</h1>', esc_html__( 'CFMB Pro', 'custom-fields-meta-box' ) );
		printf(
			'<p class="cfmb-go-pro__intro">%s</p>',
			esc_html__( 'Everything in CFMB stays free and complete. CFMB Pro is a separate add-on for sites that need more from their data.', 'custom-fields-meta-box' )
		);

		foreach ( $sections as $title => $items ) {
			printf( '<h2>%s</h2><ul class="ul-disc">', esc_html( $title ) );

			foreach ( $items as $item ) {
				printf( '<li>%s</li>', esc_html( $item ) );
			}

			echo '</ul>';
		}

		printf(
			'<p><a class="button button-primary" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s<span class="screen-reader-text"> %3$s</span></a></p>',
			esc_url( self::URL ),
			esc_html__( 'See CFMB Pro', 'custom-fields-meta-box' ),
			esc_html__( '(opens in a new tab)', 'custom-fields-meta-box' )
		);
		echo '</div>';
	}
}
