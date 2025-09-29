<?php
namespace VRSP\Integrations;

use VRSP\Settings;
use VRSP\Utilities\Logger;

/**
 * Sends booking updates to external check-in app.
 */
class CheckinApp {
private $settings;
private $logger;

public function __construct( Settings $settings, Logger $logger ) {
$this->settings = $settings;
$this->logger   = $logger;

        add_action( 'vrsp_sms_housekeeping_ready', [ $this, 'notify_ready' ], 10, 2 );
        add_action( 'vrsp_sms_housekeeping_issue', [ $this, 'notify_issue' ], 10, 2 );
        add_action( 'vrsp_booking_pending_admin', [ $this, 'push_pending' ] );
        add_action( 'vrsp_booking_confirmed', [ $this, 'push_booking' ] );
    }

    public function push_booking( int $booking_id ): void {
        $data = $this->build_payload( $booking_id );
        $this->dispatch( 'booking', $data );
    }

    public function push_pending( int $booking_id ): void {
        $data = $this->build_payload( $booking_id );
        $this->dispatch( 'pending', $data );
    }

public function notify_ready( int $booking_id ): void {
$this->dispatch( 'housekeeping_ready', $this->build_payload( $booking_id ) );
}

public function notify_issue( int $booking_id ): void {
$this->dispatch( 'housekeeping_issue', $this->build_payload( $booking_id ) );
}

public function retry( string $type, array $payload ): void {
$this->dispatch( $type, $payload );
}

    private function build_payload( int $booking_id ): array {
        $quote = (array) get_post_meta( $booking_id, '_vrsp_quote', true );
        return [
            'booking_id' => $booking_id,
            'arrival'    => get_post_meta( $booking_id, '_vrsp_arrival', true ),
            'departure'  => get_post_meta( $booking_id, '_vrsp_departure', true ),
            'guests'     => get_post_meta( $booking_id, '_vrsp_guests', true ),
            'contact'    => [
                'email' => get_post_meta( $booking_id, '_vrsp_email', true ),
                'phone' => get_post_meta( $booking_id, '_vrsp_phone', true ),
            ],
            'status'     => get_post_status( $booking_id ),
            'total'      => isset( $quote['total'] ) ? (float) $quote['total'] : 0.0,
            'currency'   => $quote['currency'] ?? 'USD',
        ];
    }

private function dispatch( string $type, array $payload ): void {
$endpoint = $this->settings->get( 'checkin_endpoint', '' );
if ( ! $endpoint ) {
return;
}

$body = [
'type'    => $type,
'payload' => $payload,
];

$response = wp_remote_post(
$endpoint,
[
'headers' => [ 'Content-Type' => 'application/json' ],
'body'    => wp_json_encode( $body ),
'timeout' => 15,
]
);

if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) >= 400 ) {
$this->logger->error( 'Failed to push update to check-in app.', [ 'type' => $type, 'response' => $response ] );
wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'vrsp_retry_checkin', [ $type, $payload ] );
return;
}

$this->logger->info( 'Pushed update to check-in app.', [ 'type' => $type ] );
}
}
