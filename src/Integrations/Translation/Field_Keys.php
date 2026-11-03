<?php
/**
 * Maps stored meta keys back to the fields that wrote them.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Translation;

use CFMB\Fields\Compound_Field;
use CFMB\Fields\Registry;
use CFMB\Fields\Structure_Field;
use CFMB\Integrations\Field_Integrations;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Translation plugins work with meta keys (price, items_0_title,
 * cta_url), not with fields. This builds one pattern per stored key shape
 * so any key can be traced to its field and translation mode.
 *
 * Row counts (items = 2) and Stacks layout lists follow the mode of their
 * structure, so a translated Repeater can have a different number of rows
 * per language.
 */
class Field_Keys {

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Field types.
	 *
	 * @var Registry
	 */
	private Registry $types;

	/**
	 * Built entries, per request.
	 *
	 * @var array<int, array{pattern: string, root: string, exact: bool, mode: string, field: Field, part: string}>|null
	 */
	private ?array $entries = null;

	/**
	 * Lookups already answered, per request.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups Field Groups.
	 * @param Registry         $types  Field types.
	 */
	public function __construct( Group_Repository $groups, Registry $types ) {
		$this->groups = $groups;
		$this->types  = $types;
	}

	/**
	 * Forget built entries, e.g. after a group was saved.
	 *
	 * @return void
	 */
	public function reset(): void {
		$this->entries = null;
		$this->cache   = array();
	}

	/**
	 * Field, mode and part behind a stored key, or null when CFMB did not write it.
	 *
	 * @param string $meta_key Stored key.
	 * @return array{mode: string, field: Field, part: string}|null
	 */
	public function find( string $meta_key ): ?array {
		if ( array_key_exists( $meta_key, $this->cache ) ) {
			return $this->cache[ $meta_key ];
		}

		$found = null;

		foreach ( $this->entries() as $entry ) {
			if ( 1 === preg_match( $entry['pattern'], $meta_key ) ) {
				$found = array(
					'mode'  => $entry['mode'],
					'field' => $entry['field'],
					'part'  => $entry['part'],
				);
				break;
			}
		}

		$this->cache[ $meta_key ] = $found;

		return $found;
	}

	/**
	 * Translation mode of a stored key, or empty when CFMB did not write it.
	 *
	 * @param string $meta_key Stored key.
	 * @return string
	 */
	public function mode( string $meta_key ): string {
		$found = $this->find( $meta_key );

		return $found ? $found['mode'] : '';
	}

	/**
	 * Every key shape: exact keys for top-level and Group fields, and the
	 * first part of row keys (items_), which callers expand from the database.
	 *
	 * @return array<int, array{pattern: string, root: string, exact: bool, mode: string, field: Field, part: string}>
	 */
	public function entries(): array {
		if ( null !== $this->entries ) {
			return $this->entries;
		}

		$this->entries = array();

		foreach ( $this->groups->active() as $group ) {
			if ( $group instanceof Group && '' === $group->block_name() ) {
				$this->walk( $group->fields(), '', '', '', true );
			}
		}

		return $this->entries;
	}

	/**
	 * Add entries for a field list.
	 *
	 * @param Field[] $fields    Fields.
	 * @param string  $prefix    Regex for what comes before the name at this level.
	 * @param string  $root      Literal start of every key at this level, for database lookups.
	 * @param string  $inherited Mode set on the closest parent structure.
	 * @param bool    $exact     Whether keys at this level have no row index (top level or plain Groups).
	 * @return void
	 */
	private function walk( array $fields, string $prefix, string $root, string $inherited, bool $exact ): void {
		foreach ( $fields as $field ) {
			$type = $this->types->get( $field->type() );

			if ( ! $type || ! $type->stores_value() || '' === $field->name() ) {
				continue;
			}

			/**
			 * Filters a field's translation mode: translate, copy, copy_once or ignore.
			 *
			 * @param string $mode  Mode in effect.
			 * @param Field  $field Field.
			 */
			$mode = (string) apply_filters( 'cfmb/translation/mode', Field_Integrations::translation( $field, $inherited ), $field ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$mode = in_array( $mode, Field_Integrations::TRANSLATION_MODES, true ) ? $mode : Field_Integrations::COPY;
			$own   = Field_Integrations::own_translation( $field );
			$name  = preg_quote( $field->name(), '/' );
			$label = $exact ? $root . $field->name() : $root;

			if ( $type instanceof Structure_Field ) {
				$pass = '' !== $own ? $own : $inherited;

				if ( Structure_Field::GROUP === $type->structure() ) {
					$this->walk( $field->sub_fields(), $prefix . $name . '_', $exact ? $root . $field->name() . '_' : $root, $pass, $exact );
					continue;
				}

				$this->add( '/^' . $prefix . $name . '$/', $label, $exact, $mode, $field );

				$children = $prefix . $name . '_\d+_';
				$child_root = $exact ? $root . $field->name() . '_' : $root;
				$lists      = Structure_Field::STACKS === $type->structure()
					? array_map( static fn( $layout ) => $layout['fields'], $field->layouts() )
					: array( $field->sub_fields() );

				foreach ( $lists as $list ) {
					$this->walk( $list, $children, $child_root, $pass, false );
				}
				continue;
			}

			if ( $type instanceof Compound_Field ) {
				foreach ( $type->parts() as $part ) {
					$this->add( '/^' . $prefix . $name . '_' . preg_quote( $part, '/' ) . '$/', $exact ? $label . '_' . $part : $root, $exact, $mode, $field, $part );
				}
				continue;
			}

			$this->add( '/^' . $prefix . $name . '$/', $label, $exact, $mode, $field );
		}
	}

	/**
	 * Store one entry.
	 *
	 * @param string $pattern Regex.
	 * @param string $root    Exact key, or the literal start of row keys.
	 * @param bool   $exact   Whether $root is the whole key.
	 * @param string $mode    Translation mode.
	 * @param Field  $field   Field.
	 * @param string $part    Compound part, or empty.
	 * @return void
	 */
	private function add( string $pattern, string $root, bool $exact, string $mode, Field $field, string $part = '' ): void {
		$this->entries[] = array(
			'pattern' => $pattern,
			'root'    => $root,
			'exact'   => $exact,
			'mode'    => $mode,
			'field'   => $field,
			'part'    => $part,
		);
	}
}
