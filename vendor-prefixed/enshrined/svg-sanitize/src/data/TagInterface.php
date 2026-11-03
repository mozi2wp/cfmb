<?php
namespace CFMB\Vendor\enshrined\svgSanitize\data;

defined( 'ABSPATH' ) || exit;

/**
 * Interface TagInterface
 *
 * @package enshrined\svgSanitize\tags
 */
interface TagInterface
{

    /**
     * Returns an array of tags
     *
     * @return array
     */
    public static function getTags();

}