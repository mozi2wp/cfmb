<?php
/**
 * Divi integration: dynamic content (Divi 4 and Divi 5) and the Field Loop module.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Divi;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Tag_Values;

defined( 'ABSPATH' ) || exit;

/**
 * Adds four entries to Divi's dynamic content list, under "CFMB": Field
 * (text), Date, URL and Image. Each has Field, Read from and Object settings.
 *
 * Divi 4 and Divi 5 use different filters with the same option shape; both
 * are added, and each only runs on its own version. Filters only fire when
 * Divi is active.
 */
class Divi {

	/**
	 * Entries: name => Divi type, Field_Catalog kind.
	 */
	private const ENTRIES = array(
		'cfmb_text'  => array( 'text', 'text' ),
		'cfmb_date'  => array( 'text', 'date' ),
		'cfmb_url'   => array( 'url', 'url' ),
		'cfmb_image' => array( 'image', 'image' ),
	);

	/**
	 * Divi selects can't hold an empty value, so "current post" travels as this.
	 */
	private const CURRENT = 'current';

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
		// Divi 4.
		add_filter( 'et_builder_custom_dynamic_content_fields', array( $this, 'add_options' ), 10, 2 );
		add_filter( 'et_builder_resolve_dynamic_content', array( $this, 'resolve_d4' ), 10, 4 );
		add_action( 'et_builder_ready', array( $this, 'register_module' ) );

		// Divi 5.
		add_filter( 'divi_module_dynamic_content_options', array( $this, 'add_options' ), 10, 2 );
		add_filter( 'divi_module_dynamic_content_resolved_value', array( $this, 'resolve_d5' ), 10, 2 );
	}

	/**
	 * Add the CFMB entries to the dynamic content list.
	 *
	 * @param mixed $options Existing options.
	 * @param mixed $post_id Post being edited.
	 * @return mixed
	 */
	public function add_options( $options, $post_id = 0 ) {
		if ( ! is_array( $options ) ) {
			return $options;
		}

		$labels = array(
			'cfmb_text'  => __( 'CFMB Field', 'custom-fields-meta-box' ),
			'cfmb_date'  => __( 'CFMB Date', 'custom-fields-meta-box' ),
			'cfmb_url'   => __( 'CFMB URL', 'custom-fields-meta-box' ),
			'cfmb_image' => __( 'CFMB Image', 'custom-fields-meta-box' ),
		);

		foreach ( self::ENTRIES as $name => $entry ) {
			$choices = $this->catalog->flat( $entry[1] );

			if ( ! $choices ) {
				continue;
			}

			$options[ $name ] = array(
				'id'     => $name,
				'label'  => $labels[ $name ],
				'type'   => $entry[0],
				'custom' => false,
				'group'  => __( 'CFMB', 'custom-fields-meta-box' ),
				'fields' => $this->settings_for( $entry, $choices ),
			);
		}

		return $options;
	}

	/**
	 * Divi 4: value for a CFMB entry.
	 *
	 * @param mixed  $content  Value so far.
	 * @param string $name     Option name.
	 * @param mixed  $settings Option settings.
	 * @param mixed  $post_id  Post being rendered.
	 * @return mixed
	 */
	public function resolve_d4( $content, $name, $settings = array(), $post_id = 0 ) {
		if ( ! is_string( $name ) || ! isset( self::ENTRIES[ $name ] ) ) {
			return $content;
		}

		return $this->value( $name, is_array( $settings ) ? $settings : array(), (int) $post_id );
	}

	/**
	 * Divi 5: value for a CFMB entry.
	 *
	 * @param mixed $value Value so far.
	 * @param mixed $args  name, settings, post_id, context, overrides, is_content.
	 * @return mixed
	 */
	public function resolve_d5( $value, $args = array() ) {
		$args = is_array( $args ) ? $args : array();
		$name = (string) ( $args['name'] ?? '' );

		if ( ! isset( self::ENTRIES[ $name ] ) ) {
			return $value;
		}

		return $this->value( $name, is_array( $args['settings'] ?? null ) ? $args['settings'] : array(), (int) ( $args['post_id'] ?? 0 ) );
	}

	/**
	 * Register the Field Loop module once Divi's module API is loaded.
	 *
	 * @return void
	 */
	public function register_module(): void {
		if ( class_exists( 'ET_Builder_Module' ) ) {
			new Field_Loop_Module();
		}
	}

	/**
	 * The value Divi shows.
	 *
	 * @param string               $name     Entry name.
	 * @param array<string, mixed> $settings Entry settings.
	 * @param int                  $post_id  Post being rendered.
	 * @return string Escaped for output.
	 */
	private function value( string $name, array $settings, int $post_id ): string {
		$path   = (string) ( $settings['cfmb_field'] ?? '' );
		$source = (string) ( $settings['cfmb_source'] ?? self::CURRENT );
		$object = Field_Catalog::object_for( self::CURRENT === $source ? '' : $source, (string) ( $settings['cfmb_object'] ?? '' ) );

		switch ( $name ) {
			case 'cfmb_url':
				return esc_url( $this->values->url( $path, $object, array(), $post_id ) );

			case 'cfmb_image':
				$image = $this->values->image( $path, $object, (string) ( $settings['cfmb_size'] ?? '' ), $post_id );

				return $image ? esc_url( $image['url'] ) : '';

			case 'cfmb_date':
				$html = esc_html( $this->values->date( $path, $object, (string) ( $settings['cfmb_format'] ?? '' ), $post_id ) );
				break;

			default:
				$html = $this->values->html( $path, $object, array(), $post_id );
		}

		if ( '' === $html ) {
			return '';
		}

		return esc_html( (string) ( $settings['before'] ?? '' ) ) . $html . esc_html( (string) ( $settings['after'] ?? '' ) );
	}

	/**
	 * Settings shown for an entry.
	 *
	 * @param array{0: string, 1: string} $entry   Divi type and kind.
	 * @param array<string, string>       $choices Field choices.
	 * @return array<string, array<string, mixed>>
	 */
	private function settings_for( array $entry, array $choices ): array {
		$sources = array();
		foreach ( Field_Catalog::sources() as $value => $label ) {
			$sources[ '' === $value ? self::CURRENT : $value ] = $label;
		}

		$settings = array();

		if ( 'text' === $entry[0] ) {
			$settings['before'] = array(
				'label'   => __( 'Before', 'custom-fields-meta-box' ),
				'type'    => 'text',
				'default' => '',
			);
			$settings['after']  = array(
				'label'   => __( 'After', 'custom-fields-meta-box' ),
				'type'    => 'text',
				'default' => '',
			);
		}

		$settings['cfmb_field']  = array(
			'label'   => __( 'Field', 'custom-fields-meta-box' ),
			'type'    => 'select',
			'options' => $choices,
			'default' => (string) array_key_first( $choices ),
		);
		$settings['cfmb_source'] = array(
			'label'   => __( 'Read from', 'custom-fields-meta-box' ),
			'type'    => 'select',
			'options' => $sources,
			'default' => self::CURRENT,
		);
		$settings['cfmb_object'] = array(
			'label'   => __( 'Object (options page ID, post_12, term_5, user_3)', 'custom-fields-meta-box' ),
			'type'    => 'text',
			'default' => '',
		);

		if ( 'date' === $entry[1] ) {
			$settings['cfmb_format'] = array(
				'label'   => __( 'Date format (PHP, empty for the field\'s own)', 'custom-fields-meta-box' ),
				'type'    => 'text',
				'default' => '',
			);
		}

		if ( 'image' === $entry[0] ) {
			$settings['cfmb_size'] = array(
				'label'   => __( 'Image size', 'custom-fields-meta-box' ),
				'type'    => 'text',
				'default' => 'full',
			);
		}

		return $settings;
	}
}
