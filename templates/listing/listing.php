<?php
/** @var WP_Post $rental */
/** @var string $content Sanitized rental content */
if ( ! isset( $rental ) || ! $rental instanceof WP_Post ) {
    return;
}

$gallery   = get_attached_media( 'image', $rental->ID );
$options   = get_option( \VRSP\Settings::OPTION_KEY, [] );
$base_rate = isset( $options['base_rate'] ) ? (float) $options['base_rate'] : 200;
$content = isset( $content ) ? (string) $content : '';


$content = isset( $content ) ? (string) $content : '';

$content   = $rental->post_content;


$patterns = [
    '/\[vrsp_listing(?:\s+[^\]]*)?\](?:.*?\[\/vrsp_listing\])?/is',
    '/<!--\s+wp:vrsp\/listing.*?-->.*?<!--\s+\/wp:vrsp\/listing\s+-->/is',
    '/<!--\s+wp:vrsp\/listing\s+\/?-->/i',
];

foreach ( $patterns as $pattern ) {
    $maybe = preg_replace( $pattern, '', $content );

    if ( null !== $maybe ) {
        $content = $maybe;
    }
}

$content = trim( $content );

$listing_block_class = 'VRSP\\Blocks\\ListingBlock';
$guard_active        = class_exists( $listing_block_class ) && $listing_block_class::is_rendering();

if ( $guard_active ) {

if ( class_exists( '\\VRSP\\Blocks\\ListingBlock' ) && \VRSP\Blocks\ListingBlock::is_rendering() ) {

    $content = wpautop( $content );
} else {
    $content = apply_filters( 'the_content', $content );
}

$content = wp_kses_post( $content );


?>
<div class="vrsp-listing">
    <div class="vrsp-hero">
        <?php if ( $gallery ) :
            $images = array_slice( $gallery, 0, 4 );
            foreach ( $images as $image ) :
?>
<img src="<?php echo esc_url( wp_get_attachment_image_url( $image->ID, 'large' ) ); ?>" alt="<?php echo esc_attr( get_post_meta( $image->ID, '_wp_attachment_image_alt', true ) ); ?>" />
<?php endforeach; else : ?>
<img src="<?php echo esc_url( VRSP_PLUGIN_URL . 'assets/placeholder.jpg' ); ?>" alt="" />
<?php endif; ?>
    </div>

    <div class="vrsp-content">
        <div class="vrsp-grid two">
            <div class="vrsp-card">
                <h2><?php echo esc_html( get_the_title( $rental ) ); ?></h2>
                <div class="vrsp-description"><?php echo $content; ?></div>
            </div>
            <div class="vrsp-card vrsp-calendar">
                <h3><?php esc_html_e( 'Availability', 'vr-single-property' ); ?></h3>
                <div class="vrsp-availability"></div>
                <p><?php esc_html_e( 'Bookings auto-sync across Airbnb, VRBO, and Booking.com via iCal.', 'vr-single-property' ); ?></p>
</div>
</div>

<div class="vrsp-card">
<h3><?php esc_html_e( 'Get an Instant Quote', 'vr-single-property' ); ?></h3>
<form class="vrsp-form">
<label><?php esc_html_e( 'Arrival', 'vr-single-property' ); ?><input type="date" name="arrival" required /></label>
<label><?php esc_html_e( 'Departure', 'vr-single-property' ); ?><input type="date" name="departure" required /></label>
<label><?php esc_html_e( 'Guests', 'vr-single-property' ); ?><input type="number" name="guests" min="1" value="2" /></label>
<label><?php esc_html_e( 'Coupon Code', 'vr-single-property' ); ?><input type="text" name="coupon" /></label>
<label><?php esc_html_e( 'First Name', 'vr-single-property' ); ?><input type="text" name="first_name" required /></label>
<label><?php esc_html_e( 'Last Name', 'vr-single-property' ); ?><input type="text" name="last_name" required /></label>
<label><?php esc_html_e( 'Email', 'vr-single-property' ); ?><input type="email" name="email" required /></label>
<label><?php esc_html_e( 'Mobile Number', 'vr-single-property' ); ?><input type="tel" name="phone" /></label>
<button type="submit"><?php esc_html_e( 'Reserve with Stripe Checkout', 'vr-single-property' ); ?></button>
</form>
<div class="vrsp-quote"><?php printf( esc_html__( 'Nightly rate from %s', 'vr-single-property' ), esc_html( number_format_i18n( $base_rate, 2 ) ) ); ?></div>
<div class="vrsp-message" aria-live="polite"></div>
</div>
</div>
</div>
