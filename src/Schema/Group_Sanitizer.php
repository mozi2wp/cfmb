<?php
/**
 * Field Group input sanitizing and validation.
 *
 * @package CFMB
 */

namespace CFMB\Schema;

use CFMB\Blocks\Block_Settings;
use CFMB\Fields\Compound_Field;
use CFMB\Fields\Field_Type;
use CFMB\Fields\Registry;
use CFMB\Fields\Structure_Field;
use CFMB\Fields\Types;
use CFMB\Integrations\Field_Integrations;
use CFMB\Location\Conditions;
use CFMB\Location\Rule_Registry;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Turns builder input into a clean group array, or explains what is wrong.
 */
class Group_Sanitizer {

	/**
	 * Longest allowed field name.
	 */
	public const NAME_MAX_LENGTH = 64;

	/**
	 * Longest storage key a field may produce, nesting included.
	 *
	 * Leaves room for the `cfmb_{page}_` prefix of options page values, whose
	 * option_name column holds 191 characters.
	 */
	public const KEY_MAX_LENGTH = 150;

	/**
	 * Field types.
	 *
	 * @var Registry
	 */
	private Registry $types;

	/**
	 * Location rules.
	 *
	 * @var Rule_Registry
	 */
	private Rule_Registry $rules;

	/**
	 * Stored groups, for checking preset names. Optional.
	 *
	 * @var Group_Repository|null
	 */
	private ?Group_Repository $groups;

	/**
	 * Constructor.
	 *
	 * @param Registry              $types  Field types.
	 * @param Rule_Registry         $rules  Location rules.
	 * @param Group_Repository|null $groups Stored groups, for checking preset names.
	 */
	public function __construct( Registry $types, Rule_Registry $rules, ?Group_Repository $groups = null ) {
		$this->types  = $types;
		$this->rules  = $rules;
		$this->groups = $groups;
	}

	/**
	 * Sanitize a whole group.
	 *
	 * @param array<string, mixed> $input    Raw group from the builder.
	 * @param Group|null           $existing The stored group when updating.
	 * @return array<string, mixed>|WP_Error
	 */
	public function sanitize( array $input, ?Group $existing = null ) {
		$title = sanitize_text_field( (string) ( $input['title'] ?? '' ) );

		if ( '' === $title ) {
			return new WP_Error( 'cfmb_group_title', __( 'Give the field group a title.', 'custom-fields-meta-box' ), array( 'status' => 400 ) );
		}

		$keys   = array();
		$fields = $this->sanitize_fields( is_array( $input['fields'] ?? null ) ? $input['fields'] : array(), $keys );

		if ( is_wp_error( $fields ) ) {
			return $fields;
		}

		$key      = $existing ? $existing->key() : (string) ( $input['key'] ?? '' );
		$key      = Key_Generator::is_valid( $key, Key_Generator::GROUP_PREFIX ) ? $key : Key_Generator::group();
		$settings = $this->sanitize_settings( is_array( $input['settings'] ?? null ) ? $input['settings'] : array() );

		if ( $settings['block']['enabled'] && '' === $settings['block']['name'] ) {
			$slug                      = Block_Settings::slug( $title );
			$settings['block']['name'] = '' !== $slug ? $slug : 'block-' . strtolower( substr( $key, 4 ) );
		}

		$taken = $this->block_name_error( $settings['block'], $key );

		if ( $taken ) {
			return $taken;
		}

		return array(
			'key'      => $key,
			'title'    => $title,
			'active'   => ! empty( $input['active'] ),
			'order'    => (int) ( $input['order'] ?? 0 ),
			'fields'   => $fields,
			'location' => $this->sanitize_location( is_array( $input['location'] ?? null ) ? $input['location'] : array() ),
			'settings' => $settings,
		);
	}

	/**
	 * Error when another group already defines a Field Block with this name.
	 *
	 * @param array<string, mixed> $block Sanitized block settings.
	 * @param string               $key   Key of the group being saved.
	 * @return WP_Error|null
	 */
	private function block_name_error( array $block, string $key ): ?WP_Error {
		if ( empty( $block['enabled'] ) || ! $this->groups ) {
			return null;
		}

		foreach ( $this->groups->all() as $other ) {
			if ( $other->key() !== $key && $other->block_name() === 'cfmb/' . $block['name'] ) {
				return new WP_Error(
					'cfmb_block_name',
					/* translators: 1: block name, 2: field group title */
					sprintf( __( 'The block name "%1$s" is already used by the field group "%2$s". Pick another name.', 'custom-fields-meta-box' ), $block['name'], $other->title() ),
					array( 'status' => 400 )
				);
			}
		}

		return null;
	}

	/**
	 * Sanitize a field list at any depth.
	 *
	 * Keys must be unique across the whole group; names only within their
	 * list, because nested values get the parent's prefix.
	 *
	 * @param array<int, mixed>   $raw_fields    Raw fields.
	 * @param array<string, bool> $keys          Keys used so far in the group. Updated.
	 * @param int                 $prefix_length Length of the storage prefix at this level.
	 * @param bool                $nested        Whether the list is inside a Group, Repeater or Stacks layout.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	private function sanitize_fields( array $raw_fields, array &$keys, int $prefix_length = 0, bool $nested = false ) {
		$fields     = array();
		$names      = array();
		$level_keys = array();

		foreach ( array_values( $raw_fields ) as $position => $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}

			$type = $this->types->get( (string) ( $raw['type'] ?? '' ) );

			if ( ! $type ) {
				return $this->field_error(
					/* translators: 1: field position, 2: field type key */
					sprintf( __( 'Field %1$d uses the type "%2$s", which is not available. Is the plugin that adds it active?', 'custom-fields-meta-box' ), $position + 1, (string) ( $raw['type'] ?? '' ) ),
					$raw
				);
			}

			$key = (string) ( $raw['key'] ?? '' );
			if ( ! Key_Generator::is_valid( $key, Key_Generator::FIELD_PREFIX ) || isset( $keys[ $key ] ) ) {
				$key = Key_Generator::field();
			}
			$keys[ $key ]       = true;
			$level_keys[ $key ] = true;

			$label    = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
			$settings = $type->sanitize_settings( is_array( $raw['settings'] ?? null ) ? $raw['settings'] : array() );
			$name     = '';

			if ( $type->stores_value() || Types\Preset_Field::needs_name( $type->name(), $settings ) ) {
				$name  = self::sanitize_name( (string) ( $raw['name'] ?? '' ) );
				$error = $this->name_error( $name, $label, $position, $names, $nested );

				if ( null === $error ) {
					$error = $this->key_length_error( $prefix_length + strlen( $name ) + $this->suffix_length( $type ), $label, $position );
				}

				if ( null !== $error ) {
					return $this->field_error( $error, array_merge( $raw, array( 'key' => $key ) ) );
				}

				$names[ $name ] = true;
			}

			if ( 'preset' === $type->name() && 'plain' === ( $settings['naming'] ?? 'plain' ) ) {
				$error = $this->preset_name_error( $settings, $label, $names );

				if ( null !== $error ) {
					return $this->field_error( $error, array_merge( $raw, array( 'key' => $key ) ) );
				}
			}

			$width = (int) ( $raw['wrapper']['width'] ?? 0 );

			$field = array(
				'key'          => $key,
				'name'         => $name,
				'label'        => $label,
				'type'         => $type->name(),
				'instructions' => wp_kses_post( (string) ( $raw['instructions'] ?? '' ) ),
				'required'     => $type->stores_value() && ! empty( $raw['required'] ),
				'wrapper'      => array(
					'width' => max( 0, min( 100, $width ) ),
					'class' => $this->sanitize_classes( (string) ( $raw['wrapper']['class'] ?? '' ) ),
				),
				'conditions'   => is_array( $raw['conditions'] ?? null ) ? $raw['conditions'] : array(),
				'rest'         => ! array_key_exists( 'rest', $raw ) || ! empty( $raw['rest'] ),
				'admin'        => $this->sanitize_admin( $raw['admin'] ?? array(), $type, $settings, $nested ),
				'settings'     => $settings,
			);

			$integrations = Field_Integrations::sanitize( $raw['integrations'] ?? array(), $type->name() );
			if ( $integrations ) {
				$field['integrations'] = $integrations;
			}

			if ( $type instanceof Structure_Field ) {
				$children = $this->sanitize_children( $type, $raw, $keys, $prefix_length + strlen( $name ), $settings );

				if ( is_wp_error( $children ) ) {
					return $children;
				}

				$field = array_merge( $field, $children );
			}

			$fields[] = $field;
		}

		// Conditions can point at any sibling, so they are cleaned once every key at this level is known.
		foreach ( $fields as $index => $field ) {
			$fields[ $index ]['conditions'] = $this->sanitize_conditions( $field['conditions'], $field['key'], array_keys( $level_keys ) );
		}

		return $fields;
	}

	/**
	 * Sub-fields of a Group or Repeater, or layouts of a Stacks field.
	 *
	 * @param Structure_Field      $type        Field type.
	 * @param array<string, mixed> $raw         Raw field.
	 * @param array<string, bool>  $keys        Keys used so far. Updated.
	 * @param int                  $name_length Length of the field's full storage name.
	 * @param array<string, mixed> $settings    Sanitized settings.
	 * @return array<string, mixed>|WP_Error
	 */
	private function sanitize_children( Structure_Field $type, array $raw, array &$keys, int $name_length, array $settings ) {
		if ( Structure_Field::GROUP === $type->structure() ) {
			$fields = $this->sanitize_fields( is_array( $raw['fields'] ?? null ) ? $raw['fields'] : array(), $keys, $name_length + 1, true );

			return is_wp_error( $fields ) ? $fields : array( 'fields' => $fields );
		}

		// items_1234_ : the row index takes as many digits as the largest row number.
		$max          = (int) ( $settings['max'] ?? 0 );
		$index_length = $max > 0 ? strlen( (string) ( $max - 1 ) ) : 4;
		$child_prefix = $name_length + 1 + $index_length + 1;

		if ( Structure_Field::ROWS === $type->structure() ) {
			$fields = $this->sanitize_fields( is_array( $raw['fields'] ?? null ) ? $raw['fields'] : array(), $keys, $child_prefix, true );

			return is_wp_error( $fields ) ? $fields : array( 'fields' => $fields );
		}

		$layouts = array();
		$names   = array();

		foreach ( (array) ( $raw['layouts'] ?? array() ) as $position => $layout ) {
			if ( ! is_array( $layout ) ) {
				continue;
			}

			$label = sanitize_text_field( (string) ( $layout['label'] ?? '' ) );
			$name  = self::sanitize_name( (string) ( $layout['name'] ?? '' ) );

			if ( '' === $name ) {
				$name = self::sanitize_name( $label );
			}

			if ( '' === $name || isset( $names[ $name ] ) ) {
				return $this->field_error(
					sprintf(
						/* translators: 1: Stacks field label, 2: layout position */
						__( 'Layout %2$d in "%1$s" needs a unique name.', 'custom-fields-meta-box' ),
						sanitize_text_field( (string) ( $raw['label'] ?? '' ) ),
						(int) $position + 1
					),
					$raw
				);
			}

			$names[ $name ] = true;
			$layout_key     = (string) ( $layout['key'] ?? '' );

			if ( ! Key_Generator::is_valid( $layout_key, Key_Generator::LAYOUT_PREFIX ) || isset( $keys[ $layout_key ] ) ) {
				$layout_key = Key_Generator::layout();
			}
			$keys[ $layout_key ] = true;

			$fields = $this->sanitize_fields( is_array( $layout['fields'] ?? null ) ? $layout['fields'] : array(), $keys, $child_prefix, true );

			if ( is_wp_error( $fields ) ) {
				return $fields;
			}

			$layouts[] = array(
				'key'    => $layout_key,
				'name'   => $name,
				'label'  => '' !== $label ? $label : $name,
				'min'    => max( 0, (int) ( $layout['min'] ?? 0 ) ),
				'max'    => max( 0, (int) ( $layout['max'] ?? 0 ) ),
				'fields' => $fields,
			);
		}

		return array( 'layouts' => $layouts );
	}

	/**
	 * Clean list screen options. Only top-level fields that store one value
	 * per row can be sorted; filters need a value too.
	 *
	 * @param mixed                $raw      Raw options.
	 * @param Field_Type           $type     Field type.
	 * @param array<string, mixed> $settings Sanitized type settings.
	 * @param bool                 $nested   Whether the field sits inside a structure.
	 * @return array{column: bool, sortable: bool, filter: string}
	 */
	private function sanitize_admin( $raw, Field_Type $type, array $settings, bool $nested ): array {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = array(
			'column'   => false,
			'sortable' => false,
			'filter'   => '',
		);

		if ( $nested || ! $type->stores_value() || $type instanceof Structure_Field ) {
			return $clean;
		}

		$probe  = new Field(
			array(
				'type'     => $type->name(),
				'settings' => $settings,
			)
		);
		$single = ! $type->is_multiple( $probe ) && ! $type instanceof Compound_Field;
		$filter = (string) ( $raw['filter'] ?? '' );

		$clean['column']   = ! empty( $raw['column'] );
		$clean['sortable'] = $single && ! empty( $raw['sortable'] );
		$clean['filter']   = in_array( $filter, array( 'choices', 'search' ), true ) && ! $type instanceof Compound_Field ? $filter : '';

		if ( 'choices' === $clean['filter'] && ! self::has_choices( $type ) ) {
			$clean['filter'] = 'search';
		}

		return $clean;
	}

	/**
	 * Whether a type offers a fixed list of choices to filter by.
	 *
	 * @param Field_Type $type Field type.
	 * @return bool
	 */
	public static function has_choices( Field_Type $type ): bool {
		return $type instanceof \CFMB\Fields\Choice_Field || 'toggle' === $type->name();
	}

	/**
	 * Extra key length a type adds after its name: "_address" for a map.
	 *
	 * @param Field_Type $type Field type.
	 * @return int
	 */
	private function suffix_length( Field_Type $type ): int {
		if ( ! $type instanceof Compound_Field ) {
			return 0;
		}

		$longest = 0;
		foreach ( $type->parts() as $part ) {
			$longest = max( $longest, strlen( $part ) );
		}

		return $longest + 1;
	}

	/**
	 * Explain a storage key that would be too long, or null.
	 *
	 * @param int    $length   Longest key the field can produce.
	 * @param string $label    Field label.
	 * @param int    $position Zero-based position.
	 * @return string|null
	 */
	private function key_length_error( int $length, string $label, int $position ): ?string {
		if ( $length <= self::KEY_MAX_LENGTH ) {
			return null;
		}

		$which = '' !== $label ? $label : sprintf(
			/* translators: %d: field position */
			__( 'Field %d', 'custom-fields-meta-box' ),
			$position + 1
		);

		return sprintf(
			/* translators: 1: field label, 2: key length, 3: maximum length */
			__( '"%1$s" is nested too deeply for its name. Its saved keys can reach %2$d characters; the limit is %3$d. Use shorter names for it or the fields around it.', 'custom-fields-meta-box' ),
			$which,
			$length,
			self::KEY_MAX_LENGTH
		);
	}

	/**
	 * Explain a clash between a plain preset's names and its siblings, or null.
	 *
	 * @param array<string, mixed> $settings Preset settings.
	 * @param string               $label    Preset field label.
	 * @param array<string, bool>  $names    Names used at this level. Updated.
	 * @return string|null
	 */
	private function preset_name_error( array $settings, string $label, array &$names ): ?string {
		foreach ( $this->preset_names( $settings, array() ) as $name ) {
			if ( isset( $names[ $name ] ) ) {
				return sprintf(
					/* translators: 1: preset field label, 2: field name */
					__( 'The preset "%1$s" adds a field named "%2$s", which this list already uses. Remove one of them, or switch the preset to prefixed names.', 'custom-fields-meta-box' ),
					$label,
					$name
				);
			}

			$names[ $name ] = true;
		}

		return null;
	}

	/**
	 * Names a plain preset adds to the list it sits in, following plain
	 * presets inside its source group too.
	 *
	 * @param array<string, mixed> $settings Preset settings.
	 * @param string[]             $visited  Source groups already followed, to stop cycles.
	 * @return string[]
	 */
	private function preset_names( array $settings, array $visited ): array {
		$key    = (string) ( $settings['source'] ?? '' );
		$source = $this->groups && ! in_array( $key, $visited, true ) ? $this->groups->find( $key ) : null;

		if ( ! $source ) {
			return array();
		}

		$visited[] = $key;
		$picked    = array_map( 'strval', (array) ( $settings['fields'] ?? array() ) );
		$names     = array();

		foreach ( $source->stored_fields() as $definition ) {
			if ( $picked && ! in_array( (string) ( $definition['key'] ?? '' ), $picked, true ) ) {
				continue;
			}

			$inner = is_array( $definition['settings'] ?? null ) ? $definition['settings'] : array();

			if ( 'preset' === ( $definition['type'] ?? '' ) && 'prefixed' !== ( $inner['naming'] ?? 'plain' ) ) {
				$names = array_merge( $names, $this->preset_names( $inner, $visited ) );
			} elseif ( '' !== (string) ( $definition['name'] ?? '' ) ) {
				$names[] = (string) $definition['name'];
			}
		}

		return $names;
	}

	/**
	 * Normalize a field name: lowercase letters, digits and underscores.
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_name( string $name ): string {
		$name = strtolower( remove_accents( trim( $name ) ) );
		$name = (string) preg_replace( '/[^a-z0-9_]+/', '_', $name );

		return trim( $name, '_' ) === '' ? '' : $name;
	}

	/**
	 * Explain what is wrong with a field name, or null if it is fine.
	 *
	 * @param string              $name     Sanitized name.
	 * @param string              $label    Field label, for the message.
	 * @param int                 $position Zero-based field position.
	 * @param array<string, bool> $taken   Names already used in the same list.
	 * @param bool                $nested   Whether the field sits inside a Group, Repeater or Stacks layout.
	 * @return string|null
	 */
	private function name_error( string $name, string $label, int $position, array $taken, bool $nested = false ): ?string {
		$which = '' !== $label ? $label : sprintf(
			/* translators: %d: field position */
			__( 'Field %d', 'custom-fields-meta-box' ),
			$position + 1
		);

		if ( '' === $name ) {
			/* translators: %s: field label */
			return sprintf( __( '"%s" needs a name. The name is the key the value is saved under.', 'custom-fields-meta-box' ), $which );
		}

		if ( strlen( $name ) > self::NAME_MAX_LENGTH ) {
			/* translators: 1: field label, 2: maximum length */
			return sprintf( __( 'The name of "%1$s" is too long. Use %2$d characters or fewer.', 'custom-fields-meta-box' ), $which, self::NAME_MAX_LENGTH );
		}

		// These belong to WordPress itself; saving over them would break core features.
		if ( 0 === strpos( $name, '_wp_' ) || 0 === strpos( $name, '_edit_' ) ) {
			/* translators: %s: field label */
			return sprintf( __( 'The name of "%s" is reserved by WordPress. Choose another name.', 'custom-fields-meta-box' ), $which );
		}

		// Rows carry these alongside sub-field values.
		if ( $nested && in_array( $name, array( '_layout', '_from', '_uid' ), true ) ) {
			/* translators: %s: field label */
			return sprintf( __( 'The name of "%s" is reserved inside rows. Choose another name.', 'custom-fields-meta-box' ), $which );
		}

		if ( isset( $taken[ $name ] ) ) {
			/* translators: 1: field label, 2: field name */
			return sprintf( __( '"%1$s" uses the name "%2$s", which another field in this group already uses. Each name must be unique.', 'custom-fields-meta-box' ), $which, $name );
		}

		return null;
	}

	/**
	 * WP_Error for a field problem, carrying the field key so the builder can point at it.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $raw     Raw field.
	 * @return WP_Error
	 */
	private function field_error( string $message, array $raw ): WP_Error {
		return new WP_Error(
			'cfmb_invalid_field',
			$message,
			array(
				'status' => 400,
				'field'  => is_string( $raw['key'] ?? null ) ? $raw['key'] : '',
			)
		);
	}

	/**
	 * Clean conditional logic. Rules pointing at missing fields or at the field itself are dropped.
	 *
	 * @param mixed    $sets       Raw rule sets.
	 * @param string   $self_key   Key of the field the conditions belong to.
	 * @param string[] $field_keys Keys of all fields in the group.
	 * @return array<int, array<int, array<string, string>>>
	 */
	private function sanitize_conditions( $sets, string $self_key, array $field_keys ): array {
		$clean = array();

		foreach ( (array) $sets as $rules ) {
			$set = array();

			foreach ( (array) $rules as $rule ) {
				$target   = is_array( $rule ) ? (string) ( $rule['field'] ?? '' ) : '';
				$operator = is_array( $rule ) ? (string) ( $rule['operator'] ?? '' ) : '';

				if ( $target === $self_key || ! in_array( $target, $field_keys, true ) || ! in_array( $operator, Conditions::operators(), true ) ) {
					continue;
				}

				$set[] = array(
					'field'    => $target,
					'operator' => $operator,
					'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
				);
			}

			if ( $set ) {
				$clean[] = $set;
			}
		}

		return $clean;
	}

	/**
	 * Clean location rules. Unknown rules and operators are dropped.
	 *
	 * @param array<int, mixed> $sets Raw rule sets.
	 * @return array<int, array<int, array<string, string>>>
	 */
	private function sanitize_location( array $sets ): array {
		$clean = array();

		foreach ( $sets as $rules ) {
			$set = array();

			foreach ( (array) $rules as $rule ) {
				if ( ! is_array( $rule ) ) {
					continue;
				}

				$type     = $this->rules->get( (string) ( $rule['param'] ?? '' ) );
				$operator = (string) ( $rule['operator'] ?? '==' );

				if ( ! $type || ! array_key_exists( $operator, $type->operators() ) ) {
					continue;
				}

				$set[] = array(
					'param'    => $type->param(),
					'operator' => $operator,
					'value'    => sanitize_text_field( (string) ( $rule['value'] ?? '' ) ),
				);
			}

			if ( $set ) {
				$clean[] = $set;
			}
		}

		return $clean;
	}

	/**
	 * Clean display settings.
	 *
	 * @param array<string, mixed> $settings Raw settings.
	 * @return array<string, mixed>
	 */
	private function sanitize_settings( array $settings ): array {
		$position  = (string) ( $settings['position'] ?? 'normal' );
		$placement = (string) ( $settings['label_placement'] ?? 'top' );

		$clean = array(
			'position'        => in_array( $position, array( 'high', 'normal', 'side', 'product_data' ), true ) ? $position : 'normal',
			'label_placement' => in_array( $placement, array( 'top', 'left' ), true ) ? $placement : 'top',
			'description'     => sanitize_textarea_field( (string) ( $settings['description'] ?? '' ) ),
			'preset_only'     => ! empty( $settings['preset_only'] ),
			'show_in_rest'    => ! empty( $settings['show_in_rest'] ),
			'block'           => Block_Settings::sanitize( is_array( $settings['block'] ?? null ) ? $settings['block'] : array() ),
		);

		// Stored only while on, so groups saved before WPGraphQL support keep their Schema Sync hash.
		$graphql = is_array( $settings['graphql'] ?? null ) ? $settings['graphql'] : array();
		if ( ! empty( $graphql['enabled'] ) ) {
			$clean['graphql'] = array(
				'enabled' => true,
				'name'    => self::sanitize_graphql_name( (string) ( $graphql['name'] ?? '' ) ),
			);
		}

		return $clean;
	}

	/**
	 * GraphQL field name: letters and digits, starting with a letter, in
	 * camelCase. Empty means "build it from the group title".
	 *
	 * @param string $name Raw name.
	 * @return string
	 */
	public static function sanitize_graphql_name( string $name ): string {
		$words = preg_split( '/[^A-Za-z0-9]+/', remove_accents( trim( $name ) ) ) ?: array();
		$words = array_values( array_filter( $words, static fn( $word ) => '' !== $word ) );

		if ( ! $words ) {
			return '';
		}

		$camel = lcfirst( $words[0] );
		foreach ( array_slice( $words, 1 ) as $word ) {
			$camel .= ucfirst( $word );
		}

		$camel = (string) preg_replace( '/^[0-9]+/', '', $camel );

		return '' === $camel ? '' : lcfirst( substr( $camel, 0, 60 ) );
	}

	/**
	 * Space-separated CSS class names.
	 *
	 * @param string $classes Raw classes.
	 * @return string
	 */
	private function sanitize_classes( string $classes ): string {
		$list = array_filter( array_map( 'sanitize_html_class', preg_split( '/\s+/', $classes ) ?: array() ) );

		return implode( ' ', $list );
	}
}
