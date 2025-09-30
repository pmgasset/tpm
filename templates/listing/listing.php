<?php
if ( ! isset( $rental ) || ! $rental instanceof \WP_Post ) {
    return;
}

$options   = get_option( \VRSP\Settings::OPTION_KEY, [] );
$base_rate = isset( $options['base_rate'] ) ? (float) $options['base_rate'] : 200;
$currency  = isset( $options['currency'] ) ? $options['currency'] : 'USD';
?>
<div class="vrsp-booking-widget" data-vrsp-widget>
    <section class="vrsp-card vrsp-card--availability">
        <header class="vrsp-card__header">
            <h2><?php esc_html_e( 'Check Availability', 'vr-single-property' ); ?></h2>
        </header>
        <div class="vrsp-availability" data-vrsp="availability" data-base-rate="<?php echo esc_attr( number_format_i18n( $base_rate, 2 ) ); ?>" data-currency="<?php echo esc_attr( $currency ); ?>">
            <div class="vrsp-availability__calendar" data-vrsp="calendar" aria-live="polite"></div>
            <div class="vrsp-availability__rates">
                <h3><?php esc_html_e( 'Nightly preview', 'vr-single-property' ); ?></h3>
                <p class="vrsp-availability__base"><?php printf( esc_html__( 'From %1$s %2$s nightly before fees and taxes.', 'vr-single-property' ), esc_html( $currency ), esc_html( number_format_i18n( $base_rate, 2 ) ) ); ?></p>
                <div class="vrsp-availability__rate-list" data-vrsp="rate-list" role="list"></div>
            </div>
        </div>
    </section>

    <section class="vrsp-card vrsp-card--form">
        <header class="vrsp-card__header">
            <h2><?php esc_html_e( 'Reserve Your Stay', 'vr-single-property' ); ?></h2>
            <p><?php esc_html_e( 'Your quote updates instantly as you fill in the details below. Review the trip summary, then continue to secure payment with Stripe.', 'vr-single-property' ); ?></p>
        </header>
        <form class="vrsp-form" data-vrsp="form">
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
            <div class="vrsp-form__actions">

                <button type="button" class="vrsp-form__continue" data-vrsp="continue" disabled><?php esc_html_e( 'Continue to Secure Payment', 'vr-single-property' ); ?></button>

                <button type="button" class="vrsp-form__continue" disabled><?php esc_html_e( 'Continue to Secure Payment', 'vr-single-property' ); ?></button>

            </div>
        </form>
        <div class="vrsp-quote" data-vrsp="quote" hidden>
            <h3><?php esc_html_e( 'Trip summary', 'vr-single-property' ); ?></h3>
            <p class="vrsp-quote__intro"><?php esc_html_e( 'Review the quote below. When everything looks good, continue to secure your payment.', 'vr-single-property' ); ?></p>
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
        <div class="vrsp-message" data-vrsp="message" aria-live="polite"></div>
    </section>
</div>
