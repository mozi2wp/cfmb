<?php
/**
 * Columns, sorting and filters on post, term and user lists.
 *
 * @package CFMB
 */

namespace CFMB\Admin;

use CFMB\Fields\Structure_Field;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Query;
use WP_Screen;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the columns, sorting and filters set per field in the builder
 * (More settings > List screens). Only top-level fields take part.
 *
 * Sorting keeps items that have no value yet: the meta query asks for the
 * key to exist OR not exist, and orders by the named clause.
 */
class List_Columns {

	/**
	 * Column and query variable prefix.
	 */
	private const PREFIX = 'cfmb_';

	/**
	 * Request array holding filter values.
	 */
	private const FILTER_VAR = 'cfmb_filter';

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
	 * Fields on the current list, by name, each with its group.
	 *
	 * @var array<string, array{0: Field, 1: Group}>
	 */
	private array $fields = array();

	/**
	 * List kind on this screen: post, term or user.
	 *
	 * @var string
	 */
	private string $kind = '';

	/**
	 * Constructor.
	 *
	 * @param Matcher      $matcher Location matcher.
	 * @param Field_Values $values  Value service.
	 */
	public function __construct( Matcher $matcher, Field_Values $values ) {
		$this->matcher = $matcher;
		$this->values  = $values;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'current_screen', array( $this, 'setup' ) );
	}

	/**
	 * Hook into the list screen being loaded, if it has CFMB columns or filters.
	 *
	 * @param WP_Screen $screen Current screen.
	 * @return void
	 */
	public function setup( $screen ): void {
		if ( ! $screen instanceof WP_Screen ) {
			return;
		}

		if ( 'edit' === $screen->base && '' !== $screen->post_type ) {
			$this->kind = 'post';
			$this->collect( $this->matcher->groups_for_list( 'post', $screen->post_type ) );

			if ( $this->fields ) {
				add_filter( "manage_{$screen->post_type}_posts_columns", array( $this, 'add_columns' ) );
				add_action( "manage_{$screen->post_type}_posts_custom_column", array( $this, 'print_post_column' ), 10, 2 );
				add_filter( "manage_{$screen->id}_sortable_columns", array( $this, 'sortable_columns' ) );
				add_action( 'restrict_manage_posts', array( $this, 'print_post_filters' ), 10, 2 );
				add_action( 'pre_get_posts', array( $this, 'query_posts' ) );
			}
			return;
		}

		if ( 'edit-tags' === $screen->base && '' !== $screen->taxonomy ) {
			$this->kind = 'term';
			$this->collect( $this->matcher->groups_for_list( 'term', $screen->taxonomy ) );

			if ( $this->fields ) {
				add_filter( "manage_{$screen->id}_columns", array( $this, 'add_columns' ) );
				add_filter( "manage_{$screen->taxonomy}_custom_column", array( $this, 'term_column' ), 10, 3 );
				add_filter( "manage_{$screen->id}_sortable_columns", array( $this, 'sortable_columns' ) );
				add_filter( 'get_terms_args', array( $this, 'query_terms' ), 10, 2 );
			}
			return;
		}

		if ( 'users' === $screen->base ) {
			$this->kind = 'user';
			$this->collect( $this->matcher->groups_for_list( 'user' ) );

			if ( $this->fields ) {
				add_filter( 'manage_users_columns', array( $this, 'add_columns' ) );
				add_filter( 'manage_users_custom_column', array( $this, 'user_column' ), 10, 3 );
				add_filter( 'manage_users_sortable_columns', array( $this, 'sortable_columns' ) );
				add_action( 'restrict_manage_users', array( $this, 'print_user_filters' ) );
				add_action( 'pre_get_users', array( $this, 'query_users' ) );
			}
		}
	}

	/**
	 * Keep top-level fields that ask for a column, sorting or a filter.
	 *
	 * @param Group[] $groups Groups on this list.
	 * @return void
	 */
	private function collect( array $groups ): void {
		$this->fields = array();

		foreach ( $groups as $group ) {
			foreach ( $group->fields() as $field ) {
				$admin = $field->admin();
				$type  = $this->values->type_of( $field );

				if ( '' === $field->name() || ! $type || ! $type->stores_value() || $type instanceof Structure_Field ) {
					continue;
				}

				if ( ( $admin['column'] || $admin['sortable'] || '' !== $admin['filter'] ) && ! isset( $this->fields[ $field->name() ] ) ) {
					$this->fields[ $field->name() ] = array( $field, $group );
				}
			}
		}
	}

	/**
	 * Add columns before the date column, or at the end.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function add_columns( $columns ): array {
		$columns = (array) $columns;
		$added   = array();

		foreach ( $this->fields as $name => $found ) {
			if ( $found[0]->admin()['column'] ) {
				$added[ self::PREFIX . $name ] = esc_html( '' !== $found[0]->label() ? $found[0]->label() : $name );
			}
		}

		$position = array_search( 'date', array_keys( $columns ), true );

		if ( false === $position ) {
			return array_merge( $columns, $added );
		}

		return array_merge( array_slice( $columns, 0, (int) $position, true ), $added, array_slice( $columns, (int) $position, null, true ) );
	}

	/**
	 * Columns that sort, mapped to their query value.
	 *
	 * @param array<string, mixed> $columns Sortable columns.
	 * @return array<string, mixed>
	 */
	public function sortable_columns( $columns ): array {
		$columns = (array) $columns;

		foreach ( $this->fields as $name => $found ) {
			if ( $found[0]->admin()['column'] && $found[0]->admin()['sortable'] ) {
				$columns[ self::PREFIX . $name ] = self::PREFIX . $name;
			}
		}

		return $columns;
	}

	/**
	 * Print a post column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function print_post_column( $column, $post_id ): void {
		echo $this->cell( (string) $column, new Object_Ref( Object_Ref::POST, (int) $post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cell().
	}

	/**
	 * Term column content.
	 *
	 * @param string $content Content so far.
	 * @param string $column  Column key.
	 * @param int    $term_id Term ID.
	 * @return string
	 */
	public function term_column( $content, $column, $term_id ): string {
		$cell = $this->cell( (string) $column, new Object_Ref( Object_Ref::TERM, (int) $term_id ) );

		return null === $cell ? (string) $content : $cell;
	}

	/**
	 * User column content.
	 *
	 * @param string $content Content so far.
	 * @param string $column  Column key.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public function user_column( $content, $column, $user_id ): string {
		$cell = $this->cell( (string) $column, new Object_Ref( Object_Ref::USER, (int) $user_id ) );

		return null === $cell ? (string) $content : $cell;
	}

	/**
	 * Escaped HTML for a cell, or null when the column isn't ours.
	 *
	 * @param string     $column Column key.
	 * @param Object_Ref $ref    Object.
	 * @return string|null
	 */
	private function cell( string $column, Object_Ref $ref ): ?string {
		if ( 0 !== strpos( $column, self::PREFIX ) ) {
			return null;
		}

		$found = $this->fields[ substr( $column, strlen( self::PREFIX ) ) ] ?? null;

		if ( ! $found ) {
			return null;
		}

		list( $field, $group ) = $found;
		$type                  = $this->values->type_of( $field );

		if ( ! $type ) {
			return '';
		}

		$raw = $this->values->load( $field, $group, $ref, false );

		switch ( $field->type() ) {
			case 'image':
				$html = is_numeric( $raw ) && (int) $raw > 0 ? wp_get_attachment_image( (int) $raw, array( 48, 48 ) ) : '';
				break;

			case 'gallery':
				$html = implode( ' ', array_map( static fn( $id ) => wp_get_attachment_image( (int) $id, array( 32, 32 ) ), array_slice( (array) $raw, 0, 4 ) ) );
				break;

			case 'textarea':
			case 'wysiwyg':
				$html = esc_html( wp_trim_words( wp_strip_all_tags( is_scalar( $raw ) ? (string) $raw : '' ), 15 ) );
				break;

			default:
				$html = $type->display( $this->values->format_raw( $field, $raw, $ref ), $field );
		}

		/**
		 * Filters the HTML of a CFMB column cell. Return escaped HTML.
		 *
		 * @param string     $html  Cell HTML.
		 * @param Field      $field Field.
		 * @param Object_Ref $ref   Object.
		 */
		$html = (string) apply_filters( 'cfmb/admin/column_value', $html, $field, $ref ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return '' === $html ? '<span aria-hidden="true">&#8212;</span>' : $html;
	}

	/**
	 * Filter controls above the post list.
	 *
	 * @param string $post_type Post type.
	 * @param string $which     top or bottom.
	 * @return void
	 */
	public function print_post_filters( $post_type = '', $which = 'top' ): void {
		if ( 'top' === $which ) {
			$this->print_filters();
		}
	}

	/**
	 * Filter controls above the user list, with their own button since the
	 * form's only button there changes roles.
	 *
	 * @param string $which top or bottom.
	 * @return void
	 */
	public function print_user_filters( $which = 'top' ): void {
		if ( 'top' !== $which || ! $this->print_filters() ) {
			return;
		}

		submit_button( __( 'Filter', 'custom-fields-meta-box' ), '', 'cfmb_filter_action', false );
	}

	/**
	 * Print a select or search input per filterable field.
	 *
	 * @return bool Whether anything was printed.
	 */
	private function print_filters(): bool {
		$current = $this->filter_values();
		$printed = false;

		foreach ( $this->fields as $name => $found ) {
			$field  = $found[0];
			$filter = $field->admin()['filter'];
			$label  = '' !== $field->label() ? $field->label() : $name;
			$id     = 'cfmb-filter-' . $name;
			$value  = $current[ $name ] ?? '';

			if ( '' === $filter ) {
				continue;
			}

			$printed = true;

			printf( '<label class="screen-reader-text" for="%1$s">%2$s</label>', esc_attr( $id ), esc_html( $label ) );

			if ( 'choices' === $filter ) {
				printf( '<select id="%1$s" name="%2$s[%3$s]">', esc_attr( $id ), esc_attr( self::FILTER_VAR ), esc_attr( $name ) );
				/* translators: %s: field label */
				printf( '<option value="">%s</option>', esc_html( sprintf( __( 'Any %s', 'custom-fields-meta-box' ), $label ) ) );

				foreach ( $this->choices( $field ) as $choice_value => $choice_label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $choice_value ), selected( $value, (string) $choice_value, false ), esc_html( $choice_label ) );
				}

				echo '</select>';
				continue;
			}

			printf(
				'<input type="search" id="%1$s" name="%2$s[%3$s]" value="%4$s" placeholder="%5$s" class="cfmb-list-filter" />',
				esc_attr( $id ),
				esc_attr( self::FILTER_VAR ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( $label )
			);
		}

		return $printed;
	}

	/**
	 * Choices to filter by.
	 *
	 * @param Field $field Field.
	 * @return array<string, string>
	 */
	private function choices( Field $field ): array {
		if ( 'toggle' === $field->type() ) {
			return array(
				'1' => __( 'Yes', 'custom-fields-meta-box' ),
				'0' => __( 'No', 'custom-fields-meta-box' ),
			);
		}

		$choices = array();
		foreach ( (array) $field->setting( 'choices', array() ) as $choice ) {
			if ( is_array( $choice ) && isset( $choice['value'] ) ) {
				$choices[ (string) $choice['value'] ] = (string) ( $choice['label'] ?? $choice['value'] );
			}
		}

		return $choices;
	}

	/**
	 * Sort and filter the main post list query.
	 *
	 * @param WP_Query $query Query.
	 * @return void
	 */
	public function query_posts( $query ): void {
		if ( ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}

		$this->apply( $query, (string) $query->get( 'orderby' ) );
	}

	/**
	 * Sort the term list query.
	 *
	 * @param array<string, mixed> $args       get_terms() arguments.
	 * @param string[]             $taxonomies Taxonomies.
	 * @return array<string, mixed>
	 */
	public function query_terms( $args, $taxonomies ): array {
		$args = (array) $args;

		// Only the list table's own query, which takes orderby from the request.
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list view.

		if ( '' === $orderby || ( $args['orderby'] ?? '' ) !== $orderby ) {
			return $args;
		}

		$sort = $this->sort_clause( $orderby );

		if ( $sort ) {
			$args['meta_query'] = $sort; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sorting by a field is what was asked for.
			$args['orderby']    = 'cfmb_sort';
		}

		return $args;
	}

	/**
	 * Sort and filter the user list query.
	 *
	 * @param WP_User_Query $query Query.
	 * @return void
	 */
	public function query_users( $query ): void {
		global $pagenow;

		if ( ! $query instanceof WP_User_Query || 'users.php' !== $pagenow ) {
			return;
		}

		$this->apply( $query, (string) $query->get( 'orderby' ) );
	}

	/**
	 * Add sort and filter clauses to a post or user query.
	 *
	 * @param WP_Query|WP_User_Query $query   Query.
	 * @param string                 $orderby Requested order.
	 * @return void
	 */
	private function apply( $query, string $orderby ): void {
		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		foreach ( $this->filter_values() as $name => $value ) {
			$field = $this->fields[ $name ][0] ?? null;

			if ( ! $field || '' === $value ) {
				continue;
			}

			$meta_query[] = 'search' === $field->admin()['filter']
				? array(
					'key'     => $name,
					'value'   => $value,
					'compare' => 'LIKE',
				)
				: array(
					'key'   => $name,
					'value' => $value,
				);
		}

		$sort = $this->sort_clause( $orderby );

		if ( $sort ) {
			$meta_query[] = $sort;
			$query->set( 'orderby', 'cfmb_sort' );
		}

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
	}

	/**
	 * Meta query that sorts by a field and keeps items without a value.
	 *
	 * @param string $orderby Requested order, cfmb_{name}.
	 * @return array<string, mixed>|null
	 */
	private function sort_clause( string $orderby ): ?array {
		if ( 0 !== strpos( $orderby, self::PREFIX ) ) {
			return null;
		}

		$field = $this->fields[ substr( $orderby, strlen( self::PREFIX ) ) ][0] ?? null;

		if ( ! $field || ! $field->admin()['sortable'] ) {
			return null;
		}

		$numeric = in_array( $field->type(), array( 'number', 'range' ), true );

		return array(
			'relation'  => 'OR',
			'cfmb_sort' => array(
				'key'     => $field->name(),
				'compare' => 'EXISTS',
				'type'    => $numeric ? 'NUMERIC' : 'CHAR',
			),
			array(
				'key'     => $field->name(),
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Filter values from the request, for known filterable fields.
	 *
	 * @return array<string, string>
	 */
	private function filter_values(): array {
		// Read-only list filtering, like core's own list filters.
		$raw = isset( $_GET[ self::FILTER_VAR ] ) && is_array( $_GET[ self::FILTER_VAR ] ) ? wp_unslash( $_GET[ self::FILTER_VAR ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$out = array();

		foreach ( $raw as $name => $value ) {
			$name = sanitize_key( (string) $name );

			if ( isset( $this->fields[ $name ] ) && '' !== $this->fields[ $name ][0]->admin()['filter'] && is_scalar( $value ) ) {
				$out[ $name ] = sanitize_text_field( (string) $value );
			}
		}

		return $out;
	}
}
