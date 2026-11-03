<?php
/**
 * Breakdance / Oxygen 6 dynamic data: a CFMB field as text.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use Breakdance\DynamicData\StringData;
use Breakdance\DynamicData\StringField;

defined( 'ABSPATH' ) || exit;

/**
 * Formatted, escaped HTML, as the shortcode prints it. Breakdance adds its
 * own Prepend, Append, Limit and Fallback controls.
 */
class Text_Field extends StringField {

	use Field_Config;


	/**
	 * Value.
	 *
	 * @param mixed $attributes Control values.
	 * @return StringData
	 */
	public function handler( $attributes ): StringData {
		return StringData::fromString( $this->values->html( $this->path, $this->object ) );
	}
}
