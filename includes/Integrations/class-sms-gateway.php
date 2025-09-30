<?php
namespace VRSP\Integrations;

use VRSP\Settings;
use VRSP\Utilities\Logger;

/**
 * voip.ms SMS integration.
 */
class SmsGateway {
private $settings;
private $logger;

    public function __construct( Settings $settings, Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;

        add_action( 'vrsp_booking_pending_admin', [ $this, 'notify_admin_pending' ] );
    }

public function send_housekeeping_checkout( int $booking_id ): void {
$number = $this->settings->get( 'sms_housekeeper_number', '' );
if ( ! $number ) {
return;
}

$message = sprintf(
/* translators: %1$s arrival date, %2$s departure date */
esc_html__( 'Guest departing %1$s. Next arrival %2$s. Reply 1 when check-in app should contact guest, reply 2 for issue.', 'vr-single-property' ),
get_post_meta( $booking_id, '_vrsp_departure', true ),
get_post_meta( $booking_id, '_vrsp_arrival', true )
);

$this->send_sms( $number, $message );
}

public function send_housekeeping_followup( int $booking_id ): void {
$number = $this->settings->get( 'sms_owner_number', '' );
if ( ! $number ) {
return;
}

$message = sprintf(
esc_html__( 'Housekeeping follow-up needed for booking #%d.', 'vr-single-property' ),
$booking_id
);

$this->send_sms( $number, $message );
}

    public function handle_inbound( array $payload ): void {
        if ( ! $this->verify_signature( $payload ) ) {
            $this->logger->warning( 'Invalid SMS webhook signature.' );
            return;
        }

$message = strtoupper( trim( $payload['message'] ?? '' ) );
$from    = sanitize_text_field( $payload['from'] ?? '' );
$booking_id = absint( $payload['booking'] ?? 0 );

        if ( '1' === $message ) {
            do_action( 'vrsp_sms_housekeeping_ready', $booking_id, $from );
        } elseif ( '2' === $message ) {
            do_action( 'vrsp_sms_housekeeping_issue', $booking_id, $from );
        }
    }

    public function notify_admin_pending( int $booking_id ): void {
        $number = $this->settings->get( 'sms_owner_number', '' );
        if ( ! $number ) {
            return;
        }

        $arrival   = get_post_meta( $booking_id, '_vrsp_arrival', true );
        $departure = get_post_meta( $booking_id, '_vrsp_departure', true );
        $guest     = get_the_title( $booking_id );

        $message = sprintf(
            /* translators: 1: guest name, 2: arrival date, 3: departure date */
            __( 'New booking from %1$s awaiting approval (%2$s → %3$s).', 'vr-single-property' ),
            $guest,
            $arrival,
            $departure
        );

        $this->send_sms( $number, $message );
    }

    public function send_message( string $to, string $message ): void {
        $to      = trim( preg_replace( '/[^\d+]/', '', $to ) );
        $message = trim( $message );

        if ( '' === $to || '' === $message ) {
            return;
        }

        $this->send_sms( $to, $message );
    }

    private function send_sms( string $to, string $message ): void {
        $username = $this->settings->get( 'sms_api_username', '' );
        $password = $this->settings->get( 'sms_api_password', '' );

if ( ! $username || ! $password ) {
$this->logger->warning( 'SMS credentials missing.' );
return;
}

$args = [
'body'    => [
'api_username' => $username,
'api_password' => $password,
'did'          => $username,
'destination'  => $to,
'message'      => $message,
],
'timeout' => 15,
];

$response = wp_remote_post( 'https://voip.ms/api/v1/rest.php?method=sendsms', $args );

if ( is_wp_error( $response ) ) {
$this->logger->error( 'Failed to send SMS.', [ 'error' => $response->get_error_message() ] );
return;
}

$this->logger->info( 'SMS sent.', [ 'to' => $to ] );
}

private function verify_signature( array $payload ): bool {
$secret = $this->settings->get( 'sms_api_password', '' );
$signature = $payload['signature'] ?? '';
if ( ! $secret || ! $signature ) {
return true;
}

$expected = hash_hmac( 'sha256', wp_json_encode( $payload['data'] ?? [] ), $secret );

return hash_equals( $expected, $signature );
}
}
