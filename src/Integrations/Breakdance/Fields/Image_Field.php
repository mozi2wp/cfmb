<?php
/**
 * Breakdance / Oxygen 6 dynamic data: a CFMB image.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use Breakdance\DynamicData\ImageData;
use Breakdance\DynamicData\ImageField;

defined( 'ABSPATH' ) || exit;

/**
 * An Image field, the first image of a Gallery, or the loop image.
 */
class Image_Field extends ImageField {

	use Field_Config;


	/**
	 * Value.
	 *
	 * @param mixed $attributes Control values.
	 * @return ImageData
	 */
	public function handler( $attributes ): ImageData {
		$ids = $this->values->image_ids( $this->path, $this->object );

		if ( $ids && method_exists( ImageData::class, 'fromAttachmentId' ) ) {
			return ImageData::fromAttachmentId( $ids[0] );
		}

		return method_exists( ImageData::class, 'emptyImage' ) ? ImageData::emptyImage() : new ImageData();
	}
}
