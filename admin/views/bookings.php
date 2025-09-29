<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'Bookings', 'vr-single-property' ); ?></h1>
<?php
$post_statuses        = get_post_stati( [ 'internal' => false ], 'objects' );
$admin_status_options = [
    'initiated'     => __( 'Initiated', 'vr-single-property' ),
    'pending_admin' => __( 'Pending Admin Review', 'vr-single-property' ),
    'approved'      => __( 'Approved', 'vr-single-property' ),
    'cancelled'     => __( 'Cancelled', 'vr-single-property' ),
];
$highlight_booking    = isset( $_GET['booking'] ) ? absint( $_GET['booking'] ) : 0;
?>
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
<?php else : ?>
<?php foreach ( $bookings as $booking ) :
$quote           = get_post_meta( $booking->ID, '_vrsp_quote', true );
$admin_status    = get_post_meta( $booking->ID, '_vrsp_admin_status', true );
$deposit_paid    = (bool) get_post_meta( $booking->ID, '_vrsp_deposit_paid', true );
$balance_paid    = (bool) get_post_meta( $booking->ID, '_vrsp_balance_paid', true );
$status_labels   = [];
$row_classes     = [];

if ( $highlight_booking === (int) $booking->ID ) {
    $row_classes[] = 'is-updated';
}

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
<tr<?php echo $row_classes ? ' class="' . esc_attr( implode( ' ', $row_classes ) ) . '"' : ''; ?>>
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
    <?php $edit_link = get_edit_post_link( $booking->ID ); ?>
    <?php if ( $edit_link ) : ?>
        <a class="button" href="<?php echo esc_url( $edit_link ); ?>"><?php esc_html_e( 'Open Booking Detail', 'vr-single-property' ); ?></a>
    <?php endif; ?>

    <form class="vrsp-inline-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="vrsp_update_booking" />
        <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->ID ); ?>" />
        <?php wp_nonce_field( 'vrsp-update-booking_' . $booking->ID ); ?>
        <label class="vrsp-inline-field">
            <span><?php esc_html_e( 'Admin Status', 'vr-single-property' ); ?></span>
            <select name="vrsp_admin_status">
                <?php foreach ( $admin_status_options as $value => $label ) : ?>
                    <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $admin_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="vrsp-inline-field">
            <span><?php esc_html_e( 'Post Status', 'vr-single-property' ); ?></span>
            <select name="vrsp_post_status">
                <?php foreach ( $post_statuses as $status_key => $status_obj ) : ?>
                    <option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( get_post_status( $booking ), $status_key ); ?>><?php echo esc_html( $status_obj->label ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <fieldset class="vrsp-inline-flags">
            <legend class="screen-reader-text"><?php esc_html_e( 'Payment Flags', 'vr-single-property' ); ?></legend>
            <label><input type="checkbox" name="vrsp_deposit_paid" value="1" <?php checked( $deposit_paid ); ?> /> <?php esc_html_e( 'Deposit Received', 'vr-single-property' ); ?></label>
            <label><input type="checkbox" name="vrsp_balance_paid" value="1" <?php checked( $balance_paid ); ?> /> <?php esc_html_e( 'Balance Paid', 'vr-single-property' ); ?></label>
        </fieldset>
        <button type="submit" class="button button-secondary"><?php esc_html_e( 'Save Changes', 'vr-single-property' ); ?></button>
    </form>

    <?php if ( 'pending_admin' === $admin_status ) : ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="vrsp_approve_booking" />
            <input type="hidden" name="booking_id" value="<?php echo esc_attr( $booking->ID ); ?>" />
            <?php wp_nonce_field( 'vrsp-approve-booking_' . $booking->ID ); ?>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Approve &amp; Trigger Check-In', 'vr-single-property' ); ?></button>
        </form>
    <?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
<?php endif; ?>
</tbody>
</table>
</div>
