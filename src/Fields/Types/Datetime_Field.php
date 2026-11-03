<?php
/**
 * Date and time field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * A date with a time, stored as Y-m-d H:i:s (2026-10-15 14:30:00) in the
 * site's time zone, like post dates. Changing the site time zone later
 * does not shift stored values.
 */
class Datetime_Field extends Date_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'datetime';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Date and time', 'custom-fields-meta-box' );
	}

	/**
	 * Storage format.
	 *
	 * @return string
	 */
	public function storage_format(): string {
		return 'Y-m-d H:i:s';
	}

	/**
	 * Display format when the field doesn't set one.
	 *
	 * @return string
	 */
	protected function site_format(): string {
		return get_option( 'date_format', 'F j, Y' ) . ' ' . get_option( 'time_format', 'g:i a' );
	}

	/**
	 * Read the storage format or what a datetime-local input sends.
	 *
	 * @param string $text Input.
	 * @return DateTimeImmutable|null
	 */
	protected function parse( string $text ): ?DateTimeImmutable {
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $text, wp_timezone() );

			if ( $date && $date->format( $format ) === $text ) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * Error for an invalid value.
	 *
	 * @return string
	 */
	protected function invalid_message(): string {
		return __( 'Enter a valid date and time.', 'custom-fields-meta-box' );
	}
}
