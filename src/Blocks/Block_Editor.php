<?php
/**
 * Field Blocks in the block editor.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

use CFMB\Admin\Assets;
use CFMB\Admin\Group_Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the Field Block editor script, with each block's groups and fields,
 * in the post, site and widget editors. Nothing loads when the site has no
 * Field Blocks.
 */
class Block_Editor {

	/**
	 * Field Blocks.
	 *
	 * @var Block_Registry
	 */
	private Block_Registry $blocks;

	/**
	 * Renderer, for each block's groups.
	 *
	 * @var Block_Renderer
	 */
	private Block_Renderer $renderer;

	/**
	 * Field config and field assets.
	 *
	 * @var Group_Renderer
	 */
	private Group_Renderer $groups;

	/**
	 * Script loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Constructor.
	 *
	 * @param Block_Registry $blocks   Field Blocks.
	 * @param Block_Renderer $renderer Renderer.
	 * @param Group_Renderer $groups   Field config.
	 * @param Assets         $assets   Script loader.
	 */
	public function __construct( Block_Registry $blocks, Block_Renderer $renderer, Group_Renderer $groups, Assets $assets ) {
		$this->blocks   = $blocks;
		$this->renderer = $renderer;
		$this->groups   = $groups;
		$this->assets   = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
		add_action( 'enqueue_block_assets', array( $this, 'enqueue_canvas_styles' ) );
	}

	/**
	 * Form styles inside the editor canvas, which is an iframe, for blocks
	 * that show their fields in place.
	 *
	 * @return void
	 */
	public function enqueue_canvas_styles(): void {
		if ( ! is_admin() || ! $this->blocks->all() ) {
			return;
		}

		$this->assets->register_bundles();
		wp_enqueue_style( 'cfmb-fields' );
		wp_enqueue_style( 'cfmb-blocks' );
	}

	/**
	 * Enqueue the editor script when there are Field Blocks.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$config = $this->config();

		if ( ! $config['blocks'] ) {
			return;
		}

		$groups = array();
		foreach ( array_keys( $config['blocks'] ) as $name ) {
			$groups = array_merge( $groups, $this->renderer->groups( $name ) );
		}

		// Field components, media and editor scripts the forms need.
		if ( $groups ) {
			$this->groups->enqueue( $groups );
		} else {
			$this->assets->enqueue( 'fields', 'cfmbFields', array() );
		}

		$this->assets->enqueue( 'blocks', 'cfmbBlocks', $config );
	}

	/**
	 * Every Field Block with its groups and fields.
	 *
	 * @return array{blocks: array<string, array<string, mixed>>}
	 */
	public function config(): array {
		$blocks = array();

		foreach ( $this->blocks->all() as $name => $definition ) {
			$template = $this->blocks->template( $definition );

			$entry = array(
				'mode'          => $definition->mode(),
				'innerBlocks'   => $this->blocks->uses_inner_blocks( $definition ),
				'templateError' => $template['error'],
				'groups'        => array_map( array( $this->groups, 'block_config' ), $this->renderer->groups( $name ) ),
			);

			/**
			 * Filters what the block editor knows about one Field Block: its
			 * default mode, whether it has an inner blocks area, the message
			 * shown when its template is missing, and its groups.
			 *
			 * @since 0.13.0
			 *
			 * @param array<string, mixed> $entry      mode, innerBlocks, templateError and groups.
			 * @param Block_Definition     $definition Block.
			 */
			$filtered = apply_filters( 'cfmb/blocks/editor_block', $entry, $definition ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			$blocks[ $name ] = is_array( $filtered ) ? $filtered : $entry;
		}

		return array( 'blocks' => $blocks );
	}
}
