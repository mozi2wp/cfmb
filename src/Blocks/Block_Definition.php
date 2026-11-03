<?php
/**
 * One Field Block.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * A Field Block: its block type settings and where its template lives.
 * Comes from a Field Group's "Field Block" settings or from a theme
 * block.json that has a "cfmb" key.
 */
final class Block_Definition {

	/**
	 * Full block name, e.g. cfmb/hero.
	 *
	 * @var string
	 */
	private string $name;

	/**
	 * Block type settings for register_block_type(), without attributes and callbacks.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Template path as configured; empty means "find it by name".
	 *
	 * @var string
	 */
	private string $template;

	/**
	 * Folder of the block.json, or empty for blocks defined in the builder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Default display mode: preview or edit.
	 *
	 * @var string
	 */
	private string $mode;

	/**
	 * Key of the group that defines the block, or empty for block.json blocks.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * Constructor.
	 *
	 * @param string               $name     Full block name.
	 * @param array<string, mixed> $settings Block type settings.
	 * @param string               $template Configured template path.
	 * @param string               $dir      block.json folder.
	 * @param string               $mode     preview or edit.
	 * @param string               $group    Defining group key.
	 */
	public function __construct( string $name, array $settings, string $template = '', string $dir = '', string $mode = 'preview', string $group = '' ) {
		$this->name     = $name;
		$this->settings = $settings;
		$this->template = $template;
		$this->dir      = $dir;
		$this->mode     = in_array( $mode, array( 'preview', 'edit' ), true ) ? $mode : 'preview';
		$this->group    = $group;
	}

	/**
	 * Definition from a group's Field Block settings.
	 *
	 * @param Group $group Field Group with block settings turned on.
	 * @return Block_Definition|null
	 */
	public static function from_group( Group $group ): ?Block_Definition {
		$name = $group->block_name();

		if ( '' === $name ) {
			return null;
		}

		$block = array_merge( Block_Settings::defaults(), (array) $group->setting( 'block' ) );
		$title = '' !== (string) $block['title'] ? (string) $block['title'] : $group->title();

		$settings = array(
			'title'       => $title,
			'description' => (string) $block['description'],
			'icon'        => (string) $block['icon'],
			'category'    => (string) $block['category'],
			'keywords'    => array_values( (array) $block['keywords'] ),
			'supports'    => self::supports( (array) $block['supports'], (array) $block['alignments'] ),
			'styles'      => array_values( (array) $block['styles'] ),
			'variations'  => self::variations( (array) $block['variations'] ),
		);

		return new self( $name, $settings, (string) $block['template'], '', (string) $block['mode'], $group->key() );
	}

	/**
	 * Definition from a block.json with a "cfmb" key.
	 *
	 * @param string               $dir      Folder of the block.json.
	 * @param array<string, mixed> $metadata Decoded block.json.
	 * @return Block_Definition|null
	 */
	public static function from_metadata( string $dir, array $metadata ): ?Block_Definition {
		$name = (string) ( $metadata['name'] ?? '' );

		if ( 1 !== preg_match( '#^[a-z][a-z0-9-]*/[a-z][a-z0-9-]*$#', $name ) || ! is_array( $metadata['cfmb'] ?? null ) ) {
			return null;
		}

		$cfmb     = $metadata['cfmb'];
		$template = (string) ( $cfmb['template'] ?? '' );

		// A core "render" file works too; CFMB still renders it so the template API knows the block.
		if ( '' === $template && is_string( $metadata['render'] ?? null ) ) {
			$template = (string) preg_replace( '#^file:\./#', '', $metadata['render'] );
		}

		return new self( $name, array(), '' !== $template ? $template : 'render.php', wp_normalize_path( $dir ), (string) ( $cfmb['mode'] ?? 'preview' ) );
	}

	/**
	 * Block.json supports from the builder switches. Field values have no
	 * HTML form, so editing the block as HTML is always off.
	 *
	 * @param array<string, bool> $switches   Support switches.
	 * @param string[]            $alignments Allowed alignments.
	 * @return array<string, mixed>
	 */
	public static function supports( array $switches, array $alignments ): array {
		$on       = static fn( string $name ): bool => ! empty( $switches[ $name ] );
		$supports = array(
			'html'     => false,
			'anchor'   => $on( 'anchor' ),
			'multiple' => ! array_key_exists( 'multiple', $switches ) || $on( 'multiple' ),
			'inserter' => ! array_key_exists( 'inserter', $switches ) || $on( 'inserter' ),
		);

		if ( $on( 'align' ) ) {
			$supports['align'] = $alignments ? array_values( $alignments ) : true;
		}

		if ( $on( 'color_text' ) || $on( 'color_background' ) || $on( 'color_link' ) ) {
			$supports['color'] = array(
				'text'       => $on( 'color_text' ),
				'background' => $on( 'color_background' ),
				'link'       => $on( 'color_link' ),
			);
		}

		if ( $on( 'spacing_padding' ) || $on( 'spacing_margin' ) ) {
			$supports['spacing'] = array(
				'padding' => $on( 'spacing_padding' ),
				'margin'  => $on( 'spacing_margin' ),
			);
		}

		if ( $on( 'typography_font_size' ) || $on( 'typography_line_height' ) ) {
			$supports['typography'] = array(
				'fontSize'   => $on( 'typography_font_size' ),
				'lineHeight' => $on( 'typography_line_height' ),
			);
		}

		return $supports;
	}

	/**
	 * Block variations from the builder list.
	 *
	 * @param array<int, array<string, mixed>> $variations Variations.
	 * @return array<int, array<string, mixed>>
	 */
	private static function variations( array $variations ): array {
		return array_values(
			array_map(
				static fn( array $variation ): array => array(
					'name'        => (string) $variation['name'],
					'title'       => (string) $variation['title'],
					'description' => (string) ( $variation['description'] ?? '' ),
					'isDefault'   => ! empty( $variation['isDefault'] ),
					'scope'       => array( 'inserter' ),
					// Variations differ by a class the template can read from $block['className'].
					'attributes'  => array( 'className' => 'is-variation-' . $variation['name'] ),
				),
				array_filter( $variations, 'is_array' )
			)
		);
	}

	/**
	 * Full block name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->name;
	}

	/**
	 * Name without the namespace, e.g. hero for cfmb/hero.
	 *
	 * @return string
	 */
	public function slug(): string {
		$parts = explode( '/', $this->name, 2 );

		return $parts[1] ?? $this->name;
	}

	/**
	 * Block type settings.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return $this->settings;
	}

	/**
	 * Title, for menus and the Block location rule.
	 *
	 * @return string
	 */
	public function title(): string {
		$registered = \WP_Block_Type_Registry::get_instance()->get_registered( $this->name );

		if ( $registered && '' !== (string) $registered->title ) {
			return (string) $registered->title;
		}

		return (string) ( $this->settings['title'] ?? $this->name );
	}

	/**
	 * Configured template path, or empty.
	 *
	 * @return string
	 */
	public function template(): string {
		return $this->template;
	}

	/**
	 * Folder of the block.json, or empty.
	 *
	 * @return string
	 */
	public function dir(): string {
		return $this->dir;
	}

	/**
	 * Default display mode in the editor.
	 *
	 * @return string
	 */
	public function mode(): string {
		return $this->mode;
	}

	/**
	 * Key of the defining group, or empty.
	 *
	 * @return string
	 */
	public function group_key(): string {
		return $this->group;
	}
}
