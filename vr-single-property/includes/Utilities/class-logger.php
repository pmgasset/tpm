<?php
namespace VRSP\Utilities;

use VRSP\PostTypes\LogPostType;

/**
 * Logger writing to custom post type.
 */
class Logger {
/**
 * Log an event.
 */
public function info( string $message, array $context = [] ): void {
$this->write( 'info', $message, $context );
}

public function warning( string $message, array $context = [] ): void {
$this->write( 'warning', $message, $context );
}

public function error( string $message, array $context = [] ): void {
$this->write( 'error', $message, $context );
}

/**
 * Store log entry.
 */
private function write( string $level, string $message, array $context ): void {
$context['level']   = $level;
$context['time']    = gmdate( 'c' );
$context['user_id'] = get_current_user_id();

wp_insert_post(
[
'post_type'   => LogPostType::get_key(),
'post_title'  => sprintf( '[%s] %s', strtoupper( $level ), wp_strip_all_tags( $message ) ),
'post_status' => 'publish',
'post_content'=> wp_json_encode( $context ),
]
);
}

/**
 * Retrieve log entries.
 */
public function get_logs( int $limit = 50 ): array {
$posts = get_posts(
[
'post_type'      => LogPostType::get_key(),
'posts_per_page' => $limit,
'orderby'        => 'date',
'order'          => 'DESC',
]
);

return array_map(
static function ( $post ) {
return [
'title'   => $post->post_title,
'content' => $post->post_content,
'created' => $post->post_date_gmt,
];
},
$posts
);
}
}
