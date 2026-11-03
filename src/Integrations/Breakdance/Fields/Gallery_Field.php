<?php
/**
 * Breakdance / Oxygen 6 dynamic data: a CFMB gallery.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use Breakdance\DynamicData\GalleryData;
use Breakdance\DynamicData\GalleryField;
use Breakdance\DynamicData\ImageData;

defined( 'ABSPATH' ) || exit;

/**
 * The images of a Gallery or Image field, for gallery and slider elements.
 */
class Gallery_Field extends GalleryField {

	use Field_Config;


	/**
	 * Value.
	 *
	 * @param mixed $attributes Control values.
	 * @return GalleryData
	 */
	public function handler( $attributes ): GalleryData {
		$gallery         = new GalleryData();
		$gallery->images = array();

		if ( method_exists( ImageData::class, 'fromAttachmentId' ) ) {
			foreach ( $this->values->image_ids( $this->path, $this->object ) as $id ) {
				$gallery->images[] = ImageData::fromAttachmentId( $id );
			}
		}

		return $gallery;
	}
}
