<?php
/**
 * Example Field Block template.
 *
 * Copy this file to your theme as cfmb-blocks/{block name}.php, for example
 * cfmb-blocks/hero.php for the block cfmb/hero. CFMB never loads this copy.
 *
 * Available variables:
 *
 * @var array<string, mixed> $block              Name, attributes, className, align, anchor and data.
 * @var array<string, mixed> $fields             Every field value by name, as cfmb_get_field() returns it.
 * @var string               $content            Rendered inner blocks (when you use <InnerBlocks />).
 * @var bool                 $is_preview         True in the block editor preview.
 * @var int                  $post_id            The post the block is on; 0 in site templates.
 * @var array<string, mixed> $context            Block context: postId, postType, queryId.
 * @var string               $wrapper_attributes Same as cfmb_block_wrapper_attributes().
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;

$cfmb_heading = cfmb_get_field( 'heading' );
?>
<section <?php echo cfmb_block_wrapper_attributes( array( 'class' => 'hero' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by WordPress. ?>>
	<?php if ( $cfmb_heading ) : ?>
		<h2 class="hero__heading"><?php cfmb_the_field( 'heading' ); ?></h2>
	<?php elseif ( $is_preview ) : ?>
		<p class="hero__hint"><?php esc_html_e( 'Add a heading in the block sidebar.', 'custom-fields-meta-box' ); ?></p>
	<?php endif; ?>

	<?php cfmb_the_field( 'image' ); ?>

	<div class="hero__content">
		<InnerBlocks allowedBlocks='["core/paragraph","core/buttons"]' />
	</div>
</section>
