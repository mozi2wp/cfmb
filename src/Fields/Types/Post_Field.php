<?php
/**
 * Post field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Fields\Abstract_Field;
use CFMB\Schema\Field;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * One or more posts picked with a search box. Stores post IDs, one meta row
 * per post when several are allowed.
 */
class Post_Field extends Abstract_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'post';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Post', 'cfmb' );
	}

	/**
	 * Builder category.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'relational';
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->post_type_setting(),
			$this->setting( 'multiple', __( 'Allow several posts', 'cfmb' ), 'toggle', false ),
			$this->return_format_setting(),
		);
	}

	/**
	 * Several posts when the setting allows it.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return (bool) $field->setting( 'multiple', false );
	}

	/**
	 * Nothing picked by default.
	 *
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function default_value( Field $field ) {
		return $this->is_multiple( $field ) ? array() : '';
	}

	/**
	 * IDs of posts that exist, in the given order.
	 *
	 * @param mixed $value Raw value: IDs or objects with an id.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$ids = array();

		foreach ( $this->as_list( $value ) as $item ) {
			$id = $this->id_of( $item );

			if ( $id > 0 && $this->exists( $id ) ) {
				$ids[] = $id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		if ( $this->is_multiple( $field ) ) {
			return $ids;
		}

		return $ids ? $ids[0] : '';
	}

	/**
	 * Posts must be of an allowed type.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$allowed = $this->post_types( $field );

		foreach ( $this->as_list( $value ) as $id ) {
			if ( ! in_array( get_post_type( (int) $id ), $allowed, true ) ) {
				return __( 'One of the chosen posts is not of an allowed type.', 'cfmb' );
			}
		}

		return null;
	}

	/**
	 * Empty without IDs.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public function is_empty( $value ): bool {
		return array() === array_filter( $this->as_list( $value ) );
	}

	/**
	 * WP_Post objects or IDs. Posts deleted since are left out.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$items = array();

		foreach ( $this->as_list( $value ) as $id ) {
			$post = get_post( (int) $id );

			if ( $post instanceof WP_Post ) {
				$items[] = $this->format_post( $post, $field );
			}
		}

		if ( $this->is_multiple( $field ) ) {
			return $items;
		}

		return $items ? $items[0] : null;
	}

	/**
	 * Title and type for the picker.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field ) {
		$items = array();

		foreach ( $this->as_list( $value ) as $id ) {
			$post = get_post( (int) $id );

			if ( $post instanceof WP_Post ) {
				$items[] = self::summary( $post );
			}
		}

		if ( $this->is_multiple( $field ) ) {
			return $items;
		}

		return $items ? $items[0] : null;
	}

	/**
	 * Linked titles.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$links = array();

		foreach ( $this->as_list( $formatted ) as $item ) {
			$post = $item instanceof WP_Post ? $item : get_post( $this->id_of( $item ) );

			if ( $post instanceof WP_Post ) {
				$links[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( (string) get_permalink( $post ) ), esc_html( get_the_title( $post ) ) );
			}
		}

		return implode( esc_html( wp_get_list_item_separator() ), $links );
	}

	/**
	 * Post types the field may point at. Empty setting means every type with an admin screen.
	 *
	 * @param Field $field Field definition.
	 * @return string[]
	 */
	public function post_types( Field $field ): array {
		$chosen = array_values( array_filter( array_map( 'strval', (array) $field->setting( 'post_type', array() ) ), 'post_type_exists' ) );

		return $chosen ? $chosen : array_keys( self::post_type_options() );
	}

	/**
	 * What the picker shows for a post.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	public static function summary( WP_Post $post ): array {
		$type = get_post_type_object( $post->post_type );

		return array(
			'id'        => $post->ID,
			'title'     => html_entity_decode( get_the_title( $post ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'type'      => $post->post_type,
			'typeLabel' => $type ? $type->labels->singular_name : $post->post_type,
			'status'    => $post->post_status,
		);
	}

	/**
	 * Post types with an admin screen, for settings.
	 *
	 * @return array<string, string>
	 */
	public static function post_type_options(): array {
		$options = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'cfmb_group', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles' ), true ) ) {
				continue;
			}

			$options[ $type->name ] = $type->labels->singular_name;
		}

		return $options;
	}

	/**
	 * One post as templates get it.
	 *
	 * @param WP_Post $post  Post.
	 * @param Field   $field Field definition.
	 * @return mixed
	 */
	protected function format_post( WP_Post $post, Field $field ) {
		return 'id' === $field->setting( 'return_format', 'object' ) ? $post->ID : $post;
	}

	/**
	 * The post type setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function post_type_setting(): array {
		return $this->setting(
			'post_type',
			__( 'Post types', 'cfmb' ),
			'checkboxes',
			array(),
			array(
				'options' => self::post_type_options(),
				'help'    => __( 'Leave all unchecked to allow every type.', 'cfmb' ),
			)
		);
	}

	/**
	 * The return format setting.
	 *
	 * @return array<string, mixed>
	 */
	protected function return_format_setting(): array {
		return $this->setting(
			'return_format',
			__( 'Template value', 'cfmb' ),
			'select',
			'object',
			array(
				'advanced' => true,
				'options'  => array(
					'object' => __( 'Post object', 'cfmb' ),
					'id'     => __( 'Post ID', 'cfmb' ),
				),
			)
		);
	}

	/**
	 * Whether a post exists. Overridden by types that point at other objects.
	 *
	 * @param int $id Object ID.
	 * @return bool
	 */
	protected function exists( int $id ): bool {
		return false !== get_post_type( $id );
	}

	/**
	 * Value as a list.
	 *
	 * @param mixed $value Value.
	 * @return array<int, mixed>
	 */
	protected function as_list( $value ): array {
		if ( null === $value || '' === $value || false === $value ) {
			return array();
		}

		// A single object with an id (from the editor) or a WP_Post is one item, not a list.
		if ( ! is_array( $value ) || isset( $value['id'] ) ) {
			return array( $value );
		}

		return array_values( $value );
	}

	/**
	 * ID from an ID, an editor object or a WP object.
	 *
	 * @param mixed $item Item.
	 * @return int
	 */
	protected function id_of( $item ): int {
		if ( is_object( $item ) && isset( $item->ID ) ) {
			return (int) $item->ID;
		}

		if ( is_object( $item ) && isset( $item->term_id ) ) {
			return (int) $item->term_id;
		}

		if ( is_array( $item ) ) {
			return isset( $item['id'] ) ? absint( $item['id'] ) : 0;
		}

		return is_numeric( $item ) ? absint( $item ) : 0;
	}
}
