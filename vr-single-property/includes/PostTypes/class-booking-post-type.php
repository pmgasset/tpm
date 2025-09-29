<?php
namespace VRSP\PostTypes;

/**
 * Booking post type.
 */
class BookingPostType extends BasePostType {
public static function get_key(): string {
return 'vrsp_booking';
}

public static function register(): void {
add_action( 'init', [ static::class, 'register_post_type' ] );
}

public static function register_post_type(): void {
register_post_type(
self::get_key(),
[
'labels'       => [
'name'          => __( 'Bookings', 'vr-single-property' ),
'singular_name' => __( 'Booking', 'vr-single-property' ),
],
'public'       => false,
'show_ui'      => false,
'show_in_menu' => false,
'show_in_rest' => false,
'supports'     => [ 'title', 'custom-fields' ],
]
);
}
}
