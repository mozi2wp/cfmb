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
		return __( 'Attachment', 'cfmb' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Forms', 'cfmb' );
	}

	/**
	 * File kinds, by the first part of the MIME type.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		return array(
			self::ALL     => __( 'All media', 'cfmb' ),
			'image'       => __( 'Images', 'cfmb' ),
			'video'       => __( 'Videos', 'cfmb' ),
			'audio'       => __( 'Audio', 'cfmb' ),
			'application' => __( 'Documents and other files', 'cfmb' ),
			'text'        => __( 'Text files', 'cfmb' ),
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
