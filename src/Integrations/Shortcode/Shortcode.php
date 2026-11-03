<?php
/**
 * The [cfmb] shortcode.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Shortcode;

use CFMB\Admin\Settings_Screen;
use CFMB\Display\Formatter;
use CFMB\Display\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Prints one field value:
 *
 *     [cfmb field="price"]
 *     [cfmb field="logo" object="option:site_settings" size="medium"]
 *     [cfmb field="colors" separator=" / "]
 *     [cfmb field="website" format="url"]
 *
 * Attributes: field (required), object (see Resolver), format (html, text,
 * url or raw; default html), size (image size), separator (between list
 * items) and default (text when there is no value). Other attributes are
 * ignored. Output is escaped, and only values the visitor may see are shown.
 * The shortcode can be turned off under CFMB > Settings.
 */
class Shortcode {

	public const TAG = 'cfmb';

	/**
	 * Source lookup.
	 *
	 * @var Resolver
	 */
	private Resolver $resolver;

	/**
	 * Value formatting.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Resolver  $resolver  Source lookup.
	 * @param Formatter $formatter Value formatting.
	 */
	public function __construct( Resolver $resolver, Formatter $formatter ) {
		$this->resolver  = $resolver;
		$this->formatter = $formatter;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'add' ) );
	}

	/**
	 * Add the shortcode unless it is turned off.
	 *
	 * @return void
	 */
	public function add(): void {
		if ( empty( Settings_Screen::current()['shortcode_enabled'] ) ) {
			return;
		}

		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode output.
	 *
	 * @param array<string, string>|string $atts Attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'field'     => '',
				'object'    => '',
				'format'    => 'html',
				'size'      => '',
				'separator' => null,
				'default'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		/**
		 * Filters the shortcode attributes after defaults are applied.
		 *
		 * @param array<string, mixed> $atts Attributes.
		 */
		$atts = (array) apply_filters( 'cfmb/shortcode/atts', $atts ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		$found   = $this->resolver->find( (string) $atts['field'], (string) $atts['object'] );
		$default = esc_html( (string) $atts['default'] );

		if ( ! $found ) {
			return $default;
		}

		$args = array(
			'size'      => sanitize_key( (string) $atts['size'] ),
			'separator' => null === $atts['separator'] ? null : (string) $atts['separator'],
		);

		switch ( (string) $atts['format'] ) {
			case 'raw':
				$output = $this->formatter->raw_html( $found['field'], $found['group'], $found['ref'] );
				break;

			case 'text':
				$output = esc_html( $this->formatter->text( $found['field'], $found['group'], $found['ref'], $args ) );
				break;

			case 'url':
				$output = esc_url( $this->formatter->parts( $found['field'], $found['group'], $found['ref'], $args )['url'] );
				break;

			default:
				$output = $this->formatter->html( $found['field'], $found['group'], $found['ref'], $args );
		}

		return '' !== trim( $output ) ? $output : $default;
	}
}
