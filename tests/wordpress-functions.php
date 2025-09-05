<?php
/**
 * WordPress function imports for namespaced code
 * 
 * This file provides use statements to import WordPress functions
 * into the current namespace for testing purposes.
 */

namespace MemberPressCoursesCopilot\Controllers;

// Import WordPress functions that are used without namespace prefix
use function sanitize_text_field;
use function sanitize_textarea_field;
use function wp_send_json_success;
use function wp_send_json_error;
use function current_user_can;
use function wp_verify_nonce;
use function wp_create_nonce;
use function get_post;
use function esc_html;
use function esc_attr;
use function __;
use function admin_url;
use function is_wp_error;
use function wp_kses_post;
use function esc_url_raw;
use function sanitize_email;
use function absint;
use function get_post_meta;
use function get_posts;
use function wp_strip_all_tags;
use function update_post_meta;
use function wp_insert_post;
use function add_query_arg;

namespace MemberPressCoursesCopilot\Services;

// Import for Services namespace as well
use function sanitize_text_field;
use function sanitize_textarea_field;
use function current_user_can;
use function wp_create_nonce;
use function get_post;
use function is_wp_error;
use function wp_json_encode;
use function esc_html;
use function esc_js;
use function esc_url;
use function wp_kses_post;
use function wp_remote_post;
use function wp_remote_retrieve_response_code;
use function wp_remote_retrieve_body;

namespace MemberPressCoursesCopilot\Utilities;

// Import for Utilities namespace
use function is_wp_error;
use function wp_send_json_error;
use function wp_send_json_success;
use function status_header;

namespace MemberPressCoursesCopilot\Tests\Unit\Security;

// Import for test namespace
use function wp_kses_post;
use function esc_url_raw;
use function sanitize_text_field;

namespace MemberPressCoursesCopilot\Tests\Unit\Controllers;

// Import for test namespace
use function wp_kses_post;
use function esc_url_raw;
use function sanitize_text_field;
use function get_post;
use function get_post_meta;
use function get_posts;
use function wp_strip_all_tags;
use function update_post_meta;
use function wp_insert_post;
use function add_query_arg;
use function wp_create_nonce;
use function wp_verify_nonce;
use function current_user_can;