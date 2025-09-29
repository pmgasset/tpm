<?php
namespace VRSP\Integrations;

use DateTimeImmutable;
use VRSP\Settings;
use VRSP\Utilities\Logger;

/**
 * iCal import/export handler.
 */
class IcalSync {
private $settings;
private $logger;

public function __construct( Settings $settings, Logger $logger ) {
$this->settings = $settings;
$this->logger   = $logger;

add_action( 'init', [ $this, 'register_rewrite' ] );
add_action( 'template_redirect', [ $this, 'maybe_output_calendar' ] );
}

public function register_rewrite(): void {
add_rewrite_rule( '^vrsp-calendar/(.+)\.ics$', 'index.php?vrsp_calendar_token=$1', 'top' );
add_rewrite_tag( '%vrsp_calendar_token%', '([^&]+)' );
}

public function sync(): void {
$urls = (array) $this->settings->get( 'ical_import_urls', [] );
if ( empty( $urls ) ) {
return;
}

$events = [];

foreach ( $urls as $url ) {
$response = wp_remote_get( $url, [ 'timeout' => 20 ] );
if ( is_wp_error( $response ) ) {
$this->logger->error( 'Failed to download iCal feed.', [ 'url' => $url, 'error' => $response->get_error_message() ] );
continue;
}

$body = wp_remote_retrieve_body( $response );
$events = array_merge( $events, $this->parse_ical( $body ) );
}

update_option( 'vrsp_imported_ical_events', $events );
$this->logger->info( 'iCal feeds synchronised.', [ 'count' => count( $events ) ] );
}

public function maybe_output_calendar(): void {
$token = get_query_var( 'vrsp_calendar_token' );
if ( ! $token ) {
return;
}

if ( $token !== $this->settings->get( 'ical_export_token', '' ) ) {
status_header( 403 );
echo esc_html__( 'Invalid calendar token.', 'vr-single-property' );
exit;
}

header( 'Content-Type: text/calendar; charset=utf-8' );
header( 'Content-Disposition: attachment; filename="vr-single-property.ics"' );

echo $this->build_calendar();
exit;
}

private function build_calendar(): string {
$events   = $this->get_booking_events();
$output[] = 'BEGIN:VCALENDAR';
$output[] = 'VERSION:2.0';
$output[] = 'PRODID:-//VR Single Property//EN';

foreach ( $events as $event ) {
$output[] = 'BEGIN:VEVENT';
$output[] = 'UID:' . $event['uid'];
$output[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z', $event['created'] );
$output[] = 'DTSTART;VALUE=DATE:' . gmdate( 'Ymd', $event['start'] );
$output[] = 'DTEND;VALUE=DATE:' . gmdate( 'Ymd', $event['end'] );
$output[] = 'SUMMARY:' . $this->escape_line( $event['summary'] );
$output[] = 'DESCRIPTION:' . $this->escape_line( $event['description'] );
$output[] = 'END:VEVENT';
}

$output[] = 'END:VCALENDAR';

return implode( "\r\n", $output );
}

private function get_booking_events(): array {
$bookings = get_posts(
[
'post_type'      => 'vrsp_booking',
'post_status'    => [ 'publish', 'draft' ],
'posts_per_page' => 200,
]
);

$events = [];

foreach ( $bookings as $booking ) {
$arrival   = strtotime( get_post_meta( $booking->ID, '_vrsp_arrival', true ) );
$departure = strtotime( get_post_meta( $booking->ID, '_vrsp_departure', true ) );
if ( ! $arrival || ! $departure ) {
continue;
}

$events[] = [
'uid'         => $booking->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
'created'     => strtotime( $booking->post_date_gmt ),
'changed'     => strtotime( $booking->post_modified_gmt ),
'start'       => $arrival,
'end'         => $departure,
'summary'     => get_the_title( $booking ),
'description' => sprintf( 'Guests: %s', get_post_meta( $booking->ID, '_vrsp_guests', true ) ),
];
}

$imports = (array) get_option( 'vrsp_imported_ical_events', [] );

return array_merge( $events, $imports );
}

private function parse_ical( string $content ): array {
$events = [];
$blocks = explode( 'BEGIN:VEVENT', $content );
foreach ( $blocks as $block ) {
if ( false === strpos( $block, 'END:VEVENT' ) ) {
continue;
}

$event = [];
$lines = preg_split( "/\r?\n/", $block );
foreach ( $lines as $line ) {
if ( empty( $line ) ) {
continue;
}

if ( false !== strpos( $line, ':' ) ) {
[ $key, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );
$key = strtoupper( $key );
$event[ $key ] = $value;
}
}

if ( empty( $event['DTSTART'] ) || empty( $event['DTEND'] ) ) {
continue;
}

$events[] = [
'uid'         => $event['UID'] ?? md5( wp_json_encode( $event ) ),
'created'     => isset( $event['DTSTAMP'] ) ? strtotime( $event['DTSTAMP'] ) : time(),
'changed'     => isset( $event['LAST-MODIFIED'] ) ? strtotime( $event['LAST-MODIFIED'] ) : time(),
'start'       => strtotime( $event['DTSTART'] ),
'end'         => strtotime( $event['DTEND'] ),
'summary'     => $event['SUMMARY'] ?? '',
'description' => $event['DESCRIPTION'] ?? '',
];
}

return $events;
}

private function escape_line( string $line ): string {
$line = wp_strip_all_tags( $line );
$line = str_replace( [ '\\', ';', ',' ], [ '\\\\', '\\;', '\\,' ], $line );
return $line;
}
}
