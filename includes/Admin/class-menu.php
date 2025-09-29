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
        add_action( 'admin_post_vrsp_approve_booking', [ $this, 'handle_approve_booking' ] );
        add_action( 'admin_notices', [ $this, 'maybe_notice' ] );
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

        add_submenu_page(
            'vrsp-dashboard',
            __( 'Rentals', 'vr-single-property' ),
            __( 'Rentals', 'vr-single-property' ),
            'manage_options',
            'edit.php?post_type=vrsp_rental'
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
$this->settings->prime_cache( $sanitized );

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

    public function handle_approve_booking(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to approve bookings.', 'vr-single-property' ) );
        }

        $booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;

        if ( ! $booking_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=vrsp-bookings' ) );
            exit;
        }

        check_admin_referer( 'vrsp-approve-booking_' . $booking_id );

        wp_update_post(
            [
                'ID'          => $booking_id,
                'post_status' => 'publish',
            ]
        );

        update_post_meta( $booking_id, '_vrsp_admin_status', 'approved' );
        update_post_meta( $booking_id, '_vrsp_approved_by', get_current_user_id() );
        update_post_meta( $booking_id, '_vrsp_approved_at', current_time( 'mysql' ) );

        do_action( 'vrsp_booking_confirmed', $booking_id );

        wp_safe_redirect(
            add_query_arg(
                [
                    'page'        => 'vrsp-bookings',
                    'vrsp_notice' => 'approved',
                ],
                admin_url( 'admin.php' )
            )
        );
        exit;
    }

    public function maybe_notice(): void {
        if ( empty( $_GET['vrsp_notice'] ) ) {
            return;
        }

        if ( 'approved' === $_GET['vrsp_notice'] ) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Reservation approved and sent to check-in.', 'vr-single-property' ) . '</p></div>';
        }
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
