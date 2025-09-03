<?php


namespace MemberPressCoursesCopilot\Utilities;

/**
 * Helper utility class
 *
 * Collection of utility methods for the plugin
 *
 * @package MemberPressCoursesCopilot\Utilities
 * @since   1.0.0
 */
final class Helper
{
    /**
     * Get plugin instance
     *
     * @return \MemberPressCoursesCopilot\Plugin
     */
    public static function getPlugin(): \MemberPressCoursesCopilot\Plugin
    {
        return \MemberPressCoursesCopilot\Plugin::instance();
    }

    /**
     * Check if debug mode is enabled
     *
     * @return boolean
     */
    public static function isDebugMode(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if we're in development environment
     *
     * @return boolean
     */
    public static function isDevelopment(): bool
    {
        return defined('WP_ENVIRONMENT_TYPE') && WP_ENVIRONMENT_TYPE === 'development';
    }

    /**
     * Get MemberPress member data
     *
     * @param  integer $user_id User ID
     * @return array<string, mixed>|null
     */
    public static function getMemberData(int $user_id): ?array
    {
        if (!class_exists('MeprUser')) {
            return null;
        }

        $user = new \MeprUser($user_id);
        if (!$user->ID) {
            return null;
        }

        return [
            'id'                   => $user->ID,
            'email'                => $user->user_email,
            'username'             => $user->user_login,
            'first_name'           => $user->first_name,
            'last_name'            => $user->last_name,
            'display_name'         => $user->display_name,
            'active_memberships'   => $user->active_product_subscriptions('ids'),
            'lifetime_memberships' => $user->lifetime_subscriptions('ids'),
            'expired_memberships'  => $user->expired_product_subscriptions('ids'),
        ];
    }

    /**
     * Get MemberPress Courses data for a user
     *
     * @param  integer $user_id User ID
     * @return array<string, mixed>
     */
    public static function getUserCoursesData(int $user_id): array
    {
        if (!class_exists('MpcsUser')) {
            return [];
        }

        $courses_user = new \MpcsUser($user_id);

        return [
            'enrolled_courses'    => $courses_user->courses('ids'),
            'completed_courses'   => $courses_user->completed_courses('ids'),
            'in_progress_courses' => $courses_user->courses_in_progress('ids'),
        ];
    }

    /**
     * Format file size in human readable format
     *
     * @param  integer $size      Size in bytes
     * @param  integer $precision Decimal precision
     * @return string
     */
    public static function formatFileSize(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }

        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Generate a secure random string
     *
     * @param  integer $length String length
     * @return string
     */
    public static function generateRandomString(int $length = 32): string
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes($length / 2));
        }

        // Fallback for older PHP versions
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }

    /**
     * Validate email address
     *
     * @param  string $email Email to validate
     * @return boolean
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate URL
     *
     * @param  string $url URL to validate
     * @return boolean
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Get current timestamp in WordPress timezone
     *
     * @return integer
     */
    public static function getCurrentTimestamp(): int
    {
        return time();
    }

    /**
     * Format date according to WordPress settings
     *
     * @param  integer|string $timestamp    Timestamp or date string
     * @param  boolean        $include_time Whether to include time
     * @return string
     */
    public static function formatDate(int|string $timestamp, bool $include_time = false): string
    {
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }

        $date_format = get_option('date_format');
        if ($include_time) {
            $date_format .= ' ' . get_option('time_format');
        }

        return date_i18n($date_format, $timestamp);
    }

    /**
     * Truncate string to specified length
     *
     * @param  string  $string String to truncate
     * @param  integer $length Maximum length
     * @param  string  $suffix Suffix to append if truncated
     * @return string
     */
    public static function truncateString(string $string, int $length, string $suffix = '...'): string
    {
        if (strlen($string) <= $length) {
            return $string;
        }

        return substr($string, 0, $length - strlen($suffix)) . $suffix;
    }

    /**
     * Convert array to query string
     *
     * @param  array<string, mixed> $data Array data
     * @return string
     */
    public static function arrayToQueryString(array $data): string
    {
        return http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Deep merge arrays
     *
     * @param  array<mixed> ...$arrays Arrays to merge
     * @return array<mixed>
     */
    public static function arrayMergeDeep(array ...$arrays): array
    {
        $result = [];

        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_array($value) && isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::arrayMergeDeep($result[$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Check if string starts with substring
     *
     * @param  string $haystack The string to search in
     * @param  string $needle   The substring to search for
     * @return boolean
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * Check if string ends with substring
     *
     * @param  string $haystack The string to search in
     * @param  string $needle   The substring to search for
     * @return boolean
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * Convert string to slug format
     *
     * @param  string $string String to convert
     * @return string
     */
    public static function toSlug(string $string): string
    {
        return sanitize_title($string);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    public static function getClientIp(): string
    {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
