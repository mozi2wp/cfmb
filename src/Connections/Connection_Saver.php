<?php
/**
 * Saves links posted by the Connection boxes.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Storage\Object_Ref;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Runs with the normal post, term and user saves. Every request needs the
 * box nonce and edit rights on the object. Each posted ID must exist, fit
 * the other side's kind and types, and be one the user may pick; IDs that
 * were already linked stay even if the user couldn't pick them now.
 */
class Connection_Saver {

	/**
	 * Active connections.
	 *
	 * @var Connection_Registry
	 */
	private Connection_Registry $registry;

	/**
	 * Links.
	 *
	 * @var Connection_Store
	 */
	private Connection_Store $store;

	/**
	 * Constructor.
	 *
	 * @param Connection_Registry $registry Active connections.
	 * @param Connection_Store    $store    Links.
	 */
	public function __construct( Connection_Registry $registry, Connection_Store $store ) {
		$this->registry = $registry;
		$this->store    = $store;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'save_post', array( $this, 'save_post' ), 20, 2 );
		add_action( 'edited_term', array( $this, 'save_term' ), 20, 1 );
		add_action( 'personal_options_update', array( $this, 'save_user' ), 20 );
		add_action( 'edit_user_profile_update', array( $this, 'save_user' ), 20 );
	}

	/**
	 * Post save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 * @return void
	 */
	public function save_post( $post_id, $post = null ): void {
		$post_id = (int) $post_id;

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$this->save( new Object_Ref( Object_Ref::POST, $post_id ) );
	}

	/**
	 * Term save.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term( $term_id ): void {
		if ( current_user_can( 'edit_term', (int) $term_id ) ) {
			$this->save( new Object_Ref( Object_Ref::TERM, (int) $term_id ) );
		}
	}

	/**
	 * User save.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_user( $user_id ): void {
		if ( current_user_can( 'edit_user', (int) $user_id ) ) {
			$this->save( new Object_Ref( Object_Ref::USER, (int) $user_id ) );
		}
	}

	/**
	 * Save every box posted for an object.
	 *
	 * @param Object_Ref $ref Object.
	 * @return void
	 */
	public function save( Object_Ref $ref ): void {
		$nonce = isset( $_POST[ Connection_Panels::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ Connection_Panels::NONCE_NAME ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, Connection_Panels::NONCE_ACTION ) ) {
			return;
		}

		$present = isset( $_POST[ Connection_Panels::PRESENT ] ) && is_array( $_POST[ Connection_Panels::PRESENT ] ) ? wp_unslash( $_POST[ Connection_Panels::PRESENT ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Keys are checked against registered connections; values unused.
		$posted  = isset( $_POST[ Connection_Panels::INPUT ] ) && is_array( $_POST[ Connection_Panels::INPUT ] ) ? wp_unslash( $_POST[ Connection_Panels::INPUT ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- IDs go through absint() below.

		foreach ( $present as $name => $sides ) {
			$connection = $this->registry->find( sanitize_key( (string) $name ) );

			if ( ! $connection || ! is_array( $sides ) ) {
				continue;
			}

			foreach ( array_keys( $sides ) as $side ) {
				$side = (string) $side;

				if ( ! in_array( $side, array( 'from', 'to' ), true ) || ! $connection->shows( $side ) || ! $connection->accepts( $side, $ref ) ) {
					continue;
				}

				$raw = isset( $posted[ $name ][ $side ] ) && is_array( $posted[ $name ][ $side ] ) ? $posted[ $name ][ $side ] : array();

				$this->store->replace( $connection, $side, $ref->id(), $this->allowed( $connection, $side, $ref, array_map( 'absint', $raw ) ) );
			}
		}
	}

	/**
	 * Posted IDs the user may link, in order.
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side of the object being saved.
	 * @param Object_Ref $ref        Object being saved.
	 * @param int[]      $ids        Posted IDs.
	 * @return int[]
	 */
	public function allowed( Connection $connection, string $side, Object_Ref $ref, array $ids ): array {
		$other   = Connection::other( $side );
		$current = $this->store->ids( $connection->name(), $side, $ref->id() );
		$clean   = array();

		foreach ( $ids as $id ) {
			if ( $id <= 0 || in_array( $id, $clean, true ) ) {
				continue;
			}

			if ( in_array( $id, $current, true ) || ( $connection->accepts_id( $other, $id ) && self::can_pick( $connection->kind( $other ), $id ) ) ) {
				$clean[] = $id;
			}
		}

		return $clean;
	}

	/**
	 * Whether the current user may link to an object.
	 *
	 * @param string $kind post, term or user.
	 * @param int    $id   Object ID.
	 * @return bool
	 */
	public static function can_pick( string $kind, int $id ): bool {
		switch ( $kind ) {
			case 'post':
				return current_user_can( 'read_post', $id );

			case 'term':
				$term     = get_term( $id );
				$taxonomy = $term instanceof \WP_Term ? get_taxonomy( $term->taxonomy ) : null;

				return $taxonomy && current_user_can( $taxonomy->cap->assign_terms );

			case 'user':
				return get_current_user_id() === $id || current_user_can( 'list_users' );
		}

		return false;
	}
}
