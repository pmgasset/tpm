<?php
namespace VRSP\Admin;

use VRSP\Integrations\IcalSync;
use VRSP\Integrations\SmsGateway;
use VRSP\Integrations\StripeGateway;
use VRSP\Rules\BusinessRules;
use VRSP\Settings;
use VRSP\Utilities\Logger;
use VRSP\Utilities\PricingEngine;

/**
 * Admin menu and settings pages.
 */
class Menu {
private $settings;
private $logger;
private $pricing;
private $rules;
private $stripe;
private $ical;
private $sms;
private $views_dir;

public function __construct( Settings $settings, Logger $logger, PricingEngine $pricing, BusinessRules $rules, StripeGateway $stripe, IcalSync $ical, SmsGateway $sms ) {
$this->settings = $settings;
$this->logger   = $logger;
$this->pricing  = $pricing;
$this->rules    = $rules;
$this->stripe   = $stripe;
$this->ical     = $ical;
$this->sms      = $sms;
$this->views_dir = rtrim( VRSP_PLUGIN_DIR, '/\\' ) . '/admin/views/';

add_action( 'admin_menu', [ $this, 'register_menu' ] );
add_action( 'admin_init', [ $this, 'register_settings' ] );
add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
}

public function register_menu(): void {
add_menu_page(
__( 'VR Rental', 'vr-single-property' ),
__( 'VR Rental', 'vr-single-property' ),
'manage_options',
'vrsp-dashboard',
[ $this, 'render_dashboard' ],
'dashicons-admin-multisite',
30
);

add_submenu_page( 'vrsp-dashboard', __( 'Settings', 'vr-single-property' ), __( 'Settings', 'vr-single-property' ), 'manage_options', 'vrsp-settings', [ $this, 'render_settings_page' ] );
add_submenu_page( 'vrsp-dashboard', __( 'Bookings', 'vr-single-property' ), __( 'Bookings', 'vr-single-property' ), 'manage_options', 'vrsp-bookings', [ $this, 'render_bookings_page' ] );
add_submenu_page( 'vrsp-dashboard', __( 'Logs', 'vr-single-property' ), __( 'Logs', 'vr-single-property' ), 'manage_options', 'vrsp-logs', [ $this, 'render_logs_page' ] );
}

public function register_settings(): void {
register_setting( 'vrsp_settings', Settings::OPTION_KEY, [ $this, 'sanitize_settings' ] );
}

public function sanitize_settings( $value ) {
if ( ! current_user_can( 'manage_options' ) ) {
return $this->settings->get_defaults();
}

$sanitized = $this->settings->sanitize( (array) $value );
update_option( Settings::OPTION_KEY, $sanitized );
$this->settings->refresh();
return $sanitized;
}

public function enqueue_assets( string $hook ): void {
if ( false === strpos( $hook, 'vrsp' ) ) {
return;
}

wp_enqueue_style( 'vrsp-admin', VRSP_PLUGIN_URL . 'admin/css/admin.css', [], VRSP_VERSION );
}

public function render_dashboard(): void {
$this->render_view( 'dashboard' );
}

public function render_settings_page(): void {
$this->render_view(
 'settings',
 [
 'settings' => $this->settings,
 ]
);
}

public function render_bookings_page(): void {
$bookings = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => 'any',
'posts_per_page' => 50,
'orderby'        => 'date',
'order'          => 'DESC',
]
);
$this->render_view(
 'bookings',
 [
 'bookings' => $bookings,
 ]
);
}

public function render_logs_page(): void {
$this->render_view(
 'logs',
 [
 'logs' => $this->logger->get_logs(),
 ]
);
}

private function render_view( string $view, array $context = [] ): void {
$file = $this->views_dir . $view . '.php';

if ( ! file_exists( $file ) ) {
$this->logger->error(
 sprintf(
 'Admin view missing: %s',
 $view
 ),
 [
 'file' => $file,
 ]
);

if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
trigger_error( sprintf( 'VR Single Property admin view missing: %s', $file ), E_USER_WARNING );
}

return;
}

if ( ! empty( $context ) ) {
extract( $context, EXTR_SKIP );
}

include $file;
}
}
