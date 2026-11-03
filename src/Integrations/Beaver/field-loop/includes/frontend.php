<?php
/**
 * Field Loop module output. Beaver Builder includes this with $module set.
 *
 * @package CFMB
 *
 * @var \CFMB\Integrations\Beaver\Field_Loop_Module $module
 */

defined( 'ABSPATH' ) || exit;

echo $module->loop_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper escaped in Loop_Markup; items rendered and escaped by Beaver Builder.
