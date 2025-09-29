<?php
namespace VRSP\Rules;

use VRSP\Settings;

/**
 * Wrapper around business rules configuration.
 */
class BusinessRules {
private $settings;

public function __construct( Settings $settings ) {
$this->settings = $settings;
}

public function get_rules(): array {
return $this->settings->get_business_rules();
}

public function update_rules( array $rules ): void {
$current = $this->settings->sanitize( [ 'business_rules' => $rules ] );
$stored  = get_option( Settings::OPTION_KEY, [] );
$stored['business_rules'] = $current['business_rules'];
update_option( Settings::OPTION_KEY, $stored );
$this->settings->refresh();
}
}
