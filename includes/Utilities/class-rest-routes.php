<?php
namespace VRSP\Utilities;

use DateTimeImmutable;
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
use function __;

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

        try {
            $arrival_dt   = new DateTimeImmutable( $arrival );
            $departure_dt = new DateTimeImmutable( $departure );
        } catch ( \Exception $e ) {
            return new WP_REST_Response( [ 'error' => __( 'Invalid dates supplied.', 'vr-single-property' ) ], 400 );
        }

        if ( $arrival_dt >= $departure_dt ) {
            return new WP_REST_Response( [ 'error' => __( 'Departure must be after arrival.', 'vr-single-property' ) ], 400 );
        }

        if ( ! $this->ical->is_range_available( $arrival_dt, $departure_dt ) ) {
            return new WP_REST_Response( [ 'error' => __( 'Those dates are no longer available.', 'vr-single-property' ) ], 409 );
        }

        $this->pricing->track_view();

        $quote = $this->pricing->calculate_quote( $arrival_dt->format( 'Y-m-d' ), $departure_dt->format( 'Y-m-d' ), $guests, $coupon );

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

        try {
            $arrival   = new DateTimeImmutable( sanitize_text_field( $body['arrival'] ) );
            $departure = new DateTimeImmutable( sanitize_text_field( $body['departure'] ) );
        } catch ( \Exception $e ) {
            return new WP_Error( 'invalid_date', __( 'Invalid arrival or departure date.', 'vr-single-property' ), [ 'status' => 400 ] );
        }

        if ( $arrival >= $departure ) {
            return new WP_Error( 'invalid_range', __( 'Departure must be after arrival.', 'vr-single-property' ), [ 'status' => 400 ] );
        }

        if ( ! $this->ical->is_range_available( $arrival, $departure ) ) {
            return new WP_Error( 'unavailable', __( 'Those dates were just booked. Choose another range.', 'vr-single-property' ), [ 'status' => 409 ] );
        }

        $coupon_code = sanitize_text_field( $body['coupon'] ?? '' );
        $quote       = $this->pricing->calculate_quote( $arrival->format( 'Y-m-d' ), $departure->format( 'Y-m-d' ), absint( $body['guests'] ?? 1 ), $coupon_code );

        if ( $coupon_code && ( empty( $quote['coupon'] ) || ! empty( $quote['coupon_error'] ) ) ) {
            $message = ! empty( $quote['coupon_error'] ) ? $quote['coupon_error'] : __( 'Coupon code is invalid, expired, or fully redeemed.', 'vr-single-property' );

            return new WP_Error( 'invalid_coupon', $message, [ 'status' => 400 ] );
        }

        $user_id = $this->sync_guest_user(
            [
                'email'      => sanitize_email( $body['email'] ),
                'first_name' => sanitize_text_field( $body['first_name'] ),
                'last_name'  => sanitize_text_field( $body['last_name'] ),
                'phone'      => sanitize_text_field( $body['phone'] ?? '' ),
                'arrival'    => $arrival->format( 'Y-m-d' ),
                'departure'  => $departure->format( 'Y-m-d' ),
                'guests'     => absint( $body['guests'] ?? 1 ),
            ]
        );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        $response = $this->stripe->create_checkout_session(
            [
                'arrival'    => $arrival->format( 'Y-m-d' ),
                'departure'  => $departure->format( 'Y-m-d' ),
                'guests'     => absint( $body['guests'] ?? 1 ),
                'email'      => sanitize_email( $body['email'] ),
                'phone'      => sanitize_text_field( $body['phone'] ?? '' ),
                'first_name' => sanitize_text_field( $body['first_name'] ),
                'last_name'  => sanitize_text_field( $body['last_name'] ),
                'coupon'     => $coupon_code,
                'user_id'    => $user_id,
            ],
            $quote
        );

        if ( isset( $response['error'] ) ) {
            return new WP_Error( 'stripe_error', $response['error'], [ 'status' => 500 ] );
        }

        return new WP_REST_Response(
            array_merge(
                $response,
                [
                    'pending_review' => true,
                    'deposit'        => $quote['deposit'],
                    'balance'        => $quote['balance'],
                ]
            )
        );
    }

    public function availability( WP_REST_Request $request ): WP_REST_Response {
        $months = absint( $request->get_param( 'months' ) );
        if ( $months <= 0 ) {
            $months = 6;
        }
        $months = min( 12, $months );

        $timezone = wp_timezone();
        $start    = new DateTimeImmutable( 'today', $timezone );
        $end      = $start->modify( '+' . $months . ' months' );

        $blocked = $this->ical->get_availability_window( $start, $end );
        $rates   = $this->pricing->get_calendar_rates( $start, $end );

        return new WP_REST_Response(
            [
                'blocked'  => $blocked,
                'rates'    => $rates,
                'currency' => $this->settings->get( 'currency', 'USD' ),
                'updated'  => current_time( 'mysql' ),
            ]
        );
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

    private function sync_guest_user( array $data ) {
        $email = $data['email'];
        if ( ! $email ) {
            return new WP_Error( 'missing_email', __( 'Email is required.', 'vr-single-property' ), [ 'status' => 400 ] );
        }

        $user_id = email_exists( $email );

        if ( ! $user_id ) {
            $username = $this->generate_username_from_email( $email );
            $password = wp_generate_password( 14, true );
            $user_id  = wp_create_user( $username, $password, $email );

            if ( is_wp_error( $user_id ) ) {
                return new WP_Error( 'user_create_failed', __( 'Unable to register guest user.', 'vr-single-property' ), [ 'status' => 500 ] );
            }
        }

        $userdata = [
            'ID'         => (int) $user_id,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'display_name' => trim( $data['first_name'] . ' ' . $data['last_name'] ),
        ];

        $updated = wp_update_user( $userdata );

        if ( is_wp_error( $updated ) ) {
            return new WP_Error( 'user_update_failed', __( 'Unable to update guest profile.', 'vr-single-property' ), [ 'status' => 500 ] );
        }

        if ( ! empty( $data['phone'] ) ) {
            update_user_meta( $user_id, 'vrsp_phone', $data['phone'] );
        }

        update_user_meta( $user_id, 'vrsp_last_arrival', $data['arrival'] );
        update_user_meta( $user_id, 'vrsp_last_departure', $data['departure'] );
        update_user_meta( $user_id, 'vrsp_last_guests', $data['guests'] );

        $user = get_userdata( $user_id );

        if ( $user && ! in_array( 'vrsp_guest', (array) $user->roles, true ) ) {
            $user->add_role( 'vrsp_guest' );
        }

        return $user_id;
    }

    private function generate_username_from_email( string $email ): string {
        list( $local ) = explode( '@', $email );
        $base = sanitize_user( $local, true );

        if ( empty( $base ) ) {
            $base = 'guest';
        }

        if ( ! username_exists( $base ) ) {
            return $base;
        }

        $suffix = 1;
        do {
            $candidate = $base . $suffix;
            $suffix++;
        } while ( username_exists( $candidate ) );

        return $candidate;
    }
}
