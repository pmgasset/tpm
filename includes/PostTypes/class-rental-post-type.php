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
'name'          => __( 'Rental', 'vr-single-property' ),
'singular_name' => __( 'Rental', 'vr-single-property' ),
],
'public'       => true,
'show_in_menu' => false,
'show_in_rest' => true,
'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ],
'has_archive'  => false,
]
);
}
}
