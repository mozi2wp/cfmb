<?php
/**
 * Field definition value object.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * One field inside a Field Group. Immutable once built.
 *
 * Shape (also the JSON shape inside a group):
 * {
 *   key: "fld_1a2b3c4d5e", name: "price", label: "Price", type: "number",
 *   instructions: "", required: false,
 *   wrapper: { width: 0, class: "" },
 *   conditions: [ [ { field: "fld_...", operator: "==", value: "1" } ] ],
 *   rest: true, (included when the group shows in REST)
 *   admin: { column: false, sortable: false, filter: "" }, (list screens, top-level fields)
 *   integrations: { translation: "copy", seo: false, graphql: false }, (optional, only non-defaults)
 *   settings: { ...type specific },
 *   fields: [ ...sub-fields, Group and Repeater only ],
 *   layouts: [ { key: "lay_...", name, label, min, max, fields: [...] }, Stacks only ]
 * }
 */
final class Field {

	/**
	 * Normalized definition.
	 *
	 * @var array<string, mixed>
	 */
	private array $definition;

	/**
	 * Key of the group this field belongs to.
	 *
	 * @var string
	 */
	private string $group_key;

	/**
	 * Built sub-fields, on first use.
	 *
	 * @var Field[]|null
	 */
	private ?array $sub_fields = null;

	/**
	 * Built layouts, on first use.
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private ?array $layouts = null;

	/**
	 * Build from a definition array. Missing keys get safe defaults.
	 *
	 * @param array<string, mixed> $definition Field definition.
	 * @param string               $group_key  Owning group key.
	 */
	public function __construct( array $definition, string $group_key = '' ) {
		$this->definition = array_merge( self::defaults(), $definition );
		$this->group_key  = $group_key;
	}

	/**
	 * Base shape of every field definition.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'key'          => '',
			'name'         => '',
			'label'        => '',
			'type'         => 'text',
			'instructions' => '',
			'required'     => false,
			'wrapper'      => array(
				'width' => 0,
				'class' => '',
			),
			'conditions'   => array(),
			'rest'         => true,
			'admin'        => array(
				'column'   => false,
				'sortable' => false,
				'filter'   => '',
			),
			'settings'     => array(),
		);
	}

	/**
	 * Stable field key, never changes after creation.
	 *
	 * @return string
	 */
	public function key(): string {
		return (string) $this->definition['key'];
	}

	/**
	 * Storage name. Used as the meta key. Inside a Group or a row this is the
	 * full flattened name, such as items_0_title.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) $this->definition['name'];
	}

	/**
	 * Name as set in the builder, before nesting added a prefix.
	 *
	 * @return string
	 */
	public function base_name(): string {
		return (string) ( $this->definition['base_name'] ?? $this->definition['name'] );
	}

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function label(): string {
		return (string) $this->definition['label'];
	}

	/**
	 * Field type key.
	 *
	 * @return string
	 */
	public function type(): string {
		return (string) $this->definition['type'];
	}

	/**
	 * Whether a value is required.
	 *
	 * @return bool
	 */
	public function required(): bool {
		return (bool) $this->definition['required'];
	}

	/**
	 * Conditional logic rule groups (OR of AND).
	 *
	 * @return array<int, array<int, array<string, mixed>>>
	 */
	public function conditions(): array {
		return is_array( $this->definition['conditions'] ) ? $this->definition['conditions'] : array();
	}

	/**
	 * Whether the value is included in REST responses when its group shows in REST.
	 *
	 * @return bool
	 */
	public function in_rest(): bool {
		return false !== $this->definition['rest'];
	}

	/**
	 * List screen options: column, sortable, filter (empty, "choices" or "search").
	 *
	 * @return array{column: bool, sortable: bool, filter: string}
	 */
	public function admin(): array {
		$admin = is_array( $this->definition['admin'] ) ? $this->definition['admin'] : array();

		return array(
			'column'   => ! empty( $admin['column'] ),
			'sortable' => ! empty( $admin['sortable'] ),
			'filter'   => in_array( $admin['filter'] ?? '', array( 'choices', 'search' ), true ) ? (string) $admin['filter'] : '',
		);
	}

	/**
	 * Read a type-specific setting.
	 *
	 * @param string $name     Setting name.
	 * @param mixed  $fallback Value when the setting is missing.
	 * @return mixed
	 */
	public function setting( string $name, $fallback = null ) {
		$settings = is_array( $this->definition['settings'] ) ? $this->definition['settings'] : array();

		return array_key_exists( $name, $settings ) ? $settings[ $name ] : $fallback;
	}

	/**
	 * Read a per-field integration option: translation (WPML / Polylang
	 * mode), seo (whether SEO plugins analyse the value) or graphql
	 * (whether it shows in WPGraphQL). Options at their default are not stored.
	 *
	 * @param string $name     Option name.
	 * @param mixed  $fallback Value when the option is not set.
	 * @return mixed
	 */
	public function integration( string $name, $fallback = null ) {
		$options = is_array( $this->definition['integrations'] ?? null ) ? $this->definition['integrations'] : array();

		return array_key_exists( $name, $options ) ? $options[ $name ] : $fallback;
	}

	/**
	 * All type-specific settings.
	 *
	 * @return array<string, mixed>
	 */
	public function settings(): array {
		return is_array( $this->definition['settings'] ) ? $this->definition['settings'] : array();
	}

	/**
	 * Sub-fields of a Group or Repeater, with their names as set in the builder.
	 *
	 * @return Field[]
	 */
	public function sub_fields(): array {
		if ( null === $this->sub_fields ) {
			$this->sub_fields = self::build_list( $this->definition['fields'] ?? array(), $this->group_key );
		}

		return $this->sub_fields;
	}

	/**
	 * Layouts of a Stacks field. Each: key, name, label, min, max, fields (Field[]).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function layouts(): array {
		if ( null !== $this->layouts ) {
			return $this->layouts;
		}

		$this->layouts = array();

		foreach ( (array) ( $this->definition['layouts'] ?? array() ) as $layout ) {
			if ( ! is_array( $layout ) || '' === (string) ( $layout['name'] ?? '' ) ) {
				continue;
			}

			$this->layouts[] = array(
				'key'    => (string) ( $layout['key'] ?? '' ),
				'name'   => (string) $layout['name'],
				'label'  => (string) ( $layout['label'] ?? $layout['name'] ),
				'min'    => (int) ( $layout['min'] ?? 0 ),
				'max'    => (int) ( $layout['max'] ?? 0 ),
				'fields' => self::build_list( $layout['fields'] ?? array(), $this->group_key ),
			);
		}

		return $this->layouts;
	}

	/**
	 * One Stacks layout by name.
	 *
	 * @param string $name Layout name.
	 * @return array<string, mixed>|null
	 */
	public function layout( string $name ): ?array {
		foreach ( $this->layouts() as $layout ) {
			if ( $layout['name'] === $name ) {
				return $layout;
			}
		}

		return null;
	}

	/**
	 * Copy of this field stored under another name. Used for values inside
	 * Groups and rows, where the name gets the parent's prefix.
	 *
	 * @param string $name Full storage name.
	 * @return Field
	 */
	public function with_name( string $name ): Field {
		$definition              = $this->definition;
		$definition['base_name'] = $this->base_name();
		$definition['name']      = $name;

		return new self( $definition, $this->group_key );
	}

	/**
	 * Find a field by key in this field's sub-fields and layouts, at any depth.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function find( string $key ): ?Field {
		$lists = array( $this->sub_fields() );

		foreach ( $this->layouts() as $layout ) {
			$lists[] = $layout['fields'];
		}

		foreach ( $lists as $fields ) {
			$found = self::find_in( $fields, $key );

			if ( $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Find a field by key in a list, at any depth.
	 *
	 * @param Field[] $fields Fields.
	 * @param string  $key    Field key.
	 * @return Field|null
	 */
	public static function find_in( array $fields, string $key ): ?Field {
		foreach ( $fields as $field ) {
			if ( $field->key() === $key ) {
				return $field;
			}

			$found = $field->find( $key );

			if ( $found ) {
				return $found;
			}
		}

		return null;
	}

	/**
	 * Key of the owning group.
	 *
	 * @return string
	 */
	public function group_key(): string {
		return $this->group_key;
	}

	/**
	 * Full definition as an array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$definition = $this->definition;
		unset( $definition['base_name'] );

		return $definition;
	}

	/**
	 * Build Field objects from definitions.
	 *
	 * @param mixed  $definitions Field definitions.
	 * @param string $group_key   Owning group key.
	 * @return Field[]
	 */
	private static function build_list( $definitions, string $group_key ): array {
		$fields = array();

		foreach ( (array) $definitions as $definition ) {
			if ( is_array( $definition ) ) {
				$fields[] = new self( $definition, $group_key );
			}
		}

		return $fields;
	}
}
