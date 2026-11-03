<?php
/**
 * Values for builder dynamic tags, one method per kind of tag.
 *
 * @package CFMB
 */

namespace CFMB\Integrations\Builders;

use CFMB\Display\Formatter;
use CFMB\Display\Resolver;

defined( 'ABSPATH' ) || exit;

/**
 * Every builder integration reads values through here, so they all follow
 * the same visibility rules as the shortcode and Block Bindings.
 *
 * Paths starting with "@" read the current Field Loop item instead of a
 * field: @index, @count, @layout, @image, @image_url, @image_alt,
 * @image_title, @image_caption, @post_url, and for a loop over posts,
 * terms or users @title (title, term name or display name) and @url
 * (permalink, term link or author archive).
 */
class Tag_Values {

	/**
	 * Field lookup.
	 *
	 * @var Field_Source
	 */
	private Field_Source $source;

	/**
	 * Value formatting.
	 *
	 * @var Formatter
	 */
	private Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @param Field_Source $source    Field lookup.
	 * @param Formatter    $formatter Value formatting.
	 */
	public function __construct( Field_Source $source, Formatter $formatter ) {
		$this->source    = $source;
		$this->formatter = $formatter;
	}

	/**
	 * Escaped HTML, as the shortcode prints it.
	 *
	 * @param string               $path    Field path.
	 * @param string               $object  Object argument.
	 * @param array<string, mixed> $args    size, separator.
	 * @param int                  $post_id Post the builder is rendering.
	 * @return string
	 */
	public function html( string $path, string $object = '', array $args = array(), int $post_id = 0 ): string {
		if ( $this->is_item_path( $path ) ) {
			return esc_html( $this->item_text( $path ) );
		}

		$found = $this->source->find( $path, $object, $post_id );

		return $found ? $this->formatter->html( $found['field'], $found['group'], $found['ref'], $this->display_args( $args ) ) : '';
	}

	/**
	 * Plain text without tags. Unescaped.
	 *
	 * @param string               $path    Field path.
	 * @param string               $object  Object argument.
	 * @param array<string, mixed> $args    size, separator.
	 * @param int                  $post_id Post the builder is rendering.
	 * @return string
	 */
	public function text( string $path, string $object = '', array $args = array(), int $post_id = 0 ): string {
		if ( $this->is_item_path( $path ) ) {
			return $this->item_text( $path );
		}

		$found = $this->source->find( $path, $object, $post_id );

		return $found ? $this->formatter->text( $found['field'], $found['group'], $found['ref'], $this->display_args( $args ) ) : '';
	}

	/**
	 * A URL: the link of a Link field, a file or image URL, a permalink, a mailto. Unescaped.
	 *
	 * @param string               $path    Field path.
	 * @param string               $object  Object argument.
	 * @param array<string, mixed> $args    size (for images).
	 * @param int                  $post_id Post the builder is rendering.
	 * @return string
	 */
	public function url( string $path, string $object = '', array $args = array(), int $post_id = 0 ): string {
		if ( '@image_url' === $path ) {
			$image = $this->item_image( (string) ( $args['size'] ?? '' ) );

			return $image ? $image['url'] : '';
		}

		if ( '@post_url' === $path ) {
			$item = Loop_Context::nearest( 'post' );

			return $item ? (string) get_permalink( (int) $item['id'] ) : '';
		}

		if ( '@url' === $path ) {
			return $this->item_url();
		}

		$found = $this->source->find( $path, $object, $post_id );

		return $found ? $this->formatter->parts( $found['field'], $found['group'], $found['ref'], $this->display_args( $args ) )['url'] : '';
	}

	/**
	 * One image. A Gallery gives its first image.
	 *
	 * @param string $path    Field path, or @image.
	 * @param string $object  Object argument.
	 * @param string $size    Image size for the URL.
	 * @param int    $post_id Post the builder is rendering.
	 * @return array{id: int, url: string, alt: string}|null
	 */
	public function image( string $path, string $object = '', string $size = '', int $post_id = 0 ): ?array {
		if ( '@image' === $path ) {
			return $this->item_image( $size );
		}

		$ids = $this->image_ids( $path, $object, $post_id );

		return $ids ? $this->attachment( $ids[0], $size ) : null;
	}

	/**
	 * Attachment IDs of a Gallery or Image field, or of the loop image.
	 *
	 * @param string $path    Field path, or @image.
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return int[]
	 */
	public function image_ids( string $path, string $object = '', int $post_id = 0 ): array {
		if ( '@image' === $path ) {
			$item = Loop_Context::nearest( 'image' );

			return $item ? array( (int) $item['id'] ) : array();
		}

		$found = $this->source->find( $path, $object, $post_id );

		if ( ! $found || ! in_array( $found['field']->type(), array( 'image', 'gallery' ), true ) || ! $this->formatter->supports( $found['field'] ) ) {
			return array();
		}

		$raw = $this->formatter->raw( $found['field'], $found['group'], $found['ref'] );

		return array_values( array_filter( array_map( 'intval', is_array( $raw ) ? $raw : array( $raw ) ) ) );
	}

	/**
	 * A number as stored, or empty.
	 *
	 * @param string $path    Field path, or @index / @count.
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return string
	 */
	public function number( string $path, string $object = '', int $post_id = 0 ): string {
		if ( $this->is_item_path( $path ) ) {
			return $this->item_text( $path );
		}

		$raw = $this->raw_scalar( $path, $object, $post_id );

		return is_numeric( $raw ) ? (string) $raw : '';
	}

	/**
	 * A color as stored (#rrggbb or rgba()), or empty.
	 *
	 * @param string $path    Field path.
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return string
	 */
	public function color( string $path, string $object = '', int $post_id = 0 ): string {
		return $this->raw_scalar( $path, $object, $post_id );
	}

	/**
	 * A Date, Time or Date Time value in a PHP date format, or the field's
	 * own display format when $format is empty. Unescaped.
	 *
	 * @param string $path    Field path.
	 * @param string $object  Object argument.
	 * @param string $format  PHP date format.
	 * @param int    $post_id Post the builder is rendering.
	 * @return string
	 */
	public function date( string $path, string $object = '', string $format = '', int $post_id = 0 ): string {
		if ( '' === trim( $format ) ) {
			return $this->text( $path, $object, array(), $post_id );
		}

		$raw = $this->raw_scalar( $path, $object, $post_id );

		if ( '' === $raw ) {
			return '';
		}

		// Stored as ISO in the site timezone (2026-10-15, 14:30:00, 2026-10-15 14:30:00); ACF's Ymd also accepted.
		$raw  = 1 === preg_match( '/^\d{8}$/', $raw ) ? substr( $raw, 0, 4 ) . '-' . substr( $raw, 4, 2 ) . '-' . substr( $raw, 6, 2 ) : $raw;
		$date = date_create_immutable( $raw, wp_timezone() );

		return $date ? (string) wp_date( $format, $date->getTimestamp() ) : '';
	}

	/**
	 * Field lookup.
	 *
	 * @return Field_Source
	 */
	public function source(): Field_Source {
		return $this->source;
	}

	/**
	 * Source lookup.
	 *
	 * @return Resolver
	 */
	public function resolver(): Resolver {
		return $this->source->resolver();
	}

	/**
	 * A scalar stored value as a string, or empty.
	 *
	 * @param string $path    Field path.
	 * @param string $object  Object argument.
	 * @param int    $post_id Post the builder is rendering.
	 * @return string
	 */
	private function raw_scalar( string $path, string $object, int $post_id ): string {
		$found = $this->source->find( $path, $object, $post_id );

		if ( ! $found || ! $this->formatter->supports( $found['field'] ) ) {
			return '';
		}

		$raw = $this->formatter->raw( $found['field'], $found['group'], $found['ref'] );

		return is_scalar( $raw ) ? trim( (string) $raw ) : '';
	}

	/**
	 * Whether a path reads the loop item rather than a field.
	 *
	 * @param string $path Path.
	 * @return bool
	 */
	private function is_item_path( string $path ): bool {
		return '' !== $path && '@' === $path[0];
	}

	/**
	 * Text facts about the current loop item. Unescaped.
	 *
	 * @param string $path @index (1-based), @count, @layout, @image_alt, @image_title, @image_caption.
	 * @return string
	 */
	private function item_text( string $path ): string {
		$item = Loop_Context::current();

		switch ( $path ) {
			case '@index':
				return $item ? (string) ( (int) $item['position'] + 1 ) : '';

			case '@count':
				return $item ? (string) (int) $item['count'] : '';

			case '@layout':
				$row = Loop_Context::nearest( 'row' );

				return $row ? (string) $row['layout'] : '';

			case '@title':
				return $this->item_title();

			case '@image_alt':
			case '@image_title':
			case '@image_caption':
				$image = Loop_Context::nearest( 'image' );

				if ( ! $image ) {
					return '';
				}

				$id = (int) $image['id'];

				if ( '@image_alt' === $path ) {
					return (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
				}

				return '@image_title' === $path ? get_the_title( $id ) : (string) wp_get_attachment_caption( $id );
		}

		return '';
	}

	/**
	 * Title of the innermost post, term or user item. Unescaped.
	 *
	 * @return string
	 */
	private function item_title(): string {
		$item = Field_Source::nearest_object_item();

		if ( ! $item ) {
			return '';
		}

		$id = (int) $item['id'];

		if ( 'term' === $item['kind'] ) {
			$term = get_term( $id );

			return $term instanceof \WP_Term ? $term->name : '';
		}

		if ( 'user' === $item['kind'] ) {
			$user = get_userdata( $id );

			return $user ? $user->display_name : '';
		}

		return get_the_title( $id );
	}

	/**
	 * Link of the innermost post, term or user item.
	 *
	 * @return string
	 */
	private function item_url(): string {
		$item = Field_Source::nearest_object_item();

		if ( ! $item ) {
			return '';
		}

		$id = (int) $item['id'];

		if ( 'term' === $item['kind'] ) {
			$link = get_term_link( $id );

			return is_string( $link ) ? $link : '';
		}

		return 'user' === $item['kind'] ? (string) get_author_posts_url( $id ) : (string) get_permalink( $id );
	}

	/**
	 * The current loop image.
	 *
	 * @param string $size Image size.
	 * @return array{id: int, url: string, alt: string}|null
	 */
	private function item_image( string $size ): ?array {
		$item = Loop_Context::nearest( 'image' );

		return $item ? $this->attachment( (int) $item['id'], $size ) : null;
	}

	/**
	 * Image data for an attachment.
	 *
	 * @param int    $id   Attachment ID.
	 * @param string $size Image size; empty for full.
	 * @return array{id: int, url: string, alt: string}|null
	 */
	private function attachment( int $id, string $size ): ?array {
		$url = wp_get_attachment_image_url( $id, '' !== $size ? $size : 'full' );

		if ( ! $url ) {
			return null;
		}

		return array(
			'id'  => $id,
			'url' => (string) $url,
			'alt' => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Display arguments the Formatter accepts.
	 *
	 * @param array<string, mixed> $args Raw arguments.
	 * @return array<string, mixed>
	 */
	private function display_args( array $args ): array {
		$clean = array( 'size' => sanitize_key( (string) ( $args['size'] ?? '' ) ) );

		if ( isset( $args['separator'] ) && is_string( $args['separator'] ) && '' !== $args['separator'] ) {
			$clean['separator'] = $args['separator'];
		}

		return $clean;
	}
}
