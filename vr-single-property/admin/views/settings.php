<?php
if ( ! current_user_can( 'manage_options' ) ) {
return;
}

$values = get_option( \VRSP\Settings::OPTION_KEY, $settings->get_defaults() );
?>
<div class="wrap vrsp-admin">
<h1><?php esc_html_e( 'VR Single Property Settings', 'vr-single-property' ); ?></h1>
<form method="post" action="options.php">
<?php settings_fields( 'vrsp_settings' ); ?>
<table class="form-table" role="presentation">
<tr>
<th scope="row"><?php esc_html_e( 'Base Nightly Rate (USD)', 'vr-single-property' ); ?></th>
<td><input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[base_rate]" value="<?php echo esc_attr( $values['base_rate'] ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Tax Rate (decimal)', 'vr-single-property' ); ?></th>
<td><input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[tax_rate]" value="<?php echo esc_attr( $values['tax_rate'] ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Cleaning Fee', 'vr-single-property' ); ?></th>
<td><input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[cleaning_fee]" value="<?php echo esc_attr( $values['cleaning_fee'] ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Damage Fee', 'vr-single-property' ); ?></th>
<td>
<label><input type="checkbox" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[enable_damage_fee]" value="1" <?php checked( ! empty( $values['enable_damage_fee'] ) ); ?> /> <?php esc_html_e( 'Enable optional damage fee', 'vr-single-property' ); ?></label>
<input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[damage_fee]" value="<?php echo esc_attr( $values['damage_fee'] ); ?>" />
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Stripe Mode', 'vr-single-property' ); ?></th>
<td>
<select name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_mode]">
<option value="test" <?php selected( $values['stripe_mode'], 'test' ); ?>><?php esc_html_e( 'Test', 'vr-single-property' ); ?></option>
<option value="live" <?php selected( $values['stripe_mode'], 'live' ); ?>><?php esc_html_e( 'Live', 'vr-single-property' ); ?></option>
</select>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Stripe Keys', 'vr-single-property' ); ?></th>
<td>
<label><?php esc_html_e( 'Test Publishable', 'vr-single-property' ); ?><br />
<input type="text" class="widefat" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_publishable_test]" value="<?php echo esc_attr( $values['stripe_publishable_test'] ); ?>" /></label>
<label><?php esc_html_e( 'Test Secret', 'vr-single-property' ); ?><br />
<input type="password" class="widefat" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_secret_test]" value="<?php echo esc_attr( $values['stripe_secret_test'] ); ?>" /></label>
<label><?php esc_html_e( 'Live Publishable', 'vr-single-property' ); ?><br />
<input type="text" class="widefat" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_publishable_live]" value="<?php echo esc_attr( $values['stripe_publishable_live'] ); ?>" /></label>
<label><?php esc_html_e( 'Live Secret', 'vr-single-property' ); ?><br />
<input type="password" class="widefat" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_secret_live]" value="<?php echo esc_attr( $values['stripe_secret_live'] ); ?>" /></label>
<label><?php esc_html_e( 'Webhook Secret', 'vr-single-property' ); ?><br />
<input type="password" class="widefat" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_webhook_secret]" value="<?php echo esc_attr( $values['stripe_webhook_secret'] ); ?>" /></label>
<label><input type="checkbox" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[stripe_tax_enabled]" value="1" <?php checked( ! empty( $values['stripe_tax_enabled'] ) ); ?> /> <?php esc_html_e( 'Enable Stripe Tax', 'vr-single-property' ); ?></label>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'iCal Import URLs', 'vr-single-property' ); ?></th>
<td>
<textarea name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[ical_import_urls]" rows="4" class="large-text"><?php echo esc_textarea( implode( "\n", (array) $values['ical_import_urls'] ) ); ?></textarea>
<p class="description"><?php esc_html_e( 'One URL per line.', 'vr-single-property' ); ?></p>
</td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'Calendar Export Token', 'vr-single-property' ); ?></th>
<td><input type="text" class="regular-text" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[ical_export_token]" value="<?php echo esc_attr( $values['ical_export_token'] ); ?>" /></td>
</tr>
<tr>
<th scope="row"><?php esc_html_e( 'SMS Credentials', 'vr-single-property' ); ?></th>
<td>
<input type="text" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[sms_api_username]" placeholder="<?php esc_attr_e( 'voip.ms DID', 'vr-single-property' ); ?>" value="<?php echo esc_attr( $values['sms_api_username'] ); ?>" />
<input type="password" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[sms_api_password]" placeholder="<?php esc_attr_e( 'API password', 'vr-single-property' ); ?>" value="<?php echo esc_attr( $values['sms_api_password'] ); ?>" />
<input type="text" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[sms_housekeeper_number]" placeholder="<?php esc_attr_e( 'Housekeeper number', 'vr-single-property' ); ?>" value="<?php echo esc_attr( $values['sms_housekeeper_number'] ); ?>" />
<input type="text" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[sms_owner_number]" placeholder="<?php esc_attr_e( 'Owner number', 'vr-single-property' ); ?>" value="<?php echo esc_attr( $values['sms_owner_number'] ); ?>" />
</td>
</tr>
</table>

<h2><?php esc_html_e( 'Dynamic Pricing Tiers', 'vr-single-property' ); ?></h2>
<table class="widefat">
<thead><tr><th><?php esc_html_e( 'Views From', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Views To', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Uplift %', 'vr-single-property' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $values['pricing_tiers'] as $index => $tier ) : ?>
<tr>
<td><input type="number" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[pricing_tiers][<?php echo esc_attr( $index ); ?>][min]" value="<?php echo esc_attr( $tier['min'] ); ?>" /></td>
<td><input type="number" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[pricing_tiers][<?php echo esc_attr( $index ); ?>][max]" value="<?php echo esc_attr( $tier['max'] ); ?>" /></td>
<td><input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[pricing_tiers][<?php echo esc_attr( $index ); ?>][uplift]" value="<?php echo esc_attr( $tier['uplift'] ); ?>" /></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<p><label><?php esc_html_e( 'Uplift Cap (decimal)', 'vr-single-property' ); ?> <input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[uplift_cap]" value="<?php echo esc_attr( $values['uplift_cap'] ); ?>" /></label></p>

<h2><?php esc_html_e( 'Coupons', 'vr-single-property' ); ?></h2>
<table class="widefat">
<thead><tr><th><?php esc_html_e( 'Code', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Type', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Amount', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Valid From', 'vr-single-property' ); ?></th><th><?php esc_html_e( 'Valid To', 'vr-single-property' ); ?></th></tr></thead>
<tbody>
<?php foreach ( $values['coupons'] as $index => $coupon ) : ?>
<tr>
<td><input type="text" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[coupons][<?php echo esc_attr( $index ); ?>][code]" value="<?php echo esc_attr( $coupon['code'] ); ?>" /></td>
<td>
<select name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[coupons][<?php echo esc_attr( $index ); ?>][type]">
<option value="flat" <?php selected( $coupon['type'], 'flat' ); ?>><?php esc_html_e( 'Flat', 'vr-single-property' ); ?></option>
<option value="percent" <?php selected( $coupon['type'], 'percent' ); ?>><?php esc_html_e( 'Percent', 'vr-single-property' ); ?></option>
</select>
</td>
<td><input type="number" step="0.01" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[coupons][<?php echo esc_attr( $index ); ?>][amount]" value="<?php echo esc_attr( $coupon['amount'] ); ?>" /></td>
<td><input type="date" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[coupons][<?php echo esc_attr( $index ); ?>][valid_from]" value="<?php echo esc_attr( $coupon['valid_from'] ); ?>" /></td>
<td><input type="date" name="<?php echo esc_attr( \VRSP\Settings::OPTION_KEY ); ?>[coupons][<?php echo esc_attr( $index ); ?>][valid_to]" value="<?php echo esc_attr( $coupon['valid_to'] ); ?>" /></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php submit_button(); ?>
</form>
</div>
