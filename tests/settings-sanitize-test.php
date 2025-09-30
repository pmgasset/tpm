<?php
declare(strict_types=1);

namespace {
    $GLOBALS['__wp_options'] = [];

    function did_action($hook): int {
        return 0;
    }

    function __($text) {
        return $text;
    }

    function wp_generate_password($length = 12, $special_chars = true): string {
        return str_repeat('a', (int) $length);
    }

    function update_option($key, $value) {
        $GLOBALS['__wp_options'][$key] = $value;
        return true;
    }

    function get_option($key, $default = false) {
        return $GLOBALS['__wp_options'][$key] ?? $default;
    }

    function wp_parse_args($args, $defaults = []) {
        if (is_object($args)) {
            $args = get_object_vars($args);
        } elseif (!is_array($args)) {
            parse_str((string) $args, $args);
        }

        if (!is_array($args)) {
            $args = [];
        }

        if (!is_array($defaults)) {
            $defaults = [];
        }

        return array_merge($defaults, $args);
    }

    function wp_list_pluck($list, $field): array {
        $result = [];

        foreach ((array) $list as $key => $value) {
            if (is_array($value) && array_key_exists($field, $value)) {
                $result[$key] = $value[$field];
            } elseif (is_object($value) && isset($value->$field)) {
                $result[$key] = $value->$field;
            }
        }

        return $result;
    }

    function esc_url_raw($url) {
        return trim((string) $url);
    }

    function sanitize_text_field($text) {
        $text = (string) $text;
        $text = strip_tags($text);
        $text = preg_replace('/[\r\n\t]+/', ' ', $text);
        return trim($text);
    }

    function sanitize_textarea_field($text) {
        return trim((string) $text);
    }

    function sanitize_email($email) {
        $filtered = filter_var($email, FILTER_VALIDATE_EMAIL);
        return $filtered !== false ? $filtered : '';
    }

    function wp_kses_post($text) {
        return (string) $text;
    }

    function absint($value): int {
        return (int) abs($value);
    }
}

namespace VRSP {
    require __DIR__ . '/../includes/class-settings.php';
}

namespace {
    use VRSP\Settings;

    update_option('vrsp_settings', [
        'sms_templates' => [
            'booking_deposit' => 'Stored deposit message',
            'booking_balance' => 'Stored balance message',
        ],
        'email_templates' => [
            'booking_deposit' => '<p>Stored deposit email</p>',
            'booking_balance' => '<p>Stored balance email</p>',
        ],
        'coupons' => [
            [
                'code' => 'SAVE10',
                'type' => 'percent_total',
                'amount' => 10,
                'max_redemptions' => 0,
                'valid_from' => '',
                'valid_to' => '',
            ],
        ],
        'checkin_email' => 'stored@example.com',
    ]);

    update_option('vrsp_coupon_usage', [
        'SAVE10' => 2,
        'EXPIRED' => 1,
    ]);

    $settings = new Settings();

    $partial = [
        'sms_templates' => [
            'booking_approved' => "Updated approved message  ",
        ],
        'checkin_email' => 'updated@example.com',
    ];

    $sanitized = $settings->sanitize($partial);

    if ($sanitized['sms_templates']['booking_deposit'] !== 'Stored deposit message') {
        throw new \RuntimeException('Existing SMS templates should be preserved when not provided.');
    }

    if ($sanitized['sms_templates']['booking_approved'] !== 'Updated approved message') {
        throw new \RuntimeException('Submitted SMS template should be sanitized.');
    }

    if ($sanitized['email_templates']['booking_balance'] !== '<p>Stored balance email</p>') {
        throw new \RuntimeException('Email templates not present in the submission should retain stored values.');
    }

    if ($sanitized['checkin_email'] !== 'updated@example.com') {
        throw new \RuntimeException('Submitted check-in email should be sanitized.');
    }

    if (empty($sanitized['coupons']) || $sanitized['coupons'][0]['code'] !== 'SAVE10') {
        throw new \RuntimeException('Stored coupons should remain when not updated.');
    }

    $usage = get_option('vrsp_coupon_usage');
    if (!isset($usage['SAVE10']) || isset($usage['EXPIRED'])) {
        throw new \RuntimeException('Coupon usage should be synced to the final coupon list.');
    }

    if ($sanitized['currency'] !== 'USD') {
        throw new \RuntimeException('Currency should remain forced to USD.');
    }

    fwrite(STDOUT, "Settings sanitization test passed\n");
}
