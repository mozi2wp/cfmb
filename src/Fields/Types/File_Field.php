<?php
/**
 * File field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;

defined( 'ABSPATH' ) || exit;

/**
 * Attachment picked from the media library. Stores the attachment ID.
 */
class File_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'file';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'File', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'content';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->return_format_setting(),
			$this->library_setting(),
			$this->setting(
				'mime_types',
				__( 'Allowed file types', 'cfmb' ),
				'text',
				'',
				array(
					'advanced' => true,
					'help'     => __( 'Comma-separated extensions, like pdf, docx. Leave empty to allow all.', 'cfmb' ),
				)
			),
		);
	}

	/**
	 * Attachment ID, or an empty string when no file is chosen.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		if ( is_array( $value ) && isset( $value['id'] ) ) {
			$value = $value['id'];
		}

		$id = is_numeric( $value ) ? absint( $value ) : 0;

		return $id > 0 ? $id : '';
	}

	/**
	 * Attachment must exist and have an allowed extension.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		if ( ! $value ) {
			return null;
		}

		if ( 'attachment' !== get_post_type( (int) $value ) ) {
			return __( 'That file is no longer in the media library. Choose another one.', 'cfmb' );
		}

		$allowed = $this->allowed_extensions( $field );
		if ( $allowed ) {
			$extension = strtolower( (string) pathinfo( (string) get_attached_file( (int) $value ), PATHINFO_EXTENSION ) );

			if ( ! in_array( $extension, $allowed, true ) ) {
				/* translators: %s: comma-separated list of file extensions */
				return sprintf( __( 'Choose a file of one of these types: %s.', 'cfmb' ), implode( ', ', $allowed ) );
			}
		}

		return null;
	}

	/**
	 * No attachment chosen.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return empty( $value );
	}

	/**
	 * ID, URL or a details array, per the return format setting.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$id = absint( $value );

		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return null;
		}

		switch ( $field->setting( 'return_format', 'array' ) ) {
			case 'id':
				return $id;
			case 'url':
				return (string) wp_get_attachment_url( $id );
			default:
				return $this->details( $id );
		}
	}

	/**
	 * What the edit screen needs to show the current file.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return array<string, mixed>|null
	 */
	public function editor_value( $value, Field $field ) {
		$id = absint( $value );

		if ( ! $id || 'attachment' !== get_post_type( $id ) ) {
			return null;
		}

		$file = get_attached_file( $id );

		return array(
			'id'       => $id,
			'url'      => (string) wp_get_attachment_url( $id ),
			'title'    => get_the_title( $id ),
			'filename' => $file ? wp_basename( $file ) : '',
			'filesize' => $file && file_exists( $file ) ? size_format( (int) filesize( $file ) ) : '',
			'icon'     => (string) wp_mime_type_icon( $id ),
		);
	}

	/**
	 * Link to the file.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$id = $this->id_from_formatted( $formatted );

		if ( ! $id ) {
			return is_string( $formatted ) ? esc_url( $formatted ) : '';
		}

		return sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( (string) wp_get_attachment_url( $id ) ),
			esc_html( get_the_title( $id ) )
		);
	}

	/**
	 * Details array for templates.
	 *
	 * @param int $id Attachment ID.
	 * @return array<string, mixed>
	 */
	protected function details( int $id ): array {
		$file = get_attached_file( $id );

		return array(
			'id'          => $id,
			'url'         => (string) wp_get_attachment_url( $id ),
			'title'       => get_the_title( $id ),
			'filename'    => $file ? wp_basename( $file ) : '',
			'filesize'    => $file && file_exists( $file ) ? (int) filesize( $file ) : 0,
			'mime_type'   => (string) get_post_mime_type( $id ),
			'caption'     => wp_get_attachment_caption( $id ),
			'description' => (string) get_post_field( 'post_content', $id ),
		);
	}

	/**
	 * Attachment ID from any return format.
	 *
	 * @param mixed $formatted Formatted value.
	 * @return int
	 */
	protected function id_from_formatted( $formatted ): int {
		if ( is_array( $formatted ) && isset( $formatted['id'] ) ) {
			return absint( $formatted['id'] );
		}

		return is_numeric( $formatted ) ? absint( $formatted ) : 0;
	}

	/**
	 * Allowed extensions from the mime_types setting.
	 *
	 * @param Field $field Field definition.
	 * @return string[]
	 */
	protected function allowed_extensions( Field $field ): array {
		$list = strtolower( (string) $field->setting( 'mime_types', '' ) );
		$list = array_map( static fn( $ext ) => ltrim( trim( $ext ), '.' ), explode( ',', $list ) );

		return array_values( array_filter( $list ) );
	}

	/**
	 * Shared return format setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function return_format_setting(): array {
		return $this->setting(
			'return_format',
			__( 'Template value', 'cfmb' ),
			'select',
			'array',
			array(
				'options' => array(
					'array' => __( 'Details array', 'cfmb' ),
					'url'   => __( 'URL', 'cfmb' ),
					'id'    => __( 'Attachment ID', 'cfmb' ),
				),
				'help'    => __( 'What cfmb_get_field() returns for this field.', 'cfmb' ),
			)
		);
	}

	/**
	 * Shared library setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function library_setting(): array {
		return $this->setting(
			'library',
			__( 'Media library', 'cfmb' ),
			'select',
			'all',
			array(
				'options' => array(
					'all'      => __( 'All files', 'cfmb' ),
					'uploaded' => __( 'Uploaded to this post', 'cfmb' ),
				),
			)
		);
	}
}
