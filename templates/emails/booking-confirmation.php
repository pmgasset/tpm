<h1><?php esc_html_e( 'Your Stay is Confirmed!', 'vr-single-property' ); ?></h1>
<p><?php printf( esc_html__( 'Hi %s, thank you for booking Jordan View Retreat.', 'vr-single-property' ), esc_html( $booking['first_name'] ?? '' ) ); ?></p>
<ul>
<li><strong><?php esc_html_e( 'Arrival', 'vr-single-property' ); ?>:</strong> <?php echo esc_html( $booking['arrival'] ?? '' ); ?></li>
<li><strong><?php esc_html_e( 'Departure', 'vr-single-property' ); ?>:</strong> <?php echo esc_html( $booking['departure'] ?? '' ); ?></li>
<li><strong><?php esc_html_e( 'Guests', 'vr-single-property' ); ?>:</strong> <?php echo esc_html( $booking['guests'] ?? '' ); ?></li>
</ul>
<p><?php esc_html_e( 'A separate email will arrive when your balance is captured 7 days before arrival. Add this message to your wallet for quick access.', 'vr-single-property' ); ?></p>
