<?php
/**
 * REST route for relational field pickers.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Fields\Types\Post_Field;
use CFMB\Fields\Types\Relationship_Field;
use CFMB\Fields\Types\Taxonomy_Field;
use CFMB\Fields\Types\User_Field;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Error;
use WP_Post;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;
use WP_User;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * cfmb/v1/search: posts, terms or users a relational field may point at.
 *
 * Which post types, taxonomy and roles apply comes from the field's own
 * settings on the server, never from the request. Results are limited to
 * what the current user may see.
 */
class Search_Controller {

	private const PER_PAGE = 20;

	/**
	 * Group storage.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Value service.
	 *
	 * @var Field_Values
	 */
	private Field_Values $values;

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Permission checks for the object being edited.
	 *
	 * @var Object_Access
	 */
	private Object_Access $access;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository $groups  Group storage.
	 * @param Field_Values     $values  Value service.
	 * @param Matcher          $matcher Location matcher.
	 * @param Object_Access    $access  Permission checks.
	 */
	public function __construct( Group_Repository $groups, Field_Values $values, Matcher $matcher, Object_Access $access ) {
		$this->groups  = $groups;
		$this->values  = $values;
		$this->matcher = $matcher;
		$this->access  = $access;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'search' ),
				'permission_callback' => array( $this, 'can_search' ),
				'args'                => array(
					'group'     => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^grp_[a-z0-9]{6,32}$',
					),
					'field'     => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^fld_[a-z0-9_]{6,80}$',
					),
					'search'    => array(
						'type'    => 'string',
						'default' => '',
					),
					'page'      => array(
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					),
					'post_type' => array(
						'type'    => 'string',
						'default' => '',
					),
					'all'       => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'term'      => array(
						'type'    => 'string',
						'default' => '',
						'pattern' => '^([a-z0-9_-]+:[0-9]+)?$',
					),
					'filters'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'object'    => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
	}

	/**
	 * Signed-in users, or a front-end form an add-on vouches for.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_search( WP_REST_Request $request ): bool {
		if ( is_user_logged_in() ) {
			return true;
		}

		list( $group, $field ) = $this->field_for( $request );

		return $group && $field && Form_Access::allows( 'search', $request, $group, $field );
	}

	/**
	 * The active group and field a request names.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array{0: Group|null, 1: Field|null}
	 */
	private function field_for( WP_REST_Request $request ): array {
		$group = $this->groups->find( (string) $request['group'] );
		$field = $group && $group->active() ? $group->find_field( (string) $request['field'] ) : null;

		return array( $field ? $group : null, $field );
	}

	/**
	 * Search for the field's kind of object.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function search( WP_REST_Request $request ) {
		list( $group, $field ) = $this->field_for( $request );
		$type                  = $field ? $this->values->type_of( $field ) : null;

		if ( ! $group || ! $field || ! $type ) {
			return new WP_Error( 'cfmb_search_field', __( 'That field no longer exists. Reload the page.', 'cfmb' ), array( 'status' => 404 ) );
		}

		$search = sanitize_text_field( (string) $request['search'] );
		$page   = max( 1, (int) $request['page'] );
		$form   = Form_Access::allows( 'search', $request, $group, $field );

		if ( $type instanceof Taxonomy_Field ) {
			return $this->terms( $type, $field, $search, $page, (bool) $request['all'], $form );
		}

		if ( $type instanceof User_Field ) {
			return $this->users( $field, $search, $page, $form || $this->edits_object_with( $group, (string) $request['object'] ) );
		}

		if ( $type instanceof Relationship_Field && $request['filters'] ) {
			return $this->term_filters( $type, $field );
		}

		if ( $type instanceof Post_Field ) {
			return $this->posts( $type, $field, $search, $page, sanitize_key( (string) $request['post_type'] ), (string) $request['term'] );
		}

		return new WP_Error( 'cfmb_search_field', __( 'That field does not search for content.', 'cfmb' ), array( 'status' => 400 ) );
	}

	/**
	 * Posts the user can read.
	 *
	 * @param Post_Field $type      Field type.
	 * @param Field      $field     Field definition.
	 * @param string     $search    Search text.
	 * @param int        $page      Page number.
	 * @param string     $post_type Optional narrowing to one allowed type.
	 * @param string     $term      Optional narrowing to one term, as "taxonomy:id".
	 * @return WP_REST_Response
	 */
	private function posts( Post_Field $type, Field $field, string $search, int $page, string $post_type, string $term = '' ): WP_REST_Response {
		$allowed = $type->post_types( $field );
		$types   = '' !== $post_type && in_array( $post_type, $allowed, true ) ? array( $post_type ) : $allowed;
		$args    = array();

		if ( '' !== $term ) {
			list( $taxonomy, $term_id ) = explode( ':', $term );

			if ( in_array( $taxonomy, $this->filter_taxonomies( $allowed ), true ) ) {
				$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Only when the editor picks a term filter.
					array(
						'taxonomy' => $taxonomy,
						'terms'    => array( (int) $term_id ),
					),
				);
			}
		}

		$query = new WP_Query(
			$args + array(
				'post_type'              => $types,
				'post_status'            => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				's'                      => $search,
				'posts_per_page'         => self::PER_PAGE,
				'paged'                  => $page,
				'orderby'                => '' === $search ? 'title' : 'relevance',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && current_user_can( 'read_post', $post->ID ) ) {
				$items[] = Post_Field::summary( $post );
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'more'  => $page < (int) $query->max_num_pages,
			)
		);
	}

	/**
	 * Terms of the field's taxonomy, for users who may assign them.
	 *
	 * @param Taxonomy_Field $type   Field type.
	 * @param Field          $field  Field definition.
	 * @param string         $search Search text.
	 * @param int            $page   Page number.
	 * @param bool           $all    Whether to return every term (checkbox and radio lists).
	 * @param bool           $form   Whether a front-end form shows this field (cfmb/rest/form_access).
	 * @return WP_REST_Response|WP_Error
	 */
	private function terms( Taxonomy_Field $type, Field $field, string $search, int $page, bool $all, bool $form = false ) {
		$taxonomy = get_taxonomy( $type->taxonomy( $field ) );

		if ( ! $taxonomy || ( ! $form && ! current_user_can( $taxonomy->cap->assign_terms ) ) ) {
			return new WP_Error( 'cfmb_search_forbidden', __( 'You are not allowed to choose these terms.', 'cfmb' ), array( 'status' => 403 ) );
		}

		$per_page = $all ? 1000 : self::PER_PAGE;
		$terms    = get_terms(
			array(
				'taxonomy'   => $taxonomy->name,
				'hide_empty' => false,
				'search'     => $search,
				'number'     => $per_page + 1,
				'offset'     => ( $page - 1 ) * $per_page,
				'orderby'    => 'name',
			)
		);

		$terms = is_array( $terms ) ? $terms : array();
		$items = array();

		foreach ( array_slice( $terms, 0, $per_page ) as $term ) {
			if ( $term instanceof WP_Term ) {
				$items[] = Taxonomy_Field::term_summary( $term );
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'more'  => count( $terms ) > $per_page,
			)
		);
	}

	/**
	 * Users with an allowed role.
	 *
	 * People who can list users search everyone. Others may still pick users
	 * for a field on something they can edit, the way an author picks a
	 * co-author; they only see names and can't search by email or login.
	 *
	 * @param Field  $field    Field definition.
	 * @param string $search   Search text.
	 * @param int    $page     Page number.
	 * @param bool   $editable Whether the request comes from an edit screen this user may use for the field.
	 * @return WP_REST_Response|WP_Error
	 */
	private function users( Field $field, string $search, int $page, bool $editable ) {
		$full = current_user_can( 'list_users' );

		if ( ! $full && ! $editable ) {
			return new WP_Error( 'cfmb_search_forbidden', __( 'You are not allowed to browse users.', 'cfmb' ), array( 'status' => 403 ) );
		}

		$args = array(
			'number'  => self::PER_PAGE,
			'paged'   => $page,
			'orderby' => 'display_name',
		);

		$roles = array_map( 'strval', (array) $field->setting( 'role', array() ) );
		if ( $roles ) {
			$args['role__in'] = $roles;
		}

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = $full ? array( 'user_login', 'user_nicename', 'display_name', 'user_email' ) : array( 'user_nicename', 'display_name' );
		}

		$query = new WP_User_Query( $args );
		$items = array();

		foreach ( $query->get_results() as $user ) {
			if ( $user instanceof WP_User ) {
				$items[] = User_Field::user_summary( $user );
			}
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'more'  => $page * self::PER_PAGE < (int) $query->get_total(),
			)
		);
	}

	/**
	 * Whether the request names an object the user may edit and the field's
	 * group is shown for it.
	 *
	 * @param Group  $group  Field Group.
	 * @param string $object Object reference from the request, like post_12.
	 * @return bool
	 */
	private function edits_object_with( Group $group, string $object ): bool {
		// Field Blocks send "block:{name}"; their values belong to whoever edits the content.
		if ( 0 === strpos( $object, 'block:' ) ) {
			return ( current_user_can( 'edit_posts' ) || current_user_can( 'edit_theme_options' ) )
				&& null !== $this->matcher->matching_group( $group->key(), Context::for_block( substr( $object, 6 ) ) );
		}

		$ref = '' === $object ? null : Object_Ref::from( $object );

		if ( ! $ref || ! $this->access->can_edit( $ref ) ) {
			return false;
		}

		return null !== $this->matcher->matching_group( $group->key(), Context::from_ref( $ref ) );
	}

	/**
	 * Terms for the Relationship field's taxonomy filter: every taxonomy with
	 * an admin screen used by the allowed post types.
	 *
	 * @param Relationship_Field $type  Field type.
	 * @param Field              $field Field definition.
	 * @return WP_REST_Response
	 */
	private function term_filters( Relationship_Field $type, Field $field ): WP_REST_Response {
		$filters = array();

		foreach ( $this->filter_taxonomies( $type->post_types( $field ) ) as $name ) {
			$taxonomy = get_taxonomy( $name );
			$terms    = get_terms(
				array(
					'taxonomy'   => $name,
					'hide_empty' => true,
					'number'     => 200,
					'orderby'    => 'name',
				)
			);

			if ( ! $taxonomy || ! is_array( $terms ) || ! $terms ) {
				continue;
			}

			$filters[] = array(
				'taxonomy' => $name,
				'label'    => $taxonomy->labels->singular_name,
				'terms'    => array_values( array_map( array( Taxonomy_Field::class, 'term_summary' ), array_filter( $terms, static fn( $term ) => $term instanceof WP_Term ) ) ),
			);
		}

		return rest_ensure_response( $filters );
	}

	/**
	 * Taxonomies posts of the given types can be filtered by.
	 *
	 * @param string[] $post_types Post types.
	 * @return string[]
	 */
	private function filter_taxonomies( array $post_types ): array {
		$names = array();

		foreach ( $post_types as $post_type ) {
			foreach ( get_object_taxonomies( $post_type, 'objects' ) as $taxonomy ) {
				if ( $taxonomy->show_ui && 'post_format' !== $taxonomy->name ) {
					$names[ $taxonomy->name ] = true;
				}
			}
		}

		return array_keys( $names );
	}
}
