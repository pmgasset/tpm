<?php
use VRSP\Settings;

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$email_templates = $settings->get_email_templates();
$sms_templates   = $settings->get_sms_templates();
$checkin_email   = $settings->get_checkin_email();
$option_key      = Settings::OPTION_KEY;
$placeholders    = [
    '{{booking_id}}'        => __( 'Internal booking ID', 'vr-single-property' ),
    '{{first_name}}'         => __( 'Guest first name', 'vr-single-property' ),
    '{{last_name}}'          => __( 'Guest last name', 'vr-single-property' ),
    '{{guest_name}}'         => __( 'Full guest name', 'vr-single-property' ),
    '{{arrival}}'            => __( 'Formatted arrival date', 'vr-single-property' ),
    '{{arrival_raw}}'        => __( 'Arrival date (Y-m-d)', 'vr-single-property' ),
    '{{departure}}'          => __( 'Formatted departure date', 'vr-single-property' ),
    '{{departure_raw}}'      => __( 'Departure date (Y-m-d)', 'vr-single-property' ),
    '{{guests}}'             => __( 'Guest count', 'vr-single-property' ),
    '{{deposit_amount}}'     => __( 'Deposit amount with currency', 'vr-single-property' ),
    '{{deposit_amount_raw}}' => __( 'Deposit amount (number only)', 'vr-single-property' ),
    '{{balance_amount}}'     => __( 'Balance amount with currency', 'vr-single-property' ),
    '{{balance_amount_raw}}' => __( 'Balance amount (number only)', 'vr-single-property' ),
    '{{total_amount}}'       => __( 'Total amount with currency', 'vr-single-property' ),
    '{{total_amount_raw}}'   => __( 'Total amount (number only)', 'vr-single-property' ),
    '{{balance_due_date}}'   => __( 'Balance due date', 'vr-single-property' ),
    '{{balance_due_raw}}'    => __( 'Balance due timestamp', 'vr-single-property' ),
    '{{property_name}}'      => __( 'Property name', 'vr-single-property' ),
    '{{site_url}}'           => __( 'Site URL', 'vr-single-property' ),
    '{{email}}'              => __( 'Guest email address', 'vr-single-property' ),
    '{{phone}}'              => __( 'Guest phone number', 'vr-single-property' ),
    '{{checkin_time}}'       => __( 'Check-in time', 'vr-single-property' ),
    '{{checkout_time}}'      => __( 'Check-out time', 'vr-single-property' ),
    '{{currency}}'           => __( 'Currency code', 'vr-single-property' ),
    '{{status}}'             => __( 'Current booking status', 'vr-single-property' ),
];
?>
<div class="wrap vrsp-admin">
    <h1><?php esc_html_e( 'Messaging Templates', 'vr-single-property' ); ?></h1>
    <p><?php esc_html_e( 'Configure the messages we send to guests and the check-in system when bookings are paid or approved.', 'vr-single-property' ); ?></p>

    <h2><?php esc_html_e( 'Available placeholders', 'vr-single-property' ); ?></h2>
    <p><?php esc_html_e( 'Use these tokens in your templates and they will be replaced with booking details automatically.', 'vr-single-property' ); ?></p>
    <table class="widefat striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Placeholder', 'vr-single-property' ); ?></th>
                <th><?php esc_html_e( 'Description', 'vr-single-property' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $placeholders as $token => $label ) : ?>
                <tr>
                    <td><code><?php echo esc_html( $token ); ?></code></td>
                    <td><?php echo esc_html( $label ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <form method="post" action="options.php">
        <?php settings_fields( 'vrsp_settings' ); ?>

        <h2><?php esc_html_e( 'Deposit Notifications', 'vr-single-property' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Sent when a booking deposit payment is received.', 'vr-single-property' ); ?></p>
        <h3><?php esc_html_e( 'Guest Email', 'vr-single-property' ); ?></h3>
        <?php
        wp_editor(
            $email_templates['booking_deposit'] ?? '',
            'vrsp_email_booking_deposit',
            [
                'textarea_name' => $option_key . '[email_templates][booking_deposit]',
                'textarea_rows' => 8,
                'editor_height' => 200,
            ]
        );
        ?>
        <p class="description"><?php esc_html_e( 'Subject: Deposit received for {{property_name}}', 'vr-single-property' ); ?></p>

        <h3><?php esc_html_e( 'Guest SMS', 'vr-single-property' ); ?></h3>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr( $option_key ); ?>[sms_templates][booking_deposit]"><?php echo esc_textarea( $sms_templates['booking_deposit'] ?? '' ); ?></textarea>

        <h2><?php esc_html_e( 'Balance Notifications', 'vr-single-property' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Sent when the remaining balance is paid in full.', 'vr-single-property' ); ?></p>
        <h3><?php esc_html_e( 'Guest Email', 'vr-single-property' ); ?></h3>
        <?php
        wp_editor(
            $email_templates['booking_balance'] ?? '',
            'vrsp_email_booking_balance',
            [
                'textarea_name' => $option_key . '[email_templates][booking_balance]',
                'textarea_rows' => 8,
                'editor_height' => 200,
            ]
        );
        ?>
        <p class="description"><?php esc_html_e( 'Subject: Booking paid in full for {{property_name}}', 'vr-single-property' ); ?></p>

        <h3><?php esc_html_e( 'Guest SMS', 'vr-single-property' ); ?></h3>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr( $option_key ); ?>[sms_templates][booking_balance]"><?php echo esc_textarea( $sms_templates['booking_balance'] ?? '' ); ?></textarea>

        <h2><?php esc_html_e( 'Booking Approval', 'vr-single-property' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Sent after staff approve a booking from the admin dashboard.', 'vr-single-property' ); ?></p>
        <h3><?php esc_html_e( 'Guest Email', 'vr-single-property' ); ?></h3>
        <?php
        wp_editor(
            $email_templates['booking_approved'] ?? '',
            'vrsp_email_booking_approved',
            [
                'textarea_name' => $option_key . '[email_templates][booking_approved]',
                'textarea_rows' => 8,
                'editor_height' => 200,
            ]
        );
        ?>
        <p class="description"><?php esc_html_e( 'Subject: Booking approved for {{property_name}}', 'vr-single-property' ); ?></p>

        <h3><?php esc_html_e( 'Guest SMS', 'vr-single-property' ); ?></h3>
        <textarea class="large-text" rows="4" name="<?php echo esc_attr( $option_key ); ?>[sms_templates][booking_approved]"><?php echo esc_textarea( $sms_templates['booking_approved'] ?? '' ); ?></textarea>

        <h3><?php esc_html_e( 'Check-in System Email', 'vr-single-property' ); ?></h3>
        <p class="description"><?php esc_html_e( 'We will send the approved booking details to this email address for the check-in integration.', 'vr-single-property' ); ?></p>
        <input type="email" class="regular-text" name="<?php echo esc_attr( $option_key ); ?>[checkin_email]" value="<?php echo esc_attr( $checkin_email ); ?>" />
        <?php
        wp_editor(
            $email_templates['checkin_approved'] ?? '',
            'vrsp_email_checkin_approved',
            [
                'textarea_name' => $option_key . '[email_templates][checkin_approved]',
                'textarea_rows' => 8,
                'editor_height' => 200,
            ]
        );
        ?>
        <p class="description"><?php esc_html_e( 'Subject: Approved booking details for {{property_name}}', 'vr-single-property' ); ?></p>

        <?php submit_button(); ?>
    </form>
</div>
