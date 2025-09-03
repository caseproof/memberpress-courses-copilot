<?php
/**
 * PHPUnit bootstrap file for MemberPress Courses Copilot
 * 
 * @package MemberPressCoursesCopilot\Tests
 */

// Define test constants
define('MEMBERPRESS_COURSES_COPILOT_TESTS', true);

// Suppress error logging to stdout during tests
ini_set('log_errors', '0');
ini_set('display_errors', '0');
error_reporting(E_ALL);

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
        if ($str === null) {
            return '';
        }
        // Remove script tags and their contents completely
        $str = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $str);
        // Remove style tags and their contents completely  
        $str = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $str);
        // Strip all remaining HTML tags
        $str = strip_tags($str);
        // Trim whitespace from beginning and end
        $str = trim($str);
        // Normalize internal whitespace (convert multiple spaces to single space)
        $str = preg_replace('/\s+/', ' ', $str);
        return $str;
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        if ($str === null) {
            return '';
        }
        // Remove script tags and their contents completely
        $str = preg_replace('#<script(.*?)>(.*?)</script>#is', '', $str);
        // Remove style tags and their contents completely  
        $str = preg_replace('#<style(.*?)>(.*?)</style>#is', '', $str);
        // Strip all remaining HTML tags
        $str = strip_tags($str);
        // Normalize line breaks
        $str = str_replace(array("\r\n", "\r"), "\n", $str);
        // Remove control characters except newlines and tabs
        $str = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $str);
        // Trim whitespace from beginning and end
        $str = trim($str);
        // Normalize internal whitespace (convert multiple spaces to single space)
        $str = preg_replace('/\s+/', ' ', $str);
        return $str;
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
        $response = ['success' => false];
        
        // Handle the ApiResponse format where data contains an 'error' key
        if (is_array($data) && isset($data['error'])) {
            $response['error'] = $data['error'];
        } else {
            // Fallback to legacy format
            $response['data'] = $data;
        }
        
        echo json_encode($response);
        throw new \Exception('wp_send_json_exit');
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        if (is_array($response) && isset($response['response']['code'])) {
            return $response['response']['code'];
        }
        return '';
    }
}

if (!function_exists('status_header')) {
    function status_header($code) {
        // Mock function for tests - doesn't actually set headers
        return;
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

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        // Simple HTML sanitization for tests - allows basic tags
        if ($data === null) {
            return '';
        }
        return strip_tags($data, '<p><br><b><strong><i><em><ul><ol><li><a><h1><h2><h3><h4><h5><h6><div>');
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        if ($url === null || $url === false) {
            return '';
        }
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        if ($email === null || $email === false) {
            return '';
        }
        // Remove all illegal characters from email
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '';
        }
        return $email;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs(intval($value));
    }
}

if (!function_exists('status_header')) {
    function status_header($code, $description = '') {
        // Mock implementation for tests - just log it
        global $test_status_headers;
        if (!isset($test_status_headers)) {
            $test_status_headers = [];
        }
        $test_status_headers[] = $code;
        return null;
    }
}

if (!function_exists('get_post')) {
    function get_post($id = null, $output = OBJECT, $filter = 'raw') {
        // Mock implementation for tests
        if ($id === null) {
            return null;
        }
        
        // Provide specific test data for known IDs
        switch ($id) {
            case 123:
                $post = (object) [
                    'ID' => 123,
                    'post_title' => 'Test Lesson',
                    'post_content' => 'This is test lesson content',
                    'post_type' => 'mpcs-lesson',
                    'post_excerpt' => 'Test excerpt',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_date' => date('Y-m-d H:i:s'),
                    'post_date_gmt' => gmdate('Y-m-d H:i:s'),
                    'post_modified' => date('Y-m-d H:i:s'),
                    'post_modified_gmt' => gmdate('Y-m-d H:i:s')
                ];
                break;
            case 456:
                $post = (object) [
                    'ID' => 456,
                    'post_title' => 'Test Course',
                    'post_content' => 'This is test course content',
                    'post_type' => 'mpcs-course',
                    'post_status' => 'publish',
                    'post_author' => 1,
                    'post_date' => date('Y-m-d H:i:s'),
                    'post_date_gmt' => gmdate('Y-m-d H:i:s'),
                    'post_modified' => date('Y-m-d H:i:s'),
                    'post_modified_gmt' => gmdate('Y-m-d H:i:s')
                ];
                break;
            default:
                return null;
        }
        
        if ($output === ARRAY_A) {
            return (array) $post;
        } elseif ($output === ARRAY_N) {
            return array_values((array) $post);
        }
        
        return $post;
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
        
        public function add_data($data, $code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            if (!empty($code)) {
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
        
        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return isset($this->error_data[$code]) ? $this->error_data[$code] : '';
        }
        
        public function get_error_messages($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            
            if (isset($this->errors[$code])) {
                return $this->errors[$code];
            }
            
            return [];
        }
        
        public function has_errors() {
            return !empty($this->errors);
        }
    }
}

if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

// Define WordPress constants for get_post output types
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('ARRAY_N')) {
    define('ARRAY_N', 'ARRAY_N');
}

// Additional WordPress mocks that may be needed
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir($time = null, $create_dir = true, $refresh_cache = false) {
        $temp_dir = sys_get_temp_dir() . '/wordpress-test-uploads';
        if (!file_exists($temp_dir)) {
            @mkdir($temp_dir, 0777, true);
        }
        
        return [
            'path'    => $temp_dir,
            'url'     => 'http://test.local/wp-content/uploads',
            'subdir'  => '',
            'basedir' => $temp_dir,
            'baseurl' => 'http://test.local/wp-content/uploads',
            'error'   => false
        ];
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $test_options;
        if (!isset($test_options)) {
            $test_options = [];
        }
        return isset($test_options[$option]) ? $test_options[$option] : $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $test_options;
        if (!isset($test_options)) {
            $test_options = [];
        }
        $test_options[$option] = $value;
        return true;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        global $test_post_meta;
        if (!isset($test_post_meta)) {
            $test_post_meta = [];
        }
        
        if (!isset($test_post_meta[$post_id])) {
            return $single ? '' : [];
        }
        
        if ($key === '') {
            return $test_post_meta[$post_id];
        }
        
        if (!isset($test_post_meta[$post_id][$key])) {
            return $single ? '' : [];
        }
        
        return $single ? $test_post_meta[$post_id][$key] : [$test_post_meta[$post_id][$key]];
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        // Simple mock implementation
        $defaults = [
            'post_type' => 'post',
            'posts_per_page' => 5,
            'post_status' => 'publish'
        ];
        
        $args = array_merge($defaults, $args);
        
        // Return mock lessons for quiz tests
        if ($args['post_type'] === 'mpcs-lesson') {
            return [
                (object) ['ID' => 123, 'post_title' => 'Lesson 1', 'post_content' => 'Lesson 1 content'],
                (object) ['ID' => 124, 'post_title' => 'Lesson 2', 'post_content' => 'Lesson 2 content']
            ];
        }
        
        return [];
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return strip_tags($text);
    }
}

if (!function_exists('update_post_meta')) {
    function update_post_meta($post_id, $meta_key, $meta_value, $prev_value = '') {
        global $test_post_meta;
        if (!isset($test_post_meta)) {
            $test_post_meta = [];
        }
        if (!isset($test_post_meta[$post_id])) {
            $test_post_meta[$post_id] = [];
        }
        $test_post_meta[$post_id][$meta_key] = $meta_value;
        return true;
    }
}

if (!function_exists('wp_insert_post')) {
    function wp_insert_post($postarr, $wp_error = false, $fire_after_hooks = true) {
        // Return a mock post ID
        return 999;
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        if (count($args) === 2) {
            list($query_vars, $url) = $args;
            $url = $url ?: '';
            return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($query_vars);
        }
        return '';
    }
}

// Initialize global test variables
global $test_transients, $test_localized_data, $test_user_caps, $test_options, $test_post_meta, $test_status_headers;
$test_transients = [];
$test_localized_data = [];
$test_user_caps = ['read' => true]; // Default capabilities
$test_options = [];
$test_post_meta = [];
$test_status_headers = [];

// Import WordPress functions into namespaces used by the plugin
require_once __DIR__ . '/wordpress-functions.php';

// Output test environment info
echo PHP_EOL . 'MemberPress Courses Copilot Test Suite' . PHP_EOL;
echo '======================================' . PHP_EOL;
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'Plugin Root: ' . $pluginRoot . PHP_EOL;
echo PHP_EOL;