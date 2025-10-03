<?php
namespace VRSP\PostTypes;

use WP_Post;
use function __;
use function did_action;

/**
 * Rental post type.
 */
class RentalPostType extends BasePostType {
    private const NONCE_ACTION = 'vrsp_rental_details';
    private const NONCE_NAME   = '_vrsp_rental_details_nonce';

    private const META_FIELDS = [
        'vrsp_address'        => [
            'type'      => 'textarea',
            'rest_type' => 'string',
        ],
        'vrsp_latitude'       => [
            'type'      => 'float',
            'rest_type' => 'number',
        ],
        'vrsp_longitude'      => [
            'type'      => 'float',
            'rest_type' => 'number',
        ],
        'vrsp_max_guests'     => [
            'type'      => 'int',
            'rest_type' => 'integer',
        ],
        'vrsp_property_type'  => [
            'type'      => 'text',
            'rest_type' => 'string',
        ],
        'vrsp_amenities'      => [
            'type'      => 'list',
            'rest_type' => 'array',
        ],
        'vrsp_regulatory_ids' => [
            'type'      => 'list',
            'rest_type' => 'array',
        ],
    ];

    public static function get_key(): string {
        return 'vrsp_rental';
    }

    public static function register(): void {
        add_action( 'init', [ static::class, 'register_post_type' ] );
        add_action( 'init', [ static::class, 'register_meta' ] );
        add_action( 'add_meta_boxes', [ static::class, 'register_meta_boxes' ] );
        add_action( 'save_post_' . self::get_key(), [ static::class, 'save_meta' ], 10, 2 );
    }

    public static function register_post_type(): void {
        register_post_type(
            self::get_key(),
            [
                'labels'              => self::get_labels(),
                'public'              => true,
                'show_ui'             => true,
                'show_in_menu'        => 'vrsp-dashboard',
                'show_in_admin_bar'   => true,
                'show_in_rest'        => true,
                'supports'            => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
                'has_archive'         => false,
                'rewrite'             => [
                    'slug'       => 'rental',
                    'with_front' => false,
                ],
                'menu_position'       => null,
                'publicly_queryable'  => true,
                'exclude_from_search' => false,
            ]
        );
    }

    private static function get_labels(): array {
        $translate = did_action( 'init' );

        return [
            'name'               => $translate ? __( 'Rentals', 'vr-single-property' ) : 'Rentals',
            'singular_name'      => $translate ? __( 'Rental', 'vr-single-property' ) : 'Rental',
            'add_new'            => $translate ? __( 'Add Rental', 'vr-single-property' ) : 'Add Rental',
            'add_new_item'       => $translate ? __( 'Add New Rental', 'vr-single-property' ) : 'Add New Rental',
            'edit_item'          => $translate ? __( 'Edit Rental', 'vr-single-property' ) : 'Edit Rental',
            'new_item'           => $translate ? __( 'New Rental', 'vr-single-property' ) : 'New Rental',
            'view_item'          => $translate ? __( 'View Rental', 'vr-single-property' ) : 'View Rental',
            'search_items'       => $translate ? __( 'Search Rentals', 'vr-single-property' ) : 'Search Rentals',
            'not_found'          => $translate ? __( 'No rentals found', 'vr-single-property' ) : 'No rentals found',
            'not_found_in_trash' => $translate ? __( 'No rentals found in Trash', 'vr-single-property' ) : 'No rentals found in Trash',
            'menu_name'          => $translate ? __( 'Rentals', 'vr-single-property' ) : 'Rentals',
        ];
    }

    public static function register_meta(): void {
        foreach ( self::META_FIELDS as $meta_key => $config ) {
            register_post_meta(
                self::get_key(),
                $meta_key,
                [
                    'single'            => true,
                    'show_in_rest'      => true,
                    'type'              => $config['rest_type'],
                    'sanitize_callback' => function ( $value ) use ( $meta_key ) {
                        return RentalPostType::sanitize_meta_value( $meta_key, $value );
                    },
                    'auth_callback'     => '__return_true',
                ]
            );
        }
    }

    public static function register_meta_boxes(): void {
        add_meta_box(
            'vrsp_rental_details',
            __( 'Rental Details', 'vr-single-property' ),
            [ static::class, 'render_meta_box' ],
            self::get_key(),
            'normal',
            'high'
        );
    }

    public static function render_meta_box( WP_Post $post ): void {
        wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

        $values = self::get_rental_meta( $post->ID );
        ?>
        <table class="form-table" role="presentation">
            <tbody>
            <tr>
                <th scope="row"><label for="vrsp_address"><?php esc_html_e( 'Street address', 'vr-single-property' ); ?></label></th>
                <td>
                    <textarea class="large-text" rows="3" name="vrsp_meta[vrsp_address]" id="vrsp_address"><?php echo esc_textarea( $values['vrsp_address'] ?? '' ); ?></textarea>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_latitude"><?php esc_html_e( 'Latitude', 'vr-single-property' ); ?></label></th>
                <td><input type="number" step="any" name="vrsp_meta[vrsp_latitude]" id="vrsp_latitude" value="<?php echo esc_attr( null !== $values['vrsp_latitude'] ? $values['vrsp_latitude'] : '' ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_longitude"><?php esc_html_e( 'Longitude', 'vr-single-property' ); ?></label></th>
                <td><input type="number" step="any" name="vrsp_meta[vrsp_longitude]" id="vrsp_longitude" value="<?php echo esc_attr( null !== $values['vrsp_longitude'] ? $values['vrsp_longitude'] : '' ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_max_guests"><?php esc_html_e( 'Maximum guests', 'vr-single-property' ); ?></label></th>
                <td><input type="number" min="0" step="1" name="vrsp_meta[vrsp_max_guests]" id="vrsp_max_guests" value="<?php echo esc_attr( null !== $values['vrsp_max_guests'] ? $values['vrsp_max_guests'] : '' ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_property_type"><?php esc_html_e( 'Property type', 'vr-single-property' ); ?></label></th>
                <td><input type="text" class="regular-text" name="vrsp_meta[vrsp_property_type]" id="vrsp_property_type" value="<?php echo esc_attr( $values['vrsp_property_type'] ?? '' ); ?>" /></td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_amenities"><?php esc_html_e( 'Amenities', 'vr-single-property' ); ?></label></th>
                <td>
                    <textarea class="large-text" rows="4" name="vrsp_meta[vrsp_amenities]" id="vrsp_amenities"><?php echo esc_textarea( implode( "\n", $values['vrsp_amenities'] ?? [] ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Enter one amenity per line.', 'vr-single-property' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="vrsp_regulatory_ids"><?php esc_html_e( 'Regulatory IDs', 'vr-single-property' ); ?></label></th>
                <td>
                    <textarea class="large-text" rows="3" name="vrsp_meta[vrsp_regulatory_ids]" id="vrsp_regulatory_ids"><?php echo esc_textarea( implode( "\n", $values['vrsp_regulatory_ids'] ?? [] ) ); ?></textarea>
                    <p class="description"><?php esc_html_e( 'Enter one ID per line.', 'vr-single-property' ); ?></p>
                </td>
            </tr>
            </tbody>
        </table>
        <?php
    }

    public static function save_meta( int $post_id, WP_Post $post ): void {
        if ( self::get_key() !== $post->post_type ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $raw = isset( $_POST['vrsp_meta'] ) ? (array) wp_unslash( $_POST['vrsp_meta'] ) : [];

        foreach ( self::META_FIELDS as $meta_key => $config ) {
            $value     = $raw[ $meta_key ] ?? null;
            $sanitized = self::sanitize_meta_value( $meta_key, $value );

            if ( null === $sanitized || ( is_string( $sanitized ) && '' === $sanitized ) || ( is_array( $sanitized ) && [] === $sanitized ) ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, $sanitized );
            }
        }
    }

    public static function get_meta_defaults(): array {
        return [
            'vrsp_address'        => '',
            'vrsp_latitude'       => null,
            'vrsp_longitude'      => null,
            'vrsp_max_guests'     => null,
            'vrsp_property_type'  => '',
            'vrsp_amenities'      => [],
            'vrsp_regulatory_ids' => [],
        ];
    }

    public static function get_rental_meta( int $post_id ): array {
        $defaults = self::get_meta_defaults();

        foreach ( array_keys( self::META_FIELDS ) as $meta_key ) {
            $stored = get_post_meta( $post_id, $meta_key, true );
            $value  = self::sanitize_meta_value( $meta_key, $stored );

            if ( null !== $value ) {
                $defaults[ $meta_key ] = $value;
            }
        }

        return $defaults;
    }

    public static function sanitize_meta_value( string $meta_key, $value ) {
        if ( ! isset( self::META_FIELDS[ $meta_key ] ) ) {
            return null;
        }

        $type = self::META_FIELDS[ $meta_key ]['type'];

        switch ( $type ) {
            case 'textarea':
                $value = is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
                return '' !== $value ? $value : '';
            case 'text':
                $value = is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
                return '' !== $value ? $value : '';
            case 'float':
                if ( is_array( $value ) ) {
                    $value = reset( $value );
                }
                $value = is_scalar( $value ) ? (string) $value : '';
                $value = str_replace( ',', '.', $value );
                if ( '' === trim( $value ) || ! is_numeric( $value ) ) {
                    return null;
                }
                return (float) $value;
            case 'int':
                if ( is_array( $value ) ) {
                    $value = reset( $value );
                }
                $value = is_scalar( $value ) ? (int) $value : 0;
                return $value > 0 ? $value : null;
            case 'list':
                return self::sanitize_list( $value );
        }

        return null;
    }

    /**
     * Sanitize newline-separated list values.
     *
     * @param mixed $value Raw value.
     */
    private static function sanitize_list( $value ): array {
        if ( is_string( $value ) ) {
            $items = preg_split( "/\r?\n/", $value );
        } elseif ( is_array( $value ) ) {
            $items = $value;
        } else {
            $items = [];
        }

        $items = array_map( static function ( $item ) {
            return sanitize_text_field( (string) $item );
        }, $items );

        $items = array_filter( array_map( 'trim', $items ), static function ( $item ) {
            return '' !== $item;
        } );

        return array_values( $items );
    }
}
