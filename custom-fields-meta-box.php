<?php
/**
 * Plugin Name:       CFMB - Custom Fields Meta Box
 * Plugin URI:        https://mozi2wp.com/cfmb
 * Description:       Custom fields, meta boxes, post types, taxonomies and options pages for WordPress.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Mozi2WP
 * Author URI:        https://mozi2wp.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       custom-fields-meta-box
 *
 * @package CFMB
 */

defined( 'ABSPATH' ) || exit;

define( 'CFMB_VERSION', '1.0.0' );
define( 'CFMB_FILE', __FILE__ );
define( 'CFMB_DIR', plugin_dir_path( __FILE__ ) );
define( 'CFMB_URL', plugin_dir_url( __FILE__ ) );
define( 'CFMB_MIN_PHP', '7.4' );
define( 'CFMB_MIN_WP', '6.6' );

require_once CFMB_DIR . 'src/Core/Requirements.php';

if ( ! CFMB\Core\Requirements::met() ) {
	CFMB\Core\Requirements::show_notice();
	return;
}

require_once CFMB_DIR . 'vendor/autoload.php';
require_once CFMB_DIR . 'src/functions.php';

register_activation_hook( __FILE__, array( 'CFMB\Core\Activator', 'activate' ) );

CFMB\Core\Plugin::instance()->boot();
