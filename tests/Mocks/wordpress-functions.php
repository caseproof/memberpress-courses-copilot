<?php
/**
 * Additional WordPress Function Mocks for Quiz Tests
 * 
 * Extended mocks that supplement the base bootstrap.php mocks
 * specifically for quiz functionality testing
 * 
 * @package MemberPressCoursesCopilot\Tests\Mocks
 */

// Mock WordPress post functions if not already defined
if (!function_exists('get_post')) {
    function get_post($id) {
        // Mock post data for testing
        $posts = [
            123 => (object) [
                'ID' => 123,
                'post_title' => 'Test Lesson',
                'post_content' => 'This is test lesson content for quiz generation',
                'post_type' => 'mpcs-lesson',
                'post_excerpt' => 'Test lesson excerpt',
                'post_status' => 'publish',
                'post_author' => 1
            ],
            456 => (object) [
                'ID' => 456,
                'post_title' => 'Test Course',
                'post_content' => 'This is test course content',
                'post_type' => 'mpcs-course',
                'post_excerpt' => 'Test course excerpt',
                'post_status' => 'publish',
                'post_author' => 1
            ],
            999 => (object) [
                'ID' => 999,
                'post_title' => 'Test Quiz',
                'post_content' => '',
                'post_type' => 'mpcs-quiz',
                'post_status' => 'draft',
                'post_author' => 1
            ]
        ];
        
        return isset($posts[$id]) ? $posts[$id] : null;
    }
}

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $meta_key = '', $single = false) {
        // Mock post meta data
        $meta = [
            123 => [ // Test lesson
                '_mpcs_course_id' => 456,
                '_mpcs_lesson_section_id' => 789,
                '_mpcs_lesson_lesson_order' => 1
            ],
            456 => [ // Test course
                '_mpcs_course_description' => 'Course description'
            ],
            999 => [ // Test quiz
                '_mpcs_lesson_id' => 123,
                '_mpcs_course_id' => 456
            ]
        ];
        
        if ($meta_key) {
            $value = isset($meta[$post_id][$meta_key]) ? $meta[$post_id][$meta_key] : '';
            return $single ? $value : [$value];
        }
        
        return isset($meta[$post_id]) ? $meta[$post_id] : [];
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
    function wp_insert_post($postarr, $wp_error = false) {
        // Return a mock post ID
        $post_id = rand(1000, 9999);
        
        // Store the post data for later retrieval if needed
        global $test_inserted_posts;
        if (!isset($test_inserted_posts)) {
            $test_inserted_posts = [];
        }
        $test_inserted_posts[$post_id] = $postarr;
        
        return $post_id;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        // Return mock posts based on query args
        if (isset($args['post_type'])) {
            switch ($args['post_type']) {
                case 'mpcs-lesson':
                    // Return mock lessons
                    $lessons = [
                        (object) [
                            'ID' => 123,
                            'post_title' => 'Introduction to Web Development',
                            'post_content' => 'Learn the basics of HTML, CSS, and JavaScript',
                            'post_type' => 'mpcs-lesson'
                        ],
                        (object) [
                            'ID' => 124,
                            'post_title' => 'Advanced JavaScript Concepts',
                            'post_content' => 'Explore closures, async/await, and ES6 features',
                            'post_type' => 'mpcs-lesson'
                        ]
                    ];
                    
                    // Filter by meta_query if provided
                    if (isset($args['meta_query'])) {
                        foreach ($args['meta_query'] as $meta) {
                            if ($meta['key'] === '_mpcs_course_id' && $meta['value'] == 456) {
                                return $lessons; // Return lessons for course 456
                            }
                        }
                    }
                    
                    return $lessons;
                    
                case 'mpcs-course':
                    return [
                        (object) [
                            'ID' => 456,
                            'post_title' => 'Web Development Fundamentals',
                            'post_content' => 'Complete course on web development',
                            'post_type' => 'mpcs-course'
                        ]
                    ];
                    
                default:
                    return [];
            }
        }
        
        return [];
    }
}

if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text) {
        return strip_tags($text);
    }
}

// Mock WordPress error handling
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

// Mock text processing functions
if (!function_exists('stripslashes')) {
    function stripslashes($str) {
        return is_string($str) ? stripslashes($str) : $str;
    }
}

// Mock WordPress URL functions
if (!function_exists('add_query_arg')) {
    function add_query_arg($args, $url = '') {
        if (empty($url)) {
            $url = 'http://test.local/wp-admin/admin.php';
        }
        
        $query = http_build_query($args);
        $separator = strpos($url, '?') !== false ? '&' : '?';
        
        return $url . $separator . $query;
    }
}

// Mock WordPress user functions
if (!function_exists('get_current_user_id')) {
    function get_current_user_id() {
        global $test_current_user_id;
        return isset($test_current_user_id) ? $test_current_user_id : 1;
    }
}

// Mock WordPress capability functions
if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        global $test_user_caps;
        
        if (!isset($test_user_caps)) {
            $test_user_caps = ['read' => true];
        }
        
        return isset($test_user_caps[$capability]) && $test_user_caps[$capability];
    }
}

// Mock WordPress nonce functions  
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce($action = -1) {
        return hash('md5', $action . 'test_salt_' . time());
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        // Simple verification for tests
        $expected_nonce = wp_create_nonce($action);
        return $nonce === $expected_nonce;
    }
}

// Mock WordPress AJAX functions
if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = 200) {
        http_response_code($status_code);
        echo json_encode(['success' => true, 'data' => $data]);
        throw new Exception('wp_send_json_exit'); // Simulate wp_die() behavior
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = 400) {
        http_response_code($status_code);
        echo json_encode(['success' => false, 'data' => $data]);
        throw new Exception('wp_send_json_exit'); // Simulate wp_die() behavior
    }
}

// Mock WordPress sanitization functions
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($str) {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs(intval($maybeint));
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) {
        // Allow basic HTML tags for testing
        $allowed_tags = '<p><br><strong><b><em><i><ul><ol><li><h1><h2><h3><h4><h5><h6>';
        return strip_tags($data, $allowed_tags);
    }
}

// Mock WordPress action/filter functions
if (!function_exists('add_action')) {
    function add_action($tag, $function_to_add, $priority = 10, $accepted_args = 1) {
        global $test_actions;
        if (!isset($test_actions)) {
            $test_actions = [];
        }
        if (!isset($test_actions[$tag])) {
            $test_actions[$tag] = [];
        }
        $test_actions[$tag][] = [
            'function' => $function_to_add,
            'priority' => $priority,
            'args' => $accepted_args
        ];
        return true;
    }
}

// Mock WordPress translation functions
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('sprintf')) {
    function sprintf($format, ...$args) {
        return call_user_func_array('sprintf', func_get_args());
    }
}

// Initialize global test variables
global $test_post_meta, $test_inserted_posts, $test_actions, $test_current_user_id, $test_user_caps;

$test_post_meta = [];
$test_inserted_posts = [];
$test_actions = [];
$test_current_user_id = 1;
$test_user_caps = ['read' => true];