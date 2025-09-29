<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'Bookings', 'vr-single-property' ); ?></h1>
<table class="widefat">
<thead>
<tr>
<th><?php esc_html_e( 'Guest', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Dates', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Status', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Total', 'vr-single-property' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $bookings ) ) : ?>
<tr><td colspan="4"><?php esc_html_e( 'No bookings yet.', 'vr-single-property' ); ?></td></tr>
<?php else :
foreach ( $bookings as $booking ) :
$quote = get_post_meta( $booking->ID, '_vrsp_quote', true );
?>
<tr>
<td><?php echo esc_html( get_the_title( $booking ) ); ?><br /><?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_email', true ) ); ?></td>
<td><?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_arrival', true ) ); ?> &rarr; <?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_departure', true ) ); ?></td>
<td>
<?php if ( get_post_meta( $booking->ID, '_vrsp_balance_paid', true ) ) : ?>
<span class="status status-success"><?php esc_html_e( 'Paid', 'vr-single-property' ); ?></span>
<?php elseif ( get_post_meta( $booking->ID, '_vrsp_deposit_paid', true ) ) : ?>
<span class="status status-warning"><?php esc_html_e( 'Deposit Paid', 'vr-single-property' ); ?></span>
<?php else : ?>
<span class="status status-pending"><?php esc_html_e( 'Pending', 'vr-single-property' ); ?></span>
<?php endif; ?>
</td>
<td><?php echo esc_html( $quote['currency'] ?? 'USD' ); ?> <?php echo esc_html( number_format_i18n( $quote['total'] ?? 0, 2 ) ); ?></td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
