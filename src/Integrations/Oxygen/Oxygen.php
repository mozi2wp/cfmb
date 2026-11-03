<?php
/**
 * Oxygen Classic integration: dynamic data and the Field Loop element.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Oxygen;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Signature;
use CFMB\Integrations\Builders\Tag_Values;

defined( 'ABSPATH' ) || exit;

/**
 * For Oxygen 4 and earlier ("Oxygen Classic"). Oxygen 6 runs on the
 * Breakdance engine and is covered by the Breakdance integration.
 *
 * Adds CFMB Field, CFMB URL and CFMB Image to Insert Dynamic Data, each with
 * Field, Read from and Object settings. The Field Loop element repeats its
 * child elements for each Repeater or Stacks row, gallery image or linked
 * post.
 */
class Oxygen {

	/**
	 * Entries: data key => Oxygen mode, Field_Catalog kind.
	 */
	private const ENTRIES = array(
		'cfmb_field' => array( 'content', 'text' ),
		'cfmb_url'   => array( 'link', 'url' ),
		'cfmb_image' => array( 'image', 'image' ),
	);

	/**
	 * Methods Field_Loop_Element declares, for Signature::fits().
	 */
	private const ELEMENT_METHODS = array(
		'init'         => '',
		'name'         => '',
		'slug'         => '',
		'button_place' => '',
		'controls'     => '',
		'render'       => '',
	);

	/**
	 * Field choices.
	 *
	 * @var Field_Catalog
	 */
	private Field_Catalog $catalog;

	/**
	 * Values.
	 *
	 * @var Tag_Values
	 */
	private Tag_Values $values;

	/**
	 * Constructor.
	 *
	 * @param Field_Catalog $catalog Field choices.
	 * @param Tag_Values    $values  Values.
	 */
	public function __construct( Field_Catalog $catalog, Tag_Values $values ) {
		$this->catalog = $catalog;
		$this->values  = $values;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'oxygen_custom_dynamic_data', array( $this, 'add_dynamic_data' ) );
		add_action( 'init', array( $this, 'register_element' ), 5 );
	}

	/**
	 * Add the CFMB entries to Insert Dynamic Data.
	 *
	 * @param mixed $dynamic_data Entries.
	 * @return mixed
	 */
	public function add_dynamic_data( $dynamic_data ) {
		if ( ! is_array( $dynamic_data ) ) {
			return $dynamic_data;
		}

		$labels = array(
			'cfmb_field' => __( 'CFMB Field', 'custom-fields-meta-box' ),
			'cfmb_url'   => __( 'CFMB URL', 'custom-fields-meta-box' ),
			'cfmb_image' => __( 'CFMB Image', 'custom-fields-meta-box' ),
		);

		foreach ( self::ENTRIES as $data => $entry ) {
			$choices = $this->catalog->flat( $entry[1] );

			if ( ! $choices ) {
				continue;
			}

			$dynamic_data[] = array(
				'name'       => $labels[ $data ],
				'mode'       => $entry[0],
				'position'   => __( 'CFMB', 'custom-fields-meta-box' ),
				'data'       => $data,
				'handler'    => array( $this, 'handle_' . substr( $data, 5 ) ),
				'properties' => $this->properties( $entry[1], $choices ),
			);
		}

		return $dynamic_data;
	}

	/**
	 * Register the Field Loop element.
	 *
	 * @return void
	 */
	public function register_element(): void {
		if ( class_exists( 'OxyEl' ) && Signature::fits( 'OxyEl', self::ELEMENT_METHODS ) ) {
			new Field_Loop_Element();
		}
	}

	/**
	 * Value of CFMB Field: formatted HTML, or a date in the format given.
	 *
	 * @param mixed $atts Property values.
	 * @return string
	 */
	public function handle_field( $atts ): string {
		$a = $this->read( $atts );

		if ( '' !== $a['format'] ) {
			$date = $this->values->date( $a['path'], $a['object'], $a['format'] );

			if ( '' !== $date ) {
				return esc_html( $date );
			}
		}

		return $this->values->html( $a['path'], $a['object'] );
	}

	/**
	 * Value of CFMB URL.
	 *
	 * @param mixed $atts Property values.
	 * @return string
	 */
	public function handle_url( $atts ): string {
		$a = $this->read( $atts );

		return esc_url( $this->values->url( $a['path'], $a['object'], array( 'size' => $a['size'] ) ) );
	}

	/**
	 * Value of CFMB Image: the image URL.
	 *
	 * @param mixed $atts Property values.
	 * @return string
	 */
	public function handle_image( $atts ): string {
		$a     = $this->read( $atts );
		$image = $this->values->image( $a['path'], $a['object'], $a['size'] );

		return $image ? esc_url( $image['url'] ) : '';
	}

	/**
	 * Property values.
	 *
	 * @param mixed $atts Raw values.
	 * @return array{path: string, object: string, size: string, format: string}
	 */
	private function read( $atts ): array {
		$atts = is_array( $atts ) ? $atts : array();

		return array(
			'path'   => (string) ( $atts['field'] ?? '' ),
			'object' => Field_Catalog::object_for( (string) ( $atts['source'] ?? '' ), (string) ( $atts['object'] ?? '' ) ),
			'size'   => sanitize_key( (string) ( $atts['size'] ?? '' ) ),
			'format' => (string) ( $atts['format'] ?? '' ),
		);
	}

	/**
	 * Settings shown for an entry.
	 *
	 * @param string                $kind    Field_Catalog kind.
	 * @param array<string, string> $choices Field choices.
	 * @return array<int, array<string, mixed>>
	 */
	private function properties( string $kind, array $choices ): array {
		$properties = array(
			array(
				'name'    => __( 'Field', 'custom-fields-meta-box' ),
				'data'    => 'field',
				'type'    => 'select',
				'options' => $choices,
				'nullval' => '',
			),
			array(
				'name'    => __( 'Read from', 'custom-fields-meta-box' ),
				'data'    => 'source',
				'type'    => 'select',
				'options' => Field_Catalog::sources(),
				'nullval' => '',
			),
			array(
				'name' => __( 'Object (options page ID, post_12, term_5, user_3)', 'custom-fields-meta-box' ),
				'data' => 'object',
				'type' => 'text',
			),
		);

		if ( 'text' === $kind ) {
			$properties[] = array(
				'name' => __( 'Date format for date fields (PHP)', 'custom-fields-meta-box' ),
				'data' => 'format',
				'type' => 'text',
			);
		}

		if ( 'text' !== $kind ) {
			$properties[] = array(
				'name' => __( 'Image size', 'custom-fields-meta-box' ),
				'data' => 'size',
				'type' => 'text',
			);
		}

		return $properties;
	}
}
