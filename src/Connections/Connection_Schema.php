<?php
/**
 * Connection definitions.
 *
 * @package CFMB
 */

namespace CFMB\Connections;

use CFMB\Content\Content_Schema;
use CFMB\Schema\Key_Generator;

defined( 'ABSPATH' ) || exit;

/**
 * A Connection links objects of one kind ("from") to objects of another
 * ("to"): posts, terms or users, narrowed to some post types, taxonomies
 * or roles. Links live in the {prefix}cfmb_connections table, so both
 * sides read them with one indexed query.
 *
 * `name` is the connection ID used in code: cfmb_get_connected( 'book_authors' ).
 */
class Connection_Schema extends Content_Schema {

	public const KINDS = array( 'post', 'term', 'user' );

	public const CARDINALITIES = array( 'many_to_many', 'one_to_many', 'many_to_one', 'one_to_one' );

	/**
	 * Kind key.
	 *
	 * @return string
	 */
	public function kind(): string {
		return 'connection';
	}

	/**
	 * Key prefix.
	 *
	 * @return string
	 */
	public function key_prefix(): string {
		return Key_Generator::CONNECTION_PREFIX;
	}

	/**
	 * Storage post type.
	 *
	 * @return string
	 */
	public function storage_type(): string {
		return 'cfmb_connection';
	}

	/**
	 * Fits the table's connection column.
	 *
	 * @return int
	 */
	public function name_max_length(): int {
		return 64;
	}

	/**
	 * Builder strings.
	 *
	 * @return array<string, string>
	 */
	public function names(): array {
		return array(
			'singular'      => __( 'Connection', 'custom-fields-meta-box' ),
			'plural'        => __( 'Connections', 'custom-fields-meta-box' ),
			'add'           => __( 'Add connection', 'custom-fields-meta-box' ),
			'title_label'   => __( 'Name', 'custom-fields-meta-box' ),
			'title_example' => __( 'For example: Book authors', 'custom-fields-meta-box' ),
			'name_label'    => __( 'Connection ID', 'custom-fields-meta-box' ),
			'name_help'     => __( 'Used in code, such as cfmb_get_connected( \'book_authors\' ). Lowercase letters, numbers, dashes and underscores.', 'custom-fields-meta-box' ),
			'title_missing' => __( 'Give the connection a name.', 'custom-fields-meta-box' ),
			'name_missing'  => __( 'Give the connection an ID, such as book_authors.', 'custom-fields-meta-box' ),
			'empty_title'   => __( 'Create your first connection', 'custom-fields-meta-box' ),
			'empty_text'    => __( 'A connection links two kinds of content both ways, like books and their authors. Each side gets a box to pick the other.', 'custom-fields-meta-box' ),
			'move'          => __( 'Keep existing links under the new ID', 'custom-fields-meta-box' ),
			'name_from'     => 'title',
		);
	}

	/**
	 * Names only need to be unique, which the base class checks.
	 *
	 * @param string $name     Sanitized name.
	 * @param string $previous Previous name.
	 * @return string|null
	 */
	protected function name_error( string $name, string $previous ): ?string {
		return null;
	}

	/**
	 * Setting descriptors. `when` hides a setting in the builder unless
	 * another setting has the given value.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings(): array {
		$settings = array();

		foreach ( array( 'from', 'to' ) as $side ) {
			$settings = array_merge( $settings, $this->side_settings( $side ) );
		}

		$settings[] = $this->setting(
			'cardinality',
			__( 'How many on each side', 'custom-fields-meta-box' ),
			'select',
			'many_to_many',
			array(
				'options' => array(
					'many_to_many' => __( 'Many to many', 'custom-fields-meta-box' ),
					'one_to_many'  => __( 'One to many (each "to" item has one "from" item)', 'custom-fields-meta-box' ),
					'many_to_one'  => __( 'Many to one (each "from" item has one "to" item)', 'custom-fields-meta-box' ),
					'one_to_one'   => __( 'One to one', 'custom-fields-meta-box' ),
				),
				'help'    => __( 'Picking an item that is already taken moves its link here.', 'custom-fields-meta-box' ),
			)
		);

		$settings[] = $this->setting( 'from_label', __( 'Box title on "from" items', 'custom-fields-meta-box' ), 'text', '', array( 'help' => __( 'Lists the "to" items. Defaults to the connection name.', 'custom-fields-meta-box' ) ) );
		$settings[] = $this->setting( 'to_label', __( 'Box title on "to" items', 'custom-fields-meta-box' ), 'text', '', array( 'help' => __( 'Lists the "from" items. Defaults to the connection name.', 'custom-fields-meta-box' ) ) );

		$settings[] = $this->setting(
			'show_from',
			__( 'Show the box on "from" items', 'custom-fields-meta-box' ),
			'toggle',
			true,
			array(
				'advanced' => true,
				'section'  => 'display',
			)
		);
		$settings[] = $this->setting(
			'show_to',
			__( 'Show the box on "to" items', 'custom-fields-meta-box' ),
			'toggle',
			true,
			array(
				'advanced' => true,
				'section'  => 'display',
			)
		);
		$settings[] = $this->setting(
			'position',
			__( 'Box position on post screens', 'custom-fields-meta-box' ),
			'select',
			'side',
			array(
				'advanced' => true,
				'section'  => 'display',
				'options'  => array(
					'side'   => __( 'Sidebar', 'custom-fields-meta-box' ),
					'normal' => __( 'Below the content', 'custom-fields-meta-box' ),
				),
			)
		);
		$settings[] = $this->setting(
			'show_in_rest',
			__( 'Show in the REST API', 'custom-fields-meta-box' ),
			'toggle',
			false,
			array(
				'advanced' => true,
				'section'  => 'rest',
				'help'     => __( 'Adds the IDs of connected items to the cfmb_connections attribute of each side. Only items the reader may see are listed.', 'custom-fields-meta-box' ),
			)
		);

		/**
		 * Filters the settings a Connection has. Add-ons add their own
		 * descriptors here; the builder shows them and saves only what is
		 * listed. Read added values with `cfmb/connections/build_args`.
		 *
		 * @since 0.12.0
		 *
		 * @param array<int, array<string, mixed>> $settings Setting descriptors.
		 */
		$filtered = apply_filters( 'cfmb/connections/settings', $settings ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $filtered ) ? array_values( $filtered ) : $settings;
	}

	/**
	 * Settings for one side.
	 *
	 * @param string $side from or to.
	 * @return array<int, array<string, mixed>>
	 */
	private function side_settings( string $side ): array {
		$is_from = 'from' === $side;

		return array(
			$this->setting(
				$side,
				$is_from ? __( 'From', 'custom-fields-meta-box' ) : __( 'To', 'custom-fields-meta-box' ),
				'select',
				'post',
				array(
					'options' => array(
						'post' => __( 'Posts', 'custom-fields-meta-box' ),
						'term' => __( 'Terms', 'custom-fields-meta-box' ),
						'user' => __( 'Users', 'custom-fields-meta-box' ),
					),
				)
			),
			$this->setting(
				$side . '_post_types',
				$is_from ? __( '"From" post types', 'custom-fields-meta-box' ) : __( '"To" post types', 'custom-fields-meta-box' ),
				'checkboxes',
				array(),
				array(
					'options' => self::post_type_options(),
					'free'    => true,
					'when'    => array( $side => 'post' ),
					'help'    => __( 'Leave all unchecked to allow every post type.', 'custom-fields-meta-box' ),
				)
			),
			$this->setting(
				$side . '_taxonomies',
				$is_from ? __( '"From" taxonomies', 'custom-fields-meta-box' ) : __( '"To" taxonomies', 'custom-fields-meta-box' ),
				'checkboxes',
				array(),
				array(
					'options' => self::taxonomy_options(),
					'free'    => true,
					'when'    => array( $side => 'term' ),
					'help'    => __( 'Leave all unchecked to allow every taxonomy.', 'custom-fields-meta-box' ),
				)
			),
			$this->setting(
				$side . '_roles',
				$is_from ? __( '"From" roles', 'custom-fields-meta-box' ) : __( '"To" roles', 'custom-fields-meta-box' ),
				'checkboxes',
				array(),
				array(
					'options' => self::role_options(),
					'free'    => true,
					'when'    => array( $side => 'user' ),
					'help'    => __( 'Leave all unchecked to allow every role.', 'custom-fields-meta-box' ),
				)
			),
		);
	}

	/**
	 * The definition as the Connection registry reads it.
	 *
	 * @param array<string, mixed> $item Normalized definition.
	 * @return array<string, mixed>
	 */
	public function build_args( array $item ): array {
		$s = $item['settings'];

		$args = array(
			'name'         => (string) $item['name'],
			'title'        => (string) $item['title'],
			'from'         => in_array( $s['from'], self::KINDS, true ) ? $s['from'] : 'post',
			'to'           => in_array( $s['to'], self::KINDS, true ) ? $s['to'] : 'post',
			'from_types'   => self::types_for( (string) $s['from'], $s, 'from' ),
			'to_types'     => self::types_for( (string) $s['to'], $s, 'to' ),
			'cardinality'  => in_array( $s['cardinality'], self::CARDINALITIES, true ) ? $s['cardinality'] : 'many_to_many',
			'from_label'   => (string) $s['from_label'],
			'to_label'     => (string) $s['to_label'],
			'show_from'    => (bool) $s['show_from'],
			'show_to'      => (bool) $s['show_to'],
			'position'     => 'normal' === $s['position'] ? 'normal' : 'side',
			'show_in_rest' => (bool) $s['show_in_rest'],
			'cfmb_key'     => (string) $item['key'],
		);

		// Add-ons may add arguments but never change the ones CFMB relies on.
		$core = $args;

		/**
		 * Filters the arguments a Connection is built from. Add-ons copy
		 * their own settings in here and read them with Connection::to_array()
		 * or Connection::arg().
		 *
		 * @since 0.12.0
		 *
		 * @param array<string, mixed> $args Arguments.
		 * @param array<string, mixed> $item Normalized definition, settings included.
		 */
		$filtered = apply_filters( 'cfmb/connections/build_args', $args, $item ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $filtered ) ? array_merge( $filtered, $core ) : $args;
	}

	/**
	 * The subtype list that applies to a side's kind.
	 *
	 * @param string               $kind     post, term or user.
	 * @param array<string, mixed> $settings Settings.
	 * @param string               $side     from or to.
	 * @return string[]
	 */
	private static function types_for( string $kind, array $settings, string $side ): array {
		$suffix = array(
			'post' => '_post_types',
			'term' => '_taxonomies',
			'user' => '_roles',
		);

		return array_values( array_map( 'strval', (array) ( $settings[ $side . ( $suffix[ $kind ] ?? '_post_types' ) ] ?? array() ) ) );
	}

	/**
	 * Post types shown in the admin.
	 *
	 * @return array<string, string>
	 */
	private static function post_type_options(): array {
		$options = array();

		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $name => $type ) {
			if ( 0 !== strpos( $name, 'wp_' ) && 0 !== strpos( $name, 'cfmb_' ) ) {
				$options[ $name ] = $type->labels->name;
			}
		}

		return $options;
	}

	/**
	 * Taxonomies shown in the admin.
	 *
	 * @return array<string, string>
	 */
	private static function taxonomy_options(): array {
		$options = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $name => $taxonomy ) {
			$options[ $name ] = $taxonomy->labels->name;
		}

		return $options;
	}

	/**
	 * User roles.
	 *
	 * @return array<string, string>
	 */
	private static function role_options(): array {
		$options = array();

		foreach ( wp_roles()->get_names() as $role => $name ) {
			$options[ $role ] = translate_user_role( $name );
		}

		return $options;
	}
}
