<?php
/**
 * ACF function names for Compatibility Mode.
 *
 * Loaded only by Compat_Mode while the setting is on and neither ACF, ACF
 * Pro nor Secure Custom Fields is active. Each function is a thin wrapper
 * around its cfmb_ version; Object_Map translates ACF's arguments.
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;

use CFMB\Compat\Object_Map;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Compatibility Mode exists to provide these unprefixed names; it is opt-in and every function is guarded.

if ( ! function_exists( 'get_field' ) ) {
	/**
	 * See cfmb_get_field().
	 *
	 * @param string $selector     Field name or field_ key.
	 * @param mixed  $post_id      ACF post ID argument.
	 * @param bool   $format_value Whether to format the value.
	 * @param bool   $escape_html  Whether to return escaped HTML.
	 * @return mixed
	 */
	function get_field( $selector, $post_id = false, $format_value = true, $escape_html = false ) {
		return $escape_html
			? cfmb_get_field_html( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ) )
			: cfmb_get_field( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ), (bool) $format_value );
	}
}

if ( ! function_exists( 'the_field' ) ) {
	/**
	 * See cfmb_the_field().
	 *
	 * @param string $selector Field name or field_ key.
	 * @param mixed  $post_id  ACF post ID argument.
	 * @return void
	 */
	function the_field( $selector, $post_id = false ) {
		cfmb_the_field( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ) );
	}
}

if ( ! function_exists( 'get_fields' ) ) {
	/**
	 * See cfmb_get_fields().
	 *
	 * @param mixed $post_id      ACF post ID argument.
	 * @param bool  $format_value Whether to format values.
	 * @return array<string, mixed>
	 */
	function get_fields( $post_id = false, $format_value = true ) {
		return cfmb_get_fields( Object_Map::ref( $post_id ), (bool) $format_value );
	}
}

if ( ! function_exists( 'get_field_object' ) ) {
	/**
	 * See cfmb_get_field_object().
	 *
	 * @param string $selector Field name or field_ key.
	 * @param mixed  $post_id  ACF post ID argument.
	 * @return array<string, mixed>|null
	 */
	function get_field_object( $selector, $post_id = false ) {
		return cfmb_get_field_object( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ) );
	}
}

if ( ! function_exists( 'update_field' ) ) {
	/**
	 * See cfmb_update_field().
	 *
	 * @param string $selector Field name or field_ key.
	 * @param mixed  $value    Value.
	 * @param mixed  $post_id  ACF post ID argument.
	 * @return bool
	 */
	function update_field( $selector, $value, $post_id = false ) {
		return cfmb_update_field( Object_Map::name( (string) $selector ), $value, Object_Map::ref( $post_id, (string) $selector ) );
	}
}

if ( ! function_exists( 'delete_field' ) ) {
	/**
	 * See cfmb_delete_field().
	 *
	 * @param string $selector Field name or field_ key.
	 * @param mixed  $post_id  ACF post ID argument.
	 * @return bool
	 */
	function delete_field( $selector, $post_id = false ) {
		return cfmb_delete_field( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ) );
	}
}

if ( ! function_exists( 'have_rows' ) ) {
	/**
	 * See cfmb_have_rows().
	 *
	 * @param string $selector Field name or field_ key.
	 * @param mixed  $post_id  ACF post ID argument.
	 * @return bool
	 */
	function have_rows( $selector, $post_id = false ) {
		return cfmb_have_rows( Object_Map::name( (string) $selector ), Object_Map::ref( $post_id, (string) $selector ) );
	}
}

if ( ! function_exists( 'the_row' ) ) {
	/**
	 * See cfmb_the_row().
	 *
	 * @return array<string, mixed>
	 */
	function the_row() {
		return cfmb_the_row();
	}
}

if ( ! function_exists( 'get_sub_field' ) ) {
	/**
	 * See cfmb_get_sub_field().
	 *
	 * @param string $selector Sub-field name.
	 * @return mixed
	 */
	function get_sub_field( $selector ) {
		return cfmb_get_sub_field( Object_Map::name( (string) $selector ) );
	}
}

if ( ! function_exists( 'the_sub_field' ) ) {
	/**
	 * See cfmb_the_sub_field().
	 *
	 * @param string $selector Sub-field name.
	 * @return void
	 */
	function the_sub_field( $selector ) {
		cfmb_the_sub_field( Object_Map::name( (string) $selector ) );
	}
}

if ( ! function_exists( 'get_row_layout' ) ) {
	/**
	 * See cfmb_get_row_layout().
	 *
	 * @return string
	 */
	function get_row_layout() {
		return cfmb_get_row_layout();
	}
}

if ( ! function_exists( 'get_row_index' ) ) {
	/**
	 * See cfmb_get_row_index(). ACF counts rows from 1.
	 *
	 * @return int
	 */
	function get_row_index() {
		return cfmb_get_row_index() + 1;
	}
}

if ( ! function_exists( 'reset_rows' ) ) {
	/**
	 * See cfmb_reset_rows().
	 *
	 * @return void
	 */
	function reset_rows() {
		cfmb_reset_rows();
	}
}

if ( ! function_exists( 'acf_add_options_page' ) ) {
	/**
	 * See cfmb_add_options_page() and Object_Map::add_page().
	 *
	 * @param mixed $page ACF page title or arguments.
	 * @return bool
	 */
	function acf_add_options_page( $page = '' ) {
		return Object_Map::add_page( $page );
	}
}

if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
	/**
	 * See cfmb_add_options_page() and Object_Map::add_page().
	 *
	 * @param mixed $page ACF page title or arguments.
	 * @return bool
	 */
	function acf_add_options_sub_page( $page = '' ) {
		return Object_Map::add_page( $page, true );
	}
}

// phpcs:enable
