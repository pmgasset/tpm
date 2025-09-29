<?php
namespace VRSP\Utilities;

/**
 * Helper for loading plugin templates.
 */
class TemplateLoader {
public function locate( string $template ): string {
$path = trailingslashit( VRSP_PLUGIN_DIR . 'templates/' ) . $template;

return file_exists( $path ) ? $path : '';
}

public function render( string $template, array $context = [] ): string {
$file = $this->locate( $template );

if ( ! $file ) {
return '';
}

extract( $context, EXTR_SKIP );

ob_start();
require $file;

return (string) ob_get_clean();
}
}
