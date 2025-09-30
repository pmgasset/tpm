<?php
/**
 * Plugin Name:       VR Single Property
 * Plugin URI:        https://example.com/vr-single-property
 * Description:       Single-property vacation rental system with booking, payments, dynamic pricing, and operations automations.
 * Version:           0.1.7
 * Author:            VR Single Property
 * Author URI:        https://example.com
 * Text Domain:       vr-single-property
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
add_action( 'admin_notices', static function () {
echo '<div class="notice notice-error"><p>' . esc_html__( 'VR Single Property requires PHP 7.4 or newer.', 'vr-single-property' ) . '</p></div>';
} );
return;
}

define( 'VRSP_VERSION', '0.1.7' );
define( 'VRSP_PLUGIN_FILE', __FILE__ );
define( 'VRSP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VRSP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once VRSP_PLUGIN_DIR . 'includes/helpers-rental.php';

spl_autoload_register(
static function ( $class ) {
if ( 0 !== strpos( $class, 'VRSP\\' ) ) {
return;
}

$relative = substr( $class, strlen( 'VRSP\\' ) );
$parts    = array_filter( explode( '\\', $relative ) );

if ( empty( $parts ) ) {
return;
}

$filename = array_pop( $parts );
$path     = VRSP_PLUGIN_DIR . 'includes/';

if ( ! empty( $parts ) ) {
$path .= implode( '/', $parts ) . '/';
}

$filename = strtolower( preg_replace( '/([a-z0-9])([A-Z])/', '$1-$2', $filename ) );
$filename = str_replace( '_', '-', $filename );
$file     = $path . 'class-' . $filename . '.php';

if ( file_exists( $file ) ) {
require_once $file;
}
}
);

add_action(
'plugins_loaded',
static function () {
if ( class_exists( '\\VRSP\\Plugin' ) ) {
\VRSP\Plugin::get_instance();
}
}
);

register_activation_hook( __FILE__, [ '\\VRSP\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ '\\VRSP\\Plugin', 'deactivate' ] );
