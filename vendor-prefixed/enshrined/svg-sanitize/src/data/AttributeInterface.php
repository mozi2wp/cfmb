<?php
namespace CFMB\Vendor\enshrined\svgSanitize\data;

defined( 'ABSPATH' ) || exit;

/**
 * Class AttributeInterface
 *
 * @package enshrined\svgSanitize\data
 */
interface AttributeInterface
{

    /**
     * Returns an array of attributes
     *
     * @return array
     */
    public static function getAttributes();
}
