<?php
/**
 * Block location rule.
 *
 * @package CFMB
 */

namespace CFMB\Location\Rules;

use CFMB\Blocks\Block_Registry;
use CFMB\Core\Plugin;
use CFMB\Location\Abstract_Rule;
use CFMB\Location\Context;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a group's fields to a Field Block, on top of the group that defines
 * it. Blocks registered from a theme block.json get all their fields this way.
 */
class Block_Rule extends Abstract_Rule {

	/**
	 * Rule key.
	 *
	 * @return string
	 */
	public function param(): string {
		return 'block';
	}

	/**
	 * Label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Block', 'custom-fields-meta-box' );
	}

	/**
	 * Category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'Forms', 'custom-fields-meta-box' );
	}

	/**
	 * Field Blocks by name.
	 *
	 * @return array<string, string>
	 */
	public function choices(): array {
		$choices = array( self::ALL => __( 'All Field Blocks', 'custom-fields-meta-box' ) );

		foreach ( Plugin::instance()->get( Block_Registry::class )->all() as $name => $definition ) {
			$choices[ $name ] = $definition->title();
		}

		return $choices;
	}

	/**
	 * Check the block name.
	 *
	 * @param Context $context Screen or object.
	 * @param string  $value   Rule value.
	 * @return bool|null
	 */
	protected function context_has( Context $context, string $value ): ?bool {
		if ( Context::BLOCK !== $context->kind() ) {
			return null;
		}

		return self::ALL === $value || $context->get( 'block' ) === $value;
	}
}
