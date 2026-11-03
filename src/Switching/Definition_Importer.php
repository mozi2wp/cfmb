<?php
/**
 * Saves imported definitions for Switch Assistant sources.
 *
 * @package CFMB
 */

namespace CFMB\Switching;

use CFMB\Connections\Connection_Repository;
use CFMB\Content\Content_Repository;
use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Post_Type_Repository;
use CFMB\Content\Taxonomy_Repository;
use CFMB\Schema\Group_Repository;
use CFMB\Schema\Group_Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Same rules as the ACF import: what CFMB already has is left alone (with a
 * note), groups import inactive while the source plugin is active and turn
 * on after the values are converted, and post types or taxonomies the
 * source still registers wait until it is deactivated. Everything saved is
 * added to the run's `created` list, so undo can trash it.
 *
 * @since 0.15.0
 */
class Definition_Importer {

	/**
	 * Field Groups.
	 *
	 * @var Group_Repository
	 */
	private Group_Repository $groups;

	/**
	 * Field Group sanitizer.
	 *
	 * @var Group_Sanitizer
	 */
	private Group_Sanitizer $sanitizer;

	/**
	 * Content repositories by kind.
	 *
	 * @var array<string, Content_Repository>
	 */
	private array $content;

	/**
	 * Constructor.
	 *
	 * @param Group_Repository        $groups        Field Groups.
	 * @param Group_Sanitizer         $sanitizer     Field Group sanitizer.
	 * @param Post_Type_Repository    $post_types    Post types.
	 * @param Taxonomy_Repository     $taxonomies    Taxonomies.
	 * @param Options_Page_Repository $options_pages Options pages.
	 * @param Connection_Repository   $connections   Connections.
	 */
	public function __construct( Group_Repository $groups, Group_Sanitizer $sanitizer, Post_Type_Repository $post_types, Taxonomy_Repository $taxonomies, Options_Page_Repository $options_pages, Connection_Repository $connections ) {
		$this->groups    = $groups;
		$this->sanitizer = $sanitizer;
		$this->content   = array(
			'post_type'    => $post_types,
			'taxonomy'     => $taxonomies,
			'options_page' => $options_pages,
			'connection'   => $connections,
		);
	}

	/**
	 * Whether a group with this key exists. Trashed groups don't count: an
	 * import saved again over its trashed copy brings it back.
	 *
	 * @param string $key Group key.
	 * @return bool
	 */
	public function has_group( string $key ): bool {
		foreach ( $this->groups->all() as $group ) {
			if ( $group->key() === $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether a definition of a kind exists, by key or by name (trashed ones don't count).
	 *
	 * @param string $kind post_type, taxonomy, options_page or connection.
	 * @param string $key  Key.
	 * @param string $name Name.
	 * @return bool
	 */
	public function has( string $kind, string $key, string $name = '' ): bool {
		$repo = $this->content[ $kind ] ?? null;

		if ( ! $repo ) {
			return false;
		}

		if ( '' !== $name && in_array( $name, $repo->names_taken(), true ) ) {
			return true;
		}

		foreach ( $repo->all() as $item ) {
			if ( '' !== $key && $item['key'] === $key ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * The name of a definition by key, or empty when it doesn't exist.
	 *
	 * @param string $kind post_type, taxonomy, options_page or connection.
	 * @param string $key  Key.
	 * @return string
	 */
	public function name_of( string $kind, string $key ): string {
		foreach ( isset( $this->content[ $kind ] ) ? $this->content[ $kind ]->all() : array() as $item ) {
			if ( $item['key'] === $key ) {
				return (string) $item['name'];
			}
		}

		return '';
	}

	/**
	 * Save a Field Group.
	 *
	 * @param array<string, mixed> $raw           Raw group, as the builder would send it.
	 * @param array<string, mixed> $run           Run, updated.
	 * @param bool                 $source_active Whether the source plugin is active.
	 * @return string Saved key, or empty when it existed or failed (a note says which).
	 */
	public function group( array $raw, array &$run, bool $source_active ): string {
		$title = (string) ( $raw['title'] ?? '' );

		if ( $this->has_group( (string) ( $raw['key'] ?? '' ) ) ) {
			/* translators: %s: field group title */
			$run['notes'][] = sprintf( __( '%s was imported before, so it was left as it is.', 'custom-fields-meta-box' ), $title );
			return '';
		}

		$wanted = ! empty( $raw['active'] );

		if ( $source_active ) {
			$raw['active'] = false;
		}

		// An earlier import that was undone left it in the trash; bring that copy back.
		if ( null !== $this->groups->find( (string) ( $raw['key'] ?? '' ) ) ) {
			$this->groups->restore( (string) $raw['key'] );
		}

		$clean = $this->sanitizer->sanitize( $raw );
		$saved = is_wp_error( $clean ) ? $clean : $this->groups->save( $clean );

		if ( is_wp_error( $saved ) ) {
			/* translators: 1: field group title, 2: error message */
			$run['notes'][] = sprintf( __( '%1$s could not be imported: %2$s', 'custom-fields-meta-box' ), $title, $saved->get_error_message() );
			return '';
		}

		$key = (string) $clean['key'];

		$run['created'][] = array(
			'kind' => 'group',
			'key'  => $key,
		);

		if ( $source_active && $wanted ) {
			$run['activate'][] = $key;
		}

		return $key;
	}

	/**
	 * Save a post type, taxonomy, options page or connection.
	 *
	 * @param string               $kind          post_type, taxonomy, options_page or connection.
	 * @param array<string, mixed> $raw           Raw definition for the kind's schema.
	 * @param array<string, mixed> $run           Run, updated.
	 * @param bool                 $source_active Whether the source plugin is active.
	 * @return string Saved key, or empty (a note says why).
	 */
	public function content( string $kind, array $raw, array &$run, bool $source_active ): string {
		$repo = $this->content[ $kind ] ?? null;

		if ( ! $repo ) {
			return '';
		}

		$name  = (string) ( $raw['name'] ?? '' );
		$label = '' !== (string) ( $raw['title'] ?? '' ) ? (string) $raw['title'] : $name;

		if ( $this->has( $kind, (string) ( $raw['key'] ?? '' ), $name ) ) {
			/* translators: %s: definition label */
			$run['notes'][] = sprintf( __( '%s was imported before, so it was left as it is.', 'custom-fields-meta-box' ), $label );
			return '';
		}

		$registered = ( 'post_type' === $kind && post_type_exists( $name ) ) || ( 'taxonomy' === $kind && taxonomy_exists( $name ) );

		if ( $source_active && $registered ) {
			/* translators: %s: post type or taxonomy label */
			$run['notes'][] = sprintf( __( '%s is still registered by the other plugin. Deactivate it and run the import again to bring it over.', 'custom-fields-meta-box' ), $label );
			return '';
		}

		if ( '' !== (string) ( $raw['key'] ?? '' ) && null !== $repo->find( (string) $raw['key'] ) ) {
			$repo->restore( (string) $raw['key'] );
		}

		$clean = $repo->schema()->sanitize( $raw, null, $repo->names_taken( (string) ( $raw['key'] ?? '' ) ) );
		$saved = is_wp_error( $clean ) ? $clean : $repo->save( $clean );

		if ( is_wp_error( $saved ) ) {
			/* translators: 1: definition label, 2: error message */
			$run['notes'][] = sprintf( __( '%1$s could not be imported: %2$s', 'custom-fields-meta-box' ), $label, $saved->get_error_message() );
			return '';
		}

		$run['created'][] = array(
			'kind' => $kind,
			'key'  => (string) $saved['key'],
		);

		return (string) $saved['key'];
	}

	/**
	 * Move a created definition to the trash.
	 *
	 * @param string $kind group, post_type, taxonomy, options_page or connection.
	 * @param string $key  Key.
	 * @return void
	 */
	public function trash( string $kind, string $key ): void {
		if ( 'group' === $kind ) {
			$this->groups->trash( $key );
		} elseif ( isset( $this->content[ $kind ] ) ) {
			$this->content[ $kind ]->trash( $key );
		}
	}

	/**
	 * Turn on a group imported inactive.
	 *
	 * @param string $key Group key.
	 * @return void
	 */
	public function activate_group( string $key ): void {
		$group = $this->groups->find( $key );

		if ( $group && ! $group->active() ) {
			$data           = $group->to_array();
			$data['active'] = true;
			$this->groups->save( $data );
		}
	}
}
