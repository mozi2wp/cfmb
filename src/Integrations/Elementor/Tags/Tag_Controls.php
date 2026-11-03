<?php
/**
 * Controls and lookups shared by every CFMB Elementor tag.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor\Tags;

use CFMB\Integrations\Builders\Field_Catalog;
use CFMB\Integrations\Builders\Tag_Values;
use CFMB\Integrations\Elementor\Elementor;
use Elementor\Controls_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Used by tags extending Elementor's Tag or Data_Tag.
 */
trait Tag_Controls {

	/**
	 * Kind of value the tag returns, from Field_Catalog::KINDS.
	 *
	 * @return string
	 */
	abstract protected function field_kind(): string;

	/**
	 * Tag group.
	 *
	 * @return string
	 */
	public function get_group() {
		return Elementor::GROUP;
	}

	/**
	 * The panel shows the chosen field next to the tag name.
	 *
	 * @return string
	 */
	public function get_panel_template_setting_key() {
		return 'field';
	}

	/**
	 * A field must be chosen before the tag shows anything.
	 *
	 * @return bool
	 */
	public function is_settings_required() {
		return true;
	}

	/**
	 * Field, source and object controls.
	 *
	 * @return void
	 */
	protected function add_field_controls(): void {
		$groups = array(
			array(
				'label'   => '',
				'options' => array( '' => __( '— Choose a field —', 'cfmb' ) ),
			),
		);

		$this->add_control(
			'field',
			array(
				'label'   => __( 'Field', 'cfmb' ),
				'type'    => Controls_Manager::SELECT,
				'groups'  => array_merge( $groups, cfmb()->get( Field_Catalog::class )->grouped( $this->field_kind() ) ),
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
	}

	/**
	 * Chosen field path.
	 *
	 * @return string
	 */
	protected function field_path(): string {
		return (string) $this->get_settings( 'field' );
	}

	/**
	 * Object argument from the source controls.
	 *
	 * @return string
	 */
	protected function field_object(): string {
		return Field_Catalog::object_for( (string) $this->get_settings( 'source' ), (string) $this->get_settings( 'object' ) );
	}

	/**
	 * Shared value service.
	 *
	 * @return Tag_Values
	 */
	protected function tag_values(): Tag_Values {
		return cfmb()->get( Tag_Values::class );
	}
}
