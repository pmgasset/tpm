<?php
namespace VRSP;

use function __;
use function did_action;
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

        if ( $translate && function_exists( '__' ) ) {
            $house_rules = __( 'No smoking. No pets. Quiet hours after 10 PM.', 'vr-single-property' );
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
'email_templates'         => [],
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
$output   = [];

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
$output[ $key ] = wp_parse_args( array_map( 'sanitize_text_field', (array) $value ), $defaults['business_rules'] );
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
$output[ $key ] = array_map( 'wp_kses_post', (array) $value );
break;
default:
$output[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
}
}

    $output['currency'] = 'USD';

    $codes = isset( $output['coupons'] ) ? wp_list_pluck( (array) $output['coupons'], 'code' ) : [];
    $this->sync_coupon_usage_codes( $codes );

    return wp_parse_args( $output, $defaults );
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
