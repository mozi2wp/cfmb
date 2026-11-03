<?php
/**
 * Field Group builder screen.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Content\Content_Repository;
use CFMB\Fields\Field_Type;
use CFMB\Fields\Registry;
use CFMB\Integrations\Graphql\Graphql;
use CFMB\Integrations\Seo\Seo_Analysis;
use CFMB\Integrations\WooCommerce\WooCommerce;
use CFMB\Location\Conditions;
use CFMB\Location\Rule;
use CFMB\Location\Rule_Registry;
use CFMB\Schema\Group_Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Hosts the React app that lists and edits Field Groups, post types,
 * taxonomies and options pages, and shows Schema Sync.
 */
class Builder_Screen {

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Field types.
	 *
	 * @var Registry
	 */
	private Registry $types;

	/**
	 * Location rules.
	 *
	 * @var Rule_Registry
	 */
	private Rule_Registry $rules;

	/**
	 * Content definition repositories.
	 *
	 * @var Content_Repository[]
	 */
	private array $content;

	/**
	 * Constructor.
	 *
	 * @param Assets               $assets  Asset loader.
	 * @param Registry             $types   Field types.
	 * @param Rule_Registry        $rules   Location rules.
	 * @param Content_Repository[] $content Post type, taxonomy and options page repositories.
	 */
	public function __construct( Assets $assets, Registry $types, Rule_Registry $rules, array $content = array() ) {
		$this->assets  = $assets;
		$this->types   = $types;
		$this->rules   = $rules;
		$this->content = $content;
	}

	/**
	 * Enqueue the builder when its screen loads.
	 *
	 * @return void
	 */
	public function load(): void {
		$this->assets->enqueue( 'builder', 'cfmbBuilder', $this->config() );
	}

	/**
	 * Print the app container.
	 *
	 * @return void
	 */
	public function render(): void {
		echo '<div class="wrap cfmb-admin">';
		printf( '<h1 class="screen-reader-text">%s</h1>', esc_html( get_admin_page_title() ) );
		echo '<div id="cfmb-builder" class="cfmb-builder">';
		printf( '<p class="cfmb-builder__loading">%s</p>', esc_html__( 'Loading…', 'custom-fields-meta-box' ) );
		echo '</div>';
		printf( '<noscript><p>%s</p></noscript>', esc_html__( 'The field group editor needs JavaScript. Turn it on in your browser to edit field groups.', 'custom-fields-meta-box' ) );
		echo '</div>';
	}

	/**
	 * Data the builder app needs at start.
	 *
	 * @return array<string, mixed>
	 */
	private function config(): array {
		// Read-only: which builder view the current menu page shows.
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : Menu::SLUG; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$pages = array();

		foreach ( Menu::VIEWS as $slug => $view ) {
			$pages[ $view ] = admin_url( 'admin.php?page=' . $slug );
		}

		$content = array();
		foreach ( $this->content as $repository ) {
			$schema                     = $repository->schema();
			$content[ $schema->kind() ] = $schema->client_config();
		}

		return array(
			'view'               => Menu::VIEWS[ $page ] ?? 'groups',
			'pages'              => $pages,
			'contentKinds'       => $content,
			'settingsUrl'        => admin_url( 'admin.php?page=' . Settings_Screen::SLUG ),
			'adminUrl'           => admin_url( 'admin.php?page=' . Menu::SLUG ),
			'fieldTypes'         => array_values( array_map( static fn( Field_Type $type ) => $type->client_config(), $this->types->all() ) ),
			'categories'         => array(
				'basic'      => __( 'Basic', 'custom-fields-meta-box' ),
				'content'    => __( 'Content', 'custom-fields-meta-box' ),
				'choice'     => __( 'Choice', 'custom-fields-meta-box' ),
				'relational' => __( 'Relational', 'custom-fields-meta-box' ),
				'picker'     => __( 'Pickers', 'custom-fields-meta-box' ),
				'layout'     => __( 'Layout', 'custom-fields-meta-box' ),
				'structure'  => __( 'Structure', 'custom-fields-meta-box' ),
			),
			'keyMaxLength'       => Group_Sanitizer::KEY_MAX_LENGTH,
			// Settings for these plugins only show while they are active.
			'integrations'       => array(
				'translation' => defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_get_post' ),
				'seo'         => array() !== Seo_Analysis::plugins(),
				'woocommerce' => WooCommerce::active(),
				'graphql'     => Graphql::available(),
			),
			'locationRules'      => array_values( array_map( array( $this, 'rule_config' ), $this->rules->all() ) ),
			'conditionOperators' => $this->condition_operators(),
			'choiceTypes'        => array_values(
				array_map(
					static fn( Field_Type $type ) => $type->name(),
					array_filter( $this->types->all(), array( Group_Sanitizer::class, 'has_choices' ) )
				)
			),
			'defaultLocation'    => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'post',
					),
				),
			),
		);
	}

	/**
	 * Builder description of a location rule. Choices load on demand over REST.
	 *
	 * @param Rule $rule Location rule.
	 * @return array<string, mixed>
	 */
	private function rule_config( Rule $rule ): array {
		$operators = array();
		foreach ( $rule->operators() as $value => $label ) {
			$operators[] = array(
				'value' => $value,
				'label' => $label,
			);
		}

		return array(
			'param'     => $rule->param(),
			'label'     => $rule->label(),
			'category'  => $rule->category(),
			'operators' => $operators,
		);
	}

	/**
	 * Conditional logic operators with labels.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function condition_operators(): array {
		$labels = array(
			'=='        => __( 'is equal to', 'custom-fields-meta-box' ),
			'!='        => __( 'is not equal to', 'custom-fields-meta-box' ),
			'empty'     => __( 'is empty', 'custom-fields-meta-box' ),
			'not_empty' => __( 'has any value', 'custom-fields-meta-box' ),
			'contains'  => __( 'contains', 'custom-fields-meta-box' ),
			'>'         => __( 'is greater than', 'custom-fields-meta-box' ),
			'<'         => __( 'is less than', 'custom-fields-meta-box' ),
		);

		$operators = array();
		foreach ( Conditions::operators() as $operator ) {
			$operators[] = array(
				'value'      => $operator,
				'label'      => $labels[ $operator ] ?? $operator,
				'takesValue' => ! in_array( $operator, array( 'empty', 'not_empty' ), true ),
			);
		}

		return $operators;
	}
}
