<?php
namespace VRSP\Blocks;

use VRSP\Integrations\StripeGateway;
use VRSP\Rules\BusinessRules;
use VRSP\Settings;
use VRSP\Utilities\Logger;
use VRSP\Utilities\PricingEngine;
use VRSP\Utilities\TemplateLoader;

/**
 * Listing block renderer and shortcode.
 */
class ListingBlock {
private $settings;
private $pricing;
private $stripe;
private $rules;
private $logger;
private $templates;

public function __construct( Settings $settings, PricingEngine $pricing, StripeGateway $stripe, BusinessRules $rules, Logger $logger, TemplateLoader $templates ) {
$this->settings  = $settings;
$this->pricing   = $pricing;
$this->stripe    = $stripe;
$this->rules     = $rules;
$this->logger    = $logger;
$this->templates = $templates;

add_action( 'init', [ $this, 'register_block' ] );
add_shortcode( 'vrsp_listing', [ $this, 'shortcode' ] );
}

public function register_block(): void {
register_block_type_from_metadata(
VRSP_PLUGIN_DIR . 'blocks/listing',
[
'render_callback' => [ $this, 'render_block' ],
]
);
}

public function render_block( array $attributes, string $content ): string {
wp_enqueue_style( 'vrsp-public', VRSP_PLUGIN_URL . 'public/css/public.css', [], VRSP_VERSION );
wp_enqueue_script( 'vrsp-listing', VRSP_PLUGIN_URL . 'public/js/listing.js', [], VRSP_VERSION, true );
wp_localize_script(
'vrsp-listing',
'vrspListing',
[
'api'      => esc_url_raw( rest_url( 'vr/v1' ) ),
'currency' => $this->settings->get( 'currency', 'USD' ),
'stripe'   => $this->stripe->get_client_settings(),
'rules'    => $this->rules->get_rules(),
]
);

return $this->templates->render( 'listing/listing.php', [
'content' => $content,
'attrs'   => $attributes,
] );
}

public function shortcode( $atts ): string {
return $this->render_block( [], '' );
}
}
