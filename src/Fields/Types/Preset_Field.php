<?php
/**
 * Preset field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;
use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * Inserts the fields of another Field Group and keeps them in sync with it.
 *
 * Presets are expanded when groups load (see Preset_Resolver), so on edit
 * screens and in templates the inserted fields behave like any other. This
 * type is only seen when the source group is missing.
 */
class Preset_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'preset';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Preset', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'structure';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'source', __( 'Field group', 'cfmb' ), 'preset_source', '' ),
			$this->setting(
				'fields',
				__( 'Fields to insert', 'cfmb' ),
				'preset_fields',
				array(),
				array( 'help' => __( 'Leave all unchecked to insert every field, including ones added to the group later.', 'cfmb' ) )
			),
			$this->setting(
				'naming',
				__( 'Names', 'cfmb' ),
				'select',
				'plain',
				array(
					'options' => array(
						'plain'    => __( 'Plain: values saved as title, image', 'cfmb' ),
						'prefixed' => __( 'Prefixed: values saved as {name}_title, {name}_image', 'cfmb' ),
					),
					'help'    => __( 'Use prefixed names to insert the same preset more than once.', 'cfmb' ),
				)
			),
		);
	}

	/**
	 * Clean settings: a valid group key and a list of field keys.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array {
		$source = is_scalar( $settings['source'] ?? null ) ? (string) $settings['source'] : '';
		$fields = array_filter(
			array_map( static fn( $key ) => is_scalar( $key ) ? (string) $key : '', (array) ( $settings['fields'] ?? array() ) ),
			static fn( $key ) => Key_Generator::is_valid( $key, Key_Generator::FIELD_PREFIX )
		);

		return array(
			'source' => Key_Generator::is_valid( $source, Key_Generator::GROUP_PREFIX ) ? $source : '',
			'fields' => array_values( array_unique( $fields ) ),
			'naming' => 'prefixed' === ( $settings['naming'] ?? '' ) ? 'prefixed' : 'plain',
		);
	}

	/**
	 * Whether a preset with these settings needs its own name: prefixed ones
	 * store their values under it.
	 *
	 * @param string               $type     Field type key.
	 * @param array<string, mixed> $settings Sanitized settings.
	 * @return bool
	 */
	public static function needs_name( string $type, array $settings ): bool {
		return 'preset' === $type && 'prefixed' === ( $settings['naming'] ?? '' );
	}

	/**
	 * The preset itself saves nothing; the fields it inserts do.
	 *
	 * @return bool
	 */
	public function stores_value(): bool {
		return false;
	}

	/**
	 * No default.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return null;
	}
}
