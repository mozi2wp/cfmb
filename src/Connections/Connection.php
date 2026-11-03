<?php
/**
 * One Connection definition.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Storage\Object_Ref;
use WP_Post;
use WP_Term;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of a definition built by Connection_Schema::build_args().
 *
 * Sides are "from" and "to". Each side has a kind (post, term, user) and
 * optional subtypes (post types, taxonomies or roles; empty allows all).
 */
final class Connection {

	/**
	 * Arguments from Connection_Schema::build_args().
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * Constructor.
	 *
	 * @param array<string, mixed> $args Arguments.
	 */
	public function __construct( array $args ) {
		$this->args = $args;
	}

	/**
	 * Connection ID.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) $this->args['name'];
	}

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function title(): string {
		return (string) $this->args['title'];
	}

	/**
	 * Kind of objects on a side: post, term or user.
	 *
	 * @param string $side from or to.
	 * @return string
	 */
	public function kind( string $side ): string {
		return (string) $this->args[ 'to' === $side ? 'to' : 'from' ];
	}

	/**
	 * Allowed post types, taxonomies or roles on a side; empty allows all.
	 *
	 * @param string $side from or to.
	 * @return string[]
	 */
	public function types( string $side ): array {
		return (array) $this->args[ 'to' === $side ? 'to_types' : 'from_types' ];
	}

	/**
	 * The other side.
	 *
	 * @param string $side from or to.
	 * @return string
	 */
	public static function other( string $side ): string {
		return 'to' === $side ? 'from' : 'to';
	}

	/**
	 * How many items an object on a side may link to; 0 for no limit.
	 *
	 * @param string $side Side of the object.
	 * @return int
	 */
	public function max( string $side ): int {
		$cardinality = (string) $this->args['cardinality'];

		if ( 'one_to_one' === $cardinality ) {
			return 1;
		}

		if ( 'from' === $side ) {
			return 'many_to_one' === $cardinality ? 1 : 0;
		}

		return 'one_to_many' === $cardinality ? 1 : 0;
	}

	/**
	 * Cardinality.
	 *
	 * @return string
	 */
	public function cardinality(): string {
		return (string) $this->args['cardinality'];
	}

	/**
	 * Box title on a side's edit screen.
	 *
	 * @param string $side Side of the object being edited.
	 * @return string
	 */
	public function label( string $side ): string {
		$label = (string) $this->args[ 'to' === $side ? 'to_label' : 'from_label' ];

		return '' !== $label ? $label : $this->title();
	}

	/**
	 * Whether a side gets an edit box.
	 *
	 * @param string $side from or to.
	 * @return bool
	 */
	public function shows( string $side ): bool {
		return (bool) $this->args[ 'to' === $side ? 'show_to' : 'show_from' ];
	}

	/**
	 * Box position on post screens: side or normal.
	 *
	 * @return string
	 */
	public function position(): string {
		return (string) $this->args['position'];
	}

	/**
	 * Whether connected IDs show in the REST API.
	 *
	 * @return bool
	 */
	public function in_rest(): bool {
		return ! empty( $this->args['show_in_rest'] );
	}

	/**
	 * Whether both sides take the same kind and types, so one object can
	 * sit on either side.
	 *
	 * @return bool
	 */
	public function is_reciprocal(): bool {
		return $this->kind( 'from' ) === $this->kind( 'to' ) && $this->types( 'from' ) === $this->types( 'to' );
	}

	/**
	 * Sides an object can be on.
	 *
	 * @param Object_Ref $ref Object.
	 * @return string[] from, to, both or none.
	 */
	public function sides_for( Object_Ref $ref ): array {
		$sides = array();

		foreach ( array( 'from', 'to' ) as $side ) {
			if ( $this->accepts( $side, $ref ) ) {
				$sides[] = $side;
			}
		}

		return $sides;
	}

	/**
	 * Whether an object can be on a side.
	 *
	 * @param string     $side from or to.
	 * @param Object_Ref $ref  Object.
	 * @return bool
	 */
	public function accepts( string $side, Object_Ref $ref ): bool {
		$kind = $this->kind( $side );

		if ( $ref->kind() !== $kind || $ref->id() <= 0 ) {
			return false;
		}

		return $this->accepts_id( $side, $ref->id() );
	}

	/**
	 * Whether an object ID of the side's kind exists and has an allowed subtype.
	 *
	 * @param string $side from or to.
	 * @param int    $id   Object ID.
	 * @return bool
	 */
	public function accepts_id( string $side, int $id ): bool {
		$types = $this->types( $side );

		switch ( $this->kind( $side ) ) {
			case 'post':
				$post = get_post( $id );

				return $post instanceof WP_Post && 'revision' !== $post->post_type && ( ! $types || in_array( $post->post_type, $types, true ) );

			case 'term':
				$term = get_term( $id );

				return $term instanceof WP_Term && ( ! $types || in_array( $term->taxonomy, $types, true ) );

			case 'user':
				$user = get_userdata( $id );

				return $user instanceof WP_User && ( ! $types || array_intersect( $types, (array) $user->roles ) );
		}

		return false;
	}

	/**
	 * Object reference for an ID on a side.
	 *
	 * @param string $side from or to.
	 * @param int    $id   Object ID.
	 * @return Object_Ref
	 */
	public function ref( string $side, int $id ): Object_Ref {
		return new Object_Ref( $this->kind( $side ), $id );
	}

	/**
	 * One argument, such as a setting an add-on added through
	 * `cfmb/connections/build_args`.
	 *
	 * @since 0.12.0
	 *
	 * @param string $name     Argument name.
	 * @param mixed  $fallback Value when missing.
	 * @return mixed
	 */
	public function arg( string $name, $fallback = null ) {
		return array_key_exists( $name, $this->args ) ? $this->args[ $name ] : $fallback;
	}

	/**
	 * Arguments.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return $this->args;
	}
}
