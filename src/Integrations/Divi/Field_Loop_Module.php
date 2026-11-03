<?php
/**
 * Divi module: Field Loop.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Divi;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Loop_Items;
use ET_Builder_Module;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Repeats a Divi Library layout for each row of a Repeater or Stacks field,
 * each image of a Gallery, or each post of a Post or Relationship field.
 * Inside the layout, CFMB dynamic content reads the current item.
 *
 * Built on the Divi 4 module API. The Visual Builder renders it on the
 * server (partial support). Divi 5 runs it through its Divi 4 module
 * compatibility.
 *
 * Only loaded from Divi::register_module(), after ET_Builder_Module exists.
 */
class Field_Loop_Module extends ET_Builder_Module {

	/**
	 * Module slug, also the shortcode name.
	 *
	 * @var string
	 */
	public $slug = 'cfmb_field_loop';

	/**
	 * The Visual Builder asks the server to render this module.
	 *
	 * @var string
	 */
	public $vb_support = 'partial';

	/**
	 * Module name and settings groups.
	 *
	 * @return void
	 */
	public function init() {
		$this->name = __( 'Field Loop', 'custom-fields-meta-box' );

		$this->settings_modal_toggles = array(
			'general' => array(
				'toggles' => array(
					'main_content' => __( 'Loop', 'custom-fields-meta-box' ),
					'loop_layout'  => __( 'Layout', 'custom-fields-meta-box' ),
				),
			),
		);
	}

	/**
	 * Module settings.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_fields() {
		$sources = array();
		foreach ( Field_Catalog::sources() as $value => $label ) {
			$sources[ '' === $value ? 'current' : $value ] = $label;
		}

		$basic = array(
			'option_category' => 'basic_option',
			'toggle_slug'     => 'main_content',
		);

		return array(
			'cfmb_field'      => $basic + array(
				'label'   => __( 'Loop over', 'custom-fields-meta-box' ),
				'type'    => 'select',
				'options' => array( '' => __( '— Choose a field —', 'custom-fields-meta-box' ) ) + cfmb()->get( Field_Catalog::class )->flat( 'loop' ),
				'default' => '',
			),
			'cfmb_source'     => $basic + array(
				'label'   => __( 'Read from', 'custom-fields-meta-box' ),
				'type'    => 'select',
				'options' => $sources,
				'default' => 'current',
			),
			'cfmb_object'     => $basic + array(
				'label'       => __( 'Object', 'custom-fields-meta-box' ),
				'type'        => 'text',
				'description' => __( 'With "Options page": the page ID, empty for the default page. With "Other": post_12, term_5, user_3 or option:{page}.', 'custom-fields-meta-box' ),
			),
			'cfmb_layout_id'  => $basic + array(
				'label'       => __( 'Layout', 'custom-fields-meta-box' ),
				'type'        => 'select',
				'options'     => array( '' => __( '— Choose a layout —', 'custom-fields-meta-box' ) ) + $this->layouts(),
				'default'     => '',
				'description' => __( 'A Divi Library layout, shown once per item.', 'custom-fields-meta-box' ),
			),
			'cfmb_layouts'    => $basic + array(
				'label'       => __( 'Only these Stacks layouts', 'custom-fields-meta-box' ),
				'type'        => 'text',
				'description' => __( 'Layout names, separated by commas. Leave empty for all.', 'custom-fields-meta-box' ),
			),
			'cfmb_limit'      => $basic + array(
				'label'       => __( 'Number of items', 'custom-fields-meta-box' ),
				'type'        => 'text',
				'default'     => '0',
				'description' => __( '0 shows all.', 'custom-fields-meta-box' ),
			),
			'cfmb_offset'     => $basic + array(
				'label'   => __( 'Skip first', 'custom-fields-meta-box' ),
				'type'    => 'text',
				'default' => '0',
			),
			'cfmb_empty_text' => $basic + array(
				'label' => __( 'Text when there are no items', 'custom-fields-meta-box' ),
				'type'  => 'text',
			),
			'cfmb_columns'    => array(
				'label'           => __( 'Columns', 'custom-fields-meta-box' ),
				'type'            => 'select',
				'options'         => array_combine( array( '1', '2', '3', '4', '5', '6' ), array( '1', '2', '3', '4', '5', '6' ) ),
				'default'         => '1',
				'option_category' => 'layout',
				'toggle_slug'     => 'loop_layout',
			),
			'cfmb_gap'        => array(
				'label'           => __( 'Gap', 'custom-fields-meta-box' ),
				'type'            => 'text',
				'default'         => '20px',
				'option_category' => 'layout',
				'toggle_slug'     => 'loop_layout',
				'description'     => __( 'A CSS length such as 20px or 1.5em.', 'custom-fields-meta-box' ),
			),
		);
	}

	/**
	 * Module output.
	 *
	 * @param array<string, mixed> $attrs       Shortcode attributes.
	 * @param string|null          $content     Inner content.
	 * @param string               $render_slug Slug being rendered.
	 * @return string
	 */
	public function render( $attrs, $content, $render_slug ) {
		$props  = is_array( $this->props ) ? $this->props : array();
		$layout = $this->layout_post( absint( $props['cfmb_layout_id'] ?? 0 ) );

		if ( ! $layout ) {
			return '';
		}

		$source = (string) ( $props['cfmb_source'] ?? 'current' );
		$loops  = cfmb()->get( Loop_Items::class );
		$items  = $loops->collect(
			(string) ( $props['cfmb_field'] ?? '' ),
			Field_Catalog::object_for( 'current' === $source ? '' : $source, (string) ( $props['cfmb_object'] ?? '' ) ),
			0,
			array(
				'layout' => (string) ( $props['cfmb_layouts'] ?? '' ),
				'limit'  => (int) ( $props['cfmb_limit'] ?? 0 ),
				'offset' => (int) ( $props['cfmb_offset'] ?? 0 ),
			)
		);

		if ( ! $items ) {
			$empty = (string) ( $props['cfmb_empty_text'] ?? '' );

			return '' !== $empty ? '<p class="cfmb-field-loop__empty">' . esc_html( $empty ) . '</p>' : '';
		}

		$layout_content = $layout->post_content;
		$html           = $loops->render(
			$items,
			static fn(): string => '<div class="cfmb-field-loop__item">' . do_shortcode( $layout_content ) . '</div>'
		);

		$columns = max( 1, min( 6, (int) ( $props['cfmb_columns'] ?? 1 ) ) );
		$gap     = (string) ( $props['cfmb_gap'] ?? '20px' );
		$gap     = 1 === preg_match( '/^\d+(\.\d+)?(px|em|rem|%|vw)$/', $gap ) ? $gap : '20px';
		$style   = sprintf( 'display:grid;grid-template-columns:repeat(%d,minmax(0,1fr));gap:%s', $columns, $gap );

		return '<div class="cfmb-field-loop" style="' . esc_attr( $style ) . '">' . $html . '</div>';
	}

	/**
	 * A published Divi Library layout that isn't the page being rendered.
	 *
	 * @param int $id Layout ID.
	 * @return WP_Post|null
	 */
	private function layout_post( int $id ): ?WP_Post {
		$post = $id ? get_post( $id ) : null;

		return $post instanceof WP_Post && 'et_pb_layout' === $post->post_type && 'publish' === $post->post_status && get_the_ID() !== $id
			? $post
			: null;
	}

	/**
	 * Library layouts for the picker.
	 *
	 * @return array<int, string>
	 */
	private function layouts(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'et_pb_layout',
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
