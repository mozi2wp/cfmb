<?php
/**
 * Field Group value object.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * A Field Group: fields, location rules and display settings.
 */
final class Group {

	/**
	 * Version of the JSON format stored in post_content.
	 */
	public const FORMAT_VERSION = 1;

	/**
	 * Group key, e.g. grp_1a2b3c4d5e.
	 *
	 * @var string
	 */
	private string $key;

	/**
	 * Title shown on edit screens.
	 *
	 * @var string
	 */
	private string $title;

	/**
	 * Post ID of the stored group, 0 for groups not stored in the database.
	 *
	 * @var int
	 */
	private int $id;

	/**
	 * Whether the group is shown on edit screens.
	 *
	 * @var bool
	 */
	private bool $active;

	/**
	 * Sort order among groups on the same screen.
	 *
	 * @var int
	 */
	private int $order;

	/**
	 * Fields, in display order, with presets expanded.
	 *
	 * @var Field[]
	 */
	private array $fields;

	/**
	 * Field definitions as stored, before presets were expanded.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $stored_fields;

	/**
	 * Location rule groups (OR of AND).
	 *
	 * @var array<int, array<int, array<string, string>>>
	 */
	private array $location;

	/**
	 * Display settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Where the group comes from: db, code or file.
	 *
	 * @var string
	 */
	private string $source;

	/**
	 * Schema Sync file the group was read from, for file groups.
	 *
	 * @var string
	 */
	private string $file;

	/**
	 * Build a group from its normalized array form.
	 *
	 * @param array<string, mixed> $group Group array, see to_array().
	 */
	public function __construct( array $group ) {
		$group = array_merge( self::defaults(), $group );

		$this->key      = (string) $group['key'];
		$this->title    = (string) $group['title'];
		$this->id       = (int) $group['id'];
		$this->active   = (bool) $group['active'];
		$this->order    = (int) $group['order'];
		$this->location = is_array( $group['location'] ) ? $group['location'] : array();
		$this->settings = array_merge( self::default_settings(), is_array( $group['settings'] ) ? $group['settings'] : array() );
		// Groups without a post and without a source come from code (the cfmb/groups/loaded filter).
		$this->source        = in_array( $group['source'], array( 'db', 'code', 'file' ), true ) ? (string) $group['source'] : ( $this->id > 0 ? 'db' : 'code' );
		$this->file          = (string) $group['file'];
		$this->stored_fields = array_values( array_filter( (array) $group['fields'], 'is_array' ) );
		$this->fields        = $this->build_fields( $this->stored_fields );
	}

	/**
	 * Copy with presets expanded. The stored definition stays as it was, so
	 * the builder and to_array() still see the preset fields themselves.
	 *
	 * @param array<int, array<string, mixed>> $definitions Expanded field definitions.
	 * @return Group
	 */
	public function with_resolved_fields( array $definitions ): Group {
		$copy         = clone $this;
		$copy->fields = $copy->build_fields( $definitions );

		return $copy;
	}

	/**
	 * Field objects from definitions.
	 *
	 * @param array<int, mixed> $definitions Definitions.
	 * @return Field[]
	 */
	private function build_fields( array $definitions ): array {
		$fields = array();

		foreach ( $definitions as $definition ) {
			if ( is_array( $definition ) ) {
				$fields[] = new Field( $definition, $this->key );
			}
		}

		return $fields;
	}

	/**
	 * Base shape of a group array.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'key'      => '',
			'title'    => '',
			'id'       => 0,
			'active'   => true,
			'order'    => 0,
			'fields'   => array(),
			'location' => array(),
			'settings' => array(),
			'source'   => '',
			'file'     => '',
		);
	}

	/**
	 * Default display settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array {
		return array(
			'position'        => 'normal',
			'label_placement' => 'top',
			'description'     => '',
			'preset_only'     => false,
			'show_in_rest'    => false,
			'block'           => array(),
		);
	}

	/**
	 * Group key.
	 *
	 * @return string
	 */
	public function key(): string {
		return $this->key;
	}

	/**
	 * Group title.
	 *
	 * @return string
	 */
	public function title(): string {
		return $this->title;
	}

	/**
	 * Stored post ID, 0 if not stored.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->id;
	}

	/**
	 * Whether the group is active.
	 *
	 * @return bool
	 */
	public function active(): bool {
		return $this->active;
	}

	/**
	 * Sort order.
	 *
	 * @return int
	 */
	public function order(): int {
		return $this->order;
	}

	/**
	 * Fields in display order.
	 *
	 * @return Field[]
	 */
	public function fields(): array {
		return $this->fields;
	}

	/**
	 * Find a field by key.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function field( string $key ): ?Field {
		foreach ( $this->fields as $field ) {
			if ( $field->key() === $key ) {
				return $field;
			}
		}

		return null;
	}

	/**
	 * Find a field by key at any depth: inside Groups, Repeaters and Stacks layouts.
	 *
	 * @param string $key Field key.
	 * @return Field|null
	 */
	public function find_field( string $key ): ?Field {
		return Field::find_in( $this->fields, $key );
	}

	/**
	 * Field definitions as stored, with presets not expanded.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function stored_fields(): array {
		return $this->stored_fields;
	}

	/**
	 * Where the group comes from: db (made in the builder), code or file (Schema Sync).
	 *
	 * @return string
	 */
	public function source(): string {
		return $this->source;
	}

	/**
	 * Schema Sync file the group was read from, or empty.
	 *
	 * @return string
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Whether the group only exists to be inserted into other groups as a preset.
	 *
	 * @return bool
	 */
	public function preset_only(): bool {
		return ! empty( $this->settings['preset_only'] );
	}

	/**
	 * Full name of the Field Block this group defines, e.g. cfmb/hero, or
	 * empty when the group is not a Field Block.
	 *
	 * @return string
	 */
	public function block_name(): string {
		$block = is_array( $this->settings['block'] ?? null ) ? $this->settings['block'] : array();

		if ( empty( $block['enabled'] ) || '' === (string) ( $block['name'] ?? '' ) ) {
			return '';
		}

		return 'cfmb/' . $block['name'];
	}

	/**
	 * Location rule groups.
	 *
	 * @return array<int, array<int, array<string, string>>>
	 */
	public function location(): array {
		return $this->location;
	}

	/**
	 * Read a display setting.
	 *
	 * @param string $name Setting name.
	 * @return mixed
	 */
	public function setting( string $name ) {
		return $this->settings[ $name ] ?? null;
	}

	/**
	 * Array form, the same shape the REST API and JSON storage use.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'key'      => $this->key,
			'title'    => $this->title,
			'id'       => $this->id,
			'active'   => $this->active,
			'order'    => $this->order,
			'fields'   => $this->stored_fields,
			'location' => $this->location,
			'settings' => $this->settings,
			'source'   => $this->source,
		);
	}
}
