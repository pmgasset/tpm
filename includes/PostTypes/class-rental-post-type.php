<?php
namespace VRSP\PostTypes;

/**
 * Rental post type.
 */
class RentalPostType extends BasePostType {
public static function get_key(): string {
return 'vrsp_rental';
}

public static function register(): void {
add_action( 'init', [ static::class, 'register_post_type' ] );
}

    public static function register_post_type(): void {
        register_post_type(
            self::get_key(),
            [
                'labels'       => self::get_labels(),
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
}
