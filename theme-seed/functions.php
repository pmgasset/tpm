<?php
add_action( 'wp_enqueue_scripts', function () {
wp_enqueue_style( 'vrsp-child', get_stylesheet_uri(), [ 'twentytwentyfour-style' ], '0.1.0' );
} );
