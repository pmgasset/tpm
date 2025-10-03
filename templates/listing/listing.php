<?php
if ( ! isset( $rental ) || ! $rental instanceof \WP_Post ) {
    return;
}

$options   = get_option( \VRSP\Settings::OPTION_KEY, [] );
$base_rate = isset( $options['base_rate'] ) ? (float) $options['base_rate'] : 200;
$currency  = isset( $options['currency'] ) ? $options['currency'] : 'USD';

$title   = get_the_title( $rental );
$excerpt = get_the_excerpt( $rental );
?>

<article class="vrsp-listing" aria-labelledby="vrsp-listing-title">
    <header class="vrsp-listing__header">
        <?php if ( $title ) : ?>
            <h1 id="vrsp-listing-title" class="vrsp-listing__title"><?php echo esc_html( $title ); ?></h1>
        <?php endif; ?>

        <?php if ( $excerpt ) : ?>
            <div class="vrsp-listing__excerpt"><?php echo wp_kses_post( $excerpt ); ?></div>
        <?php endif; ?>

        <?php if ( has_post_thumbnail( $rental ) ) : ?>
            <figure class="vrsp-listing__featured-image">
                <?php echo get_the_post_thumbnail( $rental, 'large' ); ?>
            </figure>
        <?php endif; ?>
    </header>

    <?php if ( ! empty( $content ) ) : ?>
        <section class="vrsp-listing__details">
            <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Content sanitized upstream. ?>
        </section>
    <?php endif; ?>

    <div class="vrsp-booking-widget" data-vrsp-widget>
        <section class="vrsp-card vrsp-card--availability">
            <header class="vrsp-card__header">
                <h2><?php esc_html_e( 'Check Availability', 'vr-single-property' ); ?></h2>
            </header>
            <div class="vrsp-availability" data-vrsp="availability" data-base-rate="<?php echo esc_attr( number_format_i18n( $base_rate, 2 ) ); ?>" data-currency="<?php echo esc_attr( $currency ); ?>">
            <div class="vrsp-availability__calendar" data-vrsp="calendar" aria-live="polite"></div>
            <div class="vrsp-availability__details">
                <div class="vrsp-availability__dates">
                    <h3><?php esc_html_e( 'Your stay', 'vr-single-property' ); ?></h3>
                    <dl>
                        <div>
                            <dt><?php esc_html_e( 'Check-in', 'vr-single-property' ); ?></dt>
                            <dd data-summary="arrival">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Check-out', 'vr-single-property' ); ?></dt>
                            <dd data-summary="departure">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Nights', 'vr-single-property' ); ?></dt>
                            <dd data-summary="nights">—</dd>
                        </div>
                    </dl>
                </div>
                <div class="vrsp-availability__pricing" data-vrsp="pricing">
                    <h3><?php esc_html_e( 'Trip pricing', 'vr-single-property' ); ?></h3>
                    <dl>
                        <div>
                            <dt><?php esc_html_e( 'Stay subtotal', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="stay">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Cleaning fee', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="cleaning">—</dd>
                        </div>
                        <div style="display:none;" data-pricing-row="discount">
                            <dt><?php esc_html_e( 'Coupon discount', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="discount">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Taxes &amp; fees', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="taxes">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Total', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="total">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Due today', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="deposit">—</dd>
                        </div>
                        <div>
                            <dt><?php esc_html_e( 'Balance due', 'vr-single-property' ); ?></dt>
                            <dd data-pricing="balance">—</dd>
                        </div>
                    </dl>
                    <p class="vrsp-availability__note" data-pricing="note"></p>
                </div>
            </div>
        </div>
    </section>

    <section class="vrsp-card vrsp-card--form">
        <header class="vrsp-card__header">
            <h2><?php esc_html_e( 'Reserve Your Stay', 'vr-single-property' ); ?></h2>
            <p><?php esc_html_e( 'Enter your stay details to see live pricing. When it looks good, continue to secure payment.', 'vr-single-property' ); ?></p>
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
            <fieldset class="vrsp-form__payment" data-vrsp="payment">
                <legend><?php esc_html_e( 'Payment preference', 'vr-single-property' ); ?></legend>
                <label>
                    <input type="radio" name="payment_option" value="deposit" data-payment="deposit" checked />
                    <?php esc_html_e( 'Pay 50% deposit today', 'vr-single-property' ); ?>
                </label>
                <label>
                    <input type="radio" name="payment_option" value="full" data-payment="full" />
                    <?php esc_html_e( 'Pay in full today', 'vr-single-property' ); ?>
                </label>
                <p class="vrsp-form__payment-note" data-payment="note"></p>
            </fieldset>
            <div class="vrsp-form__actions">
                <button type="button" class="vrsp-form__continue" data-vrsp="continue" disabled><?php esc_html_e( 'Continue to Secure Payment', 'vr-single-property' ); ?></button>
            </div>
        </form>
        <div class="vrsp-message" data-vrsp="message" aria-live="polite"></div>
        </section>
    </div>
</article>

<?php if ( ! empty( $block_content ) ) : ?>
    <section class="vrsp-listing__supplemental">
        <?php echo wp_kses_post( $block_content ); ?>
    </section>
<?php endif; ?>

<?php if ( ! empty( $schema ) ) : ?>
    <script type="application/ld+json">
        <?php echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); ?>
    </script>
<?php endif; ?>
