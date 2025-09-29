<?php
namespace VRSP;

use function __;
use function did_action;

/**
 * Settings repository.
 */
class Settings {
public const OPTION_KEY = 'vrsp_settings';

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
$output[ $key ] = array_values(
array_map(
static function ( $coupon ) {
return [
'code'      => isset( $coupon['code'] ) ? strtoupper( sanitize_text_field( $coupon['code'] ) ) : '',
'type'      => isset( $coupon['type'] ) && 'percent' === $coupon['type'] ? 'percent' : 'flat',
'amount'    => isset( $coupon['amount'] ) ? floatval( $coupon['amount'] ) : 0.0,
'valid_from'=> isset( $coupon['valid_from'] ) ? sanitize_text_field( $coupon['valid_from'] ) : '',
'valid_to'  => isset( $coupon['valid_to'] ) ? sanitize_text_field( $coupon['valid_to'] ) : '',
];
},
(array) $value
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
return (array) $this->get( 'coupons', [] );
}
}
