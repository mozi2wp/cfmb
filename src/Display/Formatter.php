<?php
/**
 * Field values as HTML and text for Dynamic Data.
 *
 * @package CFMB
 */

namespace CFMB\Display;

use CFMB\Fields\Structure_Field;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use WP_Term;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a field value into what a shortcode or a bound block attribute
 * shows. All HTML returned is escaped; plain parts (url, alt, title) are
 * unescaped strings for the caller to escape for its context.
 *
 * Linked posts and attachments the visitor can't see are left out.
 * Repeater, Group and Stacks values are not shown here; full coverage of
 * those comes with CFMB Pro.
 */
class Formatter {

	/**
	 * Types that hold post or attachment IDs.
	 */
	private const POST_IDS = array( 'post', 'relationship', 'page_link', 'image', 'file', 'gallery' );

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
	 * Constructor.
	 *
	 * @param Field_Values $values     Value service.
	 * @param Visibility   $visibility Visibility rules.
	 */
	public function __construct( Field_Values $values, Visibility $visibility ) {
		$this->values     = $values;
		$this->visibility = $visibility;
	}

	/**
	 * Whether Dynamic Data can show a field type. Structures are left to Pro.
	 *
	 * @param Field $field Field.
	 * @return bool
	 */
	public function supports( Field $field ): bool {
		$type = $this->values->type_of( $field );

		return $type && $type->stores_value() && ! $type instanceof Structure_Field && $this->visibility->can_show_field( $field );
	}

	/**
	 * The stored value, with links to posts the visitor can't see removed.
	 *
	 * @param Field      $field Field.
	 * @param Group      $group Group.
	 * @param Object_Ref $ref   Object.
	 * @return mixed
	 */
	public function raw( Field $field, Group $group, Object_Ref $ref ) {
		$raw = $this->values->load( $field, $group, $ref, false );

		if ( in_array( $field->type(), self::POST_IDS, true ) ) {
			$raw = is_array( $raw )
				? array_values( array_filter( $raw, fn( $id ) => is_numeric( $id ) && $this->visibility->can_read_post( (int) $id ) ) )
				: ( is_numeric( $raw ) && (int) $raw > 0 && ! $this->visibility->can_read_post( (int) $raw ) ? '' : $raw );
		}

		/**
		 * Filters a value before Dynamic Data formats it.
		 *
		 * @param mixed      $raw   Stored value.
		 * @param Field      $field Field definition.
		 * @param Object_Ref $ref   Object.
		 */
		return apply_filters( 'cfmb/display/value', $raw, $field, $ref ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Escaped HTML for a value.
	 *
	 * @param Field                $field Field.
	 * @param Group                $group Group.
	 * @param Object_Ref           $ref   Object.
	 * @param array<string, mixed> $args  size (image size), separator (between list items).
	 * @return string
	 */
	public function html( Field $field, Group $group, Object_Ref $ref, array $args = array() ): string {
		if ( ! $this->supports( $field ) ) {
			return '';
		}

		$raw  = $this->raw( $field, $group, $ref );
		$html = $this->html_for( $field, $raw, $ref, $args );

		/**
		 * Filters the HTML Dynamic Data shows for a field. Also runs as `/type={type}`.
		 *
		 * @param string               $html  Escaped HTML.
		 * @param Field                $field Field definition.
		 * @param Object_Ref           $ref   Object.
		 * @param array<string, mixed> $args  Display arguments.
		 */
		$html = (string) apply_filters( 'cfmb/display/format', $html, $field, $ref, $args ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return (string) apply_filters( "cfmb/display/format/type={$field->type()}", $html, $field, $ref, $args ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Plain text: the HTML without tags.
	 *
	 * @param Field                $field Field.
	 * @param Group                $group Group.
	 * @param Object_Ref           $ref   Object.
	 * @param array<string, mixed> $args  Display arguments.
	 * @return string Unescaped text.
	 */
	public function text( Field $field, Group $group, Object_Ref $ref, array $args = array() ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $this->html( $field, $group, $ref, $args ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	}

	/**
	 * The stored value as escaped text, for format="raw". Lists are joined with commas.
	 *
	 * @param Field      $field Field.
	 * @param Group      $group Group.
	 * @param Object_Ref $ref   Object.
	 * @return string
	 */
	public function raw_html( Field $field, Group $group, Object_Ref $ref ): string {
		if ( ! $this->supports( $field ) ) {
			return '';
		}

		$raw   = $this->raw( $field, $group, $ref );
		$items = is_array( $raw ) ? $raw : array( $raw );

		return esc_html( implode( ',', array_map( static fn( $item ) => is_scalar( $item ) ? (string) $item : '', $items ) ) );
	}

	/**
	 * Parts a block attribute can bind to.
	 *
	 * @param Field                $field Field.
	 * @param Group                $group Group.
	 * @param Object_Ref           $ref   Object.
	 * @param array<string, mixed> $args  Display arguments.
	 * @return array{html: string, text: string, url: string, alt: string, title: string, id: int}
	 */
	public function parts( Field $field, Group $group, Object_Ref $ref, array $args = array() ): array {
		$parts = array(
			'html'  => '',
			'text'  => '',
			'url'   => '',
			'alt'   => '',
			'title' => '',
			'id'    => 0,
		);

		if ( ! $this->supports( $field ) ) {
			return $parts;
		}

		$raw = $this->raw( $field, $group, $ref );
		$id  = is_numeric( $raw ) ? (int) $raw : ( is_array( $raw ) && isset( $raw[0] ) && is_numeric( $raw[0] ) ? (int) $raw[0] : 0 );

		$parts['html'] = $this->html( $field, $group, $ref, $args );
		$parts['text'] = trim( html_entity_decode( wp_strip_all_tags( $parts['html'] ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );

		switch ( $field->type() ) {
			case 'image':
			case 'gallery':
				$size           = (string) ( $args['size'] ?? 'large' );
				$parts['id']    = $id;
				$parts['url']   = $id ? (string) wp_get_attachment_image_url( $id, '' !== $size ? $size : 'large' ) : '';
				$parts['alt']   = $id ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : '';
				$parts['title'] = $id ? get_the_title( $id ) : '';
				break;

			case 'file':
				$parts['id']    = $id;
				$parts['url']   = $id ? (string) wp_get_attachment_url( $id ) : '';
				$parts['title'] = $id ? get_the_title( $id ) : '';
				break;

			case 'post':
			case 'relationship':
			case 'page_link':
				$parts['id']    = $id;
				$parts['url']   = $id ? (string) get_permalink( $id ) : '';
				$parts['title'] = $id ? get_the_title( $id ) : '';
				$parts['text']  = $parts['title'];
				break;

			case 'taxonomy':
				$term           = $id ? get_term( $id ) : null;
				$link           = $term instanceof WP_Term ? get_term_link( $term ) : '';
				$parts['id']    = $id;
				$parts['url']   = is_string( $link ) ? $link : '';
				$parts['title'] = $term instanceof WP_Term ? $term->name : '';
				break;

			case 'link':
				$link           = is_array( $raw ) ? $raw : array();
				$parts['url']   = (string) ( $link['url'] ?? '' );
				$parts['title'] = (string) ( $link['title'] ?? '' );
				$parts['text']  = '' !== $parts['title'] ? $parts['title'] : $parts['url'];
				break;

			case 'email':
				$parts['url'] = is_string( $raw ) && '' !== $raw ? 'mailto:' . $raw : '';
				break;

			case 'url':
			case 'oembed':
				$parts['url']  = is_string( $raw ) ? $raw : '';
				$parts['text'] = $parts['url'];
				break;
		}

		return $parts;
	}

	/**
	 * HTML by type.
	 *
	 * @param Field                $field Field.
	 * @param mixed                $raw   Stored value, already filtered.
	 * @param Object_Ref           $ref   Object.
	 * @param array<string, mixed> $args  Display arguments.
	 * @return string
	 */
	private function html_for( Field $field, $raw, Object_Ref $ref, array $args ): string {
		$type = $this->values->type_of( $field );
		$size = (string) ( $args['size'] ?? '' );

		if ( ! $type ) {
			return '';
		}

		if ( 'image' === $field->type() ) {
			return is_numeric( $raw ) && (int) $raw > 0 ? wp_get_attachment_image( (int) $raw, '' !== $size ? $size : (string) $field->setting( 'preview_size', 'large' ) ) : '';
		}

		if ( 'gallery' === $field->type() ) {
			$items = '';
			foreach ( (array) $raw as $id ) {
				$image  = wp_get_attachment_image( (int) $id, '' !== $size ? $size : 'medium' );
				$items .= '' !== $image ? '<li class="cfmb-gallery__item">' . $image . '</li>' : '';
			}

			return '' !== $items ? '<ul class="cfmb-gallery">' . $items . '</ul>' : '';
		}

		$formatted = $this->values->format_raw( $field, $raw, $ref );
		$separator = isset( $args['separator'] ) && is_string( $args['separator'] ) ? $args['separator'] : null;

		if ( null !== $separator && is_array( $formatted ) && array_values( $formatted ) === $formatted && ! in_array( $field->type(), array( 'link', 'map' ), true ) ) {
			$items = array_filter( array_map( static fn( $item ) => $type->display( $item, $field ), $formatted ), 'strlen' );

			return implode( esc_html( $separator ), $items );
		}

		return null === $formatted ? '' : $type->display( $formatted, $field );
	}
}
