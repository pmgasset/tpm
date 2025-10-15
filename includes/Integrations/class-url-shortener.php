<?php
namespace VRSP\Integrations;

use VRSP\Settings;
use VRSP\Utilities\Logger;

use function esc_url_raw;
use function is_wp_error;
use function rtrim;
use function trim;
use function wp_json_encode;
use function wp_remote_post;
use function wp_remote_retrieve_body;
use function wp_remote_retrieve_response_code;

/**
 * Custom URL shortener integration backed by the Cloudflare Worker.
 */
class UrlShortener {
    private $settings;
    private $logger;
    /** @var array<string, string> */
    private $cache = [];

    public function __construct( Settings $settings, Logger $logger ) {
        $this->settings = $settings;
        $this->logger   = $logger;
    }

    public function shorten( string $url ): string {
        $url = trim( $url );
        if ( '' === $url ) {
            return '';
        }

        $endpoint = trim( (string) $this->settings->get( 'shortener_endpoint', '' ) );
        if ( '' === $endpoint ) {
            return $url;
        }

        $cache_key = md5( $url . '|' . $endpoint );
        if ( isset( $this->cache[ $cache_key ] ) ) {
            return $this->cache[ $cache_key ];
        }

        $request_url = $this->build_request_url( $endpoint );
        $headers     = [ 'Content-Type' => 'application/json' ];

        $token = trim( (string) $this->settings->get( 'shortener_api_key', '' ) );
        if ( '' !== $token ) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_post(
            $request_url,
            [
                'headers' => $headers,
                'body'    => wp_json_encode( [ 'url' => $url ] ),
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) ) {
            $this->logger->warning( 'URL shortener request failed.', [ 'error' => $response->get_error_message() ] );
            return $url;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            $this->logger->warning( 'URL shortener returned non-success status.', [ 'status' => $code ] );
            return $url;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        if ( ! is_array( $data ) || empty( $data['short_url'] ) ) {
            $this->logger->warning( 'URL shortener response missing short_url.', [ 'response' => $body ] );
            return $url;
        }

        $short = esc_url_raw( (string) $data['short_url'] );
        if ( '' === $short ) {
            return $url;
        }

        $this->cache[ $cache_key ] = $short;
        return $short;
    }

    private function build_request_url( string $endpoint ): string {
        $endpoint = rtrim( $endpoint, '/' );
        $shorten  = '/shorten';

        if ( substr( $endpoint, -strlen( $shorten ) ) === $shorten ) {
            return $endpoint;
        }

        return $endpoint . $shorten;
    }
}
