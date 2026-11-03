<?php
/**
 * Taxonomy field.
 *
 * @package CFMB
 */

namespace CFMB\Fields\Types;

use CFMB\Schema\Field;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Terms of one taxonomy, shown as checkboxes, a select or radio buttons.
 * Stores term IDs. Can also set the post's own terms (see Term_Sync).
 */
class Taxonomy_Field extends Post_Field {

	/**
	 * Type key.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'taxonomy';
	}

	/**
	 * Builder label.
	 *
	 * @return string
	 */
	public function label(): string {
		return __( 'Taxonomy', 'cfmb' );
	}

	/**
	 * Builder settings.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function settings_schema(): array {
		return array(
			$this->setting( 'taxonomy', __( 'Taxonomy', 'cfmb' ), 'select', 'category', array( 'options' => self::taxonomy_options() ) ),
			$this->setting(
				'appearance',
				__( 'Appearance', 'cfmb' ),
				'select',
				'checkbox',
				array(
					'options' => array(
						'checkbox'     => __( 'Checkboxes (several)', 'cfmb' ),
						'multi_select' => __( 'Search box (several)', 'cfmb' ),
						'radio'        => __( 'Radio buttons (one)', 'cfmb' ),
						'select'       => __( 'Search box (one)', 'cfmb' ),
					),
				)
			),
			$this->setting(
				'save_terms',
				__( 'Also set the post\'s terms', 'cfmb' ),
				'toggle',
				false,
				array( 'help' => __( 'Chosen terms are assigned to the post, as if picked in the editor\'s taxonomy panel.', 'cfmb' ) )
			),
			$this->setting( 'load_terms', __( 'Show the post\'s current terms', 'cfmb' ), 'toggle', false, array( 'advanced' => true ) ),
			$this->setting(
				'return_format',
				__( 'Template value', 'cfmb' ),
				'select',
				'object',
				array(
					'advanced' => true,
					'options'  => array(
						'object' => __( 'Term object', 'cfmb' ),
						'id'     => __( 'Term ID', 'cfmb' ),
					),
				)
			),
		);
	}

	/**
	 * Several terms for checkbox and multi-select appearances.
	 *
	 * @param Field $field Field definition.
	 * @return bool
	 */
	public function is_multiple( Field $field ): bool {
		return in_array( $field->setting( 'appearance', 'checkbox' ), array( 'checkbox', 'multi_select' ), true );
	}

	/**
	 * Term IDs of the field's taxonomy.
	 *
	 * @param mixed $value Raw value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function sanitize_value( $value, Field $field ) {
		$taxonomy = $this->taxonomy( $field );
		$ids      = array();

		foreach ( $this->as_list( $value ) as $item ) {
			$term = get_term( $this->id_of( $item ), $taxonomy );

			if ( $term instanceof WP_Term ) {
				$ids[] = (int) $term->term_id;
			}
		}

		$ids = array_values( array_unique( $ids ) );

		if ( $this->is_multiple( $field ) ) {
			return $ids;
		}

		return $ids ? $ids[0] : '';
	}

	/**
	 * Terms are checked against the taxonomy in sanitize_value().
	 *
	 * @param mixed $value Sanitized value.
	 * @param Field $field Field definition.
	 * @return string|null
	 */
	public function validate_value( $value, Field $field ): ?string {
		return null;
	}

	/**
	 * WP_Term objects or IDs.
	 *
	 * @param mixed $value Stored value.
	 * @param Field $field Field definition.
	 * @return mixed
	 */
	public function format_value( $value, Field $field ) {
		$items = array();

		foreach ( $this->as_list( $value ) as $id ) {
			$term = get_term( (int) $id, $this->taxonomy( $field ) );

			if ( $term instanceof WP_Term ) {
				$items[] = 'id' === $field->setting( 'return_format', 'object' ) ? (int) $term->term_id : $term;
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
			$term = get_term( (int) $id, $this->taxonomy( $field ) );

			if ( $term instanceof WP_Term ) {
				$items[] = self::term_summary( $term );
			}
		}

		if ( $this->is_multiple( $field ) ) {
			return $items;
		}

		return $items ? $items[0] : null;
	}

	/**
	 * Linked term names.
	 *
	 * @param mixed $formatted Formatted value.
	 * @param Field $field     Field definition.
	 * @return string
	 */
	public function display( $formatted, Field $field ): string {
		$links = array();

		foreach ( $this->as_list( $formatted ) as $item ) {
			$term = $item instanceof WP_Term ? $item : get_term( $this->id_of( $item ), $this->taxonomy( $field ) );

			if ( $term instanceof WP_Term ) {
				$url     = get_term_link( $term );
				$links[] = is_string( $url )
					? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $url ), esc_html( $term->name ) )
					: esc_html( $term->name );
			}
		}

		return implode( esc_html( wp_get_list_item_separator() ), $links );
	}

	/**
	 * The field's taxonomy.
	 *
	 * @param Field $field Field definition.
	 * @return string
	 */
	public function taxonomy( Field $field ): string {
		return (string) $field->setting( 'taxonomy', 'category' );
	}

	/**
	 * What the picker shows for a term.
	 *
	 * @param WP_Term $term Term.
	 * @return array<string, mixed>
	 */
	public static function term_summary( WP_Term $term ): array {
		return array(
			'id'     => (int) $term->term_id,
			'title'  => html_entity_decode( $term->name, ENT_QUOTES, get_bloginfo( 'charset' ) ),
			'parent' => (int) $term->parent,
		);
	}

	/**
	 * Taxonomies with an admin screen.
	 *
	 * @return array<string, string>
	 */
	public static function taxonomy_options(): array {
		$options = array();

		foreach ( get_taxonomies( array( 'show_ui' => true ), 'objects' ) as $taxonomy ) {
			$options[ $taxonomy->name ] = $taxonomy->labels->singular_name;
		}

		return $options;
	}
}
