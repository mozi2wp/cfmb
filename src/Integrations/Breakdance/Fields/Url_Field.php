<?php
/**
 * Breakdance / Oxygen 6 dynamic data: a CFMB field as a URL.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use Breakdance\DynamicData\StringData;
use Breakdance\DynamicData\StringField;

defined( 'ABSPATH' ) || exit;

/**
 * The URL of a Link, URL, Email, File, Image or linked post field, for
 * link controls.
 */
class Url_Field extends StringField {

	use Field_Config;


	/**
	 * Offered to link and URL controls.
	 *
	 * @return string[]
	 */
	public function returnTypes(): array {
		return array( 'url' );
	}

	/**
	 * Value.
	 *
	 * @param mixed $attributes Control values.
	 * @return StringData
	 */
	public function handler( $attributes ): StringData {
		return StringData::fromString( esc_url( $this->values->url( $this->path, $this->object ) ) );
	}
}
