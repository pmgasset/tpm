<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'VR Single Property Dashboard', 'vr-single-property' ); ?></h1>
<p><?php esc_html_e( 'Monitor bookings, revenue, and operational automations.', 'vr-single-property' ); ?></p>

<div class="vrsp-cards">
<div class="vrsp-card">
<h2><?php esc_html_e( 'Upcoming Arrivals', 'vr-single-property' ); ?></h2>
<ul>
<?php
$upcoming = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => 'publish',
'posts_per_page' => 5,
'orderby'        => 'meta_value',
'order'          => 'ASC',
'meta_key'       => '_vrsp_arrival',
]
);
if ( ! $upcoming ) :
?>
<li><?php esc_html_e( 'No upcoming arrivals.', 'vr-single-property' ); ?></li>
<?php else :
foreach ( $upcoming as $booking ) :
?>
<li>
<strong><?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_arrival', true ) ); ?></strong>
<?php echo esc_html( get_the_title( $booking ) ); ?>
</li>
<?php endforeach; endif; ?>
</ul>
</div>

<div class="vrsp-card">
<h2><?php esc_html_e( 'Operational Status', 'vr-single-property' ); ?></h2>
<ul>
<li><?php esc_html_e( 'Stripe', 'vr-single-property' ); ?>: <?php echo $this->stripe->is_configured() ? esc_html__( 'Connected', 'vr-single-property' ) : esc_html__( 'Not configured', 'vr-single-property' ); ?></li>
<li><?php esc_html_e( 'iCal Sync', 'vr-single-property' ); ?>: <?php echo esc_html( count( (array) get_option( 'vrsp_imported_ical_events', [] ) ) ); ?> <?php esc_html_e( 'external events', 'vr-single-property' ); ?></li>
<li><?php esc_html_e( 'Dynamic Pricing Cookie', 'vr-single-property' ); ?>: <?php echo isset( $_COOKIE[ \VRSP\Utilities\PricingEngine::COOKIE_NAME ] ) ? esc_html__( 'Active', 'vr-single-property' ) : esc_html__( 'Not set', 'vr-single-property' ); ?></li>
</ul>
</div>
</div>
</div>
