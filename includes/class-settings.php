<?php
namespace VRSP;

use function __;
use function did_action;
use function get_option;
use function wp_list_pluck;

/**
 * Settings repository.
 */
class Settings {
public const OPTION_KEY = 'vrsp_settings';
public const COUPON_USAGE_KEY = 'vrsp_coupon_usage';

/**
 * Cached settings.
 *
 * @var array
 */
private $settings = [];

public function __construct() {
$this->settings = wp_parse_args( get_option( self::OPTION_KEY, [] ), $this->get_defaults() );
}

/**
 * Default settings.
 */
public function get_defaults(): array {
        $translate   = did_action( 'init' );
        $house_rules = 'No smoking. No pets. Quiet hours after 10 PM.';
        $deposit_email_template = '<p>Hi {{first_name}},</p><p>Thanks for your reservation at {{property_name}}. We\'ve received your deposit of {{deposit_amount}} for {{arrival}} to {{departure}}.</p><p>We\'ll let you know when the remaining balance has been captured.</p>';
        $balance_email_template = '<p>Hi {{first_name}},</p><p>Your stay at {{property_name}} from {{arrival}} to {{departure}} is now paid in full.</p><p>We\'re excited to welcome you soon!</p>';
        $approved_email_template = '<p>Hi {{first_name}},</p><p>Your booking for {{property_name}} from {{arrival}} to {{departure}} has been approved.</p><p>We\'ll be in touch with any additional details before your arrival.</p>';
        $checkin_email_template  = '<p>New approved booking for {{property_name}}.</p><ul><li>Guest: {{guest_name}}</li><li>Arrival: {{arrival}} ({{checkin_time}})</li><li>Departure: {{departure}} ({{checkout_time}})</li><li>Guests: {{guests}}</li><li>Email: {{email}}</li><li>Phone: {{phone}}</li><li>Total: {{total_amount}}</li><li>Deposit: {{deposit_amount}}</li><li>Balance: {{balance_amount}}</li></ul>';
        $deposit_sms_template    = 'Hi {{first_name}}, we received your deposit of {{deposit_amount}} for {{property_name}} ({{arrival}}-{{departure}}). Thank you!';
        $balance_sms_template    = 'Hi {{first_name}}, your stay at {{property_name}} ({{arrival}}-{{departure}}) is fully paid. See you soon!';
        $approved_sms_template   = 'Hi {{first_name}}, your booking for {{property_name}} on {{arrival}} has been approved. We\'ll share arrival details soon.';

        if ( $translate && function_exists( '__' ) ) {
            $house_rules = __( 'No smoking. No pets. Quiet hours after 10 PM.', 'vr-single-property' );
            $deposit_email_template = __( '<p>Hi {{first_name}},</p><p>Thanks for your reservation at {{property_name}}. We\'ve received your deposit of {{deposit_amount}} for {{arrival}} to {{departure}}.</p><p>We\'ll let you know when the remaining balance has been captured.</p>', 'vr-single-property' );
            $balance_email_template = __( '<p>Hi {{first_name}},</p><p>Your stay at {{property_name}} from {{arrival}} to {{departure}} is now paid in full.</p><p>We\'re excited to welcome you soon!</p>', 'vr-single-property' );
            $approved_email_template = __( '<p>Hi {{first_name}},</p><p>Your booking for {{property_name}} from {{arrival}} to {{departure}} has been approved.</p><p>We\'ll be in touch with any additional details before your arrival.</p>', 'vr-single-property' );
            $checkin_email_template  = __( '<p>New approved booking for {{property_name}}.</p><ul><li>Guest: {{guest_name}}</li><li>Arrival: {{arrival}} ({{checkin_time}})</li><li>Departure: {{departure}} ({{checkout_time}})</li><li>Guests: {{guests}}</li><li>Email: {{email}}</li><li>Phone: {{phone}}</li><li>Total: {{total_amount}}</li><li>Deposit: {{deposit_amount}}</li><li>Balance: {{balance_amount}}</li></ul>', 'vr-single-property' );
            $deposit_sms_template    = __( 'Hi {{first_name}}, we received your deposit of {{deposit_amount}} for {{property_name}} ({{arrival}}-{{departure}}). Thank you!', 'vr-single-property' );
            $balance_sms_template    = __( 'Hi {{first_name}}, your stay at {{property_name}} ({{arrival}}-{{departure}}) is fully paid. See you soon!', 'vr-single-property' );
            $approved_sms_template   = __( 'Hi {{first_name}}, your booking for {{property_name}} on {{arrival}} has been approved. We\'ll share arrival details soon.', 'vr-single-property' );
        }

        return [
'currency'                => 'USD',
'base_rate'               => 200.0,
'tax_rate'                => 0.12,
'cleaning_fee'            => 150.0,
'damage_fee'              => 0.0,
'enable_damage_fee'       => false,
'stripe_mode'             => 'test',
'stripe_secret_test'      => '',
'stripe_publishable_test' => '',
'stripe_secret_live'      => '',
'stripe_publishable_live' => '',
'stripe_webhook_secret'   => '',
'stripe_tax_enabled'      => false,
'ical_import_urls'        => [],
'ical_export_token'       => wp_generate_password( 32, false ),
'sms_api_username'        => '',
'sms_api_password'        => '',
'sms_housekeeper_number'  => '',
'sms_owner_number'        => '',
'sms_dnt_cookie_days'     => 30,
'checkin_endpoint'        => 'https://240jordanview.com/wp-json/gms/v1/webhook',
'pricing_tiers'           => [
[ 'min' => 0, 'max' => 2, 'uplift' => 0 ],
[ 'min' => 3, 'max' => 5, 'uplift' => 0.05 ],
[ 'min' => 6, 'max' => 8, 'uplift' => 0.08 ],
[ 'min' => 9, 'max' => 999, 'uplift' => 0.12 ],
],
'uplift_cap'              => 0.15,
'coupons'                 => [],
            'business_rules'          => [
                'checkin_time'       => '16:00',
                'checkout_time'      => '11:00',
                'deposit_threshold'  => 7,
                'deposit_percent'    => 0.5,
                'cancellation_days'  => 7,
                'house_rules'        => $house_rules,
            ],
            'email_templates'         => [
                'booking_deposit'  => $deposit_email_template,
                'booking_balance'  => $balance_email_template,
                'booking_approved' => $approved_email_template,
                'checkin_approved' => $checkin_email_template,
            ],
            'sms_templates'           => [
                'booking_deposit'  => $deposit_sms_template,
                'booking_balance'  => $balance_sms_template,
                'booking_approved' => $approved_sms_template,
            ],
            'checkin_email'           => get_option( 'admin_email', '' ),
        ];
    }

/**
 * Return setting by key.
 */
public function get( string $key, $default = null ) {
return $this->settings[ $key ] ?? $default;
}

/**
 * Update settings cache.
 */
    public function refresh(): void {
        $this->settings = wp_parse_args( get_option( self::OPTION_KEY, [] ), $this->get_defaults() );
    }

    /**
     * Prime the settings cache with sanitized data.
     */
    public function prime_cache( array $settings ): void {
        $this->settings = wp_parse_args( $settings, $this->get_defaults() );
    }

/**
 * Persist settings from admin.
 */
public function save( array $settings ): void {
$sanitized = $this->sanitize( $settings );
update_option( self::OPTION_KEY, $sanitized );
$this->refresh();
}

/**
 * Sanitise settings input.
 */
    public function sanitize( array $settings ): array {
        $defaults = $this->get_defaults();
        $stored   = get_option( self::OPTION_KEY, [] );

        if ( ! is_array( $stored ) ) {
            $stored = [];
        }

        $output = [];

        foreach ( $defaults as $key => $default ) {
            if ( ! array_key_exists( $key, $settings ) ) {
                continue;
            }

$value = $settings[ $key ];

switch ( $key ) {
case 'base_rate':
case 'cleaning_fee':
case 'damage_fee':
$output[ $key ] = floatval( $value );
break;
case 'tax_rate':
case 'uplift_cap':
$output[ $key ] = min( 1, max( 0, floatval( $value ) ) );
break;
case 'enable_damage_fee':
$output[ $key ] = (bool) $value;
break;
case 'stripe_tax_enabled':
$output[ $key ] = (bool) $value;
break;
case 'sms_dnt_cookie_days':
$output[ $key ] = absint( $value );
break;
case 'pricing_tiers':
$output[ $key ] = array_map(
static function ( $tier ) {
return [
'min'    => isset( $tier['min'] ) ? absint( $tier['min'] ) : 0,
'max'    => isset( $tier['max'] ) ? absint( $tier['max'] ) : 0,
'uplift' => isset( $tier['uplift'] ) ? min( 1, max( 0, floatval( $tier['uplift'] ) ) ) : 0,
];
}
, (array) $value );
break;
case 'ical_import_urls':
$urls = is_string( $value ) ? preg_split( "/\r?\n/", $value ) : (array) $value;
$output[ $key ] = array_filter( array_map( 'esc_url_raw', $urls ) );
break;
            case 'business_rules':
                $existing        = isset( $stored['business_rules'] ) && is_array( $stored['business_rules'] ) ? $stored['business_rules'] : [];
                $sanitized_rules = array_map( 'sanitize_text_field', (array) $value );
                $output[ $key ]  = array_merge( $existing, $sanitized_rules );
                break;
        case 'coupons':
            $coupons = array_map(
                function ( $coupon ) {
                    return $this->normalize_coupon( (array) $coupon );
                },
                (array) $value
            );

            $output[ $key ] = array_values(
                array_filter(
                    $coupons,
                    static function ( $coupon ) {
                        return ! empty( $coupon['code'] );
                    }
                )
            );
            break;
            case 'email_templates':
                $existing       = isset( $stored['email_templates'] ) && is_array( $stored['email_templates'] ) ? $stored['email_templates'] : [];
                $templates      = array_map( 'wp_kses_post', (array) $value );
                $output[ $key ] = array_merge( $existing, $templates );
                break;
            case 'sms_templates':
                $existing       = isset( $stored['sms_templates'] ) && is_array( $stored['sms_templates'] ) ? $stored['sms_templates'] : [];
                $templates      = array_map( 'sanitize_textarea_field', (array) $value );
                $output[ $key ] = array_merge( $existing, $templates );
                break;
            case 'checkin_email':
                $output[ $key ] = sanitize_email( $value );
                break;
            default:
                $output[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
        }
    }

    $output['currency'] = 'USD';

        $merged = array_replace_recursive( $stored, $output );
        $final  = wp_parse_args( $merged, $defaults );

        $codes = isset( $final['coupons'] ) ? wp_list_pluck( (array) $final['coupons'], 'code' ) : [];
        $this->sync_coupon_usage_codes( $codes );

        return $final;
    }

/**
 * Retrieve pricing tiers.
 */
public function get_pricing_tiers(): array {
return (array) $this->get( 'pricing_tiers', $this->get_defaults()['pricing_tiers'] );
}

/**
 * Retrieve business rules.
 */
    public function get_business_rules(): array {
        return (array) $this->get( 'business_rules', [] );
    }

    public function get_email_templates(): array {
        $defaults = $this->get_defaults()['email_templates'];
        $stored   = (array) $this->get( 'email_templates', [] );

        return array_merge( $defaults, $stored );
    }

    public function get_sms_templates(): array {
        $defaults = $this->get_defaults()['sms_templates'];
        $stored   = (array) $this->get( 'sms_templates', [] );

        return array_merge( $defaults, $stored );
    }

    public function get_email_template( string $key ): string {
        $templates = $this->get_email_templates();

        return isset( $templates[ $key ] ) ? (string) $templates[ $key ] : '';
    }

    public function get_sms_template( string $key ): string {
        $templates = $this->get_sms_templates();

        return isset( $templates[ $key ] ) ? (string) $templates[ $key ] : '';
    }

    public function get_checkin_email(): string {
        return (string) $this->get( 'checkin_email', '' );
    }

    public function get_coupons(): array {
        $coupons = (array) $this->get( 'coupons', [] );

        $normalized = array_map(
            function ( $coupon ) {
                return $this->normalize_coupon( (array) $coupon );
            },
            $coupons
        );

        return array_values(
            array_filter(
                $normalized,
                static function ( $coupon ) {
                    return ! empty( $coupon['code'] );
                }
            )
        );
    }

    /**
     * Retrieve coupon usage counts keyed by code.
     */
    public function get_coupon_usage_counts(): array {
        $usage = get_option( self::COUPON_USAGE_KEY, [] );

        if ( ! is_array( $usage ) ) {
            return [];
        }

        return array_map( 'absint', $usage );
    }

    /**
     * Get redemption count for a coupon code.
     */
    public function get_coupon_redemptions( string $code ): int {
        $code  = strtoupper( $code );
        $usage = $this->get_coupon_usage_counts();

        return isset( $usage[ $code ] ) ? absint( $usage[ $code ] ) : 0;
    }

    /**
     * Record a redemption for the provided coupon code.
     */
    public function record_coupon_redemption( string $code ): void {
        $code = strtoupper( $code );

        if ( ! $code ) {
            return;
        }

        $usage = $this->get_coupon_usage_counts();
        $usage[ $code ] = isset( $usage[ $code ] ) ? absint( $usage[ $code ] ) + 1 : 1;

        update_option( self::COUPON_USAGE_KEY, $usage );
    }

    /**
     * Remove usage entries for coupons that no longer exist.
     */
    public function sync_coupon_usage_codes( array $codes ): void {
        $codes = array_filter( array_map( 'strtoupper', $codes ) );
        $usage = $this->get_coupon_usage_counts();

        if ( empty( $usage ) ) {
            if ( empty( $codes ) ) {
                return;
            }

            update_option( self::COUPON_USAGE_KEY, [] );
            return;
        }

        $allowed  = array_fill_keys( $codes, true );
        $filtered = array_intersect_key( $usage, $allowed );

        if ( $filtered !== $usage ) {
            update_option( self::COUPON_USAGE_KEY, $filtered );
        }
    }

    /**
     * Normalise coupon data regardless of source.
     */
    private function normalize_coupon( array $coupon ): array {
        $code = isset( $coupon['code'] ) ? strtoupper( sanitize_text_field( $coupon['code'] ) ) : '';

        $type = isset( $coupon['type'] ) ? sanitize_text_field( $coupon['type'] ) : 'flat_total';
        $type = strtolower( $type );

        $map = [
            'flat'    => 'flat_total',
            'percent' => 'percent_total',
        ];

        if ( isset( $map[ $type ] ) ) {
            $type = $map[ $type ];
        }

        $allowed = [ 'flat_total', 'percent_total', 'flat_night', 'percent_night' ];
        if ( ! in_array( $type, $allowed, true ) ) {
            $type = 'flat_total';
        }

        $amount = isset( $coupon['amount'] ) ? floatval( $coupon['amount'] ) : 0.0;
        if ( in_array( $type, [ 'percent_total', 'percent_night' ], true ) ) {
            $amount = min( 100, max( 0, $amount ) );
        } else {
            $amount = max( 0, $amount );
        }

        $max_redemptions = isset( $coupon['max_redemptions'] ) ? absint( $coupon['max_redemptions'] ) : 0;

        return [
            'code'            => $code,
            'type'            => $type,
            'amount'          => $amount,
            'max_redemptions' => $max_redemptions,
            'valid_from'      => isset( $coupon['valid_from'] ) ? sanitize_text_field( $coupon['valid_from'] ) : '',
            'valid_to'        => isset( $coupon['valid_to'] ) ? sanitize_text_field( $coupon['valid_to'] ) : '',
        ];
    }
}
