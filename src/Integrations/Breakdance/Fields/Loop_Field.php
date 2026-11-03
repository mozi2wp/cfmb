<?php
/**
 * Breakdance / Oxygen 6 dynamic data: a CFMB Field Loop for the Repeater
 * Fields element.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Breakdance\Fields;

use Breakdance\DynamicData\RepeaterData;
use Breakdance\DynamicData\RepeaterField;
use CFMB\Integrations\Builders\Loop_Items;

defined( 'ABSPATH' ) || exit;

/**
 * Repeater rows, Stacks rows, gallery images or linked posts, one entry
 * per item. When the element moves to an item, that item opens in
 * Loop_Context, so CFMB fields inside read it ("Items › Title", "Loop item").
 */
class Loop_Field extends RepeaterField {

	use Field_Config;


	/**
	 * Items from the last handler() call.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $items = array();

	/**
	 * Item open now, if any.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $open = null;

	/**
	 * Rows for the Repeater Fields element.
	 *
	 * @param mixed $attributes Control values.
	 * @return RepeaterData
	 */
	public function handler( $attributes ): RepeaterData {
		$this->close();
		$this->items = $this->loops()->collect( $this->path, $this->object );

		$rows = array();
		foreach ( $this->items as $position => $item ) {
			$rows[] = array(
				'index' => $position,
				'kind'  => (string) $item['kind'],
			);
		}

		return RepeaterData::fromArray( $rows );
	}

	/**
	 * CFMB fields inside the loop read the open item.
	 *
	 * @param mixed $post_id Post ID.
	 * @return bool
	 */
	public function hasSubFields( $post_id = null ) {
		return true;
	}

	/**
	 * Move to an item; the one before closes.
	 *
	 * @param mixed $index Zero-based position, or null to close.
	 * @return void
	 */
	public function setSubFieldIndex( $index ) {
		$this->close();

		if ( is_numeric( $index ) && isset( $this->items[ (int) $index ] ) ) {
			$this->open = $this->items[ (int) $index ];
			$this->loops()->enter( $this->open );
		}
	}

	/**
	 * CFMB loops are not nested Breakdance repeaters.
	 *
	 * @return null
	 */
	public function parentField() {
		return null;
	}

	/**
	 * Close the open item.
	 *
	 * @return void
	 */
	public function close(): void {
		if ( null !== $this->open ) {
			$this->loops()->leave( $this->open );
			$this->open = null;
		}
	}

	/**
	 * Loop items service.
	 *
	 * @return Loop_Items
	 */
	private function loops(): Loop_Items {
		return cfmb()->get( Loop_Items::class );
	}
}
