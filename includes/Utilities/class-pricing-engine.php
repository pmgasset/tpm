<?php
namespace VRSP\Utilities;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use function __;
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
        $nightly   = $base_rate * $nights;

        $uplift_percent = $this->get_dynamic_uplift();
        $uplift_amount  = $nightly * $uplift_percent;

        $nightly_total = $nightly + $uplift_amount;

        $cleaning_fee = (float) $this->settings->get( 'cleaning_fee', 0 );
        $damage_fee   = 0.0;

        if ( $this->settings->get( 'enable_damage_fee', false ) ) {
            $damage_fee = (float) $this->settings->get( 'damage_fee', 0 );
        }

        $pre_discount_subtotal = $nightly_total + $cleaning_fee + $damage_fee;

        $coupon       = $this->get_coupon( $coupon_code, $arrive );
        $coupon_rate  = 0.0;
        $coupon_error = '';
        $discount     = 0.0;

        if ( $coupon_code && ! $coupon ) {
            $coupon_error = __( 'Coupon code is invalid, expired, or fully redeemed.', 'vr-single-property' );
        }

        if ( $coupon ) {
            $discount_details = $this->resolve_coupon_discount( $coupon, $nightly_total, $pre_discount_subtotal, $nights );
            $discount         = $discount_details['amount'];
            $coupon_rate      = $discount_details['rate'];
        }

        $discount = min( $discount, $pre_discount_subtotal );
        $subtotal = max( 0, $pre_discount_subtotal - $discount );

        $tax_rate = (float) $this->settings->get( 'tax_rate', 0 );
        $taxes    = round( $subtotal * $tax_rate, 2 );
        $total    = round( $subtotal + $taxes, 2 );

        $deposit_percent = $this->get_deposit_percentage( $arrive );
        $deposit         = round( $total * $deposit_percent, 2 );
        $balance         = $total - $deposit;

        return [
            'currency'              => $this->settings->get( 'currency', 'USD' ),
            'nights'                => $nights,
            'base_rate'             => $base_rate,
            'uplift_percent'        => $uplift_percent,
            'uplift_amount'         => round( $uplift_amount, 2 ),
            'nightly_subtotal'      => round( $nightly_total, 2 ),
            'cleaning_fee'          => $cleaning_fee,
            'damage_fee'            => $damage_fee,
            'pre_discount_subtotal' => round( $pre_discount_subtotal, 2 ),
            'discount'              => round( $discount, 2 ),
            'coupon'                => $coupon,
            'coupon_rate'           => $coupon_rate,
            'coupon_error'          => $coupon_error,
            'taxes'                 => $taxes,
            'subtotal'              => round( $subtotal, 2 ),
            'total'                 => $total,
            'deposit'               => $deposit,
            'balance'               => round( $balance, 2 ),
            'deposit_percent'       => $deposit_percent,
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

        if ( ! empty( $quote['coupon']['code'] ) ) {
            $this->record_coupon_redemption( $booking_id, $quote['coupon']['code'] );
        }
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

            $max_redemptions = isset( $coupon['max_redemptions'] ) ? (int) $coupon['max_redemptions'] : 0;
            $redemptions     = $this->settings->get_coupon_redemptions( $coupon['code'] );

            if ( $max_redemptions > 0 && $redemptions >= $max_redemptions ) {
                continue;
            }

            $coupon['redemptions'] = $redemptions;
            $coupon['remaining_redemptions'] = $max_redemptions > 0 ? max( 0, $max_redemptions - $redemptions ) : null;

            return $coupon;
        }

        return null;
    }

    private function resolve_coupon_discount( array $coupon, float $nightly_total, float $pre_discount_subtotal, int $nights ): array {
        $type   = $coupon['type'] ?? 'flat_total';
        $amount = isset( $coupon['amount'] ) ? (float) $coupon['amount'] : 0.0;
        $amount = max( 0.0, $amount );

        $discount = 0.0;
        $rate     = 0.0;

        switch ( $type ) {
            case 'percent_total':
                $rate     = min( 1, max( 0, $amount / 100 ) );
                $discount = $pre_discount_subtotal * $rate;
                break;
            case 'percent_night':
                $rate     = min( 1, max( 0, $amount / 100 ) );
                $discount = $nightly_total * $rate;
                break;
            case 'flat_night':
                $discount = min( $nightly_total, $amount * max( 1, $nights ) );
                break;
            case 'flat_total':
            default:
                $discount = $amount;
                break;
        }

        if ( $discount < 0 ) {
            $discount = 0.0;
        }

        return [
            'amount' => $discount,
            'rate'   => $rate,
        ];
    }

    private function record_coupon_redemption( int $booking_id, string $code ): void {
        $code = strtoupper( sanitize_text_field( $code ) );

        if ( ! $code ) {
            return;
        }

        $already_recorded = get_post_meta( $booking_id, '_vrsp_coupon_recorded', true );
        if ( $already_recorded ) {
            return;
        }

        $this->settings->record_coupon_redemption( $code );
        update_post_meta( $booking_id, '_vrsp_coupon_recorded', $code );
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
