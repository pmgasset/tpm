<?php
declare(strict_types=1);

namespace {
    // Minimal WordPress stubs required by the tests.
    $GLOBALS['__wp_options']   = [];
    $GLOBALS['__wp_posts']     = [];
    $GLOBALS['__wp_post_meta'] = [];
    $GLOBALS['__wp_timezone']  = 'UTC';

    function add_action(...$args): void {}
    function add_rewrite_rule(...$args): void {}
    function add_rewrite_tag(...$args): void {}
    function update_option($key, $value) {
        $GLOBALS['__wp_options'][$key] = $value;
        return true;
    }
    function get_option($key, $default = false) {
        return $GLOBALS['__wp_options'][$key] ?? $default;
    }
    function home_url(): string {
        return 'https://example.test';
    }
    function wp_parse_url($url, $component = -1) {
        return parse_url($url, $component);
    }
    function get_posts($args = []) {
        return $GLOBALS['__wp_posts'] ?? [];
    }
    function get_post_meta($post_id, $key, $single = false) {
        return $GLOBALS['__wp_post_meta'][$post_id][$key] ?? '';
    }
    function get_the_title($post): string {
        return is_object($post) && isset($post->post_title) ? (string) $post->post_title : '';
    }
    function wp_timezone(): \DateTimeZone {
        $timezone = $GLOBALS['__wp_timezone'] ?? 'UTC';
        return new \DateTimeZone($timezone);
    }
    function wp_date(string $format, int $timestamp, ?\DateTimeZone $timezone = null): string {
        $date = new \DateTimeImmutable('@' . $timestamp);
        if ($timezone instanceof \DateTimeZone) {
            $date = $date->setTimezone($timezone);
        }
        return $date->format($format);
    }
    function wp_strip_all_tags(string $text): string {
        return strip_tags($text);
    }
    function status_header($code): void {}
    function esc_html__($text) {
        return $text;
    }
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

namespace VRSP {
    class Settings {
        private $data;

        public function __construct(array $data = []) {
            $this->data = $data;
        }

        public function get(string $key, $default = null) {
            return $this->data[$key] ?? $default;
        }
    }
}

namespace VRSP\Utilities {
    class Logger {
        public function info($message, array $context = []): void {}
        public function error($message, array $context = []): void {}
    }
}

namespace {
    require __DIR__ . '/../includes/Integrations/class-ical-sync.php';

    $sync = new \VRSP\Integrations\IcalSync(new \VRSP\Settings(), new \VRSP\Utilities\Logger());

    $reflection = new ReflectionClass($sync);
    $method = $reflection->getMethod('parse_ical');
    $method->setAccessible(true);

    $ical = <<<ICS
BEGIN:VCALENDAR
BEGIN:VEVENT
UID:event-1
DTSTAMP;VALUE=DATE:20240101T000000Z
LAST-MODIFIED;VALUE=DATE:20240102T000000Z
DTSTART;VALUE=DATE:20240103
DTEND;VALUE=DATE:20240105
SUMMARY;LANGUAGE=en:Blocked Stay
DESCRIPTION;ALTREP="cid:part1":All day booking
END:VEVENT
BEGIN:VEVENT
UID:event-2
DTSTAMP;TZID=UTC:20240105T120000Z
DTSTART;TZID=America/New_York:20240110T120000
DTEND;TZID=America/New_York:20240112T100000
SUMMARY:Timezoned Booking
DESCRIPTION:Partial day booking
END:VEVENT
END:VCALENDAR
ICS;

    $events = $method->invoke($sync, $ical);

    if (count($events) !== 2) {
        throw new RuntimeException('Expected two events to be parsed.');
    }

    if (!isset($events[0]['start'], $events[0]['end']) || !isset($events[1]['start'], $events[1]['end'])) {
        throw new RuntimeException('Parsed events must include start and end timestamps.');
    }

    update_option('vrsp_imported_ical_events', $events);

    $overlap = $sync->is_range_available(new DateTimeImmutable('2024-01-04'), new DateTimeImmutable('2024-01-06'));
    if ($overlap !== false) {
        throw new RuntimeException('Range overlapping the first event should be unavailable.');
    }

    $clear = $sync->is_range_available(new DateTimeImmutable('2024-01-06'), new DateTimeImmutable('2024-01-07'));
    if ($clear !== true) {
        throw new RuntimeException('Range outside imported events should be available.');
    }

    $stored = get_option('vrsp_imported_ical_events');
    if (count($stored) !== 2) {
        throw new RuntimeException('Imported events should be stored via update_option.');
    }

    $previous_timezone = date_default_timezone_get();
    date_default_timezone_set('Pacific/Honolulu');
    $GLOBALS['__wp_timezone'] = 'Europe/Paris';

    $booking = (object) [
        'ID'                => 42,
        'post_title'        => 'Channel Booking',
        'post_date_gmt'     => '2024-08-01 10:00:00',
        'post_modified_gmt' => '2024-08-02 12:00:00',
    ];

    update_option('vrsp_imported_ical_events', []);

    $GLOBALS['__wp_posts'] = [ $booking ];
    $GLOBALS['__wp_post_meta'][42] = [
        '_vrsp_arrival'   => '2024-08-10',
        '_vrsp_departure' => '2024-08-15',
        '_vrsp_guests'    => '4',
    ];

    $sync_with_bookings = new \VRSP\Integrations\IcalSync(new \VRSP\Settings(), new \VRSP\Utilities\Logger());

    $collect_reflection = new ReflectionClass($sync_with_bookings);
    $collect_method = $collect_reflection->getMethod('collect_events');
    $collect_method->setAccessible(true);
    $collected_events = $collect_method->invoke($sync_with_bookings);

    if (count($collected_events) !== 1) {
        throw new RuntimeException('Expected one collected booking event.');
    }

    $expected_timezone = new DateTimeZone('Europe/Paris');
    $expected_start = (new DateTimeImmutable('2024-08-10 00:00:00', $expected_timezone))->getTimestamp();
    $expected_end = (new DateTimeImmutable('2024-08-15 00:00:00', $expected_timezone))->getTimestamp();

    if ($collected_events[0]['start'] !== $expected_start || $collected_events[0]['end'] !== $expected_end) {
        throw new RuntimeException('Collected booking timestamps should align with the WordPress timezone.');
    }

    $window = $sync_with_bookings->get_availability_window(
        new DateTimeImmutable('2024-08-01 00:00:00', new DateTimeZone('UTC')),
        new DateTimeImmutable('2024-08-31 00:00:00', new DateTimeZone('UTC'))
    );

    if (count($window) !== 1) {
        throw new RuntimeException('Expected availability window for the collected booking.');
    }

    if ($window[0]['start'] !== '2024-08-10' || $window[0]['end'] !== '2024-08-15') {
        throw new RuntimeException('Availability window should reflect the WordPress timezone dates.');
    }

    date_default_timezone_set($previous_timezone);
    $GLOBALS['__wp_timezone'] = 'UTC';
    $GLOBALS['__wp_posts'] = [];
    $GLOBALS['__wp_post_meta'] = [];

    fwrite(STDOUT, "All iCal sync tests passed\n");
}
