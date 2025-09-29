<?php
if ( ! isset( $rental ) || ! $rental instanceof \WP_Post ) {
    return;
}

$options   = get_option( \VRSP\Settings::OPTION_KEY, [] );
$base_rate = isset( $options['base_rate'] ) ? (float) $options['base_rate'] : 200;
$currency  = isset( $options['currency'] ) ? $options['currency'] : 'USD';
?>
<div class="vrsp-booking-widget">
    <section class="vrsp-card vrsp-card--availability">
        <header class="vrsp-card__header">
            <h2><?php esc_html_e( 'Check Availability', 'vr-single-property' ); ?></h2>
            <p><?php esc_html_e( 'Always up to date with Airbnb, VRBO, and Booking.com sync.', 'vr-single-property' ); ?></p>
        </header>
        <div class="vrsp-availability" data-base-rate="<?php echo esc_attr( number_format_i18n( $base_rate, 2 ) ); ?>" data-currency="<?php echo esc_attr( $currency ); ?>">
            <div class="vrsp-availability__calendar" aria-live="polite"></div>
            <div class="vrsp-availability__rates">
                <h3><?php esc_html_e( 'Nightly preview', 'vr-single-property' ); ?></h3>
                <p class="vrsp-availability__base"><?php printf( esc_html__( 'From %1$s %2$s nightly before fees and taxes.', 'vr-single-property' ), esc_html( $currency ), esc_html( number_format_i18n( $base_rate, 2 ) ) ); ?></p>
                <div class="vrsp-availability__rate-list" role="list"></div>
            </div>
        </div>
    </section>

    <section class="vrsp-card vrsp-card--form">
        <header class="vrsp-card__header">
            <h2><?php esc_html_e( 'Reserve Your Stay', 'vr-single-property' ); ?></h2>
            <p><?php esc_html_e( 'Secure checkout with Stripe. Deposits are calculated automatically.', 'vr-single-property' ); ?></p>
        </header>
        <form class="vrsp-form">
            <div class="vrsp-form__grid">
                <label>
                    <span><?php esc_html_e( 'Arrival', 'vr-single-property' ); ?></span>
                    <input type="date" name="arrival" required />
                </label>
                <label>
                    <span><?php esc_html_e( 'Departure', 'vr-single-property' ); ?></span>
                    <input type="date" name="departure" required />
                </label>
                <label>
                    <span><?php esc_html_e( 'Guests', 'vr-single-property' ); ?></span>
                    <input type="number" name="guests" min="1" value="2" />
                </label>
                <label>
                    <span><?php esc_html_e( 'Coupon Code', 'vr-single-property' ); ?></span>
                    <input type="text" name="coupon" />
                </label>
                <label>
                    <span><?php esc_html_e( 'First Name', 'vr-single-property' ); ?></span>
                    <input type="text" name="first_name" required />
                </label>
                <label>
                    <span><?php esc_html_e( 'Last Name', 'vr-single-property' ); ?></span>
                    <input type="text" name="last_name" required />
                </label>
                <label>
                    <span><?php esc_html_e( 'Email', 'vr-single-property' ); ?></span>
                    <input type="email" name="email" required />
                </label>
                <label>
                    <span><?php esc_html_e( 'Mobile Number', 'vr-single-property' ); ?></span>
                    <input type="tel" name="phone" />
                </label>
            </div>
            <button type="submit" class="vrsp-form__submit"><?php esc_html_e( 'Continue to Secure Payment', 'vr-single-property' ); ?></button>
        </form>
        <div class="vrsp-quote" hidden>
            <h3><?php esc_html_e( 'Trip summary', 'vr-single-property' ); ?></h3>
            <dl class="vrsp-quote__grid">
                <div>
                    <dt><?php esc_html_e( 'Nights', 'vr-single-property' ); ?></dt>
                    <dd data-quote="nights">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Subtotal', 'vr-single-property' ); ?></dt>
                    <dd data-quote="subtotal">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Taxes &amp; Fees', 'vr-single-property' ); ?></dt>
                    <dd data-quote="taxes">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Total', 'vr-single-property' ); ?></dt>
                    <dd data-quote="total">—</dd>
                </div>
                <div>
                    <dt><?php esc_html_e( 'Due Today', 'vr-single-property' ); ?></dt>
                    <dd data-quote="deposit">—</dd>
                </div>
                <div data-quote="balance-row">
                    <dt><?php esc_html_e( 'Balance on File', 'vr-single-property' ); ?></dt>
                    <dd data-quote="balance">—</dd>
                </div>
            </dl>
            <p class="vrsp-quote__note" data-quote="note"></p>
        </div>
        <div class="vrsp-message" aria-live="polite"></div>
    </section>
</div>
