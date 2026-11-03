<?php
/**
 * The "Field Block" settings of a Field Group.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Shape and cleaning of `settings.block` in a group's JSON. Supports are
 * stored as simple switches the builder can show; Block_Definition turns
 * them into block.json supports.
 */
final class Block_Settings {

	/**
	 * Alignments a block may offer.
	 */
	public const ALIGNMENTS = array( 'left', 'center', 'right', 'wide', 'full' );

	/**
	 * Switches for core block supports, with their defaults.
	 */
	public const SUPPORTS = array(
		'align'                  => false,
		'anchor'                 => false,
		'color_text'             => false,
		'color_background'       => false,
		'color_link'             => false,
		'spacing_padding'        => false,
		'spacing_margin'         => false,
		'typography_font_size'   => false,
		'typography_line_height' => false,
		'multiple'               => true,
		'inserter'               => true,
	);

	/**
	 * Default block settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'enabled'     => false,
			'name'        => '',
			'title'       => '',
			'description' => '',
			'icon'        => 'block-default',
			'category'    => 'text',
			'keywords'    => array(),
			'mode'        => 'preview',
			'template'    => '',
			'supports'    => self::SUPPORTS,
			'alignments'  => array( 'wide', 'full' ),
			'styles'      => array(),
			'variations'  => array(),
		);
	}

	/**
	 * Block name part after "cfmb/" from any text: lowercase letters, digits and dashes.
	 *
	 * @param string $text Text, e.g. the group title.
	 * @return string Empty when nothing usable is left.
	 */
	public static function slug( string $text ): string {
		$slug = sanitize_title( $text );
		$slug = (string) preg_replace( '/[^a-z0-9-]+/', '', strtolower( $slug ) );
		$slug = trim( (string) preg_replace( '/-+/', '-', $slug ), '-' );
		$slug = substr( $slug, 0, 50 );

		// Block names must start with a letter.
		return 1 === preg_match( '/^[a-z]/', $slug ) ? rtrim( $slug, '-' ) : '';
	}

	/**
	 * Clean settings coming from the builder or a sync file.
	 *
	 * @param array<string, mixed> $raw Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $raw ): array {
		$defaults = self::defaults();
		$mode     = (string) ( $raw['mode'] ?? 'preview' );
		$supports = array();

		foreach ( self::SUPPORTS as $name => $default ) {
			$supports[ $name ] = array_key_exists( $name, (array) ( $raw['supports'] ?? array() ) ) ? ! empty( $raw['supports'][ $name ] ) : $default;
		}

		$alignments = array_values( array_intersect( self::ALIGNMENTS, array_map( 'strval', (array) ( $raw['alignments'] ?? $defaults['alignments'] ) ) ) );
		$keywords   = array_slice( array_values( array_filter( array_map( static fn( $word ) => is_scalar( $word ) ? sanitize_text_field( (string) $word ) : '', (array) ( $raw['keywords'] ?? array() ) ) ) ), 0, 10 );
		$icon       = is_scalar( $raw['icon'] ?? null ) ? sanitize_key( (string) $raw['icon'] ) : '';
		$category   = is_scalar( $raw['category'] ?? null ) ? sanitize_key( (string) $raw['category'] ) : '';

		return array(
			'enabled'     => ! empty( $raw['enabled'] ),
			'name'        => self::slug( (string) ( is_scalar( $raw['name'] ?? null ) ? $raw['name'] : '' ) ),
			'title'       => sanitize_text_field( (string) ( is_scalar( $raw['title'] ?? null ) ? $raw['title'] : '' ) ),
			'description' => sanitize_textarea_field( (string) ( is_scalar( $raw['description'] ?? null ) ? $raw['description'] : '' ) ),
			'icon'        => '' !== $icon ? $icon : $defaults['icon'],
			'category'    => '' !== $category ? $category : $defaults['category'],
			'keywords'    => $keywords,
			'mode'        => in_array( $mode, array( 'preview', 'edit' ), true ) ? $mode : 'preview',
			'template'    => self::sanitize_template_path( (string) ( is_scalar( $raw['template'] ?? null ) ? $raw['template'] : '' ) ),
			'supports'    => $supports,
			'alignments'  => $alignments,
			'styles'      => self::sanitize_named_list( $raw['styles'] ?? array(), 'label' ),
			'variations'  => self::sanitize_named_list( $raw['variations'] ?? array(), 'title' ),
		);
	}

	/**
	 * A template path as typed in the builder. Only the shape is checked here;
	 * Template_Locator decides at render time whether the file may be loaded.
	 *
	 * @param string $path Raw path.
	 * @return string
	 */
	public static function sanitize_template_path( string $path ): string {
		$path = trim( wp_normalize_path( str_replace( "\0", '', $path ) ) );

		if ( '' === $path || false !== strpos( $path, '://' ) || preg_match( '#(^|/)\.\.(/|$)#', $path ) ) {
			return '';
		}

		return (string) preg_replace( '#[^A-Za-z0-9_./ :-]#', '', $path );
	}

	/**
	 * Styles ({ name, label, isDefault }) or variations ({ name, title, description, isDefault }).
	 *
	 * @param mixed  $raw   Raw list.
	 * @param string $label Key of the visible label: label for styles, title for variations.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sanitize_named_list( $raw, string $label ): array {
		$clean = array();
		$names = array();

		foreach ( is_array( $raw ) ? $raw : array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$name = self::slug( (string) ( is_scalar( $item['name'] ?? null ) ? $item['name'] : '' ) );
			$text = sanitize_text_field( (string) ( is_scalar( $item[ $label ] ?? null ) ? $item[ $label ] : '' ) );

			if ( '' === $name || '' === $text || isset( $names[ $name ] ) ) {
				continue;
			}

			$names[ $name ] = true;
			$entry          = array(
				'name'      => $name,
				$label      => $text,
				'isDefault' => ! empty( $item['isDefault'] ),
			);

			if ( 'title' === $label ) {
				$entry['description'] = sanitize_text_field( (string) ( is_scalar( $item['description'] ?? null ) ? $item['description'] : '' ) );
			}

			$clean[] = $entry;
		}

		return array_slice( $clean, 0, 20 );
	}
}
