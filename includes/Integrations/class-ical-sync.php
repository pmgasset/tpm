<?php
namespace VRSP\Integrations;

use DateTimeImmutable;
use DateTimeZone;
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

    public function get_blocked_ranges(): array {
        $blocked = [];

        $timezone = wp_timezone();

        foreach ( $this->collect_events() as $event ) {
            if ( empty( $event['start'] ) || empty( $event['end'] ) ) {
                continue;
            }

            $blocked[] = [
                'start'  => wp_date( 'Y-m-d', (int) $event['start'], $timezone ),
                'end'    => wp_date( 'Y-m-d', (int) $event['end'], $timezone ),
                'source' => $event['source'] ?? 'internal',
            ];
        }

        return $blocked;
    }

    public function is_range_available( DateTimeImmutable $arrival, DateTimeImmutable $departure ): bool {
        if ( $arrival >= $departure ) {
            return false;
        }

        foreach ( $this->collect_events() as $event ) {
            $start = isset( $event['start'] ) ? (int) $event['start'] : 0;
            $end   = isset( $event['end'] ) ? (int) $event['end'] : 0;

            if ( ! $start || ! $end ) {
                continue;
            }

            // DTEND values from channels represent the checkout date and are non-inclusive.
            $event_start = ( new DateTimeImmutable( '@' . $start ) )->setTimezone( wp_timezone() );
            $event_end   = ( new DateTimeImmutable( '@' . $end ) )->setTimezone( wp_timezone() );

            if ( $arrival < $event_end && $departure > $event_start ) {
                return false;
            }
        }

        return true;
    }

    public function get_availability_window( DateTimeImmutable $from, DateTimeImmutable $to ): array {
        $window = [];
        $events = $this->collect_events();

        $timezone = wp_timezone();

        foreach ( $events as $event ) {
            if ( empty( $event['start'] ) || empty( $event['end'] ) ) {
                continue;
            }

            $event_start = (int) $event['start'];
            $event_end   = (int) $event['end'];

            if ( $event_end < $from->getTimestamp() || $event_start > $to->getTimestamp() ) {
                continue;
            }

            $window[] = [
                'start'  => wp_date( 'Y-m-d', $event_start, $timezone ),
                'end'    => wp_date( 'Y-m-d', $event_end, $timezone ),
                'source' => $event['source'] ?? 'internal',
            ];
        }

        return $window;
    }

    private function build_calendar(): string {
        $events   = $this->collect_events();
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

    private function collect_events(): array {
        $bookings = get_posts(
            [
                'post_type'      => 'vrsp_booking',
                'post_status'    => [ 'publish', 'draft', 'pending' ],
                'posts_per_page' => 200,
            ]
        );

        $events = [];

        $timezone = wp_timezone();

        foreach ( $bookings as $booking ) {
            $arrival_raw   = get_post_meta( $booking->ID, '_vrsp_arrival', true );
            $departure_raw = get_post_meta( $booking->ID, '_vrsp_departure', true );

            $arrival_date = false;
            if ( is_string( $arrival_raw ) && $arrival_raw !== '' ) {
                $arrival_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $arrival_raw, $timezone );
                if ( $arrival_date instanceof DateTimeImmutable ) {
                    $arrival_date = $arrival_date->setTime( 0, 0 );
                }
            }

            $departure_date = false;
            if ( is_string( $departure_raw ) && $departure_raw !== '' ) {
                $departure_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $departure_raw, $timezone );
                if ( $departure_date instanceof DateTimeImmutable ) {
                    $departure_date = $departure_date->setTime( 0, 0 );
                }
            }

            if ( ! $arrival_date instanceof DateTimeImmutable || ! $departure_date instanceof DateTimeImmutable ) {
                continue;
            }

            $events[] = [
                'uid'         => $booking->ID . '@' . wp_parse_url( home_url(), PHP_URL_HOST ),
                'created'     => strtotime( $booking->post_date_gmt ),
                'changed'     => strtotime( $booking->post_modified_gmt ),
                'start'       => $arrival_date->getTimestamp(),
                'end'         => $departure_date->getTimestamp(),
                'summary'     => get_the_title( $booking ),
                'description' => sprintf( 'Guests: %s', get_post_meta( $booking->ID, '_vrsp_guests', true ) ),
                'source'      => 'direct',
            ];
        }

        $imports = (array) get_option( 'vrsp_imported_ical_events', [] );

        foreach ( $imports as $import ) {
            if ( empty( $import['start'] ) || empty( $import['end'] ) ) {
                continue;
            }

            $import['source'] = $import['source'] ?? 'channel';
            $events[]         = $import;
        }

        return $events;
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
        $parameters = [];

        foreach ( $lines as $line ) {
            if ( empty( $line ) ) {
                continue;
            }

            if ( false !== strpos( $line, ':' ) ) {
                [ $raw_key, $value ] = array_map( 'trim', explode( ':', $line, 2 ) );

                $key_parts = explode( ';', $raw_key );
                $key       = $this->normalize_property_name( (string) array_shift( $key_parts ) );

                $event[ $key ] = $value;

                if ( ! empty( $key_parts ) ) {
                    $parameters[ $key ] = $this->parse_property_parameters( $key_parts );
                }
            }
        }

        $start_value = $this->get_event_value( $event, 'DTSTART' );
        $end_value   = $this->get_event_value( $event, 'DTEND' );

        if ( empty( $start_value ) || empty( $end_value ) ) {
            continue;
        }

        $start = $this->parse_event_datetime( $start_value, $parameters['DTSTART'] ?? [] );
        $end   = $this->parse_event_datetime( $end_value, $parameters['DTEND'] ?? [] );

        if ( ! $start instanceof DateTimeImmutable || ! $end instanceof DateTimeImmutable ) {
            continue;
        }

        $events[] = [
            'uid'         => $this->get_event_value( $event, 'UID' ) ?? md5( wp_json_encode( $event ) ),
            'created'     => ( $dtstamp = $this->get_event_value( $event, 'DTSTAMP' ) ) ? strtotime( $dtstamp ) : time(),
            'changed'     => ( $modified = $this->get_event_value( $event, 'LAST-MODIFIED' ) ) ? strtotime( $modified ) : time(),
            'start'       => $start->getTimestamp(),
            'end'         => $end->getTimestamp(),
            'summary'     => $this->get_event_value( $event, 'SUMMARY' ) ?? '',
            'description' => $this->get_event_value( $event, 'DESCRIPTION' ) ?? '',
        ];
    }

    return $events;
}

private function parse_property_parameters( array $parts ): array {
    $parameters = [];

    foreach ( $parts as $part ) {
        $part = trim( (string) $part );

        if ( '' === $part ) {
            continue;
        }

        if ( false !== strpos( $part, '=' ) ) {
            [ $param_key, $param_value ] = explode( '=', $part, 2 );
            $parameters[ strtoupper( trim( $param_key ) ) ] = trim( $param_value );
        } else {
            $parameters[ strtoupper( $part ) ] = true;
        }
    }

    return $parameters;
}

private function parse_event_datetime( string $value, array $parameters ): ?DateTimeImmutable {
    $value_type = isset( $parameters['VALUE'] ) ? strtoupper( (string) $parameters['VALUE'] ) : null;

    if ( 'DATE' === $value_type ) {
        $timezone = wp_timezone();
        $date     = DateTimeImmutable::createFromFormat( '!Ymd', $value, $timezone );

        if ( ! $date instanceof DateTimeImmutable ) {
            return null;
        }

        return $date->setTime( 0, 0 );
    }

    $timezone = null;

    if ( isset( $parameters['TZID'] ) ) {
        try {
            $timezone = new DateTimeZone( (string) $parameters['TZID'] );
        } catch ( \Exception $exception ) {
            return null;
        }
    }

    if ( 'Z' === substr( $value, -1 ) ) {
        $format   = '!Ymd\THis\Z';
        $timezone = new DateTimeZone( 'UTC' );
    } else {
        $format   = '!Ymd\THis';
        $timezone = $timezone ?? wp_timezone();
    }

    $date = DateTimeImmutable::createFromFormat( $format, $value, $timezone );

    if ( ! $date instanceof DateTimeImmutable ) {
        return null;
    }

    return $date;
}

private function normalize_property_name( string $property ): string {
    $property = strtoupper( $property );

    if ( false !== strpos( $property, ';' ) ) {
        [ $property ] = explode( ';', $property, 2 );
    }

    return $property;
}

private function get_event_value( array $event, string $property ) {
    $normalized = $this->normalize_property_name( $property );

    if ( array_key_exists( $normalized, $event ) ) {
        return $event[ $normalized ];
    }

    foreach ( $event as $key => $value ) {
        if ( $this->normalize_property_name( (string) $key ) === $normalized ) {
            return $value;
        }
    }

    return null;
}

private function escape_line( string $line ): string {
$line = wp_strip_all_tags( $line );
$line = str_replace( [ '\\', ';', ',' ], [ '\\\\', '\\;', '\\,' ], $line );
return $line;
}
}
