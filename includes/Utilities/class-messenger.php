<?php
namespace VRSP\Utilities;

use VRSP\Integrations\SmsGateway;
use VRSP\Settings;

use function __;
use function absint;
use function add_action;
use function date_i18n;
use function get_bloginfo;
use function get_option;
use function get_post;
use function get_post_meta;
use function get_post_status;
use function get_the_title;
use function home_url;
use function is_email;
use function number_format_i18n;
use function sanitize_email;
use function trim;
use function wp_mail;
use function wp_strip_all_tags;

class Messenger {
    private $settings;
    private $sms;
    private $logger;

    public function __construct( Settings $settings, SmsGateway $sms, Logger $logger ) {
        $this->settings = $settings;
        $this->sms      = $sms;
        $this->logger   = $logger;

        add_action( 'vrsp_booking_payment_received', [ $this, 'handle_payment' ], 10, 2 );
        add_action( 'vrsp_booking_confirmed', [ $this, 'handle_confirmed' ] );
    }

    public function handle_payment( int $booking_id, string $type ): void {
        $context = $this->get_booking_context( $booking_id );
        if ( empty( $context ) ) {
            return;
        }

        $type = 'balance' === $type ? 'balance' : 'deposit';

        $subjects = [
            'deposit' => __( 'Deposit received for {{property_name}}', 'vr-single-property' ),
            'balance' => __( 'Booking paid in full for {{property_name}}', 'vr-single-property' ),
        ];

        $this->send_guest_email( $context, 'booking_' . $type, $subjects[ $type ] ?? __( 'Booking update for {{property_name}}', 'vr-single-property' ) );
        $this->send_guest_sms( $context, 'booking_' . $type );
    }

    public function handle_confirmed( int $booking_id ): void {
        $context = $this->get_booking_context( $booking_id );
        if ( empty( $context ) ) {
            return;
        }

        $subject = __( 'Booking approved for {{property_name}}', 'vr-single-property' );

        $this->send_guest_email( $context, 'booking_approved', $subject );
        $this->send_guest_sms( $context, 'booking_approved' );
        $this->send_checkin_email( $context );
    }

    private function send_guest_email( array $context, string $template_key, string $subject_template ): void {
        $email = isset( $context['email'] ) ? sanitize_email( $context['email'] ) : '';
        if ( ! $email || ! is_email( $email ) ) {
            return;
        }

        $template = $this->settings->get_email_template( $template_key );
        if ( '' === trim( wp_strip_all_tags( $template ) ) ) {
            return;
        }

        $body     = $this->replace_tokens( $template, $context );
        $subject  = $this->replace_tokens( $subject_template, $context );
        $headers  = [ 'Content-Type: text/html; charset=UTF-8' ];
        $sent     = wp_mail( $email, $subject, $body, $headers );

        if ( $sent ) {
            $this->logger->info( 'Guest email sent.', [ 'booking_id' => $context['booking_id'], 'template' => $template_key ] );
        } else {
            $this->logger->warning( 'Failed to send guest email.', [ 'booking_id' => $context['booking_id'], 'template' => $template_key ] );
        }
    }

    private function send_guest_sms( array $context, string $template_key ): void {
        $phone = $context['phone'] ?? '';
        if ( '' === trim( $phone ) ) {
            return;
        }

        $template = $this->settings->get_sms_template( $template_key );
        if ( '' === trim( $template ) ) {
            return;
        }

        $message = $this->replace_tokens( $template, $context );
        if ( '' === trim( $message ) ) {
            return;
        }

        $this->sms->send_message( $phone, $message );
        $this->logger->info( 'Guest SMS sent.', [ 'booking_id' => $context['booking_id'], 'template' => $template_key ] );
    }

    private function send_checkin_email( array $context ): void {
        $email = $this->settings->get_checkin_email();
        $email = sanitize_email( $email );
        if ( ! $email || ! is_email( $email ) ) {
            return;
        }

        $template = $this->settings->get_email_template( 'checkin_approved' );
        if ( '' === trim( wp_strip_all_tags( $template ) ) ) {
            return;
        }

        $subject = __( 'Approved booking details for {{property_name}}', 'vr-single-property' );
        $body    = $this->replace_tokens( $template, $context );
        $subject = $this->replace_tokens( $subject, $context );

        $sent = wp_mail( $email, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );

        if ( $sent ) {
            $this->logger->info( 'Check-in email sent.', [ 'booking_id' => $context['booking_id'] ] );
        } else {
            $this->logger->warning( 'Failed to send check-in email.', [ 'booking_id' => $context['booking_id'] ] );
        }
    }

    private function replace_tokens( string $content, array $context ): string {
        $replacements = [];
        foreach ( $context as $key => $value ) {
            if ( is_scalar( $value ) ) {
                $replacements[ '{{' . $key . '}}' ] = (string) $value;
            }
        }

        return strtr( $content, $replacements );
    }

    private function get_booking_context( int $booking_id ): array {
        $post = get_post( $booking_id );
        if ( ! $post || 'vrsp_booking' !== $post->post_type ) {
            return [];
        }

        $arrival_raw   = (string) get_post_meta( $booking_id, '_vrsp_arrival', true );
        $departure_raw = (string) get_post_meta( $booking_id, '_vrsp_departure', true );
        $date_format   = get_option( 'date_format', 'F j, Y' );

        $arrival   = $arrival_raw ? date_i18n( $date_format, strtotime( $arrival_raw ) ) : '';
        $departure = $departure_raw ? date_i18n( $date_format, strtotime( $departure_raw ) ) : '';

        $quote    = (array) get_post_meta( $booking_id, '_vrsp_quote', true );
        $currency = $quote['currency'] ?? $this->settings->get( 'currency', 'USD' );

        $deposit_amount = isset( $quote['deposit'] ) ? (float) $quote['deposit'] : (float) get_post_meta( $booking_id, '_vrsp_deposit_amount', true );
        $balance_amount = isset( $quote['balance'] ) ? (float) $quote['balance'] : (float) get_post_meta( $booking_id, '_vrsp_balance_amount', true );
        $total_amount   = isset( $quote['total'] ) ? (float) $quote['total'] : $deposit_amount + $balance_amount;

        $first_name = (string) get_post_meta( $booking_id, '_vrsp_first_name', true );
        $last_name  = (string) get_post_meta( $booking_id, '_vrsp_last_name', true );

        if ( '' === $first_name || '' === $last_name ) {
            $title = get_the_title( $booking_id );
            if ( $title ) {
                $title = trim( preg_replace( '/\s*\(.*/', '', $title ) );
                $parts = preg_split( '/\s+/', $title );
                if ( '' === $first_name && ! empty( $parts ) ) {
                    $first_name = array_shift( $parts );
                }
                if ( '' === $last_name && ! empty( $parts ) ) {
                    $last_name = implode( ' ', $parts );
                }
            }
        }

        $guest_name = trim( $first_name . ' ' . $last_name );

        $balance_due_timestamp = (int) get_post_meta( $booking_id, '_vrsp_balance_due_date', true );
        $due_format            = get_option( 'date_format', 'F j, Y' );
        $balance_due           = $balance_due_timestamp ? date_i18n( $due_format, $balance_due_timestamp ) : '';

        $checkin_time  = (string) get_post_meta( $booking_id, '_vrsp_checkin_time', true );
        $checkout_time = (string) get_post_meta( $booking_id, '_vrsp_checkout_time', true );

        return [
            'booking_id'         => $booking_id,
            'arrival'            => $arrival,
            'arrival_raw'        => $arrival_raw,
            'departure'          => $departure,
            'departure_raw'      => $departure_raw,
            'balance_due_date'   => $balance_due,
            'balance_due_raw'    => $balance_due_timestamp,
            'guests'             => absint( get_post_meta( $booking_id, '_vrsp_guests', true ) ),
            'email'              => (string) get_post_meta( $booking_id, '_vrsp_email', true ),
            'phone'              => (string) get_post_meta( $booking_id, '_vrsp_phone', true ),
            'first_name'         => $first_name,
            'last_name'          => $last_name,
            'guest_name'         => $guest_name,
            'property_name'      => get_bloginfo( 'name' ),
            'site_url'           => home_url(),
            'currency'           => $currency,
            'deposit_amount'     => $this->format_currency( $deposit_amount, $currency ),
            'deposit_amount_raw' => $deposit_amount,
            'balance_amount'     => $this->format_currency( $balance_amount, $currency ),
            'balance_amount_raw' => $balance_amount,
            'total_amount'       => $this->format_currency( $total_amount, $currency ),
            'total_amount_raw'   => $total_amount,
            'status'             => get_post_status( $booking_id ),
            'checkin_time'       => $checkin_time,
            'checkout_time'      => $checkout_time,
        ];
    }

    private function format_currency( float $amount, string $currency ): string {
        $formatted = number_format_i18n( $amount, 2 );

        return sprintf( '%s %s', $currency, $formatted );
    }
}
