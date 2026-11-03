<?php
/**
 * Translates ACF arguments for Compatibility Mode.
 *
 * @package CFMB
 */

namespace CFMB\Compat;

use CFMB\Content\Options_Page_Repository;
use CFMB\Content\Options_Pages;
use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Group_Repository;
use CFMB\Storage\Object_Ref;
use CFMB\Switching\Switch_Assistant;

defined( 'ABSPATH' ) || exit;

/**
 * ACF's $post_id and $selector arguments in CFMB terms, so the wrappers in
 * functions.php stay one line each.
 *
 * - false or null: the current post
 * - "option" or "options": the options page that has the field. Pages the
 *   Switch Assistant brought over from ACF's "options" storage are tried
 *   first, then every other page.
 * - "{taxonomy}_12" (ACF's older term form): term_12
 * - other strings an options page was imported from (its ACF post_id)
 * - field_… keys: the field the Switch Assistant imported from it
 */
final class Object_Map {

	/**
	 * Object for an ACF $post_id.
	 *
	 * @param mixed  $post_id  ACF post_id argument.
	 * @param string $selector Field name, to find the options page that has it.
	 * @return mixed What cfmb_get_field() accepts.
	 */
	public static function ref( $post_id, string $selector = '' ) {
		if ( false === $post_id || null === $post_id || '' === $post_id ) {
			return null;
		}

		if ( ! is_string( $post_id ) || ctype_digit( $post_id ) || 1 === preg_match( '/^(post|term|user|comment)_\d+$/', $post_id ) ) {
			return $post_id;
		}

		$imported = get_option( Switch_Assistant::OPTION_IDS, array() );
		$imported = is_array( $imported ) ? $imported : array();
		$acf_id   = in_array( $post_id, array( 'option', 'options' ), true ) ? 'options' : $post_id;

		if ( 'options' === $acf_id || isset( $imported[ $acf_id ] ) ) {
			return self::options_ref( (array) ( $imported[ $acf_id ] ?? array() ), self::name( $selector ) );
		}

		if ( 1 === preg_match( '/^([a-z0-9_-]+)_(\d+)$/', $post_id, $parts ) && taxonomy_exists( $parts[1] ) ) {
			return 'term_' . $parts[2];
		}

		return $post_id;
	}

	/**
	 * Field name for an ACF selector: a name, or a field_… key.
	 *
	 * @param string $selector Selector.
	 * @return string
	 */
	public static function name( string $selector ): string {
		if ( 0 !== strpos( $selector, 'field_' ) ) {
			return $selector;
		}

		$key = 'fld_' . (string) preg_replace( '/[^a-z0-9]/', '', strtolower( substr( $selector, 6 ) ) );

		foreach ( cfmb()->get( Group_Repository::class )->active() as $group ) {
			$field = $group->find_field( $key );

			if ( $field ) {
				return $field->name();
			}
		}

		return $selector;
	}

	/**
	 * Register an options page from acf_add_options_page() arguments. A page
	 * with the same ID defined under CFMB > Options Pages (for example by the
	 * Switch Assistant) wins, so the call is skipped.
	 *
	 * @param mixed $args ACF arguments: a page title string or an array.
	 * @param bool  $sub  Whether it came from acf_add_options_sub_page().
	 * @return bool
	 */
	public static function add_page( $args, bool $sub = false ): bool {
		$args  = is_string( $args ) ? array( 'page_title' => $args ) : ( is_array( $args ) ? $args : array() );
		$title = (string) ( $args['page_title'] ?? __( 'Options', 'cfmb' ) );
		$slug  = (string) ( $args['menu_slug'] ?? 'acf-options-' . sanitize_title( $title ) );
		$id    = \CFMB\Switching\Acf_Mapper::page_name( $slug );
		$pages = cfmb()->get( Options_Pages::class );

		if ( $pages->get( $id ) || in_array( $id, cfmb()->get( Options_Page_Repository::class )->names_taken(), true ) ) {
			return true;
		}

		$parent = (string) ( $args['parent_slug'] ?? '' );

		if ( $sub && '' === $parent ) {
			$first  = array_values( array_filter( $pages->all(), static fn( array $page ): bool => '' === $page['parent_slug'] ) );
			$parent = $first ? (string) $first[0]['menu_slug'] : 'options-general.php';
		}

		$clean = array(
			'id'          => $id,
			'page_title'  => $title,
			'menu_title'  => (string) ( $args['menu_title'] ?? $title ),
			'parent_slug' => $parent,
			'capability'  => (string) ( $args['capability'] ?? 'edit_posts' ),
			'icon_url'    => (string) ( $args['icon_url'] ?? 'dashicons-admin-generic' ),
		);

		if ( isset( $args['position'] ) && is_numeric( $args['position'] ) ) {
			$clean['position'] = (int) $args['position'];
		}

		return cfmb_add_options_page( $clean );
	}

	/**
	 * The first options page whose groups have the field.
	 *
	 * @param string[] $preferred Page IDs to try first.
	 * @param string   $name      Field name.
	 * @return string option:{page}
	 */
	private static function options_ref( array $preferred, string $name ): string {
		$pages   = array_keys( cfmb()->get( Options_Pages::class )->all() );
		$ordered = array_values( array_unique( array_merge( $preferred, array( Object_Ref::DEFAULT_OPTIONS_PAGE ), $pages ) ) );
		$matcher = cfmb()->get( Matcher::class );

		foreach ( $ordered as $page ) {
			foreach ( $matcher->groups_for( Context::for_options_page( (string) $page ) ) as $group ) {
				foreach ( $group->fields() as $field ) {
					if ( $field->name() === $name ) {
						return 'option:' . $page;
					}
				}
			}
		}

		return 'option:' . ( $ordered[0] ?? Object_Ref::DEFAULT_OPTIONS_PAGE );
	}
}
