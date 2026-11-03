<?php
/**
 * Picks the storage backend for a group.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * Meta for posts, terms, users and comments; options for options pages and widgets;
 * block attributes for Field Blocks. Add-ons swap the backend per group with
 * `cfmb/storage/store`, except for blocks, whose values always travel in the block.
 */
class Store_Resolver {

	/**
	 * Default meta store.
	 *
	 * @var Meta_Store
	 */
	private Meta_Store $meta;

	/**
	 * Default options store.
	 *
	 * @var Option_Store
	 */
	private Option_Store $options;

	/**
	 * Field Block values.
	 *
	 * @var Block_Store
	 */
	private Block_Store $blocks;

	/**
	 * Constructor.
	 *
	 * @param Block_Store|null $blocks Field Block values; shared with the block renderer.
	 */
	public function __construct( ?Block_Store $blocks = null ) {
		$this->meta    = new Meta_Store();
		$this->options = new Option_Store();
		$this->blocks  = $blocks ? $blocks : new Block_Store();
	}

	/**
	 * Field Block values.
	 *
	 * @return Block_Store
	 */
	public function blocks(): Block_Store {
		return $this->blocks;
	}

	/**
	 * Store for a group on an object.
	 *
	 * @param Group|null $group Owning group; null for fields CFMB does not know.
	 * @param Object_Ref $ref   Object.
	 * @return Value_Store
	 */
	public function for_group( ?Group $group, Object_Ref $ref ): Value_Store {
		if ( $ref->is_block() ) {
			return $this->blocks;
		}

		$store = $ref->uses_options() ? $this->options : $this->meta;

		/**
		 * Filters the storage backend for a group on an object.
		 *
		 * @param Value_Store $store Default store.
		 * @param Group|null  $group Field Group.
		 * @param Object_Ref  $ref   Object.
		 */
		$filtered = apply_filters( 'cfmb/storage/store', $store, $group, $ref ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $filtered instanceof Value_Store ? $filtered : $store;
	}
}
