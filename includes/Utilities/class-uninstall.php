<?php
namespace VRSP\Utilities;

use VRSP\Settings;

/**
 * Handles uninstall cleanup.
 */
class Uninstall {
public static function run(): void {
delete_option( Settings::OPTION_KEY );
delete_option( 'vrsp_imported_ical_events' );
self::delete_posts( 'vrsp_booking' );
self::delete_posts( 'vrsp_log' );
}

private static function delete_posts( string $type ): void {
$posts = get_posts(
[
'post_type'      => $type,
'post_status'    => 'any',
'posts_per_page' => -1,
]
);

foreach ( $posts as $post ) {
wp_delete_post( $post->ID, true );
}
}
}
