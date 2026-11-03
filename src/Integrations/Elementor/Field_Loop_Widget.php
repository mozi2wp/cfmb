<?php
/**
 * Elementor widget: Field Loop.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Loop_Items;
use Elementor\Controls_Manager;
use Elementor\Plugin as Elementor_Plugin;
use Elementor\Widget_Base;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Repeats a saved Elementor template (a section, container, page or Loop
 * Item template) for each row of a Repeater or Stacks field, each image of
 * a Gallery, or each post of a Post or Relationship field. Inside the
 * template, CFMB tags read the current item: pick "Items › Title" or a
 * "Loop item" entry in the tag's field list.
 */
class Field_Loop_Widget extends Widget_Base {

	/**
	 * Widget name.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'cfmb-field-loop';
	}

	/**
	 * Widget title.
	 *
	 * @return string
	 */
	public function get_title() {
		return __( 'Field Loop', 'cfmb' );
	}

	/**
	 * Widget icon.
	 *
	 * @return string
	 */
	public function get_icon() {
		return 'eicon-loop-builder';
	}

	/**
	 * Widget categories.
	 *
	 * @return string[]
	 */
	public function get_categories() {
		return array( Elementor::GROUP );
	}

	/**
	 * Search keywords.
	 *
	 * @return string[]
	 */
	public function get_keywords() {
		return array( 'cfmb', 'loop', 'repeater', 'stacks', 'gallery', 'relationship', 'rows' );
	}

	/**
	 * Output depends on the post being shown, so Elementor must not cache it.
	 *
	 * @return bool
	 */
	protected function is_dynamic_content(): bool {
		return true;
	}

	/**
	 * Controls.
	 *
	 * @return void
	 */
	protected function register_controls() {
		$this->start_controls_section( 'content', array( 'label' => __( 'Loop', 'cfmb' ) ) );

		$this->add_control(
			'field',
			array(
				'label'   => __( 'Loop over', 'cfmb' ),
				'type'    => Controls_Manager::SELECT,
				'groups'  => array_merge(
					array(
						array(
							'label'   => '',
							'options' => array( '' => __( '— Choose a field —', 'cfmb' ) ),
						),
					),
					cfmb()->get( Field_Catalog::class )->grouped( 'loop' )
				),
				'default' => '',
			)
		);

		$this->add_control(
			'source',
			array(
				'label'   => __( 'Read from', 'cfmb' ),
				'type'    => Controls_Manager::SELECT,
				'options' => Field_Catalog::sources(),
				'default' => '',
			)
		);

		$this->add_control(
			'object',
			array(
				'label'       => __( 'Object', 'cfmb' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'With "Options page": the page ID, empty for the default page. With "Other": post_12, term_5, user_3 or option:{page}.', 'cfmb' ),
				'condition'   => array( 'source' => array( 'option', 'custom' ) ),
			)
		);

		$this->add_control(
			'template',
			array(
				'label'       => __( 'Template', 'cfmb' ),
				'type'        => Controls_Manager::SELECT,
				'options'     => array( '' => __( '— Choose a template —', 'cfmb' ) ) + $this->templates(),
				'default'     => '',
				'description' => __( 'Shown once per item. Make one under Templates > Saved Templates.', 'cfmb' ),
			)
		);

		$this->add_control(
			'layouts',
			array(
				'label'       => __( 'Only these Stacks layouts', 'cfmb' ),
				'type'        => Controls_Manager::TEXT,
				'description' => __( 'Layout names, separated by commas. Leave empty for all.', 'cfmb' ),
			)
		);

		$this->add_control(
			'limit',
			array(
				'label'   => __( 'Number of items', 'cfmb' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 0,
				'description' => __( '0 shows all.', 'cfmb' ),
			)
		);

		$this->add_control(
			'offset',
			array(
				'label'   => __( 'Skip first', 'cfmb' ),
				'type'    => Controls_Manager::NUMBER,
				'min'     => 0,
				'default' => 0,
			)
		);

		$this->add_control(
			'empty',
			array(
				'label' => __( 'Text when there are no items', 'cfmb' ),
				'type'  => Controls_Manager::TEXT,
			)
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'layout',
			array(
				'label' => __( 'Layout', 'cfmb' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'columns',
			array(
				'label'     => __( 'Columns', 'cfmb' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => array_combine( array( '1', '2', '3', '4', '5', '6' ), array( '1', '2', '3', '4', '5', '6' ) ),
				'default'   => '1',
				'selectors' => array(
					'{{WRAPPER}} .cfmb-field-loop' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Gap', 'cfmb' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em', 'rem' ),
				'default'    => array(
					'size' => 20,
					'unit' => 'px',
				),
				'selectors'  => array(
					'{{WRAPPER}} .cfmb-field-loop' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Print the loop.
	 *
	 * @return void
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		$template = absint( $settings['template'] ?? 0 );
		$editing  = Elementor_Plugin::instance()->editor->is_edit_mode();

		if ( ! $this->usable_template( $template ) ) {
			if ( $editing ) {
				echo '<p class="cfmb-field-loop__notice">' . esc_html__( 'Choose a field and a saved template to show here.', 'cfmb' ) . '</p>';
			}

			return;
		}

		$loops  = cfmb()->get( Loop_Items::class );
		$object = Field_Catalog::object_for( (string) ( $settings['source'] ?? '' ), (string) ( $settings['object'] ?? '' ) );
		$items  = $loops->collect(
			(string) ( $settings['field'] ?? '' ),
			$object,
			0,
			array(
				'layout' => (string) ( $settings['layouts'] ?? '' ),
				'limit'  => (int) ( $settings['limit'] ?? 0 ),
				'offset' => (int) ( $settings['offset'] ?? 0 ),
			)
		);

		if ( ! $items ) {
			$empty = (string) ( $settings['empty'] ?? '' );

			if ( '' !== $empty ) {
				echo '<p class="cfmb-field-loop__empty">' . esc_html( $empty ) . '</p>';
			} elseif ( $editing ) {
				echo '<p class="cfmb-field-loop__notice">' . esc_html__( 'No items for this field on the post being previewed.', 'cfmb' ) . '</p>';
			}

			return;
		}

		$frontend = Elementor_Plugin::instance()->frontend;
		$with_css = true;

		$html = $loops->render(
			$items,
			static function () use ( $frontend, $template, &$with_css ): string {
				// Template CSS is printed with the first item only.
				$content  = (string) $frontend->get_builder_content_for_display( $template, $with_css );
				$with_css = false;

				return '<div class="cfmb-field-loop__item">' . $content . '</div>';
			}
		);

		echo '<div class="cfmb-field-loop" style="display:grid">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Elementor renders and escapes template content.
	}

	/**
	 * Whether a template can be shown: a published Elementor template that
	 * is not the document being rendered.
	 *
	 * @param int $template Template ID.
	 * @return bool
	 */
	private function usable_template( int $template ): bool {
		$post = $template ? get_post( $template ) : null;

		return $post instanceof WP_Post
			&& 'elementor_library' === $post->post_type
			&& 'publish' === $post->post_status
			&& get_the_ID() !== $template;
	}

	/**
	 * Saved templates for the picker.
	 *
	 * @return array<int, string>
	 */
	private function templates(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$choices = array();

		foreach ( $posts as $post ) {
			$type = (string) get_post_meta( $post->ID, '_elementor_template_type', true );
			/* translators: 1: template title, 2: template type such as section or loop-item. */
			$choices[ $post->ID ] = '' !== $type ? sprintf( __( '%1$s (%2$s)', 'cfmb' ), $post->post_title, $type ) : $post->post_title;
		}

		return $choices;
	}
}
