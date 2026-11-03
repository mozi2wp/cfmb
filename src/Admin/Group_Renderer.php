<?php
/**
 * Outputs Field Groups on edit screens.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Fields\Types\Date_Field;
use CFMB\Fields\Types\Map_Field;
use CFMB\Icons\Icon_Library_Registry;
use CFMB\Fields\Types\Post_Field;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;

defined( 'ABSPATH' ) || exit;

/**
 * Prints a container per group that the fields bundle renders into.
 *
 * Values travel back as one JSON string per group in cfmb_values[group_key].
 * Screens that show the same group for many objects at once (menu items,
 * widgets) pass their own input name base. The hidden input starts empty,
 * so if the script never runs nothing is overwritten on save.
 */
class Group_Renderer {

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Asset loader.
	 *
	 * @var Assets
	 */
	private Assets $assets;

	/**
	 * Whether the nonce field was printed on this page.
	 *
	 * @var bool
	 */
	private bool $nonce_printed = false;

	/**
	 * Constructor.
	 *
	 * @param Field_Values $values Value service.
	 * @param Assets       $assets Asset loader.
	 */
	public function __construct( Field_Values $values, Assets $assets ) {
		$this->values = $values;
		$this->assets = $assets;
	}

	/**
	 * Enqueue what the given groups need.
	 *
	 * @param Group[] $groups Groups on the screen.
	 * @return void
	 */
	public function enqueue( array $groups ): void {
		if ( ! $groups ) {
			return;
		}

		$types = array();
		$maps  = array();
		foreach ( $groups as $group ) {
			$this->collect_types( $group->fields(), $types, $maps );
		}

		$this->assets->enqueue( 'fields', 'cfmbFields', $this->screen_config() );

		$uses_media = (bool) array_intersect_key( $types, array_flip( array( 'image', 'file', 'wysiwyg', 'gallery', 'icon' ) ) );

		/**
		 * Filters whether the media library loads for these fields. Image,
		 * file, gallery, icon and rich text fields use it.
		 *
		 * A front-end form can turn it off for people who can't upload, and
		 * give those fields its own upload control instead.
		 *
		 * @since 0.14.0
		 *
		 * @param bool    $uses_media Whether a field on the screen uses the media library.
		 * @param Group[] $groups     Groups on the screen.
		 */
		if ( apply_filters( 'cfmb/admin/use_media_library', $uses_media, $groups ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			wp_enqueue_media();
		}

		if ( isset( $types['wysiwyg'] ) ) {
			wp_enqueue_editor();
		}

		if ( isset( $types['icon'] ) ) {
			wp_enqueue_style( 'dashicons' );
		}

		// The map bundle (Leaflet or the Google loader) only loads when a map will actually show.
		if ( isset( $maps['osm'] ) || isset( $maps['google'] ) ) {
			$this->assets->enqueue( 'map' );
		}

		/**
		 * Fires after CFMB enqueued assets for edit screen fields.
		 *
		 * @param Group[] $groups Groups on the screen.
		 */
		do_action( 'cfmb/admin/enqueue_fields', $groups ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Collect field types and map providers at every depth.
	 *
	 * @param Field[]             $fields Fields.
	 * @param array<string, bool> $types  Type keys found. Updated.
	 * @param array<string, bool> $maps   Map providers in use. Updated.
	 * @return void
	 */
	private function collect_types( array $fields, array &$types, array &$maps ): void {
		foreach ( $fields as $field ) {
			$types[ $field->type() ] = true;

			if ( 'map' === $field->type() ) {
				$maps[ Map_Field::provider( $field ) ] = true;
			}

			$this->collect_types( $field->sub_fields(), $types, $maps );

			foreach ( $field->layouts() as $layout ) {
				$this->collect_types( $layout['fields'], $types, $maps );
			}
		}
	}

	/**
	 * Settings the fields bundle needs on every screen.
	 *
	 * @return array<string, mixed>
	 */
	private function screen_config(): array {
		$settings = Settings_Screen::current();

		return array(
			'mapProvider'   => (string) $settings['map_provider'],
			// Browser keys are public by design; Google limits them by site address.
			'googleKey'     => (string) $settings['map_google_key'],
			'googleMapId'   => (string) $settings['map_google_map_id'],
			'postTypes'     => Post_Field::post_type_options(),
			'iconLibraries' => $this->icon_libraries(),
		);
	}

	/**
	 * Icon library names by ID, for the icon picker's tabs.
	 *
	 * @return array<string, string>
	 */
	private function icon_libraries(): array {
		$names = array();

		foreach ( cfmb()->get( Icon_Library_Registry::class )->all() as $id => $library ) {
			$names[ $id ] = $library->label();
		}

		return $names;
	}

	/**
	 * Print a group container.
	 *
	 * @param Group      $group      Field Group.
	 * @param Object_Ref $ref        Object being edited. ID 0 on add forms.
	 * @param string     $layout     box, table-row or plain.
	 * @param string     $input_base Name of the posted array, e.g. cfmb_menu_items[12]. The group key is appended.
	 * @param bool       $nonce      Whether to print the save nonce. Widgets rely on core's own nonce.
	 * @return void
	 */
	public function render( Group $group, Object_Ref $ref, string $layout = 'box', string $input_base = 'cfmb_values', bool $nonce = true ): void {
		$config = $this->config( $group, $ref );

		if ( $nonce && ! $this->nonce_printed ) {
			$this->nonce_printed = true;
			wp_nonce_field( Form_Saver::NONCE_ACTION, Form_Saver::NONCE_NAME, false );
		}

		printf(
			'<div class="cfmb-group cfmb-group--%1$s cfmb-group--labels-%2$s" data-cfmb-group="%3$s">',
			esc_attr( $layout ),
			esc_attr( (string) $group->setting( 'label_placement' ) ),
			esc_attr( $group->key() )
		);

		if ( '' !== (string) $group->setting( 'description' ) ) {
			printf( '<p class="cfmb-group__description">%s</p>', esc_html( (string) $group->setting( 'description' ) ) );
		}

		printf(
			'<script type="application/json" class="cfmb-group__config">%s</script>',
			wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON with < > & ' " hex-escaped, safe inside a script tag.
		);

		printf(
			'<input type="hidden" class="cfmb-group__input" name="%1$s[%2$s]" value="" />',
			esc_attr( $input_base ),
			esc_attr( $group->key() )
		);

		printf(
			'<div class="cfmb-group__root"><p class="cfmb-group__loading">%s</p></div>',
			esc_html__( 'Loading fields…', 'cfmb' )
		);

		echo '</div>';
	}

	/**
	 * What the Field Block editor needs for one group: its fields and default
	 * values. Saved values arrive per block over REST.
	 *
	 * @param Group $group Field Group.
	 * @return array<string, mixed>
	 */
	public function block_config( Group $group ): array {
		$fields   = array();
		$defaults = array();
		// Access rules are about who edits, not which block instance, so one reference stands for every instance.
		$ref = new Object_Ref( Object_Ref::BLOCK, 0, '' );

		foreach ( $group->fields() as $field ) {
			$access = self::access( $field, $ref );

			if ( 'none' === $access ) {
				continue;
			}

			// Like edit screens: a field the user may see but not change stays out of the form, and saves keep its value.
			if ( 'edit' === $access ) {
				$fields[] = $this->client_field( $field );
			}

			$type = $this->values->type_of( $field );

			if ( $type && $type->stores_value() ) {
				$defaults[ $field->key() ] = $this->values->default_for_editor( $field );
			}
		}

		return array(
			'key'            => $group->key(),
			'title'          => $group->title(),
			'description'    => (string) $group->setting( 'description' ),
			'labelPlacement' => $group->setting( 'label_placement' ),
			'fields'         => $fields,
			'defaults'       => (object) $defaults,
		);
	}

	/**
	 * Field definition for the browser, with the map provider in effect
	 * resolved so the script never needs the site settings.
	 *
	 * @param Field $field Field.
	 * @return array<string, mixed>
	 */
	private function client_field( Field $field ): array {
		$definition = $field->to_array();

		if ( 'map' === $field->type() ) {
			$definition['settings']['provider'] = Map_Field::provider( $field );
		}

		// Relative limits like "+7 days" are worked out here, in the site time zone.
		$type = $this->values->type_of( $field );
		if ( $type instanceof Date_Field && in_array( $field->type(), array( 'date', 'datetime' ), true ) ) {
			$definition['settings']['calendar'] = array_merge( $type->bounds( $field ), array( 'firstDay' => $type->first_day( $field ) ) );
		}

		if ( ! empty( $definition['fields'] ) ) {
			$definition['fields'] = array_map( array( $this, 'client_field' ), $field->sub_fields() );
		}

		foreach ( $field->layouts() as $index => $layout ) {
			$definition['layouts'][ $index ]['fields'] = array_map( array( $this, 'client_field' ), $layout['fields'] );
		}

		/**
		 * Filters a field definition before it goes to the edit screen script
		 * (edit screens and the Field Block editor). Sub-fields are filtered
		 * on their own first.
		 *
		 * Add what the control needs, or take out settings the browser must
		 * not see, such as a key for a server-side request.
		 *
		 * @since 0.11.0
		 *
		 * @param array<string, mixed> $definition Field definition as the script gets it.
		 * @param Field                $field      Field.
		 */
		$filtered = apply_filters( 'cfmb/admin/client_field', $definition, $field ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return is_array( $filtered ) ? $filtered : $definition;
	}

	/**
	 * What the current user may do with a top-level field on an edit screen.
	 *
	 * @param Field      $field Field.
	 * @param Object_Ref $ref   Object being edited.
	 * @return string edit, view or none.
	 */
	public static function access( Field $field, Object_Ref $ref ): string {
		/** This filter is documented in src/Display/Visibility.php */
		if ( ! apply_filters( 'cfmb/field/can_view', true, $field, $ref, 'editor' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			return 'none';
		}

		/**
		 * Filters whether the current user may change a field's value.
		 *
		 * Edit screens leave the field out of the form, saves keep its stored
		 * value, and bound blocks can't edit it. Values written from code
		 * (cfmb_update_field()) are not affected.
		 *
		 * @since 0.9.0
		 *
		 * @param bool       $allowed Default true.
		 * @param Field      $field   Top-level field.
		 * @param Object_Ref $ref     Object being edited.
		 * @param string     $context editor, save or bindings.
		 */
		return apply_filters( 'cfmb/field/can_edit', true, $field, $ref, 'editor' ) ? 'edit' : 'view'; // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Data the fields bundle needs for one group.
	 *
	 * @param Group      $group Field Group.
	 * @param Object_Ref $ref   Object.
	 * @return array<string, mixed>
	 */
	private function config( Group $group, Object_Ref $ref ): array {
		$values = array();
		$fields = array();
		$exists = 0 !== $ref->id() || $ref->uses_options();

		foreach ( $group->fields() as $field ) {
			$access = self::access( $field, $ref );

			if ( 'none' === $access ) {
				continue;
			}

			// A field the user may see but not change stays out of the form; its value
			// stays in so conditional logic on other fields still works.
			if ( 'edit' === $access ) {
				$fields[] = $this->client_field( $field );
			}

			$type = $this->values->type_of( $field );

			if ( ! $type || ! $type->stores_value() ) {
				continue;
			}

			$values[ $field->key() ] = $exists
				? $this->values->load_for_editor( $field, $group, $ref )
				: $this->values->default_for_editor( $field );
		}

		return array(
			'key'            => $group->key(),
			'title'          => $group->title(),
			'labelPlacement' => $group->setting( 'label_placement' ),
			'objectKind'     => $ref->kind(),
			'objectId'       => $ref->id(),
			'objectPage'     => $ref->page(),
			'objectRef'      => (string) $ref,
			// The same group can show for many objects on one screen (menu items, widgets).
			'instance'       => $group->key() . '@' . $ref,
			'fields'         => $fields,
			'values'         => (object) $values,
			'errors'         => (object) ( $exists ? Save_Errors::take( $ref ) : array() ),
		);
	}
}
