<?php
/**
 * PHPUnit bootstrap file for MemberPress Courses Copilot
 * 
 * @package MemberPressCoursesCopilot\Tests
 */

// Define test constants
define('MEMBERPRESS_COURSES_COPILOT_TESTS', true);

// Get plugin root directory
$pluginRoot = dirname(__DIR__);

// Load Composer autoloader
$autoloadFile = $pluginRoot . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    die('Dependencies not installed. Please run "composer install" in the plugin directory.' . PHP_EOL);
}
require_once $autoloadFile;

// Mock WordPress functions that tests might need
if (!function_exists('__')) {
    function __($text, $domain = '') {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_js')) {
    function esc_js($text) {
        return str_replace(
            ['\\', '"', "'", "\n", "\r"],
            ['\\\\', '\"', "\'", '\n', '\r'],
            $text
        );
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action) {
        return md5($action . 'test-nonce');
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return 'http://test.local/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($target) {
        return @mkdir($target, 0777, true);
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'timestamp') {
        if ($type === 'timestamp') {
            return time();
        }
        return date($type);
    }
}

if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        return 1; // Mock user ID for tests
    }
}

if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory() {
        return '/tmp/test-theme';
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo json_encode(['success' => false, 'data' => $data]);
        exit;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $test_transients;
        return isset($test_transients[$transient]) ? $test_transients[$transient] : false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $test_transients;
        if (!isset($test_transients)) {
            $test_transients = [];
        }
        $test_transients[$transient] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $test_transients;
        if (isset($test_transients[$transient])) {
            unset($test_transients[$transient]);
        }
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        // Mock implementation - tests can override if needed
        return true;
    }
}

if (!function_exists('wp_remote_post')) {
    function wp_remote_post($url, $args = []) {
        // Mock implementation for tests
        return [
            'response' => ['code' => 200],
            'body' => json_encode(['success' => true])
        ];
    }
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Initialize global test variables
global $test_transients, $test_localized_data;
$test_transients = [];
$test_localized_data = [];

// Output test environment info
echo PHP_EOL . 'MemberPress Courses Copilot Test Suite' . PHP_EOL;
echo '======================================' . PHP_EOL;
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'Plugin Root: ' . $pluginRoot . PHP_EOL;
echo PHP_EOL;