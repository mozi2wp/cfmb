<?php
/**
 * Values of Group, Repeater and Stacks fields.
 *
 * @package CFMB
 */

namespace CFMB\Storage;

use CFMB\Fields\Structure_Field;
use CFMB\Location\Conditions;
use CFMB\Schema\Field;
use CFMB\Schema\Group;

defined( 'ABSPATH' ) || exit;

/**
 * Walks values made of sub-fields. Every sub-value is stored under its own
 * flattened name, like any other field:
 *
 * - Group:    hero_title, hero_image
 * - Repeater: items = 2 (row count), items_0_title, items_1_title
 * - Stacks:   sections = hero, cta (one meta row per row, in order), sections_0_title
 *
 * Values move through four forms:
 *
 * - raw:       sub-values by name, as stored. Stacks rows carry `_layout`.
 * - formatted: like raw, each sub-value formatted for templates.
 * - editor:    sub-values by field key for the edit screen. Rows carry `_from`
 *              (their stored index) and, for Stacks, `_layout`.
 * - sanitized: like raw. A row may instead be a reference to a stored row the
 *              editor never loaded (`_keep: true` + `_from`), which is how paged
 *              Repeaters save only the pages that were opened.
 */
class Row_Values {

	/**
	 * Rows per page when pagination is on and no number is set.
	 */
	public const DEFAULT_PER_PAGE = 20;

	/**
	 * Value service, for each sub-field.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Storage backend resolver.
	 *
	 * @var Store_Resolver
	 */
	private Store_Resolver $stores;

	/**
	 * Constructor.
	 *
	 * @param Field_Values   $values Value service.
	 * @param Store_Resolver $stores Storage backends.
	 */
	public function __construct( Field_Values $values, Store_Resolver $stores ) {
		$this->values = $values;
		$this->stores = $stores;
	}

	/**
	 * Shape of a structure field: group, rows or stacks.
	 *
	 * @param Field $field Field definition.
	 * @return string
	 */
	public function structure( Field $field ): string {
		$type = $this->values->type_of( $field );

		return $type instanceof Structure_Field ? $type->structure() : '';
	}

	/**
	 * Sub-fields for one row, renamed to their flattened storage names.
	 *
	 * @param Field    $field  Group, Repeater or Stacks field.
	 * @param int|null $index  Row index; null for a Group.
	 * @param string   $layout Stacks layout name.
	 * @return Field[]
	 */
	public function children( Field $field, ?int $index = null, string $layout = '' ): array {
		$prefix = null === $index ? $field->name() . '_' : $field->name() . '_' . $index . '_';
		$fields = $this->definitions( $field, $layout );

		return array_map( static fn( Field $sub ) => $sub->with_name( $prefix . $sub->name() ), $fields );
	}

	/**
	 * Sub-field definitions for a row, with their own names.
	 *
	 * @param Field  $field  Structure field.
	 * @param string $layout Stacks layout name.
	 * @return Field[]
	 */
	public function definitions( Field $field, string $layout = '' ): array {
		if ( Structure_Field::STACKS !== $this->structure( $field ) ) {
			return $field->sub_fields();
		}

		$found = $field->layout( $layout );

		return $found ? $found['fields'] : array();
	}

	/**
	 * Number of stored rows.
	 *
	 * @param Field      $field Repeater or Stacks field.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return int
	 */
	public function count( Field $field, ?Group $group, Object_Ref $ref ): int {
		if ( Structure_Field::STACKS === $this->structure( $field ) ) {
			return count( $this->stored_layouts( $field, $group, $ref ) );
		}

		$store = $this->stores->for_group( $group, $ref );

		return $store->exists( $ref, $field->name() ) ? max( 0, (int) $store->get( $ref, $field->name(), false ) ) : 0;
	}

	/**
	 * Layout name of every stored Stacks row, in order.
	 *
	 * @param Field      $field Stacks field.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return string[]
	 */
	public function stored_layouts( Field $field, ?Group $group, Object_Ref $ref ): array {
		$store = $this->stores->for_group( $group, $ref );

		if ( ! $store->exists( $ref, $field->name() ) ) {
			return array();
		}

		return array_values( array_map( 'strval', (array) $store->get( $ref, $field->name(), true ) ) );
	}

	/**
	 * Raw value.
	 *
	 * @param Field      $field Structure field.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return array<int|string, mixed>
	 */
	public function load_raw( Field $field, ?Group $group, Object_Ref $ref ): array {
		$structure = $this->structure( $field );

		if ( Structure_Field::GROUP === $structure ) {
			return $this->load_set( $this->children( $field ), $group, $ref );
		}

		$rows = array();

		if ( Structure_Field::STACKS === $structure ) {
			foreach ( $this->stored_layouts( $field, $group, $ref ) as $index => $layout ) {
				$rows[] = $this->load_row( $field, $group, $ref, $index, $layout );
			}

			return $rows;
		}

		$count = $this->count( $field, $group, $ref );
		for ( $index = 0; $index < $count; $index++ ) {
			$rows[] = $this->load_row( $field, $group, $ref, $index );
		}

		return $rows;
	}

	/**
	 * Raw values of one stored row.
	 *
	 * @param Field      $field  Repeater or Stacks field.
	 * @param Group|null $group  Owning group.
	 * @param Object_Ref $ref    Object.
	 * @param int        $index  Row index.
	 * @param string     $layout Stacks layout name.
	 * @return array<string, mixed>
	 */
	public function load_row( Field $field, ?Group $group, Object_Ref $ref, int $index, string $layout = '' ): array {
		$row = $this->load_set( $this->children( $field, $index, $layout ), $group, $ref );

		if ( Structure_Field::STACKS === $this->structure( $field ) ) {
			$row['_layout'] = $layout;
		}

		return $row;
	}

	/**
	 * Raw default value: sub-field defaults for a Group, no rows otherwise.
	 *
	 * @param Field $field Structure field.
	 * @return array<int|string, mixed>
	 */
	public function default_raw( Field $field ): array {
		if ( Structure_Field::GROUP !== $this->structure( $field ) ) {
			return array();
		}

		$set = array();
		foreach ( $field->sub_fields() as $sub ) {
			if ( $this->stores_value( $sub ) ) {
				$set[ $sub->name() ] = $this->values->default_raw( $sub );
			}
		}

		return $set;
	}

	/**
	 * Format a raw value for templates: sub-values by name.
	 *
	 * @param Field      $field Structure field.
	 * @param mixed      $raw   Raw value.
	 * @param Object_Ref $ref   Object.
	 * @return array<int|string, mixed>
	 */
	public function format( Field $field, $raw, Object_Ref $ref ): array {
		$raw = is_array( $raw ) ? $raw : array();

		if ( Structure_Field::GROUP === $this->structure( $field ) ) {
			return $this->format_set( $this->children( $field ), $raw, $ref );
		}

		$stacks = Structure_Field::STACKS === $this->structure( $field );
		$rows   = array();

		foreach ( array_values( $raw ) as $index => $row ) {
			$row    = is_array( $row ) ? $row : array();
			$layout = $stacks ? (string) ( $row['_layout'] ?? '' ) : '';

			if ( $stacks && ! $field->layout( $layout ) ) {
				continue;
			}

			$formatted = $this->format_set( $this->children( $field, $index, $layout ), $row, $ref );

			if ( $stacks ) {
				$formatted = array( '_layout' => $layout ) + $formatted;
			}

			$rows[] = $formatted;
		}

		return $rows;
	}

	/**
	 * Editor form of a raw value: sub-values by field key.
	 *
	 * @param Field $field Structure field.
	 * @param mixed $raw   Raw value.
	 * @return array<int|string, mixed>
	 */
	public function editor( Field $field, $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();

		if ( Structure_Field::GROUP === $this->structure( $field ) ) {
			return $this->editor_set( $this->children( $field ), $raw );
		}

		$rows = array();
		foreach ( array_values( $raw ) as $index => $row ) {
			$entry = $this->editor_row( $field, is_array( $row ) ? $row : array(), $index );

			if ( null !== $entry ) {
				$rows[] = $entry;
			}
		}

		return $rows;
	}

	/**
	 * Editor value straight from storage. Top-level Repeaters and Stacks with
	 * pagination on load only their first page; the rest come over REST.
	 *
	 * @param Field      $field  Structure field.
	 * @param Group|null $group  Owning group.
	 * @param Object_Ref $ref    Object.
	 * @param bool       $nested Whether the field sits inside another structure.
	 * @return array<int|string, mixed>
	 */
	public function editor_value( Field $field, ?Group $group, Object_Ref $ref, bool $nested ): array {
		// Block values all arrive with the block, so there is nothing to page in later.
		if ( $nested || $ref->is_block() || ! $this->is_paged( $field ) ) {
			return $this->editor( $field, $this->load_raw( $field, $group, $ref ) );
		}

		$stacks   = Structure_Field::STACKS === $this->structure( $field );
		$layouts  = $stacks ? $this->stored_layouts( $field, $group, $ref ) : array();
		$total    = $stacks ? count( $layouts ) : $this->count( $field, $group, $ref );
		$per_page = $this->per_page( $field );

		return array(
			'paged'   => true,
			'total'   => $total,
			'perPage' => $per_page,
			'layouts' => $layouts,
			'rows'    => $this->editor_rows( $field, $group, $ref, range( 0, max( 0, min( $per_page, $total ) - 1 ) ) ),
		);
	}

	/**
	 * Editor rows for chosen stored indexes. Indexes past the end are skipped.
	 *
	 * @param Field      $field   Repeater or Stacks field.
	 * @param Group|null $group   Owning group.
	 * @param Object_Ref $ref     Object.
	 * @param int[]      $indexes Row indexes.
	 * @return array<int, array<string, mixed>>
	 */
	public function editor_rows( Field $field, ?Group $group, Object_Ref $ref, array $indexes ): array {
		$stacks  = Structure_Field::STACKS === $this->structure( $field );
		$layouts = $stacks ? $this->stored_layouts( $field, $group, $ref ) : array();
		$total   = $stacks ? count( $layouts ) : $this->count( $field, $group, $ref );
		$rows    = array();

		foreach ( $indexes as $index ) {
			$index = (int) $index;

			if ( $index < 0 || $index >= $total ) {
				continue;
			}

			$layout = $stacks ? $layouts[ $index ] : '';
			$entry  = $this->editor_row( $field, $this->load_row( $field, $group, $ref, $index, $layout ), $index );

			if ( null !== $entry ) {
				$rows[] = $entry;
			}
		}

		return $rows;
	}

	/**
	 * Whether a Repeater or Stacks field shows its rows in pages.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_paged( Field $field ): bool {
		return Structure_Field::GROUP !== $this->structure( $field ) && (bool) $field->setting( 'pagination', false );
	}

	/**
	 * Rows per page.
	 *
	 * @param Field $field Field definition.
	 * @return int
	 */
	public function per_page( Field $field ): int {
		$per_page = (int) $field->setting( 'per_page', self::DEFAULT_PER_PAGE );

		return $per_page > 0 ? $per_page : self::DEFAULT_PER_PAGE;
	}

	/**
	 * Clean submitted input into the sanitized form.
	 *
	 * Accepts sub-values keyed by field key (the edit screen) or by name
	 * (cfmb_update_field()). Rows may come as a list, or as
	 * { rows: [...] } from a paged editor.
	 *
	 * @param Field $field Structure field.
	 * @param mixed $value Raw input.
	 * @return array<int|string, mixed>
	 */
	public function sanitize( Field $field, $value ): array {
		$structure = $this->structure( $field );
		$value     = is_array( $value ) ? $value : array();

		if ( Structure_Field::GROUP === $structure ) {
			return $this->sanitize_set( $field->sub_fields(), $value );
		}

		$rows = isset( $value['rows'] ) && is_array( $value['rows'] ) ? $value['rows'] : $value;
		if ( array_values( $rows ) !== $rows ) {
			return array();
		}

		$stacks = Structure_Field::STACKS === $structure;
		$clean  = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$from   = isset( $row['_from'] ) && is_numeric( $row['_from'] ) && (int) $row['_from'] >= 0 ? (int) $row['_from'] : null;
			$layout = $stacks ? sanitize_key( (string) ( $row['_layout'] ?? '' ) ) : '';
			$values = array_diff_key( $row, array_flip( array( '_from', '_layout', '_uid', '_keep' ) ) );

			// A row the editor never loaded: keep what is stored.
			if ( ! empty( $row['_keep'] ) && null !== $from ) {
				$clean[] = array(
					'_keep'   => true,
					'_from'   => $from,
					'_layout' => $layout,
				);
				continue;
			}

			if ( $stacks && ! $field->layout( $layout ) ) {
				continue;
			}

			$set = $this->sanitize_set( $this->definitions( $field, $layout ), $values );

			if ( $stacks ) {
				$set['_layout'] = $layout;
			}

			// Where the row was stored before this edit; only used to place error messages.
			if ( null !== $from ) {
				$set['_from'] = $from;
			}

			$clean[] = $set;
		}

		return $clean;
	}

	/**
	 * Validation errors inside a sanitized value, keyed by path below the field.
	 *
	 * @param Field $field   Structure field.
	 * @param mixed $value   Sanitized value.
	 * @param bool  $enforce Whether required fields must have a value.
	 * @return array<string, string>
	 */
	public function errors( Field $field, $value, bool $enforce ): array {
		$value     = is_array( $value ) ? $value : array();
		$structure = $this->structure( $field );

		if ( Structure_Field::GROUP === $structure ) {
			return $this->set_errors( $field->sub_fields(), $value, $enforce, '' );
		}

		$errors = array();
		$count  = count( $value );
		$min    = (int) $field->setting( 'min', 0 );
		$max    = (int) $field->setting( 'max', 0 );

		if ( $min > 0 && $count < $min && ( $count > 0 || $enforce ) ) {
			/* translators: %d: minimum number of rows */
			$errors[''] = sprintf( _n( 'Add at least %d row.', 'Add at least %d rows.', $min, 'cfmb' ), $min );
		} elseif ( $max > 0 && $count > $max ) {
			/* translators: %d: maximum number of rows */
			$errors[''] = sprintf( _n( 'Use %d row or fewer.', 'Use %d rows or fewer.', $max, 'cfmb' ), $max );
		}

		if ( Structure_Field::STACKS === $structure && ! isset( $errors[''] ) ) {
			$layout_error = $this->layout_count_error( $field, $value, $enforce );

			if ( null !== $layout_error ) {
				$errors[''] = $layout_error;
			}
		}

		foreach ( array_values( $value ) as $index => $row ) {
			if ( ! is_array( $row ) || ! empty( $row['_keep'] ) ) {
				continue;
			}

			$fields     = $this->definitions( $field, (string) ( $row['_layout'] ?? '' ) );
			$row_errors = $this->set_errors( $fields, $row, $enforce, '' );

			if ( ! $row_errors ) {
				continue;
			}

			// An invalid save keeps the stored rows, so errors point at a row's stored
			// position; that is where it shows after the reload. A new row isn't stored
			// anywhere, so its problem is reported on the field itself.
			if ( isset( $row['_from'] ) ) {
				foreach ( $row_errors as $path => $message ) {
					$errors[ (int) $row['_from'] . '.' . $path ] = $message;
				}
			} elseif ( ! isset( $errors[''] ) ) {
				$errors[''] = sprintf(
					/* translators: 1: row number, 2: error message */
					__( 'New row %1$d was not saved: %2$s', 'cfmb' ),
					$index + 1,
					reset( $row_errors )
				);
			}
		}

		return $errors;
	}

	/**
	 * Store a sanitized value, moving and removing rows as needed.
	 *
	 * Rows that moved are read before anything is written, so a row can move
	 * onto an index whose old data it replaces.
	 *
	 * @param Field      $field Structure field.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @param mixed      $value Sanitized value.
	 * @return bool
	 */
	public function store( Field $field, ?Group $group, Object_Ref $ref, $value ): bool {
		$value     = is_array( $value ) ? $value : array();
		$structure = $this->structure( $field );

		if ( Structure_Field::GROUP === $structure ) {
			$this->store_set( $this->children( $field ), $group, $ref, $value );

			return true;
		}

		$stacks      = Structure_Field::STACKS === $structure;
		$old_layouts = $stacks ? $this->stored_layouts( $field, $group, $ref ) : array();
		$old_count   = $stacks ? count( $old_layouts ) : $this->count( $field, $group, $ref );
		$plan        = array();

		foreach ( array_values( $value ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$index = count( $plan );

			if ( empty( $row['_keep'] ) ) {
				$plan[] = array(
					'keep'   => false,
					'layout' => $stacks ? (string) ( $row['_layout'] ?? '' ) : '',
					'values' => $row,
				);
				continue;
			}

			$from = (int) ( $row['_from'] ?? -1 );
			if ( $from < 0 || $from >= $old_count ) {
				continue;
			}

			$layout = $stacks ? $old_layouts[ $from ] : '';
			if ( $stacks && ! $field->layout( $layout ) ) {
				continue;
			}

			$plan[] = $from === $index
				? array(
					'keep'   => true,
					'layout' => $layout,
				)
				: array(
					'keep'   => false,
					'layout' => $layout,
					'values' => $this->load_row( $field, $group, $ref, $from, $layout ),
				);
		}

		foreach ( $plan as $index => $entry ) {
			if ( $entry['keep'] ) {
				continue;
			}

			// A different layout at this index leaves values the new one won't overwrite.
			if ( $stacks && $index < $old_count && $old_layouts[ $index ] !== $entry['layout'] ) {
				$this->purge_row( $field, $group, $ref, $index, $old_layouts[ $index ] );
			}

			$this->store_set( $this->children( $field, $index, $entry['layout'] ), $group, $ref, $entry['values'] );
		}

		for ( $index = count( $plan ); $index < $old_count; $index++ ) {
			$this->purge_row( $field, $group, $ref, $index, $stacks ? $old_layouts[ $index ] : '' );
		}

		$store = $this->stores->for_group( $group, $ref );

		if ( $stacks ) {
			return $store->set( $ref, $field->name(), array_column( $plan, 'layout' ), true );
		}

		return $store->set( $ref, $field->name(), count( $plan ), false );
	}

	/**
	 * Delete every stored value of a structure field.
	 *
	 * @param Field      $field Structure field.
	 * @param Group|null $group Owning group.
	 * @param Object_Ref $ref   Object.
	 * @return bool
	 */
	public function delete( Field $field, ?Group $group, Object_Ref $ref ): bool {
		$structure = $this->structure( $field );

		if ( Structure_Field::GROUP === $structure ) {
			foreach ( $this->children( $field ) as $child ) {
				$this->values->delete( $child, $group, $ref );
			}

			return true;
		}

		if ( Structure_Field::STACKS === $structure ) {
			foreach ( $this->stored_layouts( $field, $group, $ref ) as $index => $layout ) {
				$this->purge_row( $field, $group, $ref, $index, $layout );
			}
		} else {
			$count = $this->count( $field, $group, $ref );
			for ( $index = 0; $index < $count; $index++ ) {
				$this->purge_row( $field, $group, $ref, $index );
			}
		}

		return $this->stores->for_group( $group, $ref )->delete( $ref, $field->name() );
	}

	/**
	 * Delete the values of one row.
	 *
	 * @param Field      $field  Repeater or Stacks field.
	 * @param Group|null $group  Owning group.
	 * @param Object_Ref $ref    Object.
	 * @param int        $index  Row index.
	 * @param string     $layout Stacks layout name.
	 * @return void
	 */
	private function purge_row( Field $field, ?Group $group, Object_Ref $ref, int $index, string $layout = '' ): void {
		foreach ( $this->children( $field, $index, $layout ) as $child ) {
			if ( $this->stores_value( $child ) ) {
				$this->values->delete( $child, $group, $ref );
			}
		}
	}

	/**
	 * Load raw values of renamed sub-fields, keyed by their own names.
	 *
	 * @param Field[]    $children Renamed sub-fields.
	 * @param Group|null $group    Owning group.
	 * @param Object_Ref $ref      Object.
	 * @return array<string, mixed>
	 */
	private function load_set( array $children, ?Group $group, Object_Ref $ref ): array {
		$set = array();

		foreach ( $children as $child ) {
			if ( $this->stores_value( $child ) ) {
				$set[ $child->base_name() ] = $this->values->load( $child, $group, $ref, false );
			}
		}

		return $set;
	}

	/**
	 * Format raw values of renamed sub-fields.
	 *
	 * @param Field[]              $children Renamed sub-fields.
	 * @param array<string, mixed> $raw      Raw values by name.
	 * @param Object_Ref           $ref      Object.
	 * @return array<string, mixed>
	 */
	private function format_set( array $children, array $raw, Object_Ref $ref ): array {
		$set = array();

		foreach ( $children as $child ) {
			if ( $this->stores_value( $child ) ) {
				$name         = $child->base_name();
				$value        = array_key_exists( $name, $raw ) ? $raw[ $name ] : $this->values->default_raw( $child );
				$set[ $name ] = $this->values->format_raw( $child, $value, $ref );
			}
		}

		return $set;
	}

	/**
	 * Editor values of renamed sub-fields, keyed by field key.
	 *
	 * @param Field[]              $children Renamed sub-fields.
	 * @param array<string, mixed> $raw      Raw values by name.
	 * @return array<string, mixed>
	 */
	private function editor_set( array $children, array $raw ): array {
		$set = array();

		foreach ( $children as $child ) {
			if ( $this->stores_value( $child ) ) {
				$name                = $child->base_name();
				$value               = array_key_exists( $name, $raw ) ? $raw[ $name ] : $this->values->default_raw( $child );
				$set[ $child->key() ] = $this->values->editor_raw( $child, $value );
			}
		}

		return $set;
	}

	/**
	 * Editor form of one raw row, or null if its layout no longer exists.
	 *
	 * @param Field                $field Repeater or Stacks field.
	 * @param array<string, mixed> $row   Raw row.
	 * @param int                  $index Stored index.
	 * @return array<string, mixed>|null
	 */
	private function editor_row( Field $field, array $row, int $index ): ?array {
		$stacks = Structure_Field::STACKS === $this->structure( $field );
		$layout = $stacks ? (string) ( $row['_layout'] ?? '' ) : '';

		if ( $stacks && ! $field->layout( $layout ) ) {
			return null;
		}

		$entry = array( '_from' => $index );

		if ( $stacks ) {
			$entry['_layout'] = $layout;
		}

		return array_merge( $entry, $this->editor_set( $this->children( $field, $index, $layout ), $row ) );
	}

	/**
	 * Sanitize one set of sub-values. Input may be keyed by field key or name.
	 *
	 * @param Field[]              $fields Sub-field definitions.
	 * @param array<string, mixed> $input  Raw input.
	 * @return array<string, mixed>
	 */
	private function sanitize_set( array $fields, array $input ): array {
		$set = array();

		foreach ( $fields as $sub ) {
			if ( ! $this->stores_value( $sub ) ) {
				continue;
			}

			if ( array_key_exists( $sub->key(), $input ) ) {
				$raw = $input[ $sub->key() ];
			} elseif ( array_key_exists( $sub->name(), $input ) ) {
				$raw = $input[ $sub->name() ];
			} else {
				$raw = null;
			}

			$set[ $sub->name() ] = $this->values->sanitize( $sub, $raw );
		}

		return $set;
	}

	/**
	 * Errors in one set of sanitized sub-values. Conditional logic is checked
	 * against siblings in the same set, as on the edit screen.
	 *
	 * @param Field[]              $fields  Sub-field definitions.
	 * @param array<string, mixed> $set     Sanitized values by name.
	 * @param bool                 $enforce Whether required fields must have a value.
	 * @param string               $prefix  Path prefix, e.g. "2.".
	 * @return array<string, string>
	 */
	private function set_errors( array $fields, array $set, bool $enforce, string $prefix ): array {
		$by_key = array();
		foreach ( $fields as $sub ) {
			$by_key[ $sub->key() ] = $set[ $sub->name() ] ?? null;
		}

		$errors = array();

		foreach ( $fields as $sub ) {
			if ( ! $this->stores_value( $sub ) ) {
				continue;
			}

			$visible = Conditions::visible( $sub, $by_key );
			$tree    = $this->values->validate_tree( $sub, $set[ $sub->name() ] ?? null, $enforce && $visible );

			foreach ( $tree as $path => $message ) {
				$errors[ $prefix . $sub->key() . ( '' === $path ? '' : '.' . $path ) ] = $message;
			}
		}

		return $errors;
	}

	/**
	 * Store sanitized values for renamed sub-fields.
	 *
	 * @param Field[]              $children Renamed sub-fields.
	 * @param Group|null           $group    Owning group.
	 * @param Object_Ref           $ref      Object.
	 * @param array<string, mixed> $values   Sanitized values by name.
	 * @return void
	 */
	private function store_set( array $children, ?Group $group, Object_Ref $ref, array $values ): void {
		foreach ( $children as $child ) {
			if ( $this->stores_value( $child ) ) {
				$name = $child->base_name();
				$this->values->store( $child, $group, $ref, array_key_exists( $name, $values ) ? $values[ $name ] : $this->values->default_raw( $child ) );
			}
		}
	}

	/**
	 * Per-layout minimum and maximum of a Stacks field.
	 *
	 * @param Field             $field   Stacks field.
	 * @param array<int, mixed> $rows    Sanitized rows.
	 * @param bool              $enforce Whether minimums apply.
	 * @return string|null
	 */
	private function layout_count_error( Field $field, array $rows, bool $enforce ): ?string {
		$counts = array_count_values(
			array_map( static fn( $row ) => is_array( $row ) ? (string) ( $row['_layout'] ?? '' ) : '', $rows )
		);

		foreach ( $field->layouts() as $layout ) {
			$count = $counts[ $layout['name'] ] ?? 0;

			if ( $layout['min'] > 0 && $count < $layout['min'] && $enforce ) {
				return sprintf(
					/* translators: 1: number of rows, 2: layout label */
					_n( 'Add at least %1$d "%2$s" row.', 'Add at least %1$d "%2$s" rows.', $layout['min'], 'cfmb' ),
					$layout['min'],
					$layout['label']
				);
			}

			if ( $layout['max'] > 0 && $count > $layout['max'] ) {
				return sprintf(
					/* translators: 1: number of rows, 2: layout label */
					_n( 'Use %1$d "%2$s" row or fewer.', 'Use %1$d "%2$s" rows or fewer.', $layout['max'], 'cfmb' ),
					$layout['max'],
					$layout['label']
				);
			}
		}

		return null;
	}

	/**
	 * Whether a sub-field stores a value (tabs, messages and accordions don't).
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	private function stores_value( Field $field ): bool {
		$type = $this->values->type_of( $field );

		return $type && $type->stores_value();
	}
}
