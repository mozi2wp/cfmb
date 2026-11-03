<?php
/**
 * Connection boxes on post, term and user screens.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Admin\Assets;
use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Each connection side that shows a box gets one: a search to add items,
 * the linked items in order, buttons to reorder and remove. The box posts
 * the IDs with the normal save, checked in Connection_Saver.
 *
 * Without JavaScript the box keeps the current links as hidden inputs, so
 * saving never drops them.
 */
class Connection_Panels {

	public const INPUT = 'cfmb_connections';

	public const PRESENT = 'cfmb_connections_present';

	public const NONCE_ACTION = 'cfmb_connections_save';

	public const NONCE_NAME = '_cfmb_connections_nonce';

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
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Boxes for the current term or user screen.
	 *
	 * @var array<int, array{connection: Connection, side: string}>
	 */
	private array $entries = array();

	/**
	 * Constructor.
	 *
	 * @param Connection_Registry $registry Active connections.
	 * @param Connection_Store    $store    Links.
	 * @param Assets              $assets   Asset loader.
	 */
	public function __construct( Connection_Registry $registry, Connection_Store $store, Assets $assets ) {
		$this->registry = $registry;
		$this->store    = $store;
		$this->assets   = $assets;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_post_boxes' ), 20, 2 );
		add_action( 'load-term.php', array( $this, 'prepare_term_screen' ) );
		add_action( 'show_user_profile', array( $this, 'render_user' ), 20 );
		add_action( 'edit_user_profile', array( $this, 'render_user' ), 20 );
	}

	/**
	 * Add boxes to the post editor.
	 *
	 * @param string       $post_type Post type.
	 * @param WP_Post|null $post      Post.
	 * @return void
	 */
	public function add_post_boxes( $post_type, $post = null ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::POST, (int) $post->ID );

		foreach ( $this->shown( $ref ) as $entry ) {
			$connection = $entry['connection'];
			$side       = $entry['side'];

			add_meta_box(
				'cfmb-connection-' . $connection->name() . '-' . $side,
				esc_html( $connection->label( $side ) ),
				function () use ( $connection, $side, $ref ) {
					$this->render_box( $connection, $side, $ref );
				},
				null,
				$connection->position(),
				'default',
				array( '__block_editor_compatible_meta_box' => true )
			);
		}
	}

	/**
	 * Find boxes for the edit-term screen.
	 *
	 * @return void
	 */
	public function prepare_term_screen(): void {
		// Read-only screen context; saving is nonce-checked in Connection_Saver.
		$taxonomy = isset( $_REQUEST['taxonomy'] ) ? sanitize_key( wp_unslash( $_REQUEST['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$term_id  = isset( $_GET['tag_ID'] ) ? absint( $_GET['tag_ID'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( '' === $taxonomy || ! $term_id ) {
			return;
		}

		$this->entries = $this->shown( new Object_Ref( Object_Ref::TERM, $term_id ) );

		if ( $this->entries ) {
			$this->enqueue();
			add_action( "{$taxonomy}_edit_form_fields", array( $this, 'render_term' ), 20 );
		}
	}

	/**
	 * Boxes on the edit-term form, which is a table.
	 *
	 * @param WP_Term $term Term.
	 * @return void
	 */
	public function render_term( $term ): void {
		if ( ! $term instanceof WP_Term ) {
			return;
		}

		$ref = new Object_Ref( Object_Ref::TERM, (int) $term->term_id );

		foreach ( $this->entries as $entry ) {
			echo '<tr class="form-field cfmb-connection-row"><th scope="row">' . esc_html( $entry['connection']->label( $entry['side'] ) ) . '</th><td>';
			$this->render_box( $entry['connection'], $entry['side'], $ref );
			echo '</td></tr>';
		}
	}

	/**
	 * Boxes on profile and edit-user screens.
	 *
	 * @param WP_User $user User.
	 * @return void
	 */
	public function render_user( $user ): void {
		if ( ! $user instanceof WP_User ) {
			return;
		}

		$ref     = new Object_Ref( Object_Ref::USER, (int) $user->ID );
		$entries = $this->shown( $ref );

		if ( ! $entries ) {
			return;
		}

		$this->enqueue();

		echo '<h2 class="cfmb-connection-heading">' . esc_html__( 'Connections', 'custom-fields-meta-box' ) . '</h2><table class="form-table" role="presentation">';

		foreach ( $entries as $entry ) {
			echo '<tr class="cfmb-connection-row"><th scope="row">' . esc_html( $entry['connection']->label( $entry['side'] ) ) . '</th><td>';
			$this->render_box( $entry['connection'], $entry['side'], $ref );
			echo '</td></tr>';
		}

		echo '</table>';
	}

	/**
	 * One box: a mount point with the current links as hidden inputs.
	 *
	 * @param Connection $connection Connection.
	 * @param string     $side       Side of the object being edited.
	 * @param Object_Ref $ref        Object being edited.
	 * @return void
	 */
	public function render_box( Connection $connection, string $side, Object_Ref $ref ): void {
		$this->enqueue();

		$name  = $connection->name();
		$other = Connection::other( $side );
		$ids   = $this->store->ids( $name, $side, $ref->id() );
		$items = array();

		foreach ( $ids as $id ) {
			$item = self::describe( $connection->kind( $other ), $id );

			if ( $item ) {
				$items[] = $item;
			}
		}

		$input  = self::INPUT . '[' . $name . '][' . $side . '][]';
		$config = array(
			'connection' => $name,
			'side'       => $side,
			'object'     => $ref->id(),
			'kind'       => $connection->kind( $other ),
			'max'        => $connection->max( $side ),
			'label'      => $connection->label( $side ),
			'input'      => $input,
			'items'      => $items,
		);

		/**
		 * Filters what the Connection box script gets for one box. Add-ons
		 * can add data for their part of the box (see the JS filter
		 * `cfmb.connections.itemExtra`). The core keys can't be changed.
		 *
		 * @since 0.12.0
		 *
		 * @param array<string, mixed> $config     Box config.
		 * @param Connection           $connection Connection.
		 * @param string               $side       Side of the object being edited.
		 * @param Object_Ref           $ref        Object being edited.
		 */
		$filtered = apply_filters( 'cfmb/connections/box_config', $config, $connection, $side, $ref ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
		$config   = is_array( $filtered ) ? array_merge( $filtered, $config ) : $config;

		// One nonce input per box, without an id, so several boxes on a page stay valid HTML.
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::NONCE_NAME ), esc_attr( wp_create_nonce( self::NONCE_ACTION ) ) );
		printf( '<input type="hidden" name="%s" value="1" />', esc_attr( self::PRESENT . '[' . $name . '][' . $side . ']' ) );

		printf( '<div class="cfmb-connection" data-cfmb-connection="%s">', esc_attr( (string) wp_json_encode( $config ) ) );

		foreach ( $items as $item ) {
			printf( '<input type="hidden" name="%1$s" value="%2$d" />', esc_attr( $input ), (int) $item['id'] );
		}

		if ( $items ) {
			echo '<ul class="cfmb-connection__static">';
			foreach ( $items as $item ) {
				echo '<li>' . esc_html( $item['title'] ) . '</li>';
			}
			echo '</ul>';
		} else {
			echo '<p class="cfmb-connection__static description">' . esc_html__( 'Nothing linked yet.', 'custom-fields-meta-box' ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Label and details of an item for the box.
	 *
	 * @param string $kind post, term or user.
	 * @param int    $id   Object ID.
	 * @return array{id: int, title: string, meta: string, url: string}|null
	 */
	public static function describe( string $kind, int $id ): ?array {
		if ( 'post' === $kind ) {
			$post = get_post( $id );

			if ( ! $post instanceof WP_Post ) {
				return null;
			}

			$type   = get_post_type_object( $post->post_type );
			$status = get_post_status_object( (string) get_post_status( $post ) );
			$meta   = $type ? $type->labels->singular_name : $post->post_type;

			if ( 'publish' !== $post->post_status && $status ) {
				$meta .= ' · ' . $status->label;
			}

			return array(
				'id'    => (int) $post->ID,
				'title' => '' !== $post->post_title ? $post->post_title : __( '(no title)', 'custom-fields-meta-box' ),
				'meta'  => (string) $meta,
				'url'   => current_user_can( 'edit_post', $post->ID ) ? (string) get_edit_post_link( $post->ID, 'raw' ) : '',
			);
		}

		if ( 'term' === $kind ) {
			$term = get_term( $id );

			if ( ! $term instanceof WP_Term ) {
				return null;
			}

			$taxonomy = get_taxonomy( $term->taxonomy );

			return array(
				'id'    => (int) $term->term_id,
				'title' => $term->name,
				'meta'  => $taxonomy ? (string) $taxonomy->labels->singular_name : $term->taxonomy,
				'url'   => current_user_can( 'edit_term', $term->term_id ) ? (string) get_edit_term_link( $term->term_id, $term->taxonomy ) : '',
			);
		}

		$user = get_userdata( $id );

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		$roles = wp_roles()->get_names();
		$role  = (string) ( $user->roles[0] ?? '' );

		return array(
			'id'    => (int) $user->ID,
			'title' => $user->display_name,
			'meta'  => isset( $roles[ $role ] ) ? translate_user_role( $roles[ $role ] ) : '',
			'url'   => current_user_can( 'edit_user', $user->ID ) ? (string) get_edit_user_link( $user->ID ) : '',
		);
	}

	/**
	 * Connection sides that show a box for an object.
	 *
	 * @param Object_Ref $ref Object.
	 * @return array<int, array{connection: Connection, side: string}>
	 */
	private function shown( Object_Ref $ref ): array {
		return array_values(
			array_filter(
				$this->registry->for_object( $ref ),
				static fn( array $entry ): bool => $entry['connection']->shows( $entry['side'] )
			)
		);
	}

	/**
	 * Load the box script and styles.
	 *
	 * @return void
	 */
	private function enqueue(): void {
		$this->assets->enqueue(
			'connections',
			'cfmbConnections',
			array(
				'searchPath' => '/cfmb/v1/connections',
			)
		);
	}
}
