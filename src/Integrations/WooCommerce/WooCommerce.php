<?php
/**
 * WooCommerce product and variation fields.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\WooCommerce;

use CFMB\Admin\Form_Saver;
use CFMB\Admin\Group_Renderer;
use CFMB\Admin\Save_Errors;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Location\Rule_Registry;
use CFMB\Schema\Group;
use CFMB\Storage\Object_Ref;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * - Location rules for the product type and for variations.
 * - Groups placed "In the Product data box" show as their own tab there.
 * - Groups on variations show inside each variation's settings and save
 *   with WooCommerce's own variation save.
 * - Dynamic Data can read the current variation (object "variation").
 *
 * Products and variations are posts, so values are post meta as usual.
 * CFMB never touches orders, so it is compatible with HPOS.
 */
class WooCommerce {

	/**
	 * Group setting value that moves a group into the Product data box.
	 */
	public const PRODUCT_DATA = 'product_data';

	/**
	 * Posted array for variations: cfmb_variations[variation_id][group_key].
	 */
	public const VARIATIONS_INPUT = 'cfmb_variations';

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Group output.
	 *
	 * @var Group_Renderer
	 */
	private Group_Renderer $renderer;

	/**
	 * Saving.
	 *
	 * @var Form_Saver
	 */
	private Form_Saver $saver;

	/**
	 * Constructor.
	 *
	 * @param Matcher        $matcher  Location matcher.
	 * @param Group_Renderer $renderer Group output.
	 * @param Form_Saver     $saver    Saving.
	 */
	public function __construct( Matcher $matcher, Group_Renderer $renderer, Form_Saver $saver ) {
		$this->matcher  = $matcher;
		$this->renderer = $renderer;
		$this->saver    = $saver;
	}

	/**
	 * Whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register hooks. Callbacks check for WooCommerce, which loads after CFMB.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
		add_action( 'cfmb/location/register', array( $this, 'register_rules' ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_filter( 'cfmb/display/object', array( $this, 'display_object' ), 10, 2 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'cfmb/admin/meta_box', array( $this, 'skip_meta_box' ), 10, 3 ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		add_action( 'add_meta_boxes_product', array( $this, 'enqueue_variation_fields' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'product_tabs' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'product_panels' ) );
		add_action( 'woocommerce_product_after_variation_settings_fields', array( $this, 'render_variation' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 10, 2 );
	}

	/**
	 * Tell WooCommerce CFMB works with High-Performance Order Storage.
	 *
	 * @return void
	 */
	public function declare_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CFMB_FILE, true );
		}
	}

	/**
	 * Add the product type and variation rules.
	 *
	 * @param Rule_Registry $rules Location rules.
	 * @return void
	 */
	public function register_rules( $rules ): void {
		if ( self::active() && $rules instanceof Rule_Registry ) {
			$rules->add( new Product_Type_Rule() );
			$rules->add( new Product_Variation_Rule() );
		}
	}

	/**
	 * Dynamic Data object "variation": the variation being shown, such as
	 * the global product inside a variations loop.
	 *
	 * @param array{ref: Object_Ref, via: string}|null $entry  Entry from earlier filters.
	 * @param string                                   $object Object argument.
	 * @return array{ref: Object_Ref, via: string}|null
	 */
	public function display_object( $entry, $object = '' ) {
		if ( null !== $entry || 'variation' !== $object || ! self::active() ) {
			return $entry;
		}

		global $product;

		if ( ! $product instanceof \WC_Product_Variation ) {
			return null;
		}

		return array(
			'ref' => new Object_Ref( Object_Ref::POST, (int) $product->get_id() ),
			'via' => '',
		);
	}

	/**
	 * Groups placed in the Product data box are not also meta boxes.
	 *
	 * @param bool    $show  Whether to add the meta box.
	 * @param Group   $group Field Group.
	 * @param WP_Post $post  Post being edited.
	 * @return bool
	 */
	public function skip_meta_box( $show, $group = null, $post = null ) {
		if ( ! $show || ! self::active() || ! $group instanceof Group || ! $post instanceof WP_Post ) {
			return $show;
		}

		return ! ( 'product' === $post->post_type && self::PRODUCT_DATA === $group->setting( 'position' ) );
	}

	/**
	 * Groups that belong in the Product data box of the product being edited.
	 *
	 * @return Group[]
	 */
	private function product_data_groups(): array {
		$post = get_post();

		if ( ! $post instanceof WP_Post || 'product' !== $post->post_type ) {
			return array();
		}

		return array_values(
			array_filter(
				$this->matcher->groups_for( Context::for_post( (int) $post->ID, 'product' ) ),
				static fn( Group $group ) => self::PRODUCT_DATA === $group->setting( 'position' )
			)
		);
	}

	/**
	 * One tab per group in the Product data box.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function product_tabs( $tabs ) {
		$tabs = is_array( $tabs ) ? $tabs : array();

		foreach ( $this->product_data_groups() as $index => $group ) {
			$tabs[ 'cfmb_' . $group->key() ] = array(
				'label'    => $group->title(),
				'target'   => 'cfmb_panel_' . $group->key(),
				'class'    => array( 'cfmb-product-tab' ),
				'priority' => 80 + $index,
			);
		}

		return $tabs;
	}

	/**
	 * Panels for the tabs above. Saved with the product by Form_Saver.
	 *
	 * @return void
	 */
	public function product_panels(): void {
		$post = get_post();

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, (int) $post->ID );

		foreach ( $this->product_data_groups() as $group ) {
			printf( '<div id="%s" class="panel woocommerce_options_panel hidden cfmb-product-panel">', esc_attr( 'cfmb_panel_' . $group->key() ) );
			$this->renderer->render( $group, $ref, 'plain' );
			echo '</div>';
		}
	}

	/**
	 * Variations load over AJAX after the page, so their assets load with the product screen.
	 *
	 * @return void
	 */
	public function enqueue_variation_fields(): void {
		if ( self::active() ) {
			$this->renderer->enqueue( $this->matcher->groups_for( Context::for_post( 0, 'product_variation' ) ) );
		}
	}

	/**
	 * Fields inside one variation's settings.
	 *
	 * @param int     $loop           Variation index on the screen.
	 * @param mixed   $variation_data Variation data. Unused.
	 * @param WP_Post $variation      Variation post.
	 * @return void
	 */
	public function render_variation( $loop, $variation_data = array(), $variation = null ): void {
		if ( ! $variation instanceof WP_Post ) {
			return;
		}

		$id     = (int) $variation->ID;
		$groups = $this->matcher->groups_for( Context::for_post( $id, 'product_variation' ) );

		/**
		 * Filters the groups shown inside a variation's settings.
		 *
		 * @param Group[] $groups    Groups.
		 * @param WP_Post $variation Variation.
		 */
		$groups = (array) apply_filters( 'cfmb/woocommerce/variation_groups', $groups, $variation ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		if ( ! $groups ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, $id );

		echo '<div class="cfmb-variation-fields form-row form-row-full">';

		foreach ( $groups as $group ) {
			if ( ! $group instanceof Group ) {
				continue;
			}

			printf( '<h4 class="cfmb-variation-fields__title">%s</h4>', esc_html( $group->title() ) );
			$this->renderer->render( $group, $ref, 'plain', self::VARIATIONS_INPUT . '[' . $id . ']', false );
		}

		echo '</div>';
	}

	/**
	 * Save one variation's fields.
	 *
	 * WooCommerce saves variations over AJAX (nonce "save-variations") and,
	 * when the product form is submitted, during the product save (CFMB's
	 * own nonce is in that form). Either check is enough.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $index        Variation index in the request. Unused.
	 * @return void
	 */
	public function save_variation( $variation_id, $index = 0 ): void {
		$variation_id = (int) $variation_id;

		// Each entry is a JSON string per group; values are sanitized per field in Form_Saver::save().
		$posted = isset( $_POST[ self::VARIATIONS_INPUT ][ $variation_id ] ) && is_array( $_POST[ self::VARIATIONS_INPUT ][ $variation_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked below.
			? wp_unslash( $_POST[ self::VARIATIONS_INPUT ][ $variation_id ] ) // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			: null;

		if ( null === $posted || ! current_user_can( 'edit_post', $variation_id ) || ! $this->verified() ) {
			return;
		}

		$ref    = new Object_Ref( Object_Ref::POST, $variation_id );
		$errors = $this->saver->save( $ref, Context::for_post( $variation_id, 'product_variation' ), true, Form_Saver::decode_groups( $posted ) );

		Save_Errors::remember( $ref, $errors );
	}

	/**
	 * Nonce check for either way variations are saved.
	 *
	 * @return bool
	 */
	private function verified(): bool {
		if ( wp_doing_ajax() ) {
			return false !== check_ajax_referer( 'save-variations', 'security', false );
		}

		$nonce = isset( $_POST[ Form_Saver::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ Form_Saver::NONCE_NAME ] ) ) : '';

		return false !== wp_verify_nonce( $nonce, Form_Saver::NONCE_ACTION );
	}
}
