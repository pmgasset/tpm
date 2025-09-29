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
                'labels'       => [
                    'name'               => __( 'Rentals', 'vr-single-property' ),
                    'singular_name'      => __( 'Rental', 'vr-single-property' ),
                    'add_new'            => __( 'Add Rental', 'vr-single-property' ),
                    'add_new_item'       => __( 'Add New Rental', 'vr-single-property' ),
                    'edit_item'          => __( 'Edit Rental', 'vr-single-property' ),
                    'new_item'           => __( 'New Rental', 'vr-single-property' ),
                    'view_item'          => __( 'View Rental', 'vr-single-property' ),
                    'search_items'       => __( 'Search Rentals', 'vr-single-property' ),
                    'not_found'          => __( 'No rentals found', 'vr-single-property' ),
                    'not_found_in_trash' => __( 'No rentals found in Trash', 'vr-single-property' ),
                    'menu_name'          => __( 'Rentals', 'vr-single-property' ),
                ],
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
}
