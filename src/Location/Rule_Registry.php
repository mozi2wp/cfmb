<?php
/**
 * Location rule registry.
 *
 * @package CFMB
 */

namespace CFMB\Location;

defined( 'ABSPATH' ) || exit;

/**
 * Holds every location rule.
 */
class Rule_Registry {

	/**
	 * Rules by param.
	 *
	 * @var array<string, Rule>
	 */
	private array $rules = array();

	/**
	 * Whether rules are registered.
	 *
	 * @var bool
	 */
	private bool $ready = false;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'load' ), 5 );
	}

	/**
	 * Register core rules, then let add-ons add theirs.
	 *
	 * @return void
	 */
	public function load(): void {
		if ( $this->ready ) {
			return;
		}

		$this->ready = true;

		$core = array(
			new Rules\Post_Type_Rule(),
			new Rules\Page_Template_Rule(),
			new Rules\Post_Status_Rule(),
			new Rules\Post_Format_Rule(),
			new Rules\Post_Term_Rule(),
			new Rules\Taxonomy_Rule(),
			new Rules\User_Role_Rule(),
			new Rules\User_Form_Rule(),
			new Rules\Options_Page_Rule(),
			new Rules\Comment_Rule(),
			new Rules\Attachment_Rule(),
			new Rules\Menu_Rule(),
			new Rules\Menu_Item_Rule(),
			new Rules\Widget_Rule(),
			new Rules\Block_Rule(),
		);

		foreach ( $core as $rule ) {
			$this->add( $rule );
		}

		/**
		 * Fires when location rules can be registered.
		 *
		 * @param Rule_Registry $registry Rule registry. Call ->add( $rule ).
		 */
		do_action( 'cfmb/location/register', $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Add or replace a rule.
	 *
	 * @param Rule $rule Rule.
	 * @return void
	 */
	public function add( Rule $rule ): void {
		$this->rules[ $rule->param() ] = $rule;
	}

	/**
	 * Get a rule by param.
	 *
	 * @param string $param Rule key.
	 * @return Rule|null
	 */
	public function get( string $param ): ?Rule {
		$this->load();

		return $this->rules[ $param ] ?? null;
	}

	/**
	 * All rules.
	 *
	 * @return array<string, Rule>
	 */
	public function all(): array {
		$this->load();

		return $this->rules;
	}
}
