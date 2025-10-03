<?php
namespace VRSP\Blocks;

use VRSP\Integrations\StripeGateway;
use VRSP\PostTypes\RentalPostType;
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
    private static $render_depth = 0;

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

        if ( function_exists( 'register_block_type_from_metadata' ) && file_exists( $block_dir . '/block.json' ) ) {
            register_block_type_from_metadata( $block_dir, [ 'render_callback' => [ $this, 'render_block' ] ] );
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
        return self::$render_depth > 0;
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

            $this->enqueue_assets();

            return $this->templates->render(
                'listing/listing.php',
                [
                    'content'       => $this->prepare_rental_content( $rental ),
                    'block_content' => $content,
                    'attrs'         => $attributes,
                    'rental'        => $rental,
                    'schema'        => $this->build_vacation_rental_schema( $rental ),
                ]
            );
        } catch ( \Throwable $exception ) {
            $this->logger->error( 'Failed to render listing block.', [ 'exception' => $exception->getMessage() ] );
            return '';
        } finally {
            self::leave_render();
        }
    }

    public function shortcode( $atts ): string {
        if ( self::is_rendering() ) {
            return '';
        }

        return $this->render_block( [], '' );
    }

    private function enqueue_assets(): void {
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
                'i18n'     => [
                    'availabilityEmpty' => __( 'Your preferred dates are open!', 'vr-single-property' ),
                    'quotePrompt'       => __( 'Select arrival and departure dates to see pricing.', 'vr-single-property' ),
                    'quoteLoading'      => __( 'Calculating pricing…', 'vr-single-property' ),
                    'depositNote'       => __( 'We will automatically charge the saved payment method 7 days prior to arrival for the remaining balance.', 'vr-single-property' ),
                    'fullBalanceNote'   => __( 'Your stay begins soon, so the full balance is due today.', 'vr-single-property' ),
                    'quoteReady'        => __( 'Pricing updated! Review and continue to secure payment.', 'vr-single-property' ),
                    'quoteRequired'     => __( 'Request a quote before continuing to secure payment.', 'vr-single-property' ),
                    'quoteRefresh'      => __( 'Your stay details changed. Updating pricing…', 'vr-single-property' ),
                    'checkoutPreparing' => __( 'Preparing secure checkout…', 'vr-single-property' ),
                    'redirecting'       => __( 'Redirecting to secure checkout…', 'vr-single-property' ),
                    'checkoutDetails'   => __( 'Add guest contact details to continue to secure payment.', 'vr-single-property' ),
                    'paymentFullRequired' => __( 'This stay begins within 7 days. Full payment is required today.', 'vr-single-property' ),
                    'paymentChoice'     => __( 'Pay a 50% deposit today or choose to pay in full.', 'vr-single-property' ),
                    'genericError'      => __( 'Unable to process booking. Please try again.', 'vr-single-property' ),
                ],
            ]
        );
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

    private function prepare_rental_content( WP_Post $rental ): string {
        $content = (string) $rental->post_content;

        $patterns = [
            '/\[vrsp_listing(?:\s+[^\]]*)?\](?:.*?\[\/vrsp_listing\])?/is',
            '/<!--\s+wp:vrsp\/listing\b.*?-->\s*<!--\s+\/wp:vrsp\/listing\s+-->/is',
            '/<!--\s+\/?wp:vrsp\/listing\b[^>]*-->/i',
        ];

        foreach ( $patterns as $pattern ) {
            $maybe = preg_replace( $pattern, '', $content );
            if ( null !== $maybe ) {
                $content = $maybe;
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

    private function build_vacation_rental_schema( WP_Post $rental ): array {
        $permalink = get_permalink( $rental );

        if ( ! $permalink ) {
            return [];
        }

        $meta         = RentalPostType::get_rental_meta( $rental->ID );
        $description  = trim( wp_strip_all_tags( get_the_excerpt( $rental ) ) );
        $raw_content  = trim( wp_strip_all_tags( $rental->post_content ) );
        $description  = $description ?: $raw_content;
        $description  = $description ? wp_trim_words( $description, 55, '…' ) : '';
        $images       = [];
        $featured     = get_the_post_thumbnail_url( $rental, 'full' );

        if ( $featured ) {
            $images[] = esc_url_raw( $featured );
        }

        $attachments = get_attached_media( 'image', $rental->ID );
        foreach ( $attachments as $attachment ) {
            $url = wp_get_attachment_image_url( $attachment->ID, 'full' );
            if ( $url ) {
                $images[] = esc_url_raw( $url );
            }
        }

        $images = array_values( array_unique( array_filter( $images ) ) );

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'VacationRental',
            '@id'      => trailingslashit( $permalink ) . '#vacationRental',
            'name'     => get_the_title( $rental ),
            'url'      => $permalink,
        ];

        if ( $description ) {
            $schema['description'] = $description;
        }

        if ( ! empty( $images ) ) {
            $schema['image'] = $images;
        }

        if ( ! empty( $meta['vrsp_address'] ) ) {
            $address = preg_replace( '/\s*\R+\s*/', ', ', (string) $meta['vrsp_address'] );
            $schema['address'] = [
                '@type'         => 'PostalAddress',
                'streetAddress' => $address,
            ];
        }

        if ( null !== $meta['vrsp_latitude'] && null !== $meta['vrsp_longitude'] ) {
            $schema['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => $meta['vrsp_latitude'],
                'longitude' => $meta['vrsp_longitude'],
            ];
        }

        if ( null !== $meta['vrsp_max_guests'] ) {
            $schema['maximumAttendeeCapacity'] = (int) $meta['vrsp_max_guests'];
        }

        if ( ! empty( $meta['vrsp_property_type'] ) ) {
            $schema['additionalPropertyType'] = $meta['vrsp_property_type'];
        }

        if ( ! empty( $meta['vrsp_amenities'] ) && is_array( $meta['vrsp_amenities'] ) ) {
            $schema['amenityFeature'] = array_map(
                static function ( string $amenity ) {
                    return [
                        '@type' => 'LocationFeatureSpecification',
                        'name'  => $amenity,
                    ];
                },
                $meta['vrsp_amenities']
            );
        }

        if ( ! empty( $meta['vrsp_regulatory_ids'] ) ) {
            $schema['regulatoryID'] = $meta['vrsp_regulatory_ids'];
        }

        $base_rate = (float) $this->settings->get( 'base_rate', 0 );
        $currency  = (string) $this->settings->get( 'currency', 'USD' );

        if ( $base_rate > 0 ) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => number_format( $base_rate, 2, '.', '' ),
                'priceCurrency' => $currency ?: 'USD',
                'availability'  => 'https://schema.org/InStock',
                'url'           => $permalink,
            ];
        }

        $availability_endpoint = esc_url_raw( rest_url( 'vr/v1/availability' ) );
        $booking_endpoint      = esc_url_raw( rest_url( 'vr/v1/booking' ) );

        $actions = [];

        if ( $availability_endpoint ) {
            $actions[] = [
                '@type'       => 'SearchAction',
                'target'      => $availability_endpoint . '?arrival={arrival}&departure={departure}',
                'query-input' => 'required arrival=arrival required departure=departure',
            ];
        }

        if ( $booking_endpoint ) {
            $actions[] = [
                '@type'  => 'ReserveAction',
                'target' => $booking_endpoint,
            ];
        }

        if ( ! empty( $actions ) ) {
            $schema['potentialAction'] = $actions;
        }

        return $schema;
    }

    private static function enter_render(): bool {
        if ( self::$render_depth > 0 ) {
            return false;
        }

        self::$render_depth++;

        return true;
    }

    private static function leave_render(): void {
        if ( self::$render_depth > 0 ) {
            self::$render_depth--;
        }
    }
}
