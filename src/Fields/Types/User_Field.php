<?php
/**
 * User field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * One or more users. Stores user IDs.
 */
class User_Field extends Post_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'user';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'User', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting(
				'role',
				__( 'Roles', 'cfmb' ),
				'checkboxes',
				array(),
				array(
					'options' => self::role_options(),
					'help'    => __( 'Leave all unchecked to allow every user.', 'cfmb' ),
				)
			),
			$this->setting( 'multiple', __( 'Allow several users', 'cfmb' ), 'toggle', false ),
			$this->setting(
				'return_format',
				__( 'Template value', 'cfmb' ),
				'select',
				'array',
				array(
					'advanced' => true,
					'options'  => array(
						'array'  => __( 'User details array', 'cfmb' ),
						'object' => __( 'User object', 'cfmb' ),
						'id'     => __( 'User ID', 'cfmb' ),
					),
				)
			),
		);
	}

	/**
	 * Users must have an allowed role.
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		$roles = array_map( 'strval', (array) $field->setting( 'role', array() ) );

		if ( ! $roles ) {
			return null;
		}

		foreach ( $this->as_list( $value ) as $id ) {
			$user = get_userdata( (int) $id );

			if ( ! $user instanceof WP_User || ! array_intersect( $roles, (array) $user->roles ) ) {
				return __( 'One of the chosen users does not have an allowed role.', 'cfmb' );
			}
		}

		return null;
	}

	/**
	 * Details arrays, WP_User objects or IDs.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$items  = array();
		$format = (string) $field->setting( 'return_format', 'array' );

		foreach ( $this->as_list( $value ) as $id ) {
			$user = get_userdata( (int) $id );

			if ( ! $user instanceof WP_User ) {
				continue;
			}

			if ( 'id' === $format ) {
				$items[] = (int) $user->ID;
			} elseif ( 'object' === $format ) {
				$items[] = $user;
			} else {
				$items[] = array(
					'ID'            => (int) $user->ID,
					'display_name'  => $user->display_name,
					'user_nicename' => $user->user_nicename,
					'user_email'    => $user->user_email,
					'user_url'      => $user->user_url,
					'avatar'        => (string) get_avatar_url( $user->ID ),
				);
			}
		}

		if ( $this->is_multiple( $field ) ) {
			return $items;
		}

		return $items ? $items[0] : null;
	}

	/**
	 * Names for the picker.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function editor_value( $value, Field $field ) {
		$items = array();

		foreach ( $this->as_list( $value ) as $id ) {
			$user = get_userdata( (int) $id );

			if ( $user instanceof WP_User ) {
				$items[] = self::user_summary( $user );
			}
		}

		if ( $this->is_multiple( $field ) ) {
			return $items;
		}

		return $items ? $items[0] : null;
	}

	/**
	 * Display names.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$names = array();

		foreach ( $this->as_list( $formatted ) as $item ) {
			$id   = is_array( $item ) ? (int) ( $item['ID'] ?? 0 ) : $this->id_of( $item );
			$user = get_userdata( $id );

			if ( $user instanceof WP_User ) {
				$names[] = $user->display_name;
			}
		}

		return esc_html( implode( wp_get_list_item_separator(), $names ) );
	}

	/**
	 * What the picker shows for a user. No email: editors may not be allowed to see it.
	 *
	 * @param WP_User $user User.
	 * @return array<string, mixed>
	 */
	public static function user_summary( WP_User $user ): array {
		return array(
			'id'     => (int) $user->ID,
			'title'  => $user->display_name,
			'avatar' => (string) get_avatar_url( $user->ID, array( 'size' => 48 ) ),
		);
	}

	/**
	 * Role names.
	 *
	 * @return array<string, string>
	 */
	public static function role_options(): array {
		return array_map( 'translate_user_role', wp_roles()->get_names() );
	}

	/**
	 * Whether a user exists.
	 *
	 * @param int $id User ID.
	 * @return bool
	 */
	protected function exists( int $id ): bool {
		return get_userdata( $id ) instanceof WP_User;
	}

	/**
	 * Value as a list; user detail arrays carry an "ID" key.
	 *
	 * @param mixed $value Value.
	 * @return array<int, mixed>
	 */
	protected function as_list( $value ): array {
		if ( is_array( $value ) && isset( $value['ID'] ) ) {
			return array( $value );
		}

		return parent::as_list( $value );
	}
}
