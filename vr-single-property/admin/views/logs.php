<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'System Logs', 'vr-single-property' ); ?></h1>
<table class="widefat">
<thead>
<tr>
<th><?php esc_html_e( 'Timestamp', 'vr-single-property' ); ?></th>
<th><?php esc_html_e( 'Message', 'vr-single-property' ); ?></th>
</tr>
</thead>
<tbody>
<?php if ( empty( $logs ) ) : ?>
<tr><td colspan="2"><?php esc_html_e( 'No logs recorded yet.', 'vr-single-property' ); ?></td></tr>
<?php else :
foreach ( $logs as $log ) :
$context = json_decode( $log['content'], true );
?>
<tr>
<td><?php echo esc_html( $log['created'] ); ?></td>
<td>
<strong><?php echo esc_html( $log['title'] ); ?></strong>
<?php if ( $context ) : ?>
<pre><?php echo esc_html( wp_json_encode( $context, JSON_PRETTY_PRINT ) ); ?></pre>
<?php endif; ?>
</td>
</tr>
<?php endforeach; endif; ?>
</tbody>
</table>
</div>
