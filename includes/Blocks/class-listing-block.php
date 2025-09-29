<?php
namespace VRSP\Blocks;

use VRSP\Integrations\StripeGateway;
use VRSP\Rules\BusinessRules;
use VRSP\Settings;
use VRSP\Utilities\Logger;
use VRSP\Utilities\PricingEngine;
use VRSP\Utilities\TemplateLoader;
use WP_Post;

/**
 * Listing block renderer and shortcode.
 */
class ListingBlock {

    private static $is_rendering = false;


    private static $render_depth = 0;



    private static $is_rendering = false;



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
        $block_dir = VRSP_PLUGIN_DIR . 'blocks/listing';

        if ( file_exists( $block_dir . '/block.json' ) && function_exists( 'register_block_type_from_metadata' ) ) {
            register_block_type_from_metadata(
                $block_dir,
                [
                    'render_callback' => [ $this, 'render_block' ],
                ]
            );
            return;
        }

        $handle = 'vrsp-listing-block-editor';

        wp_register_script(
            $handle,
            VRSP_PLUGIN_URL . 'blocks/listing/editor.js',
            [ 'wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n' ],
            VRSP_VERSION,
            true
        );

        $editor_style = VRSP_PLUGIN_DIR . 'blocks/listing/editor.css';

        if ( file_exists( $editor_style ) ) {
            wp_register_style( 'vrsp-listing-block-editor', VRSP_PLUGIN_URL . 'blocks/listing/editor.css', [], VRSP_VERSION );
        }

        $args = [
            'editor_script'   => $handle,
            'render_callback' => [ $this, 'render_block' ],
        ];

        if ( file_exists( $editor_style ) ) {
            $args['editor_style'] = 'vrsp-listing-block-editor';
        }

        register_block_type( 'vrsp/listing', $args );
    }

    public static function is_rendering(): bool {
        return (bool) self::$is_rendering;
    }

    public function render_block( array $attributes, string $content ): string {
        if ( ! self::enter_render() ) {
            return '';
        }


        try {
            $rental = $this->get_primary_rental();

            if ( ! $rental ) {
                return sprintf(
                    '<div class="vrsp-notice vrsp-notice--warning">%s</div>',
                    \esc_html__( 'No rental has been published yet. Please add one under VR Rental → Rentals.', 'vr-single-property' )
                );
            }



        try {
            $rental = $this->get_primary_rental();

            if ( ! $rental ) {
                return sprintf(
                    '<div class="vrsp-notice vrsp-notice--warning">%s</div>',
                    \esc_html__( 'No rental has been published yet. Please add one under VR Rental → Rentals.', 'vr-single-property' )
                );
            }



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





        if ( self::$is_rendering ) {
            return '';
        }

        self::$is_rendering = true;

        try {
            $rental = $this->get_primary_rental();

            if ( ! $rental ) {
                return sprintf(
                    '<div class="vrsp-notice vrsp-notice--warning">%s</div>',
                    \esc_html__( 'No rental has been published yet. Please add one under VR Rental → Rentals.', 'vr-single-property' )
                );
            }

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
                'content'       => $this->prepare_rental_content( $rental ),
                'block_content' => $content,
                'attrs'         => $attributes,
                'rental'        => $rental,
            ] );
        } finally {
            self::leave_render();

        }




                'content' => $content,
                'attrs'   => $attributes,
                'rental'  => $rental,
            ] );
        } finally {
            self::$is_rendering = false;

          }

    }

    public function shortcode( $atts ): string {
        if ( self::is_rendering() ) {




        if ( self::$is_rendering ) {


            return '';
        }

        return $this->render_block( [], '' );
    }

    private function get_primary_rental(): ?WP_Post {
        if ( ! function_exists( 'vrsp_get_primary_rental_id' ) ) {
            return null;
        }

        $rental_id = vrsp_get_primary_rental_id();

        if ( ! $rental_id ) {
            return null;
        }

        $rental = get_post( $rental_id );

        return $rental instanceof WP_Post ? $rental : null;
    }

    private static function enter_render(): bool {

        if ( self::$is_rendering ) {
            return false;
        }

        self::$is_rendering = true;

        if ( self::$render_depth > 0 ) {
            return false;
        }

        self::$render_depth++;


        return true;
    }

    private static function leave_render(): void {

        self::$is_rendering = false;
    }

    public static function is_rendering(): bool {
        return self::$is_rendering;

        if ( self::$render_depth > 0 ) {
            self::$render_depth--;
        }
    }

    public static function is_rendering(): bool {
        return self::$render_depth > 0;

    }

    private function prepare_rental_content( WP_Post $rental ): string {
        $content = (string) $rental->post_content;

        $patterns = [
            '/\[vrsp_listing(?:\s+[^\]]*)?\](?:.*?\[\/vrsp_listing\])?/is',
            '/<!--\s+wp:vrsp\/listing\b.*?-->\s*<!--\s+\/wp:vrsp\/listing\s+-->/is',
            '/<!--\s+\/?wp:vrsp\/listing\b[^>]*-->/i',
        ];

        foreach ( $patterns as $pattern ) {
            $stripped = preg_replace( $pattern, '', $content );

            if ( null !== $stripped ) {
                $content = $stripped;
            }
        }

        $content = trim( $content );

        if ( '' === $content ) {
            return '';
        }

        $filtered = \apply_filters( 'the_content', $content );

        if ( is_string( $filtered ) ) {
            $content = $filtered;
        }

        return \wp_kses_post( $content );
    }
}
