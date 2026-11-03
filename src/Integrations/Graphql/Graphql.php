<?php
/**
 * WPGraphQL support.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Graphql;

use CFMB\Content\Options_Pages;
use CFMB\Display\Visibility;
use CFMB\Fields\Compound_Field;
use CFMB\Fields\Structure_Field;
use CFMB\Integrations\Field_Integrations;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Groups with "Show in GraphQL" on become an object type (CfmbBookDetails)
 * attached to the post types, taxonomies and users their location rules
 * point at, and to RootQuery.cfmbOptions for options pages:
 *
 *   post( id: 12, idType: DATABASE_ID ) { bookDetails { price isbn cover } }
 *
 * Simple values come as GraphQL scalars (String, Int, Float, Boolean and
 * lists of them). Images, files, posts, terms and users come as database
 * IDs. Links, maps, Groups, Repeaters and Stacks come as a JSON string;
 * typed objects for those are part of CFMB Pro.
 *
 * Values follow the same visibility rules as Dynamic Data: drafts and
 * private posts only for people who may read them, user fields only for
 * the user and editors, password fields never.
 */
class Graphql {

	/**
	 * Field on RootQuery holding options page groups.
	 */
	public const OPTIONS_FIELD = 'cfmbOptions';

	/**
	 * Type behind RootQuery.cfmbOptions.
	 */
	public const OPTIONS_TYPE = 'CfmbOptions';

	/**
	 * Types whose value is one ID, or a list of IDs when they allow several.
	 */
	private const ID_TYPES = array( 'image', 'file', 'post', 'page_link', 'user', 'taxonomy' );

	/**
	 * Types that always hold a list of IDs.
	 */
	private const ID_LIST_TYPES = array( 'gallery', 'relationship' );

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Visibility rules.
	 *
	 * @var Visibility
	 */
	private Visibility $visibility;

	/**
	 * Options pages.
	 *
	 * @var Options_Pages
	 */
	private Options_Pages $pages;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups     Field Groups.
	 * @param Matcher          $matcher    Location matcher.
	 * @param Field_Values     $values     Value service.
	 * @param Visibility       $visibility Visibility rules.
	 * @param Options_Pages    $pages      Options pages.
	 */
	public function __construct( Group_Repository $groups, Matcher $matcher, Field_Values $values, Visibility $visibility, Options_Pages $pages ) {
		$this->groups     = $groups;
		$this->matcher    = $matcher;
		$this->values     = $values;
		$this->visibility = $visibility;
		$this->pages      = $pages;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'graphql_register_types', array( $this, 'register_types' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPGraphQL's hook.
	}

	/**
	 * Whether WPGraphQL is active, for the builder.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( 'WPGraphQL' ) || function_exists( 'register_graphql_field' );
	}

	/**
	 * Groups with "Show in GraphQL" on.
	 *
	 * @return Group[]
	 */
	public function graphql_groups(): array {
		return array_values(
			array_filter(
				$this->groups->active(),
				static fn( Group $group ) => ! $group->preset_only() && '' === $group->block_name() && ! empty( ( (array) $group->setting( 'graphql' ) )['enabled'] )
			)
		);
	}

	/**
	 * Register one object type per group and attach it where it shows.
	 *
	 * @return void
	 */
	public function register_types(): void {
		if ( ! function_exists( 'register_graphql_object_type' ) || ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		$groups = $this->graphql_groups();

		if ( ! $groups ) {
			return;
		}

		$names   = $this->names( $groups );
		$options = array();

		foreach ( $groups as $group ) {
			$type = $names[ $group->key() ]['type'];

			register_graphql_object_type(
				$type,
				array(
					/* translators: %s: field group title */
					'description' => sprintf( __( 'Fields of the CFMB field group "%s".', 'custom-fields-meta-box' ), $group->title() ),
					'fields'      => $this->object_fields( $group, $type ),
				)
			);

			foreach ( $this->owners( $group ) as $owner ) {
				register_graphql_field(
					$owner,
					$names[ $group->key() ]['field'],
					array(
						'type'        => $type,
						/* translators: %s: field group title */
						'description' => sprintf( __( 'Values of the "%s" field group.', 'custom-fields-meta-box' ), $group->title() ),
						'resolve'     => fn( $source ) => $this->resolve_group( $group, $this->ref_for( $source ) ),
					)
				);
			}

			$page = $this->options_page_of( $group );

			if ( '' !== $page ) {
				$options[ $names[ $group->key() ]['field'] ] = array(
					'type'        => $type,
					/* translators: %s: field group title */
					'description' => sprintf( __( 'Values of the "%s" field group.', 'custom-fields-meta-box' ), $group->title() ),
					'resolve'     => fn() => $this->resolve_group( $group, new Object_Ref( Object_Ref::OPTION, 0, $page ) ),
				);
			}
		}

		if ( $options ) {
			register_graphql_object_type(
				self::OPTIONS_TYPE,
				array(
					'description' => __( 'CFMB options page values.', 'custom-fields-meta-box' ),
					'fields'      => $options,
				)
			);

			register_graphql_field(
				'RootQuery',
				self::OPTIONS_FIELD,
				array(
					'type'        => self::OPTIONS_TYPE,
					'description' => __( 'Values of CFMB field groups on options pages.', 'custom-fields-meta-box' ),
					'resolve'     => static fn() => array(),
				)
			);
		}

		/**
		 * Fires after CFMB registered its GraphQL types.
		 *
		 * @param array<string, array{type: string, field: string}> $names  Type and field name by group key.
		 * @param Group[]                                           $groups Groups shown in GraphQL.
		 */
		do_action( 'cfmb/graphql/registered', $names, $groups ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Unique type and field names per group.
	 *
	 * @param Group[] $groups Groups.
	 * @return array<string, array{type: string, field: string}>
	 */
	public function names( array $groups ): array {
		$names = array();
		$taken = array();

		foreach ( $groups as $group ) {
			$settings = (array) $group->setting( 'graphql' );
			$field    = (string) ( $settings['name'] ?? '' );

			if ( '' === $field ) {
				$field = \CFMB\Schema\Group_Sanitizer::sanitize_graphql_name( $group->title() );
			}

			if ( '' === $field ) {
				$field = 'group' . ucfirst( strtolower( substr( $group->key(), 4 ) ) );
			}

			$base = $field;
			for ( $i = 2; isset( $taken[ $field ] ); $i++ ) {
				$field = $base . $i;
			}
			$taken[ $field ] = true;

			$names[ $group->key() ] = array(
				'type'  => 'Cfmb' . ucfirst( $field ),
				'field' => $field,
			);
		}

		return $names;
	}

	/**
	 * GraphQL types a group is attached to, from its location rules.
	 *
	 * @param Group $group Group.
	 * @return string[]
	 */
	private function owners( Group $group ): array {
		$owners = array();

		foreach ( get_post_types( array( 'show_in_graphql' => true ), 'objects' ) as $type ) {
			if ( $this->in_list( $group, $this->matcher->groups_for_list( 'post', $type->name ) ) && ! empty( $type->graphql_single_name ) ) {
				$owners[] = ucfirst( (string) $type->graphql_single_name );
			}
		}

		foreach ( get_taxonomies( array( 'show_in_graphql' => true ), 'objects' ) as $taxonomy ) {
			if ( $this->in_list( $group, $this->matcher->groups_for_list( 'term', $taxonomy->name ) ) && ! empty( $taxonomy->graphql_single_name ) ) {
				$owners[] = ucfirst( (string) $taxonomy->graphql_single_name );
			}
		}

		if ( $this->in_list( $group, $this->matcher->groups_for_list( 'user' ) ) ) {
			$owners[] = 'User';
		}

		/**
		 * Filters the GraphQL types a group's field is added to.
		 *
		 * @param string[] $owners Type names, e.g. Post, Page, Category, User.
		 * @param Group    $group  Group.
		 */
		return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'cfmb/graphql/owners', $owners, $group ) ) ) ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Whether a group is in a list.
	 *
	 * @param Group   $group Group.
	 * @param Group[] $list  Groups.
	 * @return bool
	 */
	private function in_list( Group $group, array $list ): bool {
		foreach ( $list as $item ) {
			if ( $item->key() === $group->key() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * First options page a group's rules point at with "is", or empty.
	 *
	 * @param Group $group Group.
	 * @return string
	 */
	private function options_page_of( Group $group ): string {
		foreach ( $group->location() as $set ) {
			foreach ( (array) $set as $rule ) {
				if ( 'options_page' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '==' ) && $this->pages->get( (string) ( $rule['value'] ?? '' ) ) ) {
					return (string) $rule['value'];
				}
			}
		}

		return '';
	}

	/**
	 * Field config for the group's object type.
	 *
	 * @param Group  $group     Group.
	 * @param string $type_name The group's GraphQL type name.
	 * @return array<string, array<string, mixed>>
	 */
	private function object_fields( Group $group, string $type_name = '' ): array {
		$fields = array();

		foreach ( $group->fields() as $field ) {
			$type = $this->values->type_of( $field );

			if ( ! $type || ! $type->stores_value() || '' === $field->name() || 'password' === $field->type() || ! Field_Integrations::graphql( $field ) ) {
				continue;
			}

			$name = self::field_name( $field->name() );
			$base = $name;
			for ( $i = 2; isset( $fields[ $name ] ); $i++ ) {
				$name = $base . $i;
			}

			$gql_type = $this->graphql_type( $field );

			$config = array(
				'type'        => $gql_type,
				'description' => $this->describe( $field, $gql_type ),
				'resolve'     => fn( $source ) => $this->resolve_field( $field, $gql_type, $source ),
			);

			/**
			 * Filters one field of a group's GraphQL object type, for example to
			 * give a Repeater a typed list of rows instead of a JSON string.
			 * Return null to leave the field out. A resolver gets the same
			 * source as the default one: the output of resolve_group().
			 *
			 * @since 0.16.0
			 *
			 * @param array<string, mixed>|null $config    Field config: type, description, resolve.
			 * @param Field                     $field     Field.
			 * @param Group                     $group     Group.
			 * @param string                    $type_name The group's object type name.
			 */
			$config = apply_filters( 'cfmb/graphql/field_config', $config, $field, $group, $type_name ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			if ( is_array( $config ) ) {
				$fields[ $name ] = $config;
			}
		}

		if ( ! $fields ) {
			// GraphQL types need at least one field.
			$fields['empty'] = array(
				'type'        => 'Boolean',
				'description' => __( 'The group has no fields shown in GraphQL.', 'custom-fields-meta-box' ),
				'resolve'     => static fn() => null,
			);
		}

		return $fields;
	}

	/**
	 * camelCase GraphQL name for a field name such as hero_title.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	public static function field_name( string $name ): string {
		$camel = \CFMB\Schema\Group_Sanitizer::sanitize_graphql_name( $name );

		return '' === $camel ? 'field' . md5( $name ) : $camel;
	}

	/**
	 * GraphQL type of a field: a scalar name or [ 'list_of' => scalar ].
	 *
	 * @param Field $field Field.
	 * @return string|array<string, string>
	 */
	public function graphql_type( Field $field ) {
		$type     = $this->values->type_of( $field );
		$multiple = $type ? $type->is_multiple( $field ) : false;

		if ( ! $type || $type instanceof Structure_Field || $type instanceof Compound_Field ) {
			return 'String';
		}

		if ( in_array( $field->type(), self::ID_LIST_TYPES, true ) ) {
			return array( 'list_of' => 'Int' );
		}

		if ( in_array( $field->type(), self::ID_TYPES, true ) ) {
			return $multiple ? array( 'list_of' => 'Int' ) : 'Int';
		}

		if ( in_array( $field->type(), array( 'number', 'range' ), true ) ) {
			return 'Float';
		}

		if ( 'toggle' === $field->type() ) {
			return 'Boolean';
		}

		return $multiple ? array( 'list_of' => 'String' ) : 'String';
	}

	/**
	 * Description shown in GraphiQL.
	 *
	 * @param Field                        $field    Field.
	 * @param string|array<string, string> $gql_type Its GraphQL type.
	 * @return string
	 */
	private function describe( Field $field, $gql_type ): string {
		$type = $this->values->type_of( $field );

		if ( $type instanceof Structure_Field || $type instanceof Compound_Field ) {
			/* translators: %s: field label */
			return sprintf( __( '%s, as a JSON string.', 'custom-fields-meta-box' ), $field->label() );
		}

		if ( in_array( $field->type(), array_merge( self::ID_TYPES, self::ID_LIST_TYPES ), true ) ) {
			/* translators: %s: field label */
			return sprintf( __( '%s, as database IDs.', 'custom-fields-meta-box' ), $field->label() );
		}

		return $field->label();
	}

	/**
	 * Object reference for a WPGraphQL model (Post, Term, User, Comment).
	 *
	 * @param mixed $source Model.
	 * @return Object_Ref|null
	 */
	private function ref_for( $source ): ?Object_Ref {
		if ( ! is_object( $source ) ) {
			return null;
		}

		$class = get_class( $source );
		$id    = 0;

		try {
			$id = (int) $source->databaseId; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- WPGraphQL model property.
		} catch ( \Throwable $error ) {
			$id = 0;
		}

		if ( $id <= 0 ) {
			return null;
		}

		if ( false !== strpos( $class, 'Model\\Term' ) ) {
			return new Object_Ref( Object_Ref::TERM, $id );
		}

		if ( false !== strpos( $class, 'Model\\User' ) ) {
			return new Object_Ref( Object_Ref::USER, $id );
		}

		if ( false !== strpos( $class, 'Model\\Comment' ) ) {
			return new Object_Ref( Object_Ref::COMMENT, $id );
		}

		return new Object_Ref( Object_Ref::POST, $id );
	}

	/**
	 * Source for a group's fields, or null when the visitor may not see
	 * it or the group doesn't apply to that object.
	 *
	 * @param Group           $group Group.
	 * @param Object_Ref|null $ref   Object.
	 * @return array{group: Group, ref: Object_Ref}|null
	 */
	public function resolve_group( Group $group, ?Object_Ref $ref ): ?array {
		if ( ! $ref || ! $this->visibility->can_read( $ref ) || ! $this->matcher->matches( $group, Context::from_ref( $ref ) ) ) {
			return null;
		}

		return array(
			'group' => $group,
			'ref'   => $ref,
		);
	}

	/**
	 * One field's value in its GraphQL shape.
	 *
	 * @param Field                        $field    Field.
	 * @param string|array<string, string> $gql_type GraphQL type.
	 * @param mixed                        $source   Output of resolve_group().
	 * @return mixed
	 */
	public function resolve_field( Field $field, $gql_type, $source ) {
		if ( ! is_array( $source ) || ! ( $source['ref'] ?? null ) instanceof Object_Ref || ! $this->visibility->can_show_field( $field, $source['ref'] ) ) {
			return null;
		}

		$raw   = $this->values->load( $field, $source['group'], $source['ref'], false );
		$value = self::cast( $raw, $gql_type );

		/**
		 * Filters a value in GraphQL responses.
		 *
		 * @param mixed      $value Value in its GraphQL shape.
		 * @param mixed      $raw   Stored value.
		 * @param Field      $field Field.
		 * @param Object_Ref $ref   Object.
		 */
		return apply_filters( 'cfmb/graphql/field_value', $value, $raw, $field, $source['ref'] ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Cast a stored value to a GraphQL type.
	 *
	 * @param mixed                        $raw      Stored value.
	 * @param string|array<string, string> $gql_type GraphQL type.
	 * @return mixed
	 */
	public static function cast( $raw, $gql_type ) {
		if ( is_array( $gql_type ) ) {
			$list = is_array( $raw ) ? array_values( $raw ) : ( '' === $raw || null === $raw ? array() : array( $raw ) );
			$list = array_map( static fn( $item ) => self::cast( $item, $gql_type['list_of'] ), $list );

			return array_values( array_filter( $list, static fn( $item ) => null !== $item ) );
		}

		if ( null === $raw || '' === $raw || array() === $raw ) {
			return null;
		}

		switch ( $gql_type ) {
			case 'Int':
				return is_numeric( $raw ) ? (int) $raw : null;
			case 'Float':
				return is_numeric( $raw ) ? (float) $raw : null;
			case 'Boolean':
				return (bool) $raw;
			default:
				return is_array( $raw ) || is_object( $raw ) ? (string) wp_json_encode( $raw ) : (string) $raw;
		}
	}
}
