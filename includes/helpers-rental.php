<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'vrsp_get_primary_rental_id' ) ) {
    /**
     * Retrieve the primary rental ID for single-property workflows.
     */
    function vrsp_get_primary_rental_id(): int {
        $query = new WP_Query(
            [
                'post_type'      => 'vrsp_rental',
                'post_status'    => 'publish',
                'posts_per_page' => 1,
                'orderby'        => [
                    'menu_order' => 'ASC',
                    'date'       => 'ASC',
                ],
                'no_found_rows'  => true,
                'fields'         => 'ids',
            ]
        );

        return $query->have_posts() ? (int) $query->posts[0] : 0;
    }
}
