<?php
namespace VRSP\Integrations;

use DateTimeImmutable;
use VRSP\Settings;
use VRSP\Utilities\Logger;
use VRSP\Utilities\PricingEngine;

/**
 * Stripe payment integration.
 */
class StripeGateway {
private $settings;
private $logger;
private $pricing;

public function __construct( Settings $settings, Logger $logger, PricingEngine $pricing ) {
$this->settings = $settings;
$this->logger   = $logger;
$this->pricing  = $pricing;

add_action( 'init', [ $this, 'register_webhook_rewrite' ] );
}

public function register_webhook_rewrite(): void {
add_rewrite_rule( '^vrsp-stripe-webhook/?$', 'index.php?vrsp_stripe_webhook=1', 'top' );
add_rewrite_tag( '%vrsp_stripe_webhook%', '([0-1])' );
}

public function is_configured(): bool {
return (bool) $this->get_secret_key();
}

private function get_secret_key(): string {
$mode = $this->settings->get( 'stripe_mode', 'test' );
if ( 'live' === $mode ) {
return (string) $this->settings->get( 'stripe_secret_live', '' );
}

return (string) $this->settings->get( 'stripe_secret_test', '' );
}

private function get_publishable_key(): string {
$mode = $this->settings->get( 'stripe_mode', 'test' );
if ( 'live' === $mode ) {
return (string) $this->settings->get( 'stripe_publishable_live', '' );
}

return (string) $this->settings->get( 'stripe_publishable_test', '' );
}

public function get_client_settings(): array {
return [
'publishableKey' => $this->get_publishable_key(),
'mode'           => $this->settings->get( 'stripe_mode', 'test' ),
];
}

public function create_checkout_session( array $booking, array $quote ): array {
if ( ! $this->is_configured() ) {
return [ 'error' => __( 'Stripe is not configured.', 'vr-single-property' ) ];
}

$booking_id = $this->create_booking_post( $booking, $quote );

        $amount = $quote['deposit'];
        $description = sprintf( __( 'Deposit for stay %s - %s', 'vr-single-property' ), $booking['arrival'], $booking['departure'] );

$payload = [
'mode'                 => 'payment',
'success_url'          => add_query_arg( [ 'vrsp_payment' => 'success', 'booking' => $booking_id ], home_url() ),
'cancel_url'           => add_query_arg( [ 'vrsp_payment' => 'cancel', 'booking' => $booking_id ], home_url() ),
'customer_email'       => $booking['email'],
'line_items'           => [
[
'quantity'   => 1,
'price_data' => [
'currency'     => strtolower( $quote['currency'] ),
'unit_amount'  => (int) round( $amount * 100 ),
'product_data' => [
'name' => get_bloginfo( 'name' ) . ' ' . __( 'Vacation Rental', 'vr-single-property' ),
],
],
],
],
'payment_intent_data' => [
'setup_future_usage' => 'off_session',
'metadata'           => [ 'booking_id' => $booking_id ],
],
];

if ( $this->settings->get( 'stripe_tax_enabled', false ) ) {
$payload['automatic_tax'] = [ 'enabled' => true ];
}

$response = $this->request( 'POST', '/v1/checkout/sessions', $payload );

if ( isset( $response['error'] ) ) {
$this->logger->error( 'Failed to create Stripe checkout session.', $response );
return [ 'error' => __( 'Unable to start checkout. Please try again.', 'vr-single-property' ) ];
}

update_post_meta( $booking_id, '_vrsp_stripe_session_id', $response['id'] ?? '' );
update_post_meta( $booking_id, '_vrsp_deposit_amount', $quote['deposit'] );
update_post_meta( $booking_id, '_vrsp_balance_amount', $quote['balance'] );
update_post_meta( $booking_id, '_vrsp_balance_due_date', $this->get_balance_due_timestamp( $booking['arrival'] ) );

return [
'checkout_url' => $response['url'] ?? '',
'session_id'   => $response['id'] ?? '',
'booking_id'   => $booking_id,
];
}

public function handle_webhook( string $payload, string $signature ): bool {
if ( ! $this->verify_signature( $payload, $signature ) ) {
$this->logger->warning( 'Stripe webhook signature invalid.' );
return false;
}

$event = json_decode( $payload, true );
if ( ! is_array( $event ) ) {
return false;
}

switch ( $event['type'] ?? '' ) {
case 'checkout.session.completed':
$this->handle_session_completed( $event['data']['object'] ?? [] );
break;
case 'payment_intent.succeeded':
$this->handle_payment_succeeded( $event['data']['object'] ?? [] );
break;
case 'payment_intent.payment_failed':
$this->handle_payment_failed( $event['data']['object'] ?? [] );
break;
}

return true;
}

public function process_balance_charges(): void {
$bookings = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => 'publish',
'posts_per_page' => 50,
'orderby'        => 'meta_value_num',
'order'          => 'ASC',
'meta_query'     => [
[
'key'     => '_vrsp_balance_due_date',
'value'   => time(),
'compare' => '<=',
'type'    => 'NUMERIC',
],
[
'key'     => '_vrsp_balance_paid',
'compare' => 'NOT EXISTS',
],
],
]
);

foreach ( $bookings as $booking ) {
$this->charge_booking_balance( $booking->ID );
}
}

private function charge_booking_balance( int $booking_id ): void {
$amount = (float) get_post_meta( $booking_id, '_vrsp_balance_amount', true );
if ( $amount <= 0 ) {
return;
}

$customer       = get_post_meta( $booking_id, '_vrsp_stripe_customer', true );
$payment_method = get_post_meta( $booking_id, '_vrsp_payment_method', true );

if ( ! $customer || ! $payment_method ) {
$this->logger->warning( 'Booking missing customer or payment method for balance charge.', [ 'booking_id' => $booking_id ] );
return;
}

$response = $this->request(
'POST',
'/v1/payment_intents',
[
'amount'         => (int) round( $amount * 100 ),
'currency'       => strtolower( $this->settings->get( 'currency', 'USD' ) ),
'customer'       => $customer,
'payment_method' => $payment_method,
'off_session'    => true,
'confirm'        => true,
'metadata'       => [ 'booking_id' => $booking_id, 'type' => 'balance' ],
]
);

if ( isset( $response['status'] ) && 'succeeded' === $response['status'] ) {
    $was_balance_paid = (bool) get_post_meta( $booking_id, '_vrsp_balance_paid', true );
    update_post_meta( $booking_id, '_vrsp_balance_paid', 1 );
    $this->logger->info( 'Balance payment succeeded.', [ 'booking_id' => $booking_id ] );

    if ( ! $was_balance_paid ) {
        do_action( 'vrsp_booking_payment_received', $booking_id, 'balance' );
    }
} elseif ( isset( $response['error'] ) ) {
$this->logger->error( 'Balance payment failed.', $response );
}
}

    private function handle_session_completed( array $session ): void {
        $session_id = $session['id'] ?? '';
        if ( ! $session_id ) {
            return;
        }

        $booking = $this->get_booking_by_meta( '_vrsp_stripe_session_id', $session_id );
        if ( ! $booking ) {
            return;
        }

        $was_deposit_paid = (bool) get_post_meta( $booking->ID, '_vrsp_deposit_paid', true );
        update_post_meta( $booking->ID, '_vrsp_deposit_paid', 1 );
        if ( isset( $session['customer'] ) ) {
            update_post_meta( $booking->ID, '_vrsp_stripe_customer', sanitize_text_field( $session['customer'] ) );
        }

        if ( isset( $session['payment_intent'] ) ) {
            update_post_meta( $booking->ID, '_vrsp_stripe_payment_intent', sanitize_text_field( $session['payment_intent'] ) );
        }

        if ( isset( $session['setup_intent'] ) ) {
            $setup = $this->request( 'GET', '/v1/setup_intents/' . $session['setup_intent'] );
            if ( isset( $setup['payment_method'] ) ) {
                update_post_meta( $booking->ID, '_vrsp_payment_method', sanitize_text_field( $setup['payment_method'] ) );
            }
        }

        update_post_meta( $booking->ID, '_vrsp_admin_status', 'pending_admin' );

        wp_update_post(
            [
                'ID'          => $booking->ID,
                'post_status' => 'pending',
            ]
        );

        $this->logger->info( 'Stripe checkout completed.', [ 'booking_id' => $booking->ID ] );

        if ( ! $was_deposit_paid ) {
            do_action( 'vrsp_booking_payment_received', $booking->ID, 'deposit' );
        }

        do_action( 'vrsp_booking_pending_admin', $booking->ID );
    }

private function handle_payment_succeeded( array $intent ): void {
$booking_id = $intent['metadata']['booking_id'] ?? 0;
if ( ! $booking_id ) {
return;
}

if ( ( $intent['metadata']['type'] ?? '' ) === 'balance' ) {
    $was_balance_paid = (bool) get_post_meta( $booking_id, '_vrsp_balance_paid', true );
    update_post_meta( $booking_id, '_vrsp_balance_paid', 1 );

    if ( ! $was_balance_paid ) {
        do_action( 'vrsp_booking_payment_received', $booking_id, 'balance' );
    }
}

$this->logger->info( 'Stripe payment succeeded.', [ 'booking_id' => $booking_id ] );
}

private function handle_payment_failed( array $intent ): void {
$booking_id = $intent['metadata']['booking_id'] ?? 0;
if ( $booking_id ) {
$this->logger->error( 'Stripe payment failed.', [ 'booking_id' => $booking_id, 'intent' => $intent['id'] ?? '' ] );
}
}

    private function create_booking_post( array $booking, array $quote ): int {
        $post_id = wp_insert_post(
            [
                'post_type'   => 'vrsp_booking',
                'post_title'  => sprintf( '%s %s (%s)', $booking['first_name'], $booking['last_name'], $booking['arrival'] ),
                'post_status' => 'draft',
                'post_author' => isset( $booking['user_id'] ) ? (int) $booking['user_id'] : 0,
            ]
        );

        update_post_meta( $post_id, '_vrsp_arrival', $booking['arrival'] );
        update_post_meta( $post_id, '_vrsp_departure', $booking['departure'] );
        update_post_meta( $post_id, '_vrsp_guests', (int) $booking['guests'] );
        update_post_meta( $post_id, '_vrsp_email', sanitize_email( $booking['email'] ) );
        update_post_meta( $post_id, '_vrsp_phone', sanitize_text_field( $booking['phone'] ) );
        update_post_meta( $post_id, '_vrsp_first_name', sanitize_text_field( $booking['first_name'] ?? '' ) );
        update_post_meta( $post_id, '_vrsp_last_name', sanitize_text_field( $booking['last_name'] ?? '' ) );
        update_post_meta( $post_id, '_vrsp_checkin_time', $this->settings->get_business_rules()['checkin_time'] ?? '16:00' );
        update_post_meta( $post_id, '_vrsp_checkout_time', $this->settings->get_business_rules()['checkout_time'] ?? '11:00' );
        update_post_meta( $post_id, '_vrsp_quote', $quote );
        update_post_meta( $post_id, '_vrsp_coupon_code', $booking['coupon'] ?? '' );
        update_post_meta( $post_id, '_vrsp_user_id', isset( $booking['user_id'] ) ? (int) $booking['user_id'] : 0 );
        update_post_meta( $post_id, '_vrsp_admin_status', 'initiated' );

        $this->pricing->persist_booking_meta( $post_id, $quote );
        $this->pricing->mark_uplift_applied();

        return $post_id;
}

private function get_balance_due_timestamp( string $arrival ): int {
try {
$arrival_dt = new DateTimeImmutable( $arrival );
$due        = $arrival_dt->modify( '-7 days' );
$timestamp  = $due->getTimestamp();
if ( $timestamp < time() ) {
return time();
}

return $timestamp;
} catch ( \Exception $e ) {
return time();
}
}

private function get_booking_by_meta( string $key, string $value ) {
$posts = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => 'any',
'posts_per_page' => 1,
'meta_query'     => [
[
'key'   => $key,
'value' => $value,
],
],
]
);

return $posts ? $posts[0] : null;
}

private function verify_signature( string $payload, string $header ): bool {
$secret = $this->settings->get( 'stripe_webhook_secret', '' );
if ( ! $secret ) {
return true;
}

$parts = [];
foreach ( explode( ',', $header ) as $segment ) {
[ $k, $v ] = array_pad( explode( '=', trim( $segment ) ), 2, '' );
$parts[ $k ] = $v;
}

if ( empty( $parts['t'] ) || empty( $parts['v1'] ) ) {
return false;
}

$expected = hash_hmac( 'sha256', $parts['t'] . '.' . $payload, $secret );

return hash_equals( $expected, $parts['v1'] );
}

private function request( string $method, string $path, array $body = [] ): array {
$secret = $this->get_secret_key();
if ( ! $secret ) {
return [ 'error' => __( 'Missing Stripe secret key.', 'vr-single-property' ) ];
}

$args = [
'headers' => [
'Authorization' => 'Bearer ' . $secret,
],
'timeout' => 20,
'method'  => strtoupper( $method ),
];

if ( 'GET' === $args['method'] ) {
$url = 'https://api.stripe.com' . $path . ( $body ? '?' . http_build_query( $body ) : '' );
} else {
$args['body'] = $body;
$url         = 'https://api.stripe.com' . $path;
}

$response = wp_remote_request( $url, $args );
if ( is_wp_error( $response ) ) {
return [ 'error' => $response->get_error_message() ];
}

$data = json_decode( wp_remote_retrieve_body( $response ), true );
if ( null === $data ) {
return [ 'error' => __( 'Invalid response from Stripe.', 'vr-single-property' ) ];
}

return $data;
}
}
