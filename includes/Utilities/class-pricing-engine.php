<?php
namespace VRSP\Utilities;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use VRSP\Settings;

/**
 * Pricing engine handling dynamic pricing and quotes.
 */
class PricingEngine {
public const COOKIE_NAME = 'vrsp_view_history';
public const OPT_OUT_COOKIE = 'vrsp_price_optout';

private $settings;
private $logger;

public function __construct( Settings $settings, Logger $logger ) {
$this->settings = $settings;
$this->logger   = $logger;
}

public function calculate_quote( string $arrival, string $departure, int $guests = 1, string $coupon_code = '' ): array {
$arrive = new DateTimeImmutable( $arrival );
$leave  = new DateTimeImmutable( $departure );

$nights = max( 1, (int) $leave->diff( $arrive )->days );

$base_rate = (float) $this->settings->get( 'base_rate', 200 );
$subtotal  = $base_rate * $nights;

$uplift_percent = $this->get_dynamic_uplift();
$uplift_amount  = $subtotal * $uplift_percent;

$subtotal += $uplift_amount;

$cleaning_fee = (float) $this->settings->get( 'cleaning_fee', 0 );
$subtotal    += $cleaning_fee;

$damage_fee = 0;
if ( $this->settings->get( 'enable_damage_fee', false ) ) {
$damage_fee = (float) $this->settings->get( 'damage_fee', 0 );
$subtotal  += $damage_fee;
}

$coupon      = $this->get_coupon( $coupon_code, $arrive );
$coupon_rate = 0;
if ( $coupon ) {
if ( 'percent' === $coupon['type'] ) {
$coupon_rate = min( 1, max( 0, $coupon['amount'] / 100 ) );
$subtotal   -= $subtotal * $coupon_rate;
} else {
$subtotal -= $coupon['amount'];
}
}

$subtotal = max( 0, $subtotal );

$tax_rate = (float) $this->settings->get( 'tax_rate', 0 );
$taxes    = round( $subtotal * $tax_rate, 2 );
$total    = round( $subtotal + $taxes, 2 );

$deposit_percent = $this->get_deposit_percentage( $arrive );
$deposit         = round( $total * $deposit_percent, 2 );
$balance         = $total - $deposit;

return [
'currency'        => $this->settings->get( 'currency', 'USD' ),
'nights'          => $nights,
'base_rate'       => $base_rate,
'uplift_percent'  => $uplift_percent,
'uplift_amount'   => round( $uplift_amount, 2 ),
'cleaning_fee'    => $cleaning_fee,
'damage_fee'      => $damage_fee,
'coupon'          => $coupon,
'coupon_rate'     => $coupon_rate,
'taxes'           => $taxes,
'subtotal'        => round( $subtotal, 2 ),
'total'           => $total,
'deposit'         => $deposit,
'balance'         => round( $balance, 2 ),
'deposit_percent' => $deposit_percent,
];
}

public function track_view(): void {
if ( ! $this->is_tracking_allowed() ) {
return;
}

$state = $this->get_state();
$now   = time();

$state['views']   = array_filter(
$state['views'],
static function ( $timestamp ) use ( $now ) {
return $timestamp >= $now - WEEK_IN_SECONDS;
}
);

$state['views'][] = $now;
$state['last_view'] = $now;

$this->save_state( $state );
}

public function mark_uplift_applied(): void {
if ( ! $this->is_tracking_allowed() ) {
return;
}

$state                 = $this->get_state();
$state['last_applied'] = time();
$this->save_state( $state );
}

public function get_dynamic_uplift(): float {
$state = $this->get_state();
$count = count( $state['views'] );

$tiers = $this->settings->get_pricing_tiers();
$uplift = 0.0;

foreach ( $tiers as $tier ) {
if ( $count >= (int) $tier['min'] && $count <= (int) $tier['max'] ) {
$uplift = (float) $tier['uplift'];
break;
}
}

$uplift = min( (float) $this->settings->get( 'uplift_cap', 0.15 ), $uplift );

$cooldown_until = (int) $state['last_applied'] + 2 * DAY_IN_SECONDS;
if ( time() < $cooldown_until ) {
$uplift = (float) $state['last_uplift'];
} else {
$state['last_uplift'] = $uplift;
$this->save_state( $state );
}

return $uplift;
}

    public function persist_booking_meta( int $booking_id, array $quote ): void {
        update_post_meta( $booking_id, '_vrsp_quote', $quote );
        update_post_meta( $booking_id, '_vrsp_uplift_percent', $quote['uplift_percent'] ?? 0 );
        update_post_meta( $booking_id, '_vrsp_coupon', $quote['coupon']['code'] ?? '' );
    }

    public function get_calendar_rates( DateTimeImmutable $from, DateTimeImmutable $to ): array {
        if ( $to <= $from ) {
            return [];
        }

        $period  = new DatePeriod( $from, new DateInterval( 'P1D' ), $to );
        $rates   = [];
        $base    = (float) $this->settings->get( 'base_rate', 200 );
        $uplift  = $this->get_dynamic_uplift();
        $nightly = round( $base + ( $base * $uplift ), 2 );

        foreach ( $period as $day ) {
            $rates[] = [
                'date'   => $day->format( 'Y-m-d' ),
                'amount' => $nightly,
            ];
        }

        return $rates;
    }

private function get_coupon( string $code, DateTimeImmutable $arrival ): ?array {
if ( ! $code ) {
return null;
}

$code    = strtoupper( sanitize_text_field( $code ) );
$coupons = $this->settings->get_coupons();

foreach ( $coupons as $coupon ) {
if ( $coupon['code'] !== $code ) {
continue;
}

$valid_from = ! empty( $coupon['valid_from'] ) ? new DateTimeImmutable( $coupon['valid_from'] ) : null;
$valid_to   = ! empty( $coupon['valid_to'] ) ? new DateTimeImmutable( $coupon['valid_to'] ) : null;

if ( $valid_from && $arrival < $valid_from ) {
continue;
}

if ( $valid_to && $arrival > $valid_to ) {
continue;
}

return $coupon;
}

return null;
}

private function get_deposit_percentage( DateTimeImmutable $arrival ): float {
$rules = $this->settings->get_business_rules();
$threshold = isset( $rules['deposit_threshold'] ) ? absint( $rules['deposit_threshold'] ) : 7;
$deposit   = isset( $rules['deposit_percent'] ) ? (float) $rules['deposit_percent'] : 0.5;

$current    = new DateTimeImmutable( 'now', wp_timezone() );
$interval   = $current->diff( $arrival );
$days_until = ( $arrival < $current ) ? 0 : (int) $interval->days;

if ( $days_until < $threshold ) {
return 1.0;
}

return min( 1.0, max( 0.0, $deposit ) );
}

private function is_tracking_allowed(): bool {
if ( isset( $_COOKIE[ self::OPT_OUT_COOKIE ] ) && '1' === $_COOKIE[ self::OPT_OUT_COOKIE ] ) {
return false;
}

if ( isset( $_SERVER['HTTP_DNT'] ) && '1' === $_SERVER['HTTP_DNT'] ) {
return false;
}

return true;
}

private function get_state(): array {
$state = [
'views'        => [],
'last_view'    => 0,
'last_applied' => 0,
'last_uplift'  => 0.0,
];

if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) ) {
$data = json_decode( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ), true );
if ( is_array( $data ) ) {
$state = wp_parse_args( $data, $state );
}
}

$now            = time();
$state['views'] = array_filter(
(array) $state['views'],
static function ( $timestamp ) use ( $now ) {
return absint( $timestamp ) >= $now - WEEK_IN_SECONDS;
}
);

return $state;
}

private function save_state( array $state ): void {
$expiry = time() + DAY_IN_SECONDS * (int) $this->settings->get( 'sms_dnt_cookie_days', 30 );
setcookie( self::COOKIE_NAME, wp_json_encode( $state ), $expiry, COOKIEPATH, COOKIE_DOMAIN );
}
}
