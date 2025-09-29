<?php
namespace VRSP\Utilities;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use VRSP\Integrations\CheckinApp;
use VRSP\Integrations\IcalSync;
use VRSP\Integrations\SmsGateway;
use VRSP\Integrations\StripeGateway;
use VRSP\Rules\BusinessRules;
use VRSP\Settings;

/**
 * Registers REST API routes.
 */
class RestRoutes {
private $settings;
private $pricing;
private $stripe;
private $ical;
private $sms;
private $checkin;
private $rules;
private $logger;

public function __construct( Settings $settings, PricingEngine $pricing, StripeGateway $stripe, IcalSync $ical, SmsGateway $sms, CheckinApp $checkin, BusinessRules $rules, Logger $logger ) {
$this->settings = $settings;
$this->pricing  = $pricing;
$this->stripe   = $stripe;
$this->ical     = $ical;
$this->sms      = $sms;
$this->checkin  = $checkin;
$this->rules    = $rules;
$this->logger   = $logger;

add_action( 'rest_api_init', [ $this, 'register_routes' ] );
}

public function register_routes(): void {
register_rest_route(
'vr/v1',
'/quote',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'quote' ],
'permission_callback' => '__return_true',
]
);

register_rest_route(
'vr/v1',
'/booking',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'create_booking' ],
'permission_callback' => '__return_true',
]
);

register_rest_route(
'vr/v1',
'/availability',
[
'methods'             => WP_REST_Server::READABLE,
'callback'            => [ $this, 'availability' ],
'permission_callback' => '__return_true',
]
);

register_rest_route(
'vr/v1',
'/stripe/webhook',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'stripe_webhook' ],
'permission_callback' => '__return_true',
]
);

register_rest_route(
'vr/v1',
'/sms/webhook',
[
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => [ $this, 'sms_webhook' ],
'permission_callback' => '__return_true',
]
);
}

public function quote( WP_REST_Request $request ): WP_REST_Response {
$body = $request->get_json_params();
$arrival   = sanitize_text_field( $body['arrival'] ?? '' );
$departure = sanitize_text_field( $body['departure'] ?? '' );
$guests    = absint( $body['guests'] ?? 1 );
$coupon    = sanitize_text_field( $body['coupon'] ?? '' );

if ( ! $arrival || ! $departure ) {
return new WP_REST_Response( [ 'error' => __( 'Arrival and departure required.', 'vr-single-property' ) ], 400 );
}

$this->pricing->track_view();

$quote = $this->pricing->calculate_quote( $arrival, $departure, $guests, $coupon );

return new WP_REST_Response( $quote );
}

public function create_booking( WP_REST_Request $request ) {
$body = $request->get_json_params();

$required = [ 'arrival', 'departure', 'email', 'first_name', 'last_name' ];
foreach ( $required as $field ) {
if ( empty( $body[ $field ] ) ) {
return new WP_Error( 'missing_field', sprintf( __( 'Field %s is required.', 'vr-single-property' ), $field ), [ 'status' => 400 ] );
}
}

$quote = $this->pricing->calculate_quote( sanitize_text_field( $body['arrival'] ), sanitize_text_field( $body['departure'] ), absint( $body['guests'] ?? 1 ), sanitize_text_field( $body['coupon'] ?? '' ) );

$response = $this->stripe->create_checkout_session(
[
'arrival'    => sanitize_text_field( $body['arrival'] ),
'departure'  => sanitize_text_field( $body['departure'] ),
'guests'     => absint( $body['guests'] ?? 1 ),
'email'      => sanitize_email( $body['email'] ),
'phone'      => sanitize_text_field( $body['phone'] ?? '' ),
'first_name' => sanitize_text_field( $body['first_name'] ),
'last_name'  => sanitize_text_field( $body['last_name'] ),
'coupon'     => sanitize_text_field( $body['coupon'] ?? '' ),
],
$quote
);

if ( isset( $response['error'] ) ) {
return new WP_Error( 'stripe_error', $response['error'], [ 'status' => 500 ] );
}

return new WP_REST_Response( $response );
}

public function availability(): WP_REST_Response {
$events = [];
$bookings = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => [ 'publish', 'draft' ],
'posts_per_page' => 200,
]
);

foreach ( $bookings as $booking ) {
$events[] = [
'arrival'   => get_post_meta( $booking->ID, '_vrsp_arrival', true ),
'departure' => get_post_meta( $booking->ID, '_vrsp_departure', true ),
'guests'    => get_post_meta( $booking->ID, '_vrsp_guests', true ),
];
}

return new WP_REST_Response( $events );
}

public function stripe_webhook( WP_REST_Request $request ) {
$ok = $this->stripe->handle_webhook( $request->get_body(), $request->get_header( 'Stripe-Signature' ) );
return new WP_REST_Response( [ 'received' => (bool) $ok ] );
}

public function sms_webhook( WP_REST_Request $request ): WP_REST_Response {
$payload = $request->get_json_params();
$this->sms->handle_inbound( $payload );
return new WP_REST_Response( [ 'received' => true ] );
}
}
