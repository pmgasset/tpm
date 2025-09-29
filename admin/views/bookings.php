<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'Bookings', 'vr-single-property' ); ?></h1>
<table class="widefat vrsp-bookings-table">
<thead>
<tr>
<th><?php esc_html_e( 'Guest', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Dates', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Status', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Financials', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Actions', 'vr-single-property' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $bookings ) ) : ?>
<tr><td colspan="5"><?php esc_html_e( 'No bookings yet.', 'vr-single-property' ); ?></td></tr>
<?php else :
foreach ( $bookings as $booking ) :
$quote = get_post_meta( $booking->ID, '_vrsp_quote', true );
$admin_status = get_post_meta( $booking->ID, '_vrsp_admin_status', true );
$status_labels = [];

if ( get_post_meta( $booking->ID, '_vrsp_balance_paid', true ) ) {
    $status_labels[] = '<span class="status status-success">' . esc_html__( 'Paid in Full', 'vr-single-property' ) . '</span>';
} elseif ( get_post_meta( $booking->ID, '_vrsp_deposit_paid', true ) ) {
    $status_labels[] = '<span class="status status-warning">' . esc_html__( 'Deposit Paid', 'vr-single-property' ) . '</span>';
} else {
    $status_labels[] = '<span class="status status-pending">' . esc_html__( 'Awaiting Payment', 'vr-single-property' ) . '</span>';
}

if ( 'pending_admin' === $admin_status ) {
    $status_labels[] = '<span class="status status-info">' . esc_html__( 'Needs Approval', 'vr-single-property' ) . '</span>';
} elseif ( 'approved' === $admin_status ) {
    $status_labels[] = '<span class="status status-success">' . esc_html__( 'Approved', 'vr-single-property' ) . '</span>';
}
?>
<tr>
<td><?php echo esc_html( get_the_title( $booking ) ); ?><br /><?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_email', true ) ); ?></td>
<td><?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_arrival', true ) ); ?> &rarr; <?php echo esc_html( get_post_meta( $booking->ID, '_vrsp_departure', true ) ); ?></td>
<td><?php echo wp_kses_post( implode( '<br />', $status_labels ) ); ?><br /><small><?php echo esc_html( ucfirst( get_post_status( $booking ) ) ); ?></small></td>
<td>
    <strong><?php printf( '%s %s', esc_html( $quote['currency'] ?? 'USD' ), esc_html( number_format_i18n( $quote['total'] ?? 0, 2 ) ) ); ?></strong><br />
    <?php if ( ! empty( $quote['deposit'] ) && $quote['deposit'] < ( $quote['total'] ?? 0 ) ) : ?>
        <?php printf( esc_html__( 'Deposit: %1$s %2$s', 'vr-single-property' ), esc_html( $quote['currency'] ?? 'USD' ), esc_html( number_format_i18n( $quote['deposit'], 2 ) ) ); ?><br />
        <?php printf( esc_html__( 'Balance: %1$s %2$s', 'vr-single-property' ), esc_html( $quote['currency'] ?? 'USD' ), esc_html( number_format_i18n( $quote['balance'] ?? 0, 2 ) ) ); ?>
    <?php else : ?>
        <?php esc_html_e( 'Full balance due now.', 'vr-single-property' ); ?>
    <?php endif; ?>
</td>
<td class="vrsp-actions">
    <?php if ( 'pending_admin' === $admin_status ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="vrsp_approve_booking" />
            <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->ID ); ?>" />
            <?php wp_nonce_field( 'vrsp-approve-booking_' . $booking->ID ); ?>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Approve &amp; Trigger Check-In', 'vr-single-property' ); ?></button>
        </form>
    <?php else : ?>
        <span class="description"><?php esc_html_e( 'No actions', 'vr-single-property' ); ?></span>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
