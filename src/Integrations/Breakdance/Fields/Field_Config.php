<?php
/**
 * Shared setup for CFMB dynamic data fields in Breakdance and Oxygen 6.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use CFMB\Integrations\Builders\Tag_Values;

defined( 'ABSPATH' ) || exit;

/**
 * One field instance is registered per CFMB field and kind of value, so
 * the Breakdance picker lists them by name under "CFMB", with the Field
 * Group as subcategory.
 */
trait Field_Config {

	/**
	 * Field path (see Field_Source).
	 *
	 * @var string
	 */
	private string $path;

	/**
	 * Label in the picker.
	 *
	 * @var string
	 */
	private string $label;

	/**
	 * Field Group title.
	 *
	 * @var string
	 */
	private string $group;

	/**
	 * Object argument, e.g. option:footer; empty for the current post.
	 *
	 * @var string
	 */
	private string $object;

	/**
	 * Unique slug.
	 *
	 * @var string
	 */
	private string $slug;

	/**
	 * Values.
	 *
	 * @var Tag_Values
	 */
	private Tag_Values $values;

	/**
	 * Constructor.
	 *
	 * @param array{path: string, label: string, group: string, object: string, slug: string} $config Field setup.
	 * @param Tag_Values                                                                      $values Values.
	 */
	public function __construct( array $config, Tag_Values $values ) {
		$this->path   = $config['path'];
		$this->label  = $config['label'];
		$this->group  = $config['group'];
		$this->object = $config['object'];
		$this->slug   = $config['slug'];
		$this->values = $values;
	}

	/**
	 * Label in the picker.
	 *
	 * @return string
	 */
	public function label(): string {
		return $this->label;
	}

	/**
	 * Picker category.
	 *
	 * @return string
	 */
	public function category(): string {
		return __( 'CFMB', 'custom-fields-meta-box' );
	}

	/**
	 * Picker subcategory: the Field Group.
	 *
	 * @return string
	 */
	public function subcategory(): string {
		return $this->group;
	}

	/**
	 * Unique slug.
	 *
	 * @return string
	 */
	public function slug(): string {
		return $this->slug;
	}
}
