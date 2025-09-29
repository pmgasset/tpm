<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
exit;
}

require_once __DIR__ . '/vr-single-property.php';

if ( class_exists( '\\VRSP\\Utilities\\Uninstall' ) ) {
\VRSP\Utilities\Uninstall::run();
}
