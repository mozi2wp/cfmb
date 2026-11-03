<?php
/**
 * Renders Field Blocks.
 *
 * @package CFMB
 */

namespace CFMB\Blocks;

use CFMB\Location\Context;
use CFMB\Location\Matcher;
use CFMB\Schema\Field;
use CFMB\Schema\Group;
use CFMB\Storage\Block_Context;
use CFMB\Storage\Field_Values;
use CFMB\Storage\Object_Ref;
use CFMB\Storage\Store_Resolver;
use WP_Block;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a block's attributes into HTML through its PHP template.
 *
 * Values come from the block's `data` attribute. Anyone who can edit the
 * post can change that attribute by hand, so every value is sanitized again
 * by its field type before the template sees it.
 *
 * The template gets these variables:
 *
 * - $block              Name, attributes, className, align, anchor and data of the block.
 * - $fields             Every field value by name, formatted like cfmb_get_field() returns it.
 * - $content            Rendered inner blocks.
 * - $is_preview         True while the block editor shows the preview.
 * - $post_id            The post the block shows on, from the block context (0 in templates).
 * - $context            The block context (postId, postType, queryId).
 * - $wrapper_attributes Wrapper attributes from block supports (class, style, id).
 * - $wp_block           The WP_Block instance.
 *
 * cfmb_get_field() and the other template functions read this block's
 * values while the template runs.
 */
class Block_Renderer {

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
	 * Storage backends; holds the block store.
	 *
	 * @var Store_Resolver
	 */
	private Store_Resolver $stores;

	/**
	 * Template finder.
	 *
	 * @var Template_Locator
	 */
	private Template_Locator $locator;

	/**
	 * Whether the current render is the editor preview.
	 *
	 * @var bool
	 */
	private bool $preview = false;

	/**
	 * Constructor.
	 *
	 * @param Matcher          $matcher Location matcher.
	 * @param Field_Values     $values  Value service.
	 * @param Store_Resolver   $stores  Storage backends.
	 * @param Template_Locator $locator Template finder.
	 */
	public function __construct( Matcher $matcher, Field_Values $values, Store_Resolver $stores, Template_Locator $locator ) {
		$this->matcher = $matcher;
		$this->values  = $values;
		$this->stores  = $stores;
		$this->locator = $locator;
	}

	/**
	 * Groups whose fields belong to a block, in display order.
	 *
	 * @param string $block Block name.
	 * @return Group[]
	 */
	public function groups( string $block ): array {
		return $this->matcher->groups_for( Context::for_block( $block ) );
	}

	/**
	 * Render a block for the editor preview.
	 *
	 * @param WP_Block $block Block instance.
	 * @return string
	 */
	public function render_preview( WP_Block $block ): string {
		$this->preview = true;

		try {
			return $block->render();
		} finally {
			$this->preview = false;
		}
	}

	/**
	 * Block render callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Rendered inner blocks.
	 * @param WP_Block|null        $block      Block instance.
	 * @return string
	 */
	public function render( $attributes, $content = '', $block = null ): string {
		$name       = $block instanceof WP_Block ? (string) $block->name : '';
		$definition = cfmb()->get( Block_Registry::class )->get( $name );

		if ( ! $definition ) {
			return '';
		}

		$template   = cfmb()->get( Block_Registry::class )->template( $definition );
		$attributes = is_array( $attributes ) ? $attributes : array();
		$data       = is_array( $attributes['data'] ?? null ) ? $attributes['data'] : array();
		$blocks     = $this->stores->blocks();
		$ref        = $this->clean_instance( $name, $data );
		$context    = $block instanceof WP_Block ? (array) $block->context : array();

		$details = array(
			'name'       => $name,
			'attributes' => $attributes,
			'data'       => $blocks->data( $ref ),
			'className'  => (string) ( $attributes['className'] ?? '' ),
			'align'      => (string) ( $attributes['align'] ?? '' ),
			'anchor'     => (string) ( $attributes['anchor'] ?? '' ),
		);

		Block_Context::push( $ref, $details );

		try {
			$vars = array(
				'block'              => $details,
				'content'            => (string) $content,
				'is_preview'         => $this->preview,
				'post_id'            => (int) ( $context['postId'] ?? 0 ),
				'context'            => $context,
				'wrapper_attributes' => cfmb_block_wrapper_attributes(),
				'wp_block'           => $block,
			);

			/**
			 * Filters a Field Block's HTML before its PHP template runs. Return
			 * a string to render the block another way; the block's values are
			 * open in the template API while this runs. <InnerBlocks /> in the
			 * returned HTML and the `cfmb/blocks/render` filter still apply.
			 *
			 * @since 0.13.0
			 *
			 * @param string|null          $html    Null to use the PHP template.
			 * @param array<string, mixed> $details Block name, attributes, data, className, align and anchor.
			 * @param Object_Ref           $ref     The block instance, with cleaned values.
			 * @param array<string, mixed> $vars    content, is_preview, post_id, context, wrapper_attributes and wp_block.
			 */
			$html = apply_filters( 'cfmb/blocks/pre_render', null, $details, $ref, $vars ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

			if ( ! is_string( $html ) ) {
				if ( '' === $template['path'] ) {
					return $this->preview ? $this->notice( $template['error'] ) : '';
				}

				$html = $this->include_template( $template['path'], array_merge( $vars, array( 'fields' => cfmb_get_fields( $ref ) ) ) );
			}
		} finally {
			Block_Context::pop();
			$blocks->close( $ref );
		}

		$html = Inner_Blocks_Tag::replace( $html, (string) $content, $this->preview );

		/**
		 * Filters a Field Block's HTML.
		 *
		 * @param string               $html       Rendered HTML.
		 * @param array<string, mixed> $details    Block name, attributes and data.
		 * @param bool                 $is_preview Whether this is the editor preview.
		 */
		return (string) apply_filters( 'cfmb/blocks/render', $html, $details, $this->preview ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
	}

	/**
	 * Open a block instance holding only values that pass each field's
	 * sanitizer. Values for fields the block no longer has are dropped.
	 *
	 * @param string               $name Block name.
	 * @param array<string, mixed> $data Values from the attribute.
	 * @return Object_Ref
	 */
	public function clean_instance( string $name, array $data ): Object_Ref {
		$blocks = $this->stores->blocks();
		$dirty  = $blocks->open( $name, $data );
		$clean  = $blocks->open( $name, array() );

		foreach ( $this->groups( $name ) as $group ) {
			foreach ( $group->fields() as $field ) {
				$type = $this->values->type_of( $field );

				if ( ! $type || ! $type->stores_value() || '' === $field->name() || ! $this->has_value( $field, $dirty ) ) {
					continue;
				}

				$raw = $this->values->load( $field, $group, $dirty, false );
				$this->values->store( $field, $group, $clean, $this->values->sanitize( $field, $raw ) );
			}
		}

		$blocks->close( $dirty );

		return $clean;
	}

	/**
	 * Whether the attribute holds anything for a field, so fields never
	 * saved keep falling back to their defaults.
	 *
	 * @param Field $field Field.
	 * @param Object_Ref         $ref   Block instance.
	 * @return bool
	 */
	private function has_value( Field $field, Object_Ref $ref ): bool {
		$prefix = $field->name();

		foreach ( array_keys( $this->stores->blocks()->data( $ref ) ) as $key ) {
			if ( $key === $prefix || 0 === strpos( (string) $key, $prefix . '_' ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Include a template with its variables and return the output.
	 *
	 * @param string               $template Template path, already checked by Template_Locator.
	 * @param array<string, mixed> $vars     Template variables.
	 * @return string
	 */
	private function include_template( string $template, array $vars ): string {
		if ( ! $this->locator->is_allowed( $template ) ) {
			return '';
		}

		ob_start();

		( static function ( string $cfmb_template, array $cfmb_vars ): void {
			extract( $cfmb_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template variables, documented above.
			include $cfmb_template;
		} )( $template, $vars );

		return (string) ob_get_clean();
	}

	/**
	 * Notice shown in the editor preview when the block can't render.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private function notice( string $message ): string {
		return sprintf( '<div class="cfmb-block-notice"><p>%s</p></div>', esc_html( $message ) );
	}
}
