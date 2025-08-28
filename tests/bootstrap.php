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
        throw new \Exception('wp_send_json_exit');
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        echo json_encode(['success' => false, 'data' => $data]);
        throw new \Exception('wp_send_json_exit');
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

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        // Simple verification for tests
        return $nonce === wp_create_nonce($action);
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        $nonce = $_REQUEST['_wpnonce'] ?? $_REQUEST['nonce'] ?? '';
        if (!wp_verify_nonce($nonce, $action)) {
            if ($die) {
                wp_die('Security check failed');
            }
            return false;
        }
        return 1;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        global $test_user_caps;
        return isset($test_user_caps[$capability]) && $test_user_caps[$capability];
    }
}

if (!function_exists('wp_die')) {
    function wp_die($message = '', $title = '', $args = []) {
        throw new \Exception('wp_die: ' . $message);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Mock WP_Error class
if (!class_exists('WP_Error')) {
    class WP_Error {
        private $errors = [];
        private $error_data = [];
        
        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->add($code, $message, $data);
            }
        }
        
        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
        
        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->errors[$code]) ? $this->errors[$code][0] : '';
        }
        
        public function get_error_code() {
            $codes = array_keys($this->errors);
            return !empty($codes) ? $codes[0] : '';
        }
    }
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Initialize global test variables
global $test_transients, $test_localized_data, $test_user_caps;
$test_transients = [];
$test_localized_data = [];
$test_user_caps = ['read' => true]; // Default capabilities

// Output test environment info
echo PHP_EOL . 'MemberPress Courses Copilot Test Suite' . PHP_EOL;
echo '======================================' . PHP_EOL;
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'Plugin Root: ' . $pluginRoot . PHP_EOL;
echo PHP_EOL;