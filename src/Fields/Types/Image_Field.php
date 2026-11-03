<?php
/**
 * Image field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Image picked from the media library. Stores the attachment ID.
 */
class Image_Field extends File_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'image';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Image', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->return_format_setting(),
			$this->setting(
				'preview_size',
				__( 'Preview size', 'cfmb' ),
				'select',
				'medium',
				array( 'options' => $this->image_sizes() )
			),
			$this->library_setting(),
			$this->setting(
				'mime_types',
				__( 'Allowed file types', 'cfmb' ),
				'text',
				'',
				array(
					'advanced' => true,
					'help'     => __( 'Comma-separated extensions, like jpg, png, webp. Leave empty to allow all images.', 'cfmb' ),
				)
			),
		);
	}

	/**
	 * Must be an image attachment.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( $value && ! wp_attachment_is_image( (int) $value ) ) {
			return __( 'Choose an image file.', 'cfmb' );
		}

		return parent::validate_value( $value, $field );
	}

	/**
	 * Preview data for the edit screen.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return array<string, mixed>|null
	 */
	public function editor_value( $value, Field $field ) {
		$file = parent::editor_value( $value, $field );

		if ( null === $file ) {
			return null;
		}

		$size    = (string) $field->setting( 'preview_size', 'medium' );
		$preview = wp_get_attachment_image_src( $file['id'], $size );

		$file['preview'] = $preview ? $preview[0] : $file['url'];
		$file['alt']     = (string) get_post_meta( $file['id'], '_wp_attachment_image_alt', true );

		return $file;
	}

	/**
	 * Image tag at the preview size.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$id = $this->id_from_formatted( $formatted );

		if ( ! $id ) {
			return is_string( $formatted ) && '' !== $formatted ? sprintf( '<img src="%s" alt="" />', esc_url( $formatted ) ) : '';
		}

		return wp_get_attachment_image( $id, (string) $field->setting( 'preview_size', 'medium' ) );
	}

	/**
	 * Image details including every registered size.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string, mixed>
	 */
	protected function details( int $id ): array {
		$details = parent::details( $id );
		$meta    = wp_get_attachment_metadata( $id );
		$sizes   = array();

		foreach ( get_intermediate_image_sizes() as $size ) {
			$src = wp_get_attachment_image_src( $id, $size );
			if ( $src ) {
				$sizes[ $size ] = array(
					'url'    => $src[0],
					'width'  => $src[1],
					'height' => $src[2],
				);
			}
		}

		$details['alt']    = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
		$details['width']  = is_array( $meta ) ? (int) ( $meta['width'] ?? 0 ) : 0;
		$details['height'] = is_array( $meta ) ? (int) ( $meta['height'] ?? 0 ) : 0;
		$details['sizes']  = $sizes;

		return $details;
	}

	/**
	 * Registered image sizes for the preview size setting.
	 *
	 * @return array<string, string>
	 */
	protected function image_sizes(): array {
		$names = apply_filters(
			'image_size_names_choose', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
			array(
				'thumbnail' => __( 'Thumbnail', 'cfmb' ),
				'medium'    => __( 'Medium', 'cfmb' ),
				'large'     => __( 'Large', 'cfmb' ),
				'full'      => __( 'Full size', 'cfmb' ),
			)
		);

		return is_array( $names ) ? array_map( 'strval', $names ) : array();
	}
}
