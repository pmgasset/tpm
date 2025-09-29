<?php
namespace VRSP;

use VRSP\Admin\Menu as AdminMenu;
use VRSP\Blocks\ListingBlock;
use VRSP\Integrations\CheckinApp;
use VRSP\Integrations\IcalSync;
use VRSP\Integrations\SmsGateway;
use VRSP\Integrations\StripeGateway;
use VRSP\PostTypes\BookingPostType;
use VRSP\PostTypes\BasePostType;
use VRSP\PostTypes\LogPostType;
use VRSP\PostTypes\RentalPostType;
use VRSP\Rules\BusinessRules;
use VRSP\Utilities\CronManager;
use VRSP\Utilities\Logger;
use VRSP\Utilities\PricingEngine;
use VRSP\Utilities\RestRoutes;
use VRSP\Utilities\TemplateLoader;

/**
 * Main plugin bootstrap.
 */
class Plugin {
/**
 * Singleton instance.
 *
 * @var Plugin|null
 */
private static $instance = null;

/**
 * Settings manager.
 *
 * @var Settings
 */
private $settings;

/**
 * Logger instance.
 *
 * @var Logger
 */
private $logger;

/**
 * Accessor for singleton.
 */
public static function get_instance(): Plugin {
if ( null === self::$instance ) {
self::$instance = new self();
}

return self::$instance;
}

/**
 * Activation hook.
 */
    public static function activate(): void {
        self::get_instance();
        RentalPostType::register_post_type();
        BasePostType::flush_rewrite();
        CronManager::activate();
    }

/**
 * Deactivation hook.
 */
    public static function deactivate(): void {
        CronManager::deactivate();
        BasePostType::flush_rewrite();
    }

/**
 * Constructor.
 */
private function __construct() {
$this->settings = new Settings();
$this->logger   = new Logger();

RentalPostType::register();
BookingPostType::register();
LogPostType::register();

$this->boot_modules();

add_action( 'init', [ $this, 'load_textdomain' ] );
}

/**
 * Load text domain.
 */
public function load_textdomain(): void {
load_plugin_textdomain( 'vr-single-property', false, dirname( plugin_basename( VRSP_PLUGIN_FILE ) ) . '/languages/' );
}

/**
 * Boot supporting modules.
 */
private function boot_modules(): void {
$pricing     = new PricingEngine( $this->settings, $this->logger );
$template    = new TemplateLoader();
$rules       = new BusinessRules( $this->settings );
$stripe      = new StripeGateway( $this->settings, $this->logger, $pricing );
$ical        = new IcalSync( $this->settings, $this->logger );
$sms         = new SmsGateway( $this->settings, $this->logger );
$checkin_app = new CheckinApp( $this->settings, $this->logger );

new AdminMenu( $this->settings, $this->logger, $pricing, $rules, $stripe, $ical, $sms );
new RestRoutes( $this->settings, $pricing, $stripe, $ical, $sms, $checkin_app, $rules, $this->logger );
new CronManager( $this->settings, $this->logger, $stripe, $ical, $sms, $checkin_app );
new ListingBlock( $this->settings, $pricing, $stripe, $rules, $this->logger, $template );
}

/**
 * Settings accessor.
 */
public function get_settings(): Settings {
return $this->settings;
}

/**
 * Logger accessor.
 */
public function get_logger(): Logger {
return $this->logger;
}
}
