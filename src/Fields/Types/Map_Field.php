<?php
/**
 * Map field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Admin\Settings_Screen;
use CFMB\Fields\Abstract_Field;
use CFMB\Fields\Compound_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * A place on a map: latitude, longitude, address and zoom, each stored on
 * its own (loc_lat, loc_lng, loc_address, loc_zoom) so meta_query can find
 * posts inside a latitude/longitude box.
 *
 * Nothing is loaded from a map service until a provider is chosen in CFMB
 * settings or on the field. Without one, editors type the address and
 * coordinates themselves.
 */
class Map_Field extends Abstract_Field implements Compound_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'map';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Map', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'picker';
	}

	/**
	 * Stored parts.
	 *
	 * @return string[]
	 */
	public function parts(): array {
		return array( 'lat', 'lng', 'address', 'zoom' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'provider',
				__( 'Map', 'cfmb' ),
				'select',
				'default',
				array(
					'options' => array(
						'default' => __( 'As in CFMB settings', 'cfmb' ),
						'osm'     => __( 'OpenStreetMap', 'cfmb' ),
						'google'  => __( 'Google Maps (needs an API key in CFMB settings)', 'cfmb' ),
						'none'    => __( 'No map, address and coordinates only', 'cfmb' ),
					),
				)
			),
			$this->setting( 'center_lat', __( 'Start latitude', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting( 'center_lng', __( 'Start longitude', 'cfmb' ), 'text', '', array( 'advanced' => true ) ),
			$this->setting( 'zoom', __( 'Start zoom', 'cfmb' ), 'number', 13, array( 'advanced' => true ) ),
			$this->setting( 'height', __( 'Map height (px)', 'cfmb' ), 'number', 320, array( 'advanced' => true ) ),
		);
	}

	/**
	 * Map provider in effect for a field: osm, google or none.
	 *
	 * @param Field $field Field definition.
	 * @return string
	 */
	public static function provider( Field $field ): string {
		$provider = (string) $field->setting( 'provider', 'default' );

		if ( 'default' === $provider ) {
			$provider = (string) Settings_Screen::current()['map_provider'];
		}

		if ( 'google' === $provider && '' === (string) Settings_Screen::current()['map_google_key'] ) {
			return 'none';
		}

		return in_array( $provider, array( 'osm', 'google' ), true ) ? $provider : 'none';
	}

	/**
	 * Empty place.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return array(
			'lat'     => '',
			'lng'     => '',
			'address' => '',
			'zoom'    => '',
		);
	}

	/**
	 * Numbers in range, plain-text address. Out-of-range numbers are kept so validation can explain.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$value = is_array( $value ) ? $value : array();
		$zoom  = $value['zoom'] ?? '';

		return array(
			'lat'     => self::number( $value['lat'] ?? '' ),
			'lng'     => self::number( $value['lng'] ?? '' ),
			'address' => is_scalar( $value['address'] ?? null ) ? sanitize_text_field( (string) $value['address'] ) : '',
			'zoom'    => is_numeric( $zoom ) ? max( 0, min( 21, (int) $zoom ) ) : '',
		);
	}

	/**
	 * Latitude and longitude come together and stay in range.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$lat = $value['lat'] ?? '';
		$lng = $value['lng'] ?? '';

		if ( '' === $lat && '' === $lng ) {
			return null;
		}

		if ( '' === $lat || '' === $lng ) {
			return __( 'Enter both latitude and longitude, or pick the place on the map.', 'cfmb' );
		}

		if ( abs( (float) $lat ) > 90 || abs( (float) $lng ) > 180 ) {
			return __( 'Latitude must be between -90 and 90, longitude between -180 and 180.', 'cfmb' );
		}

		return null;
	}

	/**
	 * Empty without coordinates and address.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return ! is_array( $value ) || ( '' === (string) ( $value['lat'] ?? '' ) && '' === (string) ( $value['address'] ?? '' ) );
	}

	/**
	 * Numbers as floats and int.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		if ( $this->is_empty( $value ) ) {
			return null;
		}

		return array(
			'lat'     => '' === (string) $value['lat'] ? null : (float) $value['lat'],
			'lng'     => '' === (string) $value['lng'] ? null : (float) $value['lng'],
			'address' => (string) ( $value['address'] ?? '' ),
			'zoom'    => '' === (string) ( $value['zoom'] ?? '' ) ? null : (int) $value['zoom'],
		);
	}

	/**
	 * The address, or the coordinates.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( ! is_array( $formatted ) ) {
			return '';
		}

		if ( '' !== (string) ( $formatted['address'] ?? '' ) ) {
			return esc_html( (string) $formatted['address'] );
		}

		return null === ( $formatted['lat'] ?? null ) ? '' : esc_html( $formatted['lat'] . ', ' . $formatted['lng'] );
	}

	/**
	 * A decimal number as a string, or empty.
	 *
	 * @param mixed $value Input.
	 * @return string
	 */
	private static function number( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}

		$text = str_replace( ',', '.', trim( (string) $value ) );

		if ( ! is_numeric( $text ) ) {
			return '';
		}

		// Seven decimals is about 1 cm; fixed notation avoids "1.0E-5".
		$fixed = rtrim( rtrim( sprintf( '%.7F', (float) $text ), '0' ), '.' );

		return '-0' === $fixed ? '0' : $fixed;
	}
}
