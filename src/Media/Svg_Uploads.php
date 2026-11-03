<?php
/**
 * Safe SVG uploads, when turned on in CFMB settings.
 *
 * @package CFMB
 */

namespace CFMB\Media;

use CFMB\Admin\Settings_Screen;
use CFMB\Vendor\enshrined\svgSanitize\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress refuses SVG uploads because an SVG can carry scripts. With
 * "Allow SVG files" on, CFMB accepts them and rewrites every file through
 * a sanitizer before it reaches the uploads folder. Files that can't be
 * cleaned are rejected.
 */
class Svg_Uploads {

	private const MIME = 'image/svg+xml';

	/**
	 * Register hooks when the setting is on.
	 *
	 * @return void
	 */
	public function register(): void {
		if ( empty( Settings_Screen::current()['allow_svg_uploads'] ) ) {
			return;
		}

		add_filter( 'upload_mimes', array( $this, 'allow_mime' ), 10, 2 );
		add_filter( 'wp_check_filetype_and_ext', array( $this, 'confirm_type' ), 10, 4 );
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'sanitize_upload' ) );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'sanitize_upload' ) );
	}

	/**
	 * Add SVG for users who can upload files.
	 *
	 * @param array<string, string> $mimes Allowed types.
	 * @param mixed                 $user  User ID or object, or null for the current user.
	 * @return array<string, string>
	 */
	public function allow_mime( $mimes, $user = null ): array {
		$mimes   = is_array( $mimes ) ? $mimes : array();
		$user_id = $user instanceof \WP_User ? $user->ID : ( is_numeric( $user ) ? (int) $user : get_current_user_id() );

		if ( user_can( $user_id, 'upload_files' ) ) {
			$mimes['svg'] = self::MIME;
		}

		return $mimes;
	}

	/**
	 * PHP's file type detection may report SVGs as text; trust the extension
	 * once the content looks like SVG.
	 *
	 * @param array<string, mixed> $data     ext, type, proper_filename.
	 * @param string               $file     Path to the uploaded file.
	 * @param string               $filename Original file name.
	 * @param mixed                $mimes    Allowed types.
	 * @return array<string, mixed>
	 */
	public function confirm_type( $data, $file, $filename, $mimes ): array {
		$data = is_array( $data ) ? $data : array();

		if ( ! empty( $data['type'] ) || 'svg' !== strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) ) {
			return $data;
		}

		if ( $this->looks_like_svg( (string) $file ) ) {
			$data['ext']  = 'svg';
			$data['type'] = self::MIME;
		}

		return $data;
	}

	/**
	 * Clean an uploaded SVG in place, or reject it.
	 *
	 * @param array<string, mixed> $file Upload data from $_FILES.
	 * @return array<string, mixed>
	 */
	public function sanitize_upload( $file ): array {
		$file = is_array( $file ) ? $file : array();
		$name = (string) ( $file['name'] ?? '' );
		$path = (string) ( $file['tmp_name'] ?? '' );

		if ( 'svg' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || '' === $path ) {
			return $file;
		}

		$clean = $this->clean( $path );

		if ( null === $clean ) {
			$file['error'] = __( 'This SVG file could not be cleaned safely, so it was not uploaded.', 'cfmb' );

			return $file;
		}

		file_put_contents( $path, $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Rewriting PHP's temporary upload file before WordPress moves it.

		return $file;
	}

	/**
	 * Sanitized SVG markup, or null when the file is not a usable SVG.
	 *
	 * @param string $path File path.
	 * @return string|null
	 */
	public function clean( string $path ): ?string {
		$markup = is_readable( $path ) ? (string) file_get_contents( $path ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temporary file.

		if ( '' === $markup ) {
			return null;
		}

		if ( 0 === strpos( $markup, "\x1f\x8b" ) ) {
			// Compressed SVG (svgz). Not supported: it can't be inspected safely here.
			return null;
		}

		require_once CFMB_DIR . 'vendor-prefixed/autoload.php';

		$sanitizer = new Sanitizer();
		$sanitizer->removeRemoteReferences( true );
		$sanitizer->minify( true );

		$clean = $sanitizer->sanitize( $markup );

		return is_string( $clean ) && '' !== trim( $clean ) ? $clean : null;
	}

	/**
	 * Whether a file starts like SVG markup.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	private function looks_like_svg( string $path ): bool {
		$start = is_readable( $path ) ? (string) file_get_contents( $path, false, null, 0, 1024 ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local temporary file.

		return false !== stripos( $start, '<svg' );
	}
}
