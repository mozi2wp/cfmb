<?php
/**
 * REST route that re-checks location rules while a post is edited.
 *
 * @package CFMB
 */

namespace CFMB\Rest;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * POST cfmb/v1/location/match
 *
 * Takes what the editor has picked but not saved yet (template, format,
 * status, terms, product type) and answers which of the given groups
 * match now, so the edit screen can show or hide their boxes right away.
 */
class Location_Match_Controller {

	/**
	 * Location matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Constructor.
	 *
	 * @param Matcher $matcher Location matcher.
	 */
	public function __construct( Matcher $matcher ) {
		$this->matcher = $matcher;
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
	 * Register the route.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			Groups_Controller::REST_NAMESPACE,
			'/location/match',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'match' ),
				'permission_callback' => array( $this, 'can_edit' ),
				'args'                => array(
					'post_id'      => array(
						'type'    => 'integer',
						'minimum' => 0,
						'default' => 0,
					),
					'post_type'    => array(
						'type'     => 'string',
						'required' => true,
						'pattern'  => '^[a-z0-9_-]+$',
					),
					'groups'       => array(
						'type'     => 'array',
						'items'    => array(
							'type'    => 'string',
							'pattern' => '^grp_[A-Za-z0-9_]+$',
						),
						'required' => true,
					),
					'template'     => array( 'type' => array( 'string', 'null' ) ),
					'format'       => array( 'type' => array( 'string', 'null' ) ),
					'status'       => array( 'type' => array( 'string', 'null' ) ),
					'product_type' => array( 'type' => array( 'string', 'null' ) ),
					'terms'        => array( 'type' => array( 'object', 'null' ) ),
				),
			)
		);
	}

	/**
	 * Only people who can edit the post (or create posts of the type) may ask.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_edit( WP_REST_Request $request ): bool {
		$post_id = (int) $request['post_id'];

		if ( $post_id > 0 ) {
			return current_user_can( 'edit_post', $post_id );
		}

		$type = get_post_type_object( (string) $request['post_type'] );

		return $type && current_user_can( $type->cap->edit_posts );
	}

	/**
	 * Which of the given groups match the edited state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function match( WP_REST_Request $request ) {
		$post_id   = (int) $request['post_id'];
		$post_type = (string) $request['post_type'];

		if ( $post_id > 0 && get_post_type( $post_id ) !== $post_type ) {
			return new WP_Error( 'cfmb_post_type', __( 'The post type does not match the post.', 'custom-fields-meta-box' ), array( 'status' => 400 ) );
		}

		$details = array();

		foreach ( array( 'template', 'format', 'status', 'product_type' ) as $name ) {
			if ( is_string( $request[ $name ] ) ) {
				$details[ $name ] = sanitize_text_field( $request[ $name ] );
			}
		}

		if ( is_array( $request['terms'] ) ) {
			$details['terms'] = $this->term_slugs( $request['terms'], $post_type );
		}

		$context = Context::for_post( $post_id, $post_type )->with( $details );
		$asked   = array_map( 'strval', (array) $request['groups'] );
		$matched = array();

		foreach ( $this->matcher->groups_for( $context ) as $group ) {
			if ( in_array( $group->key(), $asked, true ) ) {
				$matched[] = $group->key();
			}
		}

		return rest_ensure_response( array( 'groups' => $matched ) );
	}

	/**
	 * Slugs per taxonomy from term IDs, names or slugs. Taxonomies not on
	 * the post type are left out.
	 *
	 * @param array<string, mixed> $raw       Terms by taxonomy.
	 * @param string               $post_type Post type.
	 * @return array<string, string[]>
	 */
	private function term_slugs( array $raw, string $post_type ): array {
		$allowed = get_object_taxonomies( $post_type );
		$slugs   = array();

		foreach ( $raw as $taxonomy => $values ) {
			$taxonomy = sanitize_key( (string) $taxonomy );

			if ( ! in_array( $taxonomy, $allowed, true ) ) {
				continue;
			}

			$slugs[ $taxonomy ] = array();

			foreach ( array_slice( (array) $values, 0, 500 ) as $value ) {
				$term = $this->find_term( $value, $taxonomy );

				if ( $term ) {
					$slugs[ $taxonomy ][] = $term->slug;
				}
			}
		}

		return $slugs;
	}

	/**
	 * A term by ID, slug or name.
	 *
	 * @param mixed  $value    ID, slug or name.
	 * @param string $taxonomy Taxonomy.
	 * @return WP_Term|null
	 */
	private function find_term( $value, string $taxonomy ): ?WP_Term {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$term = get_term( (int) $value, $taxonomy );

			return $term instanceof WP_Term ? $term : null;
		}

		$value = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $value ) {
			return null;
		}

		foreach ( array( 'slug', 'name' ) as $by ) {
			$term = get_term_by( $by, $value, $taxonomy );

			if ( $term instanceof WP_Term ) {
				return $term;
			}
		}

		return null;
	}
}
