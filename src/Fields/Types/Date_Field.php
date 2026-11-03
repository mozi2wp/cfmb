<?php
/**
 * Date field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;
use DateTimeImmutable;

defined( 'ABSPATH' ) || exit;

/**
 * A calendar date, stored as Y-m-d (2026-10-15) so it sorts correctly and
 * meta_query can compare it as a DATE.
 *
 * Time and Datetime extend this and only change the formats.
 */
class Date_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'date';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Date', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'picker';
	}

	/**
	 * Storage format (PHP date format).
	 *
	 * @return string
	 */
	public function storage_format(): string {
		return 'Y-m-d';
	}

	/**
	 * Display format when the field doesn't set one.
	 *
	 * @return string
	 */
	protected function site_format(): string {
		return (string) get_option( 'date_format', 'F j, Y' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		$settings = array(
			$this->setting(
				'display_format',
				__( 'Display format', 'cfmb' ),
				'text',
				'',
				array(
					/* translators: %s: example PHP date format */
					'help' => sprintf( __( 'How cfmb_the_field() prints it, as a PHP date format such as %s. Leave empty to use the site setting.', 'cfmb' ), $this->site_format() ),
				)
			),
			$this->setting(
				'return_format',
				__( 'Template format', 'cfmb' ),
				'text',
				'',
				array(
					'advanced' => true,
					/* translators: %s: storage format */
					'help'     => sprintf( __( 'What cfmb_get_field() returns. Leave empty for the stored value, like %s.', 'cfmb' ), $this->storage_format() ),
				)
			),
		);

		if ( ! $this->has_calendar() ) {
			return $settings;
		}

		$bound_help = __( 'A date like 2026-01-31, or relative to the day the field is filled in: today, +7 days, -1 month, +1 year.', 'cfmb' );

		return array_merge(
			$settings,
			array(
				$this->setting( 'min_date', __( 'Earliest date', 'cfmb' ), 'text', '', array( 'help' => $bound_help ) ),
				$this->setting( 'max_date', __( 'Latest date', 'cfmb' ), 'text', '', array( 'help' => $bound_help ) ),
				$this->setting(
					'first_day',
					__( 'Week starts on', 'cfmb' ),
					'select',
					'',
					array(
						'advanced' => true,
						'options'  => array(
							''  => __( 'As in Settings > General', 'cfmb' ),
							'0' => __( 'Sunday', 'cfmb' ),
							'1' => __( 'Monday', 'cfmb' ),
							'2' => __( 'Tuesday', 'cfmb' ),
							'3' => __( 'Wednesday', 'cfmb' ),
							'4' => __( 'Thursday', 'cfmb' ),
							'5' => __( 'Friday', 'cfmb' ),
							'6' => __( 'Saturday', 'cfmb' ),
						),
					)
				),
			)
		);
	}

	/**
	 * Clean settings; date limits must be a date or a relative date we understand.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( array $settings ): array {
		$clean = parent::sanitize_settings( $settings );

		foreach ( array( 'min_date', 'max_date' ) as $name ) {
			if ( isset( $clean[ $name ] ) && '' === $this->resolve_bound( (string) $clean[ $name ] ) ) {
				$clean[ $name ] = '';
			}
		}

		return $clean;
	}

	/**
	 * Whether the type picks days on a calendar (date and date-time, not time).
	 *
	 * @return bool
	 */
	protected function has_calendar(): bool {
		return true;
	}

	/**
	 * Earliest and latest allowed values in the storage format, empty when unset.
	 * Relative limits are worked out for today in the site time zone.
	 *
	 * @param Field $field Field definition.
	 * @return array{min: string, max: string}
	 */
	public function bounds( Field $field ): array {
		return array(
			'min' => $this->resolve_bound( (string) $field->setting( 'min_date', '' ) ),
			'max' => $this->resolve_bound( (string) $field->setting( 'max_date', '' ), true ),
		);
	}

	/**
	 * First day of the week for the calendar, 0 (Sunday) to 6.
	 *
	 * @param Field $field Field definition.
	 * @return int
	 */
	public function first_day( Field $field ): int {
		$day = (string) $field->setting( 'first_day', '' );

		return '' === $day ? (int) get_option( 'start_of_week', 1 ) : max( 0, min( 6, (int) $day ) );
	}

	/**
	 * A limit as a value in the storage format, or empty when it can't be read.
	 *
	 * @param string $text   Date or relative date.
	 * @param bool   $is_max Whether this is an upper limit (a date-time limit then covers the whole day).
	 * @return string
	 */
	private function resolve_bound( string $text, bool $is_max = false ): string {
		$text = strtolower( trim( $text ) );

		if ( '' === $text ) {
			return '';
		}

		$date = $this->parse( $text );

		if ( ! $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $text ) ) {
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $text, wp_timezone() );
		}

		if ( ! $date ) {
			// Only simple, predictable relative forms; anything else is ignored.
			if ( ! preg_match( '/^(today|now|[+-]\s*\d{1,4}\s*(day|week|month|year)s?)$/', $text ) ) {
				return '';
			}

			$date = new DateTimeImmutable( 'today', wp_timezone() );

			if ( ! in_array( $text, array( 'today', 'now' ), true ) ) {
				$date = $date->modify( (string) preg_replace( '/\s+/', '', $text ) );
			}

			if ( ! $date ) {
				return '';
			}
		}

		if ( $is_max && 'Y-m-d H:i:s' === $this->storage_format() && '00:00:00' === $date->format( 'H:i:s' ) ) {
			$date = $date->setTime( 23, 59, 59 );
		}

		return $date->format( $this->storage_format() );
	}

	/**
	 * No default date.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return '';
	}

	/**
	 * Normalize input to the storage format. Unparseable text is kept so
	 * validation can explain the problem.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$text = is_scalar( $value ) ? trim( sanitize_text_field( (string) $value ) ) : '';

		if ( '' === $text ) {
			return '';
		}

		$parsed = $this->parse( $text );

		return $parsed ? $parsed->format( $this->storage_format() ) : $text;
	}

	/**
	 * Must be a real date in the storage format.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$date = DateTimeImmutable::createFromFormat( '!' . $this->storage_format(), (string) $value, wp_timezone() );

		if ( ! $date || $date->format( $this->storage_format() ) !== $value ) {
			return $this->invalid_message();
		}

		if ( ! $this->has_calendar() ) {
			return null;
		}

		// Values in the storage format sort as text, so plain comparison works.
		$bounds = $this->bounds( $field );

		if ( '' !== $bounds['min'] && $value < $bounds['min'] ) {
			/* translators: %s: earliest allowed date */
			return sprintf( __( 'Choose a date on or after %s.', 'cfmb' ), $this->localize( $bounds['min'], $this->site_format() ) );
		}

		if ( '' !== $bounds['max'] && $value > $bounds['max'] ) {
			/* translators: %s: latest allowed date */
			return sprintf( __( 'Choose a date on or before %s.', 'cfmb' ), $this->localize( $bounds['max'], $this->site_format() ) );
		}

		return null;
	}

	/**
	 * The stored value, or the template format.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		if ( ! is_string( $value ) || '' === $value ) {
			return null;
		}

		$format = (string) $field->setting( 'return_format', '' );

		return '' === $format ? $value : $this->localize( $value, $format );
	}

	/**
	 * The date in the display format.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		if ( ! is_string( $formatted ) || '' === $formatted ) {
			return '';
		}

		// Templates may have set a return format; displays always start from the stored value.
		$parsed = $this->parse( $formatted );
		$stored = $parsed ? $parsed->format( $this->storage_format() ) : $formatted;
		$format = (string) $field->setting( 'display_format', '' );

		return esc_html( $this->localize( $stored, '' !== $format ? $format : $this->site_format() ) );
	}

	/**
	 * Read a date in the storage format or a few common input forms.
	 *
	 * @param string $text Input.
	 * @return DateTimeImmutable|null
	 */
	protected function parse( string $text ): ?DateTimeImmutable {
		// Ymd is what ACF stores; accepting it keeps imported values readable.
		foreach ( array( $this->storage_format(), 'Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Ymd', 'Y-m-d' ) as $format ) {
			$date = DateTimeImmutable::createFromFormat( '!' . $format, $text, wp_timezone() );

			if ( $date && $date->format( $format ) === $text ) {
				return $date;
			}
		}

		return null;
	}

	/**
	 * Format a stored value with a PHP date format, translated.
	 *
	 * @param string $stored Stored value.
	 * @param string $format PHP date format.
	 * @return string
	 */
	protected function localize( string $stored, string $format ): string {
		$date = $this->parse( $stored );

		if ( ! $date ) {
			return $stored;
		}

		$text = wp_date( $format, $date->getTimestamp(), wp_timezone() );

		return is_string( $text ) ? $text : $stored;
	}

	/**
	 * Error for an invalid value.
	 *
	 * @return string
	 */
	protected function invalid_message(): string {
		return __( 'Enter a valid date.', 'cfmb' );
	}
}
