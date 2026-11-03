<?php
/**
 * Time field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * A time of day, stored as H:i:s (14:30:00).
 */
class Time_Field extends Date_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'time';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Time', 'custom-fields-meta-box' );
	}

	/**
	 * Storage format.
	 *
	 * @return string
	 */
	public function storage_format(): string {
		return 'H:i:s';
	}

	/**
	 * Display format when the field doesn't set one.
	 *
	 * @return string
	 */
	protected function site_format(): string {
		return (string) get_option( 'time_format', 'g:i a' );
	}

	/**
	 * Read H:i:s or H:i.
	 *
	 * @param string $text Input.
	 * @return DateTimeImmutable|null
	 */
	protected function parse( string $text ): ?DateTimeImmutable {
		foreach ( array( 'H:i:s', 'H:i' ) as $format ) {
			$time = DateTimeImmutable::createFromFormat( '!' . $format, $text, wp_timezone() );

			if ( $time && $time->format( $format ) === $text ) {
				return $time;
			}
		}

		return null;
	}

	/**
	 * Times have no calendar, so no date limits or week start.
	 *
	 * @return bool
	 */
	protected function has_calendar(): bool {
		return false;
	}

	/**
	 * Error for an invalid value.
	 *
	 * @return string
	 */
	protected function invalid_message(): string {
		return __( 'Enter a valid time.', 'custom-fields-meta-box' );
	}
}
