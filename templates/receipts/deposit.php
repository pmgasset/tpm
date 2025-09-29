<table style="width:100%; border-collapse:collapse; font-family:Arial, sans-serif;">
<tr>
<th align="left" style="padding:8px; border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Description', 'vr-single-property' ); ?></th>
<th align="right" style="padding:8px; border-bottom:1px solid #e2e8f0;"><?php esc_html_e( 'Amount', 'vr-single-property' ); ?></th>
</tr>
<tr>
<td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php esc_html_e( 'Booking Deposit', 'vr-single-property' ); ?></td>
<td style="padding:8px; border-bottom:1px solid #f1f5f9;" align="right"><?php echo esc_html( $quote['currency'] ?? 'USD' ); ?> <?php echo esc_html( number_format_i18n( $quote['deposit'] ?? 0, 2 ) ); ?></td>
</tr>
<tr>
<td style="padding:8px; border-bottom:1px solid #f1f5f9;"><?php esc_html_e( 'Balance Due', 'vr-single-property' ); ?></td>
<td style="padding:8px; border-bottom:1px solid #f1f5f9;" align="right"><?php echo esc_html( $quote['currency'] ?? 'USD' ); ?> <?php echo esc_html( number_format_i18n( $quote['balance'] ?? 0, 2 ) ); ?></td>
</tr>
</table>
