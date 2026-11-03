<?php
/**
 * Public template API.
 *
 * Every function here is part of the public API. Signatures only change
 * through deprecation (two minor versions with _deprecated_function()).
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;

use CFMB\Connections\Connections;
use CFMB\Content\Options_Pages;
use CFMB\Core\Plugin;
use CFMB\Schema\Field_Locator;
use CFMB\Schema\Local_Definitions;
use CFMB\Storage\Block_Context;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use CFMB\Storage\Store_Resolver;
use CFMB\Template\Row_Loop;

/**
 * The CFMB plugin instance.
 *
 * @return Plugin
 */
function cfmb(): Plugin {
	return Plugin::instance();
}

/**
 * Capability needed to manage Field Groups and CFMB settings.
 *
 * @return string
 */
function cfmb_admin_capability(): string {
	/**
	 * Filters the capability for CFMB admin screens. Default manage_options.
	 *
	 * @param string $capability Capability.
	 */
	return (string) apply_filters( 'cfmb/admin/capability', 'manage_options' ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
}

/**
 * Get a field value.
 *
 * @param string $name   Field name.
 * @param mixed  $object Post ID, "term_12", "user_3", "option", "option:{page}",
 *                       a WP_Post/WP_Term/WP_User, or null for the current post.
 * @param bool   $format Whether to format the value for templates. False returns the stored value.
 * @return mixed Null when there is no value and no default.
 */
function cfmb_get_field( string $name, $object = null, bool $format = true ) {
	$ref = Object_Ref::from( $object );

	if ( ! $ref ) {
		return null;
	}

	$found = cfmb()->get( Field_Locator::class )->find( $name, $ref );

	if ( ! $found ) {
		// Not a CFMB field; return whatever is stored under that name.
		$store = cfmb()->get( Store_Resolver::class )->for_group( null, $ref );

		return $store->exists( $ref, $name ) ? $store->get( $ref, $name, false ) : null;
	}

	return cfmb()->get( Field_Values::class )->load( $found[0], $found[1], $ref, $format );
}

/**
 * Print a field value as escaped HTML.
 *
 * @param string $name   Field name.
 * @param mixed  $object See cfmb_get_field().
 * @return void
 */
function cfmb_the_field( string $name, $object = null ): void {
	echo cfmb_get_field_html( $name, $object ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the field type's display().
}

/**
 * Get a field value as escaped HTML, as cfmb_the_field() prints it.
 *
 * @param string $name   Field name.
 * @param mixed  $object See cfmb_get_field().
 * @return string
 */
function cfmb_get_field_html( string $name, $object = null ): string {
	$ref = Object_Ref::from( $object );

	if ( ! $ref ) {
		return '';
	}

	$found = cfmb()->get( Field_Locator::class )->find( $name, $ref );

	if ( ! $found ) {
		$value = cfmb_get_field( $name, $ref, false );

		return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}

	$values = cfmb()->get( Field_Values::class );
	$type   = $values->type_of( $found[0] );

	return $type ? $type->display( $values->load( $found[0], $found[1], $ref ), $found[0] ) : '';
}

/**
 * Get every field value shown for an object, keyed by field name.
 *
 * @param mixed $object See cfmb_get_field().
 * @param bool  $format Whether to format values for templates.
 * @return array<string, mixed>
 */
function cfmb_get_fields( $object = null, bool $format = true ): array {
	$ref = Object_Ref::from( $object );

	if ( ! $ref ) {
		return array();
	}

	$values = cfmb()->get( Field_Values::class );
	$fields = array();

	foreach ( cfmb()->get( Field_Locator::class )->groups_for( $ref ) as $group ) {
		foreach ( $group->fields() as $field ) {
			if ( '' !== $field->name() && ! array_key_exists( $field->name(), $fields ) ) {
				$fields[ $field->name() ] = $values->load( $field, $group, $ref, $format );
			}
		}
	}

	return $fields;
}

/**
 * Get a field's definition together with its value.
 *
 * @param string $name   Field name.
 * @param mixed  $object See cfmb_get_field().
 * @return array<string, mixed>|null Definition plus "value" and "group" keys, or null if not found.
 */
function cfmb_get_field_object( string $name, $object = null ): ?array {
	$ref   = Object_Ref::from( $object );
	$found = $ref ? cfmb()->get( Field_Locator::class )->find( $name, $ref ) : null;

	if ( ! $ref || ! $found ) {
		return null;
	}

	return array_merge(
		$found[0]->to_array(),
		array(
			'group' => $found[1]->key(),
			'value' => cfmb()->get( Field_Values::class )->load( $found[0], $found[1], $ref ),
		)
	);
}

/**
 * Save a field value. The value is sanitized and validated like a form submission.
 *
 * Only fields defined in a Field Group can be updated, so every stored value
 * has a known type.
 *
 * @param string $name   Field name.
 * @param mixed  $value  New value.
 * @param mixed  $object See cfmb_get_field().
 * @return bool True when saved.
 */
function cfmb_update_field( string $name, $value, $object = null ): bool {
	$ref   = Object_Ref::from( $object );
	$found = $ref ? cfmb()->get( Field_Locator::class )->find( $name, $ref ) : null;

	if ( ! $ref || ! $found ) {
		return false;
	}

	return null === cfmb()->get( Field_Values::class )->save( $found[0], $found[1], $ref, $value );
}

/**
 * Delete a field value. The field falls back to its default.
 *
 * @param string $name   Field name.
 * @param mixed  $object See cfmb_get_field().
 * @return bool
 */
function cfmb_delete_field( string $name, $object = null ): bool {
	$ref   = Object_Ref::from( $object );
	$found = $ref ? cfmb()->get( Field_Locator::class )->find( $name, $ref ) : null;

	if ( ! $ref || ! $found ) {
		return false;
	}

	return cfmb()->get( Field_Values::class )->delete( $found[0], $found[1], $ref );
}

/**
 * Whether a Repeater, Stacks or Group field has another row to show.
 *
 * Use it as a loop condition, with cfmb_the_row() inside:
 *
 *     while ( cfmb_have_rows( 'items' ) ) {
 *         cfmb_the_row();
 *         cfmb_the_sub_field( 'title' );
 *     }
 *
 * Inside a row, calling it with a sub-field name (and no object) loops over
 * that nested Repeater or Stacks field. A Group counts as one row.
 *
 * @param string $name   Field name, or sub-field name inside a row.
 * @param mixed  $object See cfmb_get_field(). Leave null inside a row.
 * @return bool
 */
function cfmb_have_rows( string $name, $object = null ): bool {
	$top     = Row_Loop::top();
	$ref     = null === $object ? null : Object_Ref::from( $object );
	$ref_key = $ref ? (string) $ref : '';

	if ( $top && $top['name'] === $name && ( null === $object || $top['ref'] === $ref_key ) ) {
		if ( $top['index'] + 1 < count( $top['rows'] ) ) {
			return true;
		}

		Row_Loop::pop();

		return false;
	}

	$current = Row_Loop::current();

	if ( $top && null === $object && array_key_exists( $name, $current ) ) {
		$rows  = $current[ $name ];
		$field = Row_Loop::sub_field( $name );
		$ref   = (string) $top['ref'];
	} else {
		$ref = $ref ? $ref : Object_Ref::from( null );

		if ( ! $ref ) {
			return false;
		}

		$found = cfmb()->get( Field_Locator::class )->find( $name, $ref );
		$rows  = cfmb_get_field( $name, $ref );
		$field = $found ? $found[0] : null;
		$ref   = (string) $ref;
	}

	if ( ! is_array( $rows ) || array() === $rows ) {
		return false;
	}

	// A Group's value is one set of sub-values, not a list of rows.
	if ( array_values( $rows ) !== $rows ) {
		$rows = array( $rows );
	}

	Row_Loop::push( $name, $ref, $field, $rows );

	return true;
}

/**
 * Move to the next row of the innermost cfmb_have_rows() loop.
 *
 * @return array<string, mixed> The row's formatted sub-values by name.
 */
function cfmb_the_row(): array {
	return Row_Loop::advance();
}

/**
 * A sub-field value in the current row, formatted for templates.
 *
 * @param string $name Sub-field name.
 * @return mixed Null when the row has no such sub-field.
 */
function cfmb_get_sub_field( string $name ) {
	$row = Row_Loop::current();

	return $row[ $name ] ?? null;
}

/**
 * Print a sub-field value in the current row as escaped HTML.
 *
 * @param string $name Sub-field name.
 * @return void
 */
function cfmb_the_sub_field( string $name ): void {
	$value = cfmb_get_sub_field( $name );
	$field = Row_Loop::sub_field( $name );
	$type  = $field ? cfmb()->get( Field_Values::class )->type_of( $field ) : null;

	if ( $type ) {
		echo $type->display( $value, $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the field type's display().
		return;
	}

	echo is_scalar( $value ) ? esc_html( (string) $value ) : '';
}

/**
 * Layout name of the current Stacks row.
 *
 * @return string Empty outside a Stacks loop.
 */
function cfmb_get_row_layout(): string {
	return (string) ( Row_Loop::current()['_layout'] ?? '' );
}

/**
 * Position of the current row, starting at 0 like the stored keys (items_0_title).
 *
 * @return int -1 outside a loop.
 */
function cfmb_get_row_index(): int {
	$top = Row_Loop::top();

	return $top ? (int) $top['index'] : -1;
}

/**
 * Leave the innermost loop early, for example after a break.
 *
 * @return void
 */
function cfmb_reset_rows(): void {
	Row_Loop::pop();
}

/**
 * Register an options page. Call on `init` or earlier.
 *
 * @param array<string, mixed> $args {
 *     @type string $id          Page ID used in "option:{id}" and the "Options page" location rule. Default "options".
 *     @type string $page_title  Title at the top of the page.
 *     @type string $menu_title  Menu label. Defaults to page_title.
 *     @type string $parent_slug Parent menu slug for a submenu, e.g. "options-general.php". Empty for a top-level menu.
 *     @type string $capability  Capability to view and save the page. Default manage_options.
 *     @type string $icon_url    Dashicon or URL for top-level menus.
 *     @type int    $position    Menu position.
 * }
 * @return bool False when the arguments are invalid or the ID is taken.
 */
function cfmb_add_options_page( array $args ): bool {
	return cfmb()->get( Options_Pages::class )->add( $args );
}

/**
 * Register a Field Group in code. It shows in the builder as read-only and
 * can be imported to edit it there. A Field Group saved in the database
 * with the same key wins.
 *
 * Call it from a `cfmb/register` callback (or earlier on `init`). The array
 * is the same shape as a Schema Sync file; CFMB > Sync > Export writes it.
 *
 * @param array<string, mixed> $group Group with a stable key (grp_…), title, fields, location and settings.
 * @return bool False when the key is missing or invalid.
 */
function cfmb_register_group( array $group ): bool {
	return cfmb()->get( Local_Definitions::class )->add_code( 'group', $group );
}

/**
 * Register a post type definition in code, in the shape CFMB > Post Types uses.
 * CFMB registers it with WordPress on `init`. See cfmb_register_group().
 *
 * @param array<string, mixed> $post_type Definition with a key (cpt_…), name, title and settings.
 * @return bool False when the key is missing or invalid.
 */
function cfmb_register_post_type( array $post_type ): bool {
	return cfmb()->get( Local_Definitions::class )->add_code( 'post_type', $post_type );
}

/**
 * Register a taxonomy definition in code, in the shape CFMB > Taxonomies uses.
 * See cfmb_register_group().
 *
 * @param array<string, mixed> $taxonomy Definition with a key (tax_…), name, title and settings.
 * @return bool False when the key is missing or invalid.
 */
function cfmb_register_taxonomy( array $taxonomy ): bool {
	return cfmb()->get( Local_Definitions::class )->add_code( 'taxonomy', $taxonomy );
}

/**
 * Register an options page definition in code, in the shape CFMB > Options
 * Pages uses. For a quick page without a key, cfmb_add_options_page() still works.
 *
 * @param array<string, mixed> $page Definition with a key (opt_…), name (page ID), title and settings.
 * @return bool False when the key is missing or invalid.
 */
function cfmb_register_options_page( array $page ): bool {
	return cfmb()->get( Local_Definitions::class )->add_code( 'options_page', $page );
}

/**
 * Register a Connection definition in code, in the shape CFMB > Connections
 * uses. See cfmb_register_group().
 *
 * @param array<string, mixed> $connection Definition with a key (con_…), name (connection ID), title and settings.
 * @return bool False when the key is missing or invalid.
 */
function cfmb_register_connection( array $connection ): bool {
	return cfmb()->get( Local_Definitions::class )->add_code( 'connection', $connection );
}

/**
 * Items connected to an object: posts, terms or users, in the order set in
 * the admin. Only items the current visitor may see are returned.
 *
 *     foreach ( cfmb_get_connected( 'book_authors' ) as $author ) {
 *         echo esc_html( $author->display_name );
 *     }
 *
 * @param string               $connection Connection ID.
 * @param mixed                $object     See cfmb_get_field(). Null for the current post.
 * @param array<string, mixed> $args {
 *     @type string $side  "from" or "to" when the object could be on both sides. Default both.
 *     @type string $fields "objects" (default) or "ids".
 * }
 * @return array<int, WP_Post|WP_Term|WP_User|int>
 */
function cfmb_get_connected( string $connection, $object = null, array $args = array() ): array {
	$ref = Object_Ref::from( $object );

	if ( ! $ref ) {
		return array();
	}

	$service = cfmb()->get( Connections::class );

	return 'ids' === ( $args['fields'] ?? 'objects' ) ? $service->ids( $connection, $ref, $args ) : $service->objects( $connection, $ref, $args );
}

/**
 * Whether two objects are connected.
 *
 * @param string $connection Connection ID.
 * @param mixed  $from       "From" object, see cfmb_get_field().
 * @param mixed  $to         "To" object.
 * @return bool
 */
function cfmb_is_connected( string $connection, $from, $to ): bool {
	$from_ref = Object_Ref::from( $from );
	$to_ref   = Object_Ref::from( $to );

	if ( ! $from_ref || ! $to_ref ) {
		return false;
	}

	$ids = cfmb()->get( Connections::class )->ids(
		$connection,
		$from_ref,
		array(
			'side'    => 'from',
			'visible' => false,
		)
	);

	return in_array( $to_ref->id(), $ids, true );
}

/**
 * Connect two objects. Limits apply: in a one-to-many connection, the "to"
 * object leaves any other "from" object.
 *
 * @param string $connection Connection ID.
 * @param mixed  $from       "From" object, see cfmb_get_field().
 * @param mixed  $to         "To" object.
 * @return bool False when the connection doesn't exist or an object doesn't fit its side.
 */
function cfmb_connect( string $connection, $from, $to ): bool {
	$service    = cfmb()->get( Connections::class );
	$definition = $service->registry()->find( $connection );
	$from_ref   = Object_Ref::from( $from );
	$to_ref     = Object_Ref::from( $to );

	if ( ! $definition || ! $from_ref || ! $to_ref || ! $definition->accepts( 'from', $from_ref ) || ! $definition->accepts( 'to', $to_ref ) ) {
		return false;
	}

	return $service->store()->connect( $definition, $from_ref->id(), $to_ref->id() );
}

/**
 * Remove the link between two objects.
 *
 * @param string $connection Connection ID.
 * @param mixed  $from       "From" object, see cfmb_get_field().
 * @param mixed  $to         "To" object.
 * @return bool
 */
function cfmb_disconnect( string $connection, $from, $to ): bool {
	$service    = cfmb()->get( Connections::class );
	$definition = $service->registry()->find( $connection );
	$from_ref   = Object_Ref::from( $from );
	$to_ref     = Object_Ref::from( $to );

	if ( ! $definition || ! $from_ref || ! $to_ref ) {
		return false;
	}

	return $service->store()->disconnect( $definition, $from_ref->id(), $to_ref->id() );
}

/**
 * Attributes for a Field Block template's outer element: the block class
 * (wp-block-cfmb-hero), the editor's extra classes, alignment, anchor and
 * any color or spacing styles the block supports.
 *
 *     <section <?php echo cfmb_block_wrapper_attributes( array( 'class' => 'hero' ) ); ?>>
 *
 * Outside a block render it returns only the extra attributes.
 *
 * @param array<string, string> $extra Extra attributes; class and style are merged.
 * @return string Escaped attributes.
 */
function cfmb_block_wrapper_attributes( array $extra = array() ): string {
	if ( function_exists( 'get_block_wrapper_attributes' ) && class_exists( 'WP_Block_Supports' ) && ! empty( WP_Block_Supports::$block_to_render ) ) {
		return get_block_wrapper_attributes( $extra );
	}

	$parts = array();
	foreach ( $extra as $name => $value ) {
		$name = sanitize_key( (string) $name );

		if ( '' !== $name ) {
			$parts[] = sprintf( '%s="%s"', $name, esc_attr( (string) $value ) );
		}
	}

	return implode( ' ', $parts );
}

/**
 * The Field Block being rendered: name, attributes, className, align, anchor and data.
 *
 * @return array<string, mixed>|null Null outside a Field Block template.
 */
function cfmb_get_block(): ?array {
	return Block_Context::block();
}
