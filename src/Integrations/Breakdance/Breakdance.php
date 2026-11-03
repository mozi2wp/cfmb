<?php
/**
 * Breakdance and Oxygen 6 integration: dynamic data fields and Field Loops.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Signature;
use CFMB\Integrations\Builders\Tag_Values;

defined( 'ABSPATH' ) || exit;

/**
 * Oxygen 6 runs on the Breakdance engine, so one registration covers both.
 *
 * Each CFMB field is listed under "CFMB" with its Field Group as
 * subcategory, once per kind of value it can give: text, URL, image,
 * gallery. Loopable fields (Repeater, Stacks, Gallery, Post, Relationship)
 * are also listed as repeaters for the Repeater Fields element; inside it,
 * pick "Items › Title" or a "Loop item" entry.
 *
 * Field classes extend Breakdance's own and are only loaded when
 * Signature::fits() confirms the parent class matches what they declare.
 */
class Breakdance {

	/**
	 * Kind => CFMB class, Breakdance parent class, methods the class declares.
	 */
	private const CLASSES = array(
		'text'    => array(
			Fields\Text_Field::class,
			'Breakdance\DynamicData\StringField',
			array(
				'handler' => 'Breakdance\DynamicData\StringData',
			),
		),
		'url'     => array(
			Fields\Url_Field::class,
			'Breakdance\DynamicData\StringField',
			array(
				'returnTypes' => 'array',
				'handler'     => 'Breakdance\DynamicData\StringData',
			),
		),
		'image'   => array(
			Fields\Image_Field::class,
			'Breakdance\DynamicData\ImageField',
			array(
				'handler' => 'Breakdance\DynamicData\ImageData',
			),
		),
		'gallery' => array(
			Fields\Gallery_Field::class,
			'Breakdance\DynamicData\GalleryField',
			array(
				'handler' => 'Breakdance\DynamicData\GalleryData',
			),
		),
		'loop'    => array(
			Fields\Loop_Field::class,
			'Breakdance\DynamicData\RepeaterField',
			array(
				'handler'          => 'Breakdance\DynamicData\RepeaterData',
				'hasSubFields'     => '',
				'setSubFieldIndex' => '',
				'parentField'      => '',
			),
		),
	);

	/**
	 * Methods every field class declares through Field_Config.
	 */
	private const SHARED = array(
		'label'       => 'string',
		'category'    => 'string',
		'subcategory' => 'string',
		'slug'        => 'string',
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
	 * Loop fields registered, closed after the page renders.
	 *
	 * @var Fields\Loop_Field[]
	 */
	private array $loops = array();

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
		add_action( 'init', array( $this, 'register_fields' ), 20 );
		add_action( 'wp_footer', array( $this, 'close_loops' ), 0 );
	}

	/**
	 * Register the fields with Breakdance.
	 *
	 * @return void
	 */
	public function register_fields(): void {
		if ( ! function_exists( 'Breakdance\DynamicData\registerField' ) ) {
			return;
		}

		foreach ( self::CLASSES as $kind => $spec ) {
			if ( ! Signature::fits( $spec[1], self::SHARED + $spec[2] ) ) {
				continue;
			}

			$class = $spec[0];

			foreach ( $this->entries( $kind ) as $config ) {
				$field = new $class( $config, $this->values );

				if ( $field instanceof Fields\Loop_Field ) {
					$this->loops[] = $field;
				}

				\Breakdance\DynamicData\registerField( $field );
			}
		}
	}

	/**
	 * Close any loop item a Repeater Fields element left open.
	 *
	 * @return void
	 */
	public function close_loops(): void {
		foreach ( $this->loops as $loop ) {
			$loop->close();
		}
	}

	/**
	 * Field setups for a kind.
	 *
	 * @param string $kind Field_Catalog kind.
	 * @return array<int, array{path: string, label: string, group: string, object: string, slug: string}>
	 */
	public function entries( string $kind ): array {
		$entries = array();

		foreach ( $this->catalog->grouped( $kind ) as $section ) {
			if ( 'loop' === $kind && $this->catalog->is_item_section( $section ) ) {
				continue;
			}

			$object = (string) ( $section['object'] ?? '' );

			foreach ( $section['options'] as $path => $label ) {
				$path      = (string) $path;
				$entries[] = array(
					'path'   => $path,
					'label'  => (string) $label,
					'group'  => (string) $section['label'],
					'object' => $object,
					'slug'   => self::slug( $kind, $path, $object ),
				);
			}
		}

		return $entries;
	}

	/**
	 * Unique slug for a field entry, e.g. cfmb_text_items__title.
	 *
	 * @param string $kind   Kind.
	 * @param string $path   Path.
	 * @param string $object Object argument.
	 * @return string
	 */
	public static function slug( string $kind, string $path, string $object = '' ): string {
		$path = str_replace( array( '.', '@', '/', '-' ), array( '__', 'item_', '__', '_' ), $path );
		$slug = 'cfmb_' . $kind . '_' . $path;

		return '' !== $object ? $slug . '_' . str_replace( ':', '_', sanitize_key( str_replace( ':', '-', $object ) ) ) : $slug;
	}
}
