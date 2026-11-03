<?php
/**
 * Contract for field types that hold other fields.
 *
 * @package CFMB
 */

namespace CFMB\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Group, Repeater and Stacks. Their values are made of sub-field values,
 * each stored under its own flattened name (hero_title, items_0_title).
 * Field_Values walks them; the type only describes the shape.
 */
interface Structure_Field extends Field_Type {

	/**
	 * One set of sub-fields: hero_title.
	 */
	public const GROUP = 'group';

	/**
	 * Rows of the same sub-fields: items = 2, items_0_title.
	 */
	public const ROWS = 'rows';

	/**
	 * Rows that each pick a layout: sections = hero, cta; sections_0_title.
	 */
	public const STACKS = 'stacks';

	/**
	 * Which shape this type stores: one of the constants above.
	 *
	 * @return string
	 */
	public function structure(): string;
}
