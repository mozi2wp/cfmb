<?php
/**
 * Beaver Builder module: Field Loop.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Beaver;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Loop_Items;
use CFMB\Integrations\Builders\Loop_Markup;
use FLBuilderModel;
use FLBuilderModule;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Repeats a saved row, column or layout (Beaver Builder > Saved) for each
 * row of a Repeater or Stacks field, each image of a Gallery, or each post
 * of a Post or Relationship field. Inside it, CFMB field connections read
 * the current item.
 *
 * Only loaded from Beaver::register_module(), after FLBuilderModule exists.
 */
class Field_Loop_Module extends FLBuilderModule {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'name'            => __( 'Field Loop', 'custom-fields-meta-box' ),
				'description'     => __( 'Repeat a saved row for each Repeater row, gallery image or linked post.', 'custom-fields-meta-box' ),
				'category'        => __( 'CFMB', 'custom-fields-meta-box' ),
				'dir'             => CFMB_DIR . 'src/Integrations/Beaver/field-loop/',
				'url'             => CFMB_URL . 'src/Integrations/Beaver/field-loop/',
				'partial_refresh' => true,
			)
		);
	}

	/**
	 * Settings form.
	 *
	 * @return array<string, mixed>
	 */
	public static function form(): array {
		$catalog = cfmb()->get( Field_Catalog::class );

		return array(
			'general' => array(
				'title'    => __( 'Loop', 'custom-fields-meta-box' ),
				'sections' => array(
					'loop'   => array(
						'title'  => '',
						'fields' => array(
							'cfmb_field'    => array(
								'type'    => 'select',
								'label'   => __( 'Loop over', 'custom-fields-meta-box' ),
								'default' => '',
								'options' => array( '' => __( '— Choose a field —', 'custom-fields-meta-box' ) ) + $catalog->flat( 'loop' ),
							),
							'cfmb_source'   => array(
								'type'    => 'select',
								'label'   => __( 'Read from', 'custom-fields-meta-box' ),
								'default' => '',
								'options' => Field_Catalog::sources(),
								'toggle'  => array(
									'option' => array( 'fields' => array( 'cfmb_object' ) ),
									'custom' => array( 'fields' => array( 'cfmb_object' ) ),
								),
							),
							'cfmb_object'   => array(
								'type'        => 'text',
								'label'       => __( 'Object', 'custom-fields-meta-box' ),
								'description' => __( 'Options page ID, or post_12, term_5, user_3.', 'custom-fields-meta-box' ),
							),
							'cfmb_template' => array(
								'type'        => 'select',
								'label'       => __( 'Saved row or layout', 'custom-fields-meta-box' ),
								'default'     => '',
								'options'     => array( '' => __( '— Choose a template —', 'custom-fields-meta-box' ) ) + self::templates(),
								'description' => __( 'Shown once per item. Save a row from its settings menu to use it here.', 'custom-fields-meta-box' ),
							),
							'cfmb_layouts'  => array(
								'type'        => 'text',
								'label'       => __( 'Only these Stacks layouts', 'custom-fields-meta-box' ),
								'description' => __( 'Layout names, separated by commas. Leave empty for all.', 'custom-fields-meta-box' ),
							),
							'cfmb_limit'    => array(
								'type'        => 'unit',
								'label'       => __( 'Number of items', 'custom-fields-meta-box' ),
								'default'     => '0',
								'description' => __( '0 shows all.', 'custom-fields-meta-box' ),
							),
							'cfmb_offset'   => array(
								'type'    => 'unit',
								'label'   => __( 'Skip first', 'custom-fields-meta-box' ),
								'default' => '0',
							),
							'cfmb_empty'    => array(
								'type'  => 'text',
								'label' => __( 'Text when there are no items', 'custom-fields-meta-box' ),
							),
						),
					),
					'layout' => array(
						'title'  => __( 'Layout', 'custom-fields-meta-box' ),
						'fields' => array(
							'cfmb_columns' => array(
								'type'    => 'select',
								'label'   => __( 'Columns', 'custom-fields-meta-box' ),
								'default' => '1',
								'options' => array_combine( array( '1', '2', '3', '4', '5', '6' ), array( '1', '2', '3', '4', '5', '6' ) ),
							),
							'cfmb_gap'     => array(
								'type'        => 'text',
								'label'       => __( 'Gap', 'custom-fields-meta-box' ),
								'default'     => '20px',
								'description' => __( 'A CSS length such as 20px or 1.5em.', 'custom-fields-meta-box' ),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Output for frontend.php.
	 *
	 * @return string
	 */
	public function loop_html(): string {
		$s        = is_object( $this->settings ) ? get_object_vars( $this->settings ) : array();
		$template = $this->template_post( absint( $s['cfmb_template'] ?? 0 ) );
		$editing  = class_exists( 'FLBuilderModel' ) && FLBuilderModel::is_builder_active();

		if ( ! $template ) {
			return $editing ? Loop_Markup::notice( __( 'Choose a field and a saved row to show here.', 'custom-fields-meta-box' ) ) : '';
		}

		$loops = cfmb()->get( Loop_Items::class );
		$items = $loops->collect(
			(string) ( $s['cfmb_field'] ?? '' ),
			Field_Catalog::object_for( (string) ( $s['cfmb_source'] ?? '' ), (string) ( $s['cfmb_object'] ?? '' ) ),
			0,
			array(
				'layout' => (string) ( $s['cfmb_layouts'] ?? '' ),
				'limit'  => (int) ( $s['cfmb_limit'] ?? 0 ),
				'offset' => (int) ( $s['cfmb_offset'] ?? 0 ),
			)
		);

		if ( ! $items ) {
			return Loop_Markup::empty( (string) ( $s['cfmb_empty'] ?? '' ), $editing );
		}

		$id   = (int) $template->ID;
		$html = $loops->render(
			$items,
			static function () use ( $id ): string {
				ob_start();
				\FLBuilder::render_content_by_id( $id );

				return Loop_Markup::item( (string) ob_get_clean() );
			}
		);

		return Loop_Markup::grid( $html, (int) ( $s['cfmb_columns'] ?? 1 ), (string) ( $s['cfmb_gap'] ?? '20px' ) );
	}

	/**
	 * A published saved template that isn't the post being rendered.
	 *
	 * @param int $id Template ID.
	 * @return WP_Post|null
	 */
	private function template_post( int $id ): ?WP_Post {
		$post = $id ? get_post( $id ) : null;

		return $post instanceof WP_Post && 'fl-builder-template' === $post->post_type && 'publish' === $post->post_status && get_the_ID() !== $id
			? $post
			: null;
	}

	/**
	 * Saved rows, columns, modules and layouts for the picker.
	 *
	 * @return array<int, string>
	 */
	private static function templates(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'fl-builder-template',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- Template picker in the builder; one query of IDs and titles.
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$choices = array();
		foreach ( $posts as $post ) {
			$choices[ $post->ID ] = $post->post_title;
		}

		return $choices;
	}
}
