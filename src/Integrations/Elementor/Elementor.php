<?php
/**
 * Elementor integration: dynamic tags and the Field Loop widget.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Elementor;

defined( 'ABSPATH' ) || exit;

/**
 * Hooks only fire when Elementor is active, so the tag and widget classes
 * (which extend Elementor's own) are never loaded without it.
 *
 * Tags sit in a "CFMB" group, one per kind of value: Field (text), URL,
 * Image, Gallery, Number, Color and Date. Each has a field picker grouped by
 * Field Group and an object picker. The Field Loop widget repeats a saved
 * Elementor template for each Repeater or Stacks row, gallery image or
 * linked post.
 */
class Elementor {

	public const GROUP = 'cfmb';

	/**
	 * Tag classes, registered in this order.
	 */
	private const TAGS = array(
		Tags\Field_Tag::class,
		Tags\Url_Tag::class,
		Tags\Image_Tag::class,
		Tags\Gallery_Tag::class,
		Tags\Number_Tag::class,
		Tags\Color_Tag::class,
		Tags\Date_Tag::class,
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'elementor/dynamic_tags/register', array( $this, 'register_tags' ) );
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Add the CFMB tag group and tags.
	 *
	 * @param \Elementor\Core\DynamicTags\Manager $manager Dynamic tags manager.
	 * @return void
	 */
	public function register_tags( $manager ): void {
		$manager->register_group( self::GROUP, array( 'title' => __( 'CFMB', 'custom-fields-meta-box' ) ) );

		foreach ( self::TAGS as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * Add the CFMB widget category.
	 *
	 * @param \Elementor\Elements_Manager $manager Elements manager.
	 * @return void
	 */
	public function register_category( $manager ): void {
		$manager->add_category(
			self::GROUP,
			array(
				'title' => __( 'CFMB', 'custom-fields-meta-box' ),
				'icon'  => 'eicon-database',
			)
		);
	}

	/**
	 * Add the Field Loop widget.
	 *
	 * @param \Elementor\Widgets_Manager $manager Widgets manager.
	 * @return void
	 */
	public function register_widgets( $manager ): void {
		$manager->register( new Field_Loop_Widget() );
	}
}
