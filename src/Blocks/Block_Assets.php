<?php
/**
 * Style and script files that sit next to a Field Block template.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * hero.php can come with hero.css (front end and editor) and hero.js (front
 * end only). They are registered as the block's own handles, so WordPress
 * loads them only on pages where the block is rendered.
 */
class Block_Assets {

	/**
	 * Template finder.
	 *
	 * @var Template_Locator
	 */
	private Template_Locator $locator;

	/**
	 * Constructor.
	 *
	 * @param Template_Locator $locator Template finder.
	 */
	public function __construct( Template_Locator $locator ) {
		$this->locator = $locator;
	}

	/**
	 * Register the files next to a template and return the block type arguments for them.
	 *
	 * @param Block_Definition $definition Block.
	 * @param string           $template   Template path.
	 * @return array<string, string[]> style_handles and view_script_handles.
	 */
	public function register_for( Block_Definition $definition, string $template ): array {
		$args   = array();
		$handle = 'cfmb-block-' . str_replace( '/', '-', $definition->name() );
		$style  = $this->locator->sibling( $template, 'css' );
		$script = $this->locator->sibling( $template, 'js' );

		if ( '' !== $style && '' !== $this->locator->url( $style ) ) {
			wp_register_style( $handle, $this->locator->url( $style ), array(), (string) filemtime( $style ) );
			$args['style_handles'] = array( $handle );
		}

		if ( '' !== $script && '' !== $this->locator->url( $script ) ) {
			wp_register_script(
				$handle,
				$this->locator->url( $script ),
				array(),
				(string) filemtime( $script ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			$args['view_script_handles'] = array( $handle );
		}

		return $args;
	}
}
