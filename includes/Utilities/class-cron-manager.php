<?php
namespace VRSP\Utilities;

use function __;
use function did_action;

use DateTimeImmutable;
use VRSP\Integrations\CheckinApp;
use VRSP\Integrations\IcalSync;
use VRSP\Integrations\SmsGateway;
use VRSP\Integrations\StripeGateway;
use VRSP\Settings;

/**
 * Manages scheduled events.
 */
class CronManager {
private $settings;
private $logger;
private $stripe;
private $ical;
private $sms;
private $checkin;

public function __construct( Settings $settings, Logger $logger, StripeGateway $stripe, IcalSync $ical, SmsGateway $sms, CheckinApp $checkin ) {
$this->settings = $settings;
$this->logger   = $logger;
$this->stripe   = $stripe;
$this->ical     = $ical;
$this->sms      = $sms;
$this->checkin  = $checkin;

add_filter( 'cron_schedules', [ $this, 'register_schedules' ] );

add_action( 'vrsp_ical_sync', [ $this, 'run_ical_sync' ] );
add_action( 'vrsp_charge_balances', [ $this, 'run_balance_charges' ] );
add_action( 'vrsp_housekeeping_notify', [ $this, 'run_housekeeping_notifications' ] );
add_action( 'vrsp_housekeeping_followup', [ $this, 'notify_housekeeping' ], 10, 2 );
add_action( 'vrsp_retry_checkin', [ $this, 'retry_checkin_dispatch' ], 10, 2 );

add_action( 'init', [ $this, 'ensure_events' ] );
}

public static function activate(): void {
wp_clear_scheduled_hook( 'vrsp_ical_sync' );
wp_clear_scheduled_hook( 'vrsp_charge_balances' );
wp_clear_scheduled_hook( 'vrsp_housekeeping_notify' );

if ( ! wp_next_scheduled( 'vrsp_ical_sync' ) ) {
wp_schedule_event( time(), 'vrsp_quarter_hour', 'vrsp_ical_sync' );
}

if ( ! wp_next_scheduled( 'vrsp_charge_balances' ) ) {
wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'vrsp_charge_balances' );
}

if ( ! wp_next_scheduled( 'vrsp_housekeeping_notify' ) ) {
wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', 'vrsp_housekeeping_notify' );
}
}

public static function deactivate(): void {
wp_clear_scheduled_hook( 'vrsp_ical_sync' );
wp_clear_scheduled_hook( 'vrsp_charge_balances' );
wp_clear_scheduled_hook( 'vrsp_housekeeping_notify' );
}

public function register_schedules( array $schedules ): array {
        $display = 'Every 15 Minutes';

        if ( did_action( 'init' ) ) {
            $display = __( 'Every 15 Minutes', 'vr-single-property' );
        }

        $schedules['vrsp_quarter_hour'] = [
            'interval' => 15 * MINUTE_IN_SECONDS,
            'display'  => $display,
        ];

return $schedules;
}

public function ensure_events(): void {
if ( ! wp_next_scheduled( 'vrsp_ical_sync' ) ) {
wp_schedule_event( time(), 'vrsp_quarter_hour', 'vrsp_ical_sync' );
}

if ( ! wp_next_scheduled( 'vrsp_charge_balances' ) ) {
wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'vrsp_charge_balances' );
}

if ( ! wp_next_scheduled( 'vrsp_housekeeping_notify' ) ) {
wp_schedule_event( strtotime( 'tomorrow 06:00' ), 'daily', 'vrsp_housekeeping_notify' );
}
}

public function run_ical_sync(): void {
$this->logger->info( 'Running iCal sync job.' );
$this->ical->sync();
}

public function run_balance_charges(): void {
$this->logger->info( 'Checking for bookings requiring balance capture.' );
$this->stripe->process_balance_charges();
}

public function run_housekeeping_notifications(): void {
$today = current_time( 'Y-m-d' );
$bookings = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => 'publish',
'posts_per_page' => 20,
'meta_query'     => [
[
'key'   => '_vrsp_departure',
'value' => $today,
],
],
]
);

foreach ( $bookings as $booking ) {
$this->logger->info( 'Sending checkout SMS to housekeeper.', [ 'booking_id' => $booking->ID ] );
$this->sms->send_housekeeping_checkout( $booking->ID );
}
}

public function notify_housekeeping( int $booking_id, string $action ): void {
if ( 'followup' === $action ) {
$this->logger->info( 'Triggering housekeeping follow-up message.', [ 'booking_id' => $booking_id ] );
$this->sms->send_housekeeping_followup( $booking_id );
}
}

public function retry_checkin_dispatch( string $type, array $payload ): void {
$this->logger->info( 'Retrying check-in app dispatch.', [ 'type' => $type ] );
$this->checkin->retry( $type, $payload );
}
}
