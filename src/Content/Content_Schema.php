<?php
/**
 * Shared shape of post type, taxonomy and options page definitions.
 *
 * @package CFMB
 */

namespace CFMB\Content;

use CFMB\Schema\Key_Generator;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Describes one kind of content definition made in the CFMB admin.
 *
 * A definition is stored and synced in this shape:
 * { key: "cpt_1a2b3c4d5e", name: "book", title: "Books", active: true, settings: { ... } }
 *
 * `name` is what WordPress knows it by (post type, taxonomy or options
 * page ID). `title` is the plural label, or the page title for options pages.
 */
abstract class Content_Schema {

	/**
	 * Kind key: post_type, taxonomy or options_page.
	 *
	 * @return string
	 */
	abstract public function kind(): string;

	/**
	 * Key prefix, e.g. cpt_.
	 *
	 * @return string
	 */
	abstract public function key_prefix(): string;

	/**
	 * Private post type the definitions are stored in.
	 *
	 * @return string
	 */
	abstract public function storage_type(): string;

	/**
	 * Longest allowed name.
	 *
	 * @return int
	 */
	abstract public function name_max_length(): int;

	/**
	 * Setting descriptors, basic ones first. See Setting_Sanitizer.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function settings(): array;

	/**
	 * Explain what is wrong with a name, or null.
	 *
	 * @param string $name     Sanitized name.
	 * @param string $previous Name the definition had before, if any.
	 * @return string|null
	 */
	abstract protected function name_error( string $name, string $previous ): ?string;

	/**
	 * Arguments for WordPress (register_post_type(), register_taxonomy() or the options page registry).
	 *
	 * @param array<string, mixed> $item Normalized definition.
	 * @return array<string, mixed>
	 */
	abstract public function build_args( array $item ): array;

	/**
	 * Builder strings: what the kind is called.
	 *
	 * @return array<string, string>
	 */
	abstract public function names(): array;

	/**
	 * Label templates by label key: [ label shown in the builder, template ].
	 * Templates take %1$s singular, %2$s plural, %3$s lowercase singular, %4$s lowercase plural.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function label_templates(): array {
		return array();
	}

	/**
	 * Base shape of a definition.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return array(
			'key'      => '',
			'name'     => '',
			'title'    => '',
			'active'   => true,
			'settings' => Setting_Sanitizer::defaults( $this->settings() ),
		);
	}

	/**
	 * Fill in missing parts so stored, file and code definitions compare equal.
	 *
	 * @param array<string, mixed> $item Definition.
	 * @return array<string, mixed>
	 */
	public function normalize( array $item ): array {
		$defaults = $this->defaults();

		return array(
			'key'      => (string) ( $item['key'] ?? '' ),
			'name'     => (string) ( $item['name'] ?? '' ),
			'title'    => (string) ( $item['title'] ?? '' ),
			'active'   => (bool) ( $item['active'] ?? true ),
			'settings' => array_merge( $defaults['settings'], is_array( $item['settings'] ?? null ) ? $item['settings'] : array() ),
		);
	}

	/**
	 * Clean a definition from the builder, a file or code.
	 *
	 * @param array<string, mixed>      $input    Raw definition.
	 * @param array<string, mixed>|null $existing Stored definition when updating.
	 * @param string[]                  $taken    Names other CFMB definitions of this kind use.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sanitize( array $input, ?array $existing = null, array $taken = array() ) {
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );
		$name  = $this->sanitize_name( (string) ( $input['name'] ?? '' ) );

		if ( '' === $title ) {
			return new WP_Error( 'cfmb_content_title', $this->names()['title_missing'], array( 'status' => 400 ) );
		}

		$previous = $existing ? (string) $existing['name'] : '';
		$error    = $this->basic_name_error( $name, $taken );

		if ( null === $error ) {
			$error = $this->name_error( $name, $previous );
		}

		if ( null !== $error ) {
			return new WP_Error( 'cfmb_content_name', $error, array( 'status' => 400 ) );
		}

		$key = $existing ? (string) $existing['key'] : (string) ( $input['key'] ?? '' );

		return array(
			'key'      => Key_Generator::is_valid( $key, $this->key_prefix() ) ? $key : Key_Generator::make( $this->key_prefix() ),
			'name'     => $name,
			'title'    => $title,
			'active'   => ! empty( $input['active'] ),
			'settings' => Setting_Sanitizer::sanitize( is_array( $input['settings'] ?? null ) ? $input['settings'] : array(), $this->settings() ),
		);
	}

	/**
	 * Lowercase letters, digits, underscores and dashes.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public function sanitize_name( string $name ): string {
		return sanitize_key( $name );
	}

	/**
	 * Checks every kind shares: present, short enough, unique within CFMB.
	 *
	 * @param string   $name  Sanitized name.
	 * @param string[] $taken Names used by other definitions of this kind.
	 * @return string|null
	 */
	private function basic_name_error( string $name, array $taken ): ?string {
		$names = $this->names();

		if ( '' === $name ) {
			return $names['name_missing'];
		}

		if ( strlen( $name ) > $this->name_max_length() ) {
			/* translators: %d: maximum number of characters */
			return sprintf( __( 'Use %d characters or fewer for the name.', 'custom-fields-meta-box' ), $this->name_max_length() );
		}

		if ( in_array( $name, $taken, true ) ) {
			/* translators: %s: name */
			return sprintf( __( 'Another CFMB definition already uses the name "%s".', 'custom-fields-meta-box' ), $name );
		}

		return null;
	}

	/**
	 * Fill labels from the singular and plural names, keeping any set by hand.
	 *
	 * @param string               $singular Singular name.
	 * @param string               $plural   Plural name.
	 * @param array<string, mixed> $custom   Labels set in the builder.
	 * @return array<string, string>
	 */
	protected function labels( string $singular, string $plural, array $custom ): array {
		$lower_singular = function_exists( 'mb_strtolower' ) ? mb_strtolower( $singular ) : strtolower( $singular );
		$lower_plural   = function_exists( 'mb_strtolower' ) ? mb_strtolower( $plural ) : strtolower( $plural );
		$labels         = array();

		foreach ( $this->label_templates() as $key => $template ) {
			$set            = isset( $custom[ $key ] ) && is_scalar( $custom[ $key ] ) ? (string) $custom[ $key ] : '';
			$labels[ $key ] = '' !== $set ? $set : sprintf( $template[1], $singular, $plural, $lower_singular, $lower_plural );
		}

		return $labels;
	}

	/**
	 * Resolve a tristate setting: '' follows the fallback.
	 *
	 * @param mixed $value    '' , '0' or '1'.
	 * @param bool  $fallback Value used for ''.
	 * @return bool
	 */
	protected static function tristate( $value, bool $fallback ): bool {
		if ( '' === $value || null === $value ) {
			return $fallback;
		}

		return '1' === (string) $value;
	}

	/**
	 * Everything the builder needs to edit this kind.
	 *
	 * @return array<string, mixed>
	 */
	public function client_config(): array {
		$labels = array();
		foreach ( $this->label_templates() as $key => $template ) {
			$labels[] = array(
				'key'      => $key,
				'label'    => $template[0],
				'template' => $template[1],
			);
		}

		return array(
			'kind'          => $this->kind(),
			'prefix'        => $this->key_prefix(),
			'nameMaxLength' => $this->name_max_length(),
			'settings'      => $this->settings(),
			'labels'        => $labels,
			'defaults'      => $this->defaults(),
			'names'         => $this->names(),
		);
	}

	/**
	 * Setting descriptor helper.
	 *
	 * @param string               $name    Setting name.
	 * @param string               $label   Label.
	 * @param string               $control Control type.
	 * @param mixed                $default Default value.
	 * @param array<string, mixed> $extra   options, help, advanced, section.
	 * @return array<string, mixed>
	 */
	protected function setting( string $name, string $label, string $control, $default = '', array $extra = array() ): array {
		return array_merge(
			array(
				'name'    => $name,
				'label'   => $label,
				'control' => $control,
				'default' => $default,
			),
			$extra
		);
	}

	/**
	 * Options for a tristate select.
	 *
	 * @param string $follows What '' follows, e.g. "Same as Public".
	 * @return array<string, string>
	 */
	protected static function tristate_options( string $follows ): array {
		return array(
			''  => $follows,
			'1' => __( 'Yes', 'custom-fields-meta-box' ),
			'0' => __( 'No', 'custom-fields-meta-box' ),
		);
	}
}
