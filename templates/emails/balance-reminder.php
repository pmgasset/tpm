<h1><?php esc_html_e( 'Balance Capture Reminder', 'vr-single-property' ); ?></h1>
<p><?php printf( esc_html__( 'We will automatically charge the remaining balance of %s on %s using your saved payment method.', 'vr-single-property' ), esc_html( $quote['currency'] ?? 'USD' ), esc_html( $due_date ?? '' ) ); ?></p>
<p><?php esc_html_e( 'If anything has changed, reply to this message so we can help.', 'vr-single-property' ); ?></p>
