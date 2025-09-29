<?php
namespace VRSP\PostTypes;

/**
 * Log post type for operational events.
 */
class LogPostType extends BasePostType {
public static function get_key(): string {
return 'vrsp_log';
}

public static function register(): void {
add_action( 'init', [ static::class, 'register_post_type' ] );
}

public static function register_post_type(): void {
register_post_type(
self::get_key(),
[
'labels'       => [
'name'          => __( 'Logs', 'vr-single-property' ),
'singular_name' => __( 'Log Entry', 'vr-single-property' ),
],
'public'       => false,
'show_ui'      => false,
'show_in_menu' => false,
'supports'     => [ 'title', 'editor', 'custom-fields' ],
]
);
}
}
