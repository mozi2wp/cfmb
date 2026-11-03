<?php
/**
 * Attachment location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Matches media library items, all of them or by kind of file.
 */
class Attachment_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'attachment';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Attachment', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Forms', 'custom-fields-meta-box' );
	}

	/**
	 * File kinds, by the first part of the MIME type.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		return array(
			self::ALL     => __( 'All media', 'custom-fields-meta-box' ),
			'image'       => __( 'Images', 'custom-fields-meta-box' ),
			'video'       => __( 'Videos', 'custom-fields-meta-box' ),
			'audio'       => __( 'Audio', 'custom-fields-meta-box' ),
			'application' => __( 'Documents and other files', 'custom-fields-meta-box' ),
			'text'        => __( 'Text files', 'custom-fields-meta-box' ),
		);
	}

	/**
	 * Check the file kind.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::ATTACHMENT !== $context->kind() ) {
			return null;
		}

		if ( self::ALL === $value ) {
			return true;
		}

		$mime = (string) get_post_mime_type( (int) $context->get( 'post_id', 0 ) );

		return 0 === strpos( $mime, $value . '/' );
	}
}
