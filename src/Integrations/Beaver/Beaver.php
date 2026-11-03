<?php
/**
 * Beaver Builder integration: Themer field connections and the Field Loop module.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Beaver;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Tag_Values;
use FLPageData;

defined( 'ABSPATH' ) || exit;

/**
 * Field connections (Beaver Themer) sit in a "CFMB" group, one per kind of
 * value: Field, URL, Image, Gallery, Number, Color and Date. They are added
 * for posts, archives and the site (options pages), each with Field, Read
 * from and Object settings.
 *
 * The Field Loop module (Beaver Builder, with or without Themer) repeats a
 * saved row or layout for each Repeater or Stacks row, gallery image or
 * linked post.
 *
 * Both hooks only fire when Beaver Builder (or Themer) is active.
 */
class Beaver {

	public const GROUP = 'cfmb';

	/**
	 * Connections: property => Themer types, Field_Catalog kind.
	 */
	private const PROPERTIES = array(
		'cfmb_field'   => array( array( 'string', 'html', 'custom_field' ), 'text' ),
		'cfmb_url'     => array( array( 'url' ), 'url' ),
		'cfmb_image'   => array( array( 'photo' ), 'image' ),
		'cfmb_gallery' => array( array( 'multiple-photos' ), 'gallery' ),
		'cfmb_number'  => array( array( 'string', 'custom_field' ), 'number' ),
		'cfmb_color'   => array( array( 'color' ), 'color' ),
		'cfmb_date'    => array( array( 'string', 'html' ), 'date' ),
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
		add_action( 'fl_page_data_add_properties', array( $this, 'add_properties' ) );
		add_action( 'init', array( $this, 'register_module' ), 20 );
	}

	/**
	 * Add the CFMB field connections.
	 *
	 * @return void
	 */
	public function add_properties(): void {
		if ( ! class_exists( 'FLPageData' ) ) {
			return;
		}

		FLPageData::add_group( self::GROUP, array( 'label' => __( 'CFMB', 'cfmb' ) ) );

		$labels = array(
			'cfmb_field'   => __( 'CFMB Field', 'cfmb' ),
			'cfmb_url'     => __( 'CFMB URL', 'cfmb' ),
			'cfmb_image'   => __( 'CFMB Image', 'cfmb' ),
			'cfmb_gallery' => __( 'CFMB Gallery', 'cfmb' ),
			'cfmb_number'  => __( 'CFMB Number', 'cfmb' ),
			'cfmb_color'   => __( 'CFMB Color', 'cfmb' ),
			'cfmb_date'    => __( 'CFMB Date', 'cfmb' ),
		);

		foreach ( self::PROPERTIES as $name => $property ) {
			$choices = $this->catalog->flat( $property[1] );

			if ( ! $choices ) {
				continue;
			}

			$args     = array(
				'label'  => $labels[ $name ],
				'group'  => self::GROUP,
				'type'   => $property[0],
				'getter' => array( $this, 'get_' . substr( $name, 5 ) ),
			);
			$settings = $this->settings_for( $property[1], $choices );

			foreach ( array( 'post', 'archive', 'site' ) as $where ) {
				$add    = 'add_' . $where . '_property';
				$fields = 'site' === $where ? $this->site_settings( $settings ) : $settings;

				if ( method_exists( 'FLPageData', $add ) ) {
					FLPageData::$add( $name, $args );
					FLPageData::{$add . '_settings_fields'}( $name, $fields );
				}
			}
		}
	}

	/**
	 * Register the Field Loop module.
	 *
	 * @return void
	 */
	public function register_module(): void {
		if ( ! class_exists( 'FLBuilder' ) || ! class_exists( 'FLBuilderModule' ) ) {
			return;
		}

		\FLBuilder::register_module( Field_Loop_Module::class, Field_Loop_Module::form() );
	}

	/**
	 * Field connection: formatted HTML.
	 *
	 * @param object $settings Connection settings.
	 * @return string
	 */
	public function get_field( $settings ): string {
		$s = $this->read( $settings );

		return $this->wrap( $this->values->html( $s['path'], $s['object'] ), $settings );
	}

	/**
	 * Field connection: URL.
	 *
	 * @param object $settings Connection settings.
	 * @return string
	 */
	public function get_url( $settings ): string {
		$s = $this->read( $settings );

		return esc_url_raw( $this->values->url( $s['path'], $s['object'], array( 'size' => $s['size'] ) ) );
	}

	/**
	 * Field connection: one image as Beaver expects it.
	 *
	 * @param object $settings Connection settings.
	 * @return array{id: int, url: string}|string
	 */
	public function get_image( $settings ) {
		$s     = $this->read( $settings );
		$image = $this->values->image( $s['path'], $s['object'], $s['size'] );

		return $image ? array(
			'id'  => $image['id'],
			'url' => $image['url'],
		) : '';
	}

	/**
	 * Field connection: attachment IDs.
	 *
	 * @param object $settings Connection settings.
	 * @return int[]
	 */
	public function get_gallery( $settings ): array {
		$s = $this->read( $settings );

		return $this->values->image_ids( $s['path'], $s['object'] );
	}

	/**
	 * Field connection: number.
	 *
	 * @param object $settings Connection settings.
	 * @return string
	 */
	public function get_number( $settings ): string {
		$s = $this->read( $settings );

		return $this->values->number( $s['path'], $s['object'] );
	}

	/**
	 * Field connection: color, without the leading # as Beaver color settings store it.
	 *
	 * @param object $settings Connection settings.
	 * @return string
	 */
	public function get_color( $settings ): string {
		$s     = $this->read( $settings );
		$color = $this->values->color( $s['path'], $s['object'] );

		if ( 1 === preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $color ) ) {
			return substr( $color, 1 );
		}

		return 1 === preg_match( '/^rgba?\([\d\s.,%]+\)$/i', $color ) ? $color : '';
	}

	/**
	 * Field connection: date.
	 *
	 * @param object $settings Connection settings.
	 * @return string
	 */
	public function get_date( $settings ): string {
		$s = $this->read( $settings );

		return $this->wrap( esc_html( $this->values->date( $s['path'], $s['object'], $s['format'] ) ), $settings );
	}

	/**
	 * Settings read from a connection.
	 *
	 * @param mixed $settings Connection settings object.
	 * @return array{path: string, object: string, size: string, format: string}
	 */
	private function read( $settings ): array {
		$s      = is_object( $settings ) ? get_object_vars( $settings ) : ( is_array( $settings ) ? $settings : array() );
		$source = (string) ( $s['cfmb_source'] ?? '' );

		return array(
			'path'   => (string) ( $s['cfmb_field'] ?? '' ),
			'object' => Field_Catalog::object_for( $source, (string) ( $s['cfmb_object'] ?? '' ) ),
			'size'   => sanitize_key( (string) ( $s['cfmb_size'] ?? '' ) ),
			'format' => (string) ( $s['cfmb_format'] ?? '' ),
		);
	}

	/**
	 * Add Before / After text around a non-empty value.
	 *
	 * @param string $html     Escaped HTML.
	 * @param mixed  $settings Connection settings.
	 * @return string
	 */
	private function wrap( string $html, $settings ): string {
		if ( '' === $html ) {
			return '';
		}

		$s = is_object( $settings ) ? get_object_vars( $settings ) : array();

		return esc_html( (string) ( $s['cfmb_before'] ?? '' ) ) . $html . esc_html( (string) ( $s['cfmb_after'] ?? '' ) );
	}

	/**
	 * Connection settings form.
	 *
	 * @param string                $kind    Field_Catalog kind.
	 * @param array<string, string> $choices Field choices.
	 * @return array<string, array<string, mixed>>
	 */
	private function settings_for( string $kind, array $choices ): array {
		$settings = array(
			'cfmb_field'  => array(
				'type'    => 'select',
				'label'   => __( 'Field', 'cfmb' ),
				'options' => $choices,
			),
			'cfmb_source' => array(
				'type'    => 'select',
				'label'   => __( 'Read from', 'cfmb' ),
				'default' => '',
				'options' => Field_Catalog::sources(),
				'toggle'  => array(
					'option' => array( 'fields' => array( 'cfmb_object' ) ),
					'custom' => array( 'fields' => array( 'cfmb_object' ) ),
				),
			),
			'cfmb_object' => array(
				'type'        => 'text',
				'label'       => __( 'Object', 'cfmb' ),
				'description' => __( 'Options page ID, or post_12, term_5, user_3.', 'cfmb' ),
			),
		);

		if ( in_array( $kind, array( 'text', 'date' ), true ) ) {
			$settings['cfmb_before'] = array(
				'type'  => 'text',
				'label' => __( 'Before', 'cfmb' ),
			);
			$settings['cfmb_after']  = array(
				'type'  => 'text',
				'label' => __( 'After', 'cfmb' ),
			);
		}

		if ( 'date' === $kind ) {
			$settings['cfmb_format'] = array(
				'type'        => 'text',
				'label'       => __( 'Date format', 'cfmb' ),
				'description' => __( 'A PHP date format. Leave empty for the field\'s own.', 'cfmb' ),
			);
		}

		if ( in_array( $kind, array( 'image', 'url' ), true ) ) {
			$settings['cfmb_size'] = array(
				'type'    => 'text',
				'label'   => __( 'Image size', 'cfmb' ),
				'default' => 'full',
			);
		}

		return $settings;
	}

	/**
	 * Site connections read options pages by default.
	 *
	 * @param array<string, array<string, mixed>> $settings Settings form.
	 * @return array<string, array<string, mixed>>
	 */
	private function site_settings( array $settings ): array {
		$settings['cfmb_source']['default'] = 'option';

		return $settings;
	}
}
