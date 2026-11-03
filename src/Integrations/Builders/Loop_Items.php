<?php
/**
 * Items a Field Loop repeats its template for.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

use CFMB\Connections\Connections;
use CFMB\Display\Formatter;
use CFMB\Fields\Structure_Field;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use CFMB\Template\Row_Loop;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * A Field Loop repeats a builder template once per:
 *
 * - row of a Repeater or Stacks field (a Group counts as one row)
 * - image of a Gallery or Image field
 * - post of a Post or Relationship field
 * - item linked through a Connection (path "@connection/{id}"): a post,
 *   term or user
 *
 * While an item renders it is on Loop_Context, so tags read its values.
 * Rows are also open in the template API (cfmb_get_sub_field() works), and
 * posts are set up as the global post, so the builder's own post tags show
 * the loop post.
 */
class Loop_Items {

	/**
	 * Nested loops deeper than this are not rendered. Protects against a
	 * template that contains a loop over itself.
	 */
	public const MAX_DEPTH = 8;

	/**
	 * Field types whose values are post IDs a loop can walk.
	 */
	private const POST_TYPES = array( 'post', 'relationship' );

	/**
	 * Field types whose values are attachment IDs.
	 */
	private const IMAGE_TYPES = array( 'gallery', 'image' );

	/**
	 * Field lookup.
	 *
	 * @var Field_Source
	 */
	private Field_Source $source;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Value formatting and visibility filtering.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	public const CONNECTION_PREFIX = '@connection/';

	/**
	 * Connections, for "@connection/{id}" loops.
	 *
	 * @var Connections|null
	 */
	private ?Connections $connections;

	/**
	 * Global posts replaced while a post item renders, innermost last.
	 *
	 * @var array<int, WP_Post|null>
	 */
	private array $saved_posts = array();

	/**
	 * Constructor.
	 *
	 * @param Field_Source $source    Field lookup.
	 * @param Field_Values $values    Value service.
	 * @param Formatter        $formatter   Value formatting.
	 * @param Connections|null $connections Connections.
	 */
	public function __construct( Field_Source $source, Field_Values $values, Formatter $formatter, ?Connections $connections = null ) {
		$this->source      = $source;
		$this->values      = $values;
		$this->formatter   = $formatter;
		$this->connections = $connections;
	}

	/**
	 * Whether a field type can be looped.
	 *
	 * @param Field $field Field.
	 * @return bool
	 */
	public function can_loop( Field $field ): bool {
		return $this->values->is_structure( $field )
			|| in_array( $field->type(), self::POST_TYPES, true )
			|| in_array( $field->type(), self::IMAGE_TYPES, true );
	}

	/**
	 * Items for a loop.
	 *
	 * @param string               $path    Field path (see Field_Source).
	 * @param string               $object  Object argument.
	 * @param int                  $post_id Post the builder is rendering; 0 to use the loop.
	 * @param array<string, mixed> $args    layout (Stacks layout names, comma separated), offset, limit (0 = all).
	 * @return array<int, array<string, mixed>>
	 */
	public function collect( string $path, string $object = '', int $post_id = 0, array $args = array() ): array {
		$path = trim( $path );

		if ( 0 === strpos( $path, self::CONNECTION_PREFIX ) ) {
			return $this->finish( $this->connected( substr( $path, strlen( self::CONNECTION_PREFIX ) ), $object, $post_id ), $args, null, null );
		}

		$found = $this->source->find( $path, $object, $post_id );

		if ( ! $found || ! $this->can_loop( $found['field'] ) ) {
			return array();
		}

		$field = $found['field'];
		$group = $found['group'];
		$ref   = $found['ref'];

		if ( $this->values->is_structure( $field ) ) {
			$items = $this->rows( $path, $field, $group, $ref, $this->layouts( (string) ( $args['layout'] ?? '' ) ) );
		} else {
			$items = $this->linked( $field, $group, $ref );
		}

		return $this->finish( $items, $args, $field, $ref );
	}

	/**
	 * Apply offset and limit, number the items and let filters change them.
	 *
	 * @param array<int, array<string, mixed>> $items Items.
	 * @param array<string, mixed>             $args  offset, limit.
	 * @param Field|null                       $field Looped field; null for a Connection.
	 * @param Object_Ref|null                  $ref   Object the value belongs to.
	 * @return array<int, array<string, mixed>>
	 */
	private function finish( array $items, array $args, ?Field $field, ?Object_Ref $ref ): array {
		$offset = max( 0, (int) ( $args['offset'] ?? 0 ) );
		$limit  = max( 0, (int) ( $args['limit'] ?? 0 ) );
		$items  = array_slice( $items, $offset, $limit > 0 ? $limit : null );
		$count  = count( $items );

		foreach ( $items as $position => $item ) {
			$items[ $position ]['position'] = $position;
			$items[ $position ]['count']    = $count;
		}

		/**
		 * Filters the items a Field Loop repeats its template for.
		 *
		 * @param array<int, array<string, mixed>> $items Items: kind (row, image, post, term, user), position, count and kind data.
		 * @param Field|null                       $field Looped field; null for a Connection loop.
		 * @param Object_Ref|null                  $ref   Object the value belongs to.
		 */
		return (array) apply_filters( 'cfmb/builders/loop_items', $items, $field, $ref ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Render every item with a callback, each with its context open.
	 *
	 * @param array<int, array<string, mixed>> $items  Items from collect().
	 * @param callable                         $render Receives the item, returns HTML.
	 * @return string
	 */
	public function render( array $items, callable $render ): string {
		if ( Loop_Context::depth() >= self::MAX_DEPTH ) {
			return '';
		}

		$html = '';

		foreach ( $items as $item ) {
			$this->enter( $item );

			try {
				$html .= (string) $render( $item );
			} finally {
				$this->leave( $item );
			}
		}

		return $html;
	}

	/**
	 * Open an item.
	 *
	 * @param array<string, mixed> $item Item.
	 * @return void
	 */
	public function enter( array $item ): void {
		Loop_Context::push( $item );

		if ( 'row' === $item['kind'] ) {
			Row_Loop::push( $item['parent']->base_name(), (string) $item['ref'], $item['parent'], array( $this->formatted_row( $item ) ) );
			Row_Loop::advance();
		}

		if ( 'post' === $item['kind'] ) {
			global $post;

			$this->saved_posts[] = $post instanceof WP_Post ? $post : null;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored in leave().
			$post = get_post( (int) $item['id'] );
			setup_postdata( $post );
		}

		/**
		 * Fires when a Field Loop starts rendering an item.
		 *
		 * @param array<string, mixed> $item Item.
		 */
		do_action( 'cfmb/builders/loop_item', $item ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Close an item opened with enter().
	 *
	 * @param array<string, mixed> $item Item.
	 * @return void
	 */
	public function leave( array $item ): void {
		if ( 'post' === $item['kind'] ) {
			global $post;

			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Puts back the post replaced in enter().
			$post = array_pop( $this->saved_posts );

			if ( $post instanceof WP_Post ) {
				setup_postdata( $post );
			}
		}

		if ( 'row' === $item['kind'] ) {
			Row_Loop::pop();
		}

		Loop_Context::pop();
	}

	/**
	 * Row items of a Group, Repeater or Stacks field.
	 *
	 * @param string     $path    Loop path, matched by sub-field paths.
	 * @param Field      $field   Structure field.
	 * @param Group      $group   Owning group.
	 * @param Object_Ref $ref     Object.
	 * @param string[]   $layouts Stacks layouts to keep; empty keeps all.
	 * @return array<int, array<string, mixed>>
	 */
	private function rows( string $path, Field $field, Group $group, Object_Ref $ref, array $layouts ): array {
		$rows      = $this->values->rows();
		$structure = $rows->structure( $field );
		$item      = array(
			'kind'   => 'row',
			'path'   => $path,
			'parent' => $field,
			'group'  => $group,
			'ref'    => $ref,
		);

		if ( Structure_Field::GROUP === $structure ) {
			return array(
				$item + array(
					'index'  => null,
					'layout' => '',
					'fields' => $rows->children( $field ),
				),
			);
		}

		$stored = Structure_Field::STACKS === $structure
			? $rows->stored_layouts( $field, $group, $ref )
			: array_fill( 0, $rows->count( $field, $group, $ref ), '' );
		$items  = array();

		foreach ( $stored as $index => $layout ) {
			if ( Structure_Field::STACKS === $structure && ( ! $field->layout( $layout ) || ( $layouts && ! in_array( $layout, $layouts, true ) ) ) ) {
				continue;
			}

			$items[] = $item + array(
				'index'  => (int) $index,
				'layout' => $layout,
				'fields' => $rows->children( $field, (int) $index, $layout ),
			);
		}

		return $items;
	}

	/**
	 * Image or post items. IDs the visitor can't see are already removed.
	 *
	 * @param Field      $field Gallery, Image, Post or Relationship field.
	 * @param Group      $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return array<int, array<string, mixed>>
	 */
	private function linked( Field $field, Group $group, Object_Ref $ref ): array {
		if ( ! $this->formatter->supports( $field ) ) {
			return array();
		}

		$kind  = in_array( $field->type(), self::IMAGE_TYPES, true ) ? 'image' : 'post';
		$raw   = $this->formatter->raw( $field, $group, $ref );
		$items = array();

		foreach ( is_array( $raw ) ? $raw : array( $raw ) as $id ) {
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$items[] = array(
					'kind'   => $kind,
					'id'     => (int) $id,
					'parent' => $field,
					'ref'    => $ref,
				);
			}
		}

		return $items;
	}

	/**
	 * Items linked to the current object through a Connection.
	 *
	 * @param string $name    Connection ID.
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return array<int, array<string, mixed>>
	 */
	private function connected( string $name, string $object, int $post_id ): array {
		$connection = $this->connections ? $this->connections->registry()->find( sanitize_key( $name ) ) : null;
		$entry      = $connection ? $this->source->object( $object, $post_id ) : null;

		if ( ! $connection || ! $entry || ! $this->source->resolver()->visibility()->can_read( $entry['ref'], $entry['via'] ) ) {
			return array();
		}

		$kind  = $this->connections->other_kind( $connection, $entry['ref'] );
		$items = array();

		foreach ( $this->connections->ids( $connection->name(), $entry['ref'] ) as $id ) {
			$items[] = array(
				'kind'       => $kind,
				'id'         => (int) $id,
				'connection' => $connection->name(),
				'ref'        => $entry['ref'],
			);
		}

		return $items;
	}

	/**
	 * A row's values formatted for the template API.
	 *
	 * @param array<string, mixed> $item Row item.
	 * @return array<string, mixed>
	 */
	private function formatted_row( array $item ): array {
		$rows  = $this->values->rows();
		$field = $item['parent'];

		if ( null === $item['index'] ) {
			return $rows->format( $field, $rows->load_raw( $field, $item['group'], $item['ref'] ), $item['ref'] );
		}

		$raw       = $rows->load_row( $field, $item['group'], $item['ref'], (int) $item['index'], (string) $item['layout'] );
		$formatted = $rows->format( $field, array( $raw ), $item['ref'] );

		return is_array( $formatted[0] ?? null ) ? $formatted[0] : array();
	}

	/**
	 * Layout names from a comma-separated list.
	 *
	 * @param string $list Names.
	 * @return string[]
	 */
	private function layouts( string $list ): array {
		return array_values( array_filter( array_map( 'sanitize_key', explode( ',', $list ) ) ) );
	}
}
