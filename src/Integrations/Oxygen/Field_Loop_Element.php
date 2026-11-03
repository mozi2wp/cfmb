<?php
/**
 * Oxygen Classic element: Field Loop.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Oxygen;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Loop_Items;
use CFMB\Integrations\Builders\Loop_Markup;
use OxyEl;

defined( 'ABSPATH' ) || exit;

/**
 * Elements placed inside it are repeated for each row of a Repeater or
 * Stacks field, each image of a Gallery, or each post of a Post or
 * Relationship field. CFMB dynamic data inside reads the current item.
 *
 * Only loaded from Oxygen::register_element(), after OxyEl exists.
 */
class Field_Loop_Element extends OxyEl {

	/**
	 * Allow child elements.
	 *
	 * @return void
	 */
	public function init() {
		if ( method_exists( $this, 'enableNesting' ) ) {
			$this->enableNesting();
		}
	}

	/**
	 * Element name.
	 *
	 * @return string
	 */
	public function name() {
		return __( 'Field Loop', 'cfmb' );
	}

	/**
	 * Element slug.
	 *
	 * @return string
	 */
	public function slug() {
		return 'cfmb-field-loop';
	}

	/**
	 * Where the button sits in the +Add panel.
	 *
	 * @return string
	 */
	public function button_place() {
		return 'dynamic';
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	public function controls() {
		$sources = array();
		foreach ( Field_Catalog::sources() as $value => $label ) {
			$sources[ '' === $value ? 'current' : $value ] = $label;
		}

		$this->addOptionControl(
			array(
				'type' => 'dropdown',
				'name' => __( 'Loop over', 'cfmb' ),
				'slug' => 'cfmb_field',
			)
		)->setValue( cfmb()->get( Field_Catalog::class )->flat( 'loop' ) );

		$this->addOptionControl(
			array(
				'type'    => 'dropdown',
				'name'    => __( 'Read from', 'cfmb' ),
				'slug'    => 'cfmb_source',
				'default' => 'current',
			)
		)->setValue( $sources );

		foreach ( array(
			'cfmb_object'  => __( 'Object (options page ID, post_12, term_5, user_3)', 'cfmb' ),
			'cfmb_layouts' => __( 'Only these Stacks layouts (comma separated)', 'cfmb' ),
			'cfmb_limit'   => __( 'Number of items (0 for all)', 'cfmb' ),
			'cfmb_offset'  => __( 'Skip first', 'cfmb' ),
			'cfmb_empty'   => __( 'Text when there are no items', 'cfmb' ),
			'cfmb_columns' => __( 'Columns (1-6)', 'cfmb' ),
			'cfmb_gap'     => __( 'Gap (CSS length)', 'cfmb' ),
		) as $slug => $label ) {
			$this->addOptionControl(
				array(
					'type' => 'textfield',
					'name' => $label,
					'slug' => $slug,
				)
			);
		}
	}

	/**
	 * Output.
	 *
	 * @param mixed $options  Settings.
	 * @param mixed $defaults Defaults.
	 * @param mixed $content  Child elements as shortcodes.
	 * @return void
	 */
	public function render( $options, $defaults, $content ) {
		$o       = is_array( $options ) ? $options : array();
		$source  = (string) ( $o['cfmb_source'] ?? 'current' );
		$content = is_string( $content ) ? $content : '';
		$editing = isset( $_GET['ct_builder'] ) || ( defined( 'OXY_ELEMENTS_API_AJAX' ) && OXY_ELEMENTS_API_AJAX ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides whether to show an editor hint.
		$loops   = cfmb()->get( Loop_Items::class );
		$items   = $loops->collect(
			(string) ( $o['cfmb_field'] ?? '' ),
			Field_Catalog::object_for( 'current' === $source ? '' : $source, (string) ( $o['cfmb_object'] ?? '' ) ),
			0,
			array(
				'layout' => (string) ( $o['cfmb_layouts'] ?? '' ),
				'limit'  => (int) ( $o['cfmb_limit'] ?? 0 ),
				'offset' => (int) ( $o['cfmb_offset'] ?? 0 ),
			)
		);

		if ( ! $items ) {
			echo Loop_Markup::empty( (string) ( $o['cfmb_empty'] ?? '' ), $editing ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Loop_Markup.

			return;
		}

		$html = $loops->render(
			$items,
			static fn(): string => Loop_Markup::item( do_shortcode( $content ) )
		);

		echo Loop_Markup::grid( $html, (int) ( $o['cfmb_columns'] ?? 1 ), (string) ( $o['cfmb_gap'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper escaped in Loop_Markup; children rendered by Oxygen.
	}
}
