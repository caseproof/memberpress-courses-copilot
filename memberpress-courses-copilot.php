<?php
/**
 * Plugin Name: MemberPress Courses Copilot
 * Plugin URI: https://memberpress.com/
 * Description: AI-powered course creation assistant for MemberPress Courses
 * Version: 1.0.0
 * Author: MemberPress
 * Author URI: https://memberpress.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: memberpress-courses-copilot
 * Domain Path: /i18n/languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * 
 * @package MemberPressCoursesCopilot
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin constants
define( 'MEMBERPRESS_COURSES_COPILOT_VERSION', '1.0.0' );
define( 'MEMBERPRESS_COURSES_COPILOT_PLUGIN_FILE', __FILE__ );
define( 'MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEMBERPRESS_COURSES_COPILOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
$autoload_file = MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoload_file ) ) {
    wp_die( 'Composer autoload file not found. Please run composer install.' );
}
require_once $autoload_file;

use MemberPressCoursesCopilot\Plugin;
use MemberPressCoursesCopilot\Services\DatabaseService;

/**
 * Check if MemberPress is active
 *
 * @return bool
 */
function memberpress_courses_copilot_is_memberpress_active(): bool {
    return defined( 'MEPR_PLUGIN_NAME' ) && class_exists( 'MeprCtrlFactory' );
}

/**
 * Check if MemberPress Courses is active and properly loaded
 *
 * @return bool
 */
function memberpress_courses_copilot_is_courses_active(): bool {
    // First check if plugin is active
    if ( ! is_plugin_active( 'memberpress-courses/main.php' ) ) {
        return false;
    }
    
    // Check if main namespace and core classes exist
    return (
        defined( 'memberpress\\courses\\VERSION' ) &&
        class_exists( 'memberpress\\courses\\models\\Course' ) &&
        class_exists( 'memberpress\\courses\\controllers\\App' )
    );
}

/**
 * Display admin notice for missing dependencies
 *
 * @return void
 */
function memberpress_courses_copilot_missing_dependencies_notice(): void {
    $class = 'notice notice-error';
    $message = '<strong>' . esc_html__( 'MemberPress Courses Copilot', 'memberpress-courses-copilot' ) . '</strong> ';
    
    if ( ! memberpress_courses_copilot_is_memberpress_active() && ! memberpress_courses_copilot_is_courses_active() ) {
        $message .= esc_html__( 'requires both MemberPress and MemberPress Courses to be installed and activated.', 'memberpress-courses-copilot' );
    } elseif ( ! memberpress_courses_copilot_is_memberpress_active() ) {
        $message .= esc_html__( 'requires MemberPress to be installed and activated.', 'memberpress-courses-copilot' );
    } elseif ( ! memberpress_courses_copilot_is_courses_active() ) {
        $message .= esc_html__( 'requires MemberPress Courses to be installed and activated.', 'memberpress-courses-copilot' );
    }
    
    printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), wp_kses_post( $message ) );
}

/**
 * Initialize the plugin
 *
 * @return void
 */
function memberpress_courses_copilot_init(): void {
    // Check dependencies
    if ( ! memberpress_courses_copilot_is_memberpress_active() || ! memberpress_courses_copilot_is_courses_active() ) {
        add_action( 'admin_notices', 'memberpress_courses_copilot_missing_dependencies_notice' );
        return;
    }
    
    // Initialize the plugin
    Plugin::instance();
}
add_action( 'plugins_loaded', 'memberpress_courses_copilot_init', 20 );

// Debug helper (remove in production)
if (defined('WP_DEBUG') && WP_DEBUG && file_exists(__DIR__ . '/debug-test.php')) {
    require_once __DIR__ . '/debug-test.php';
}

// Add admin menu for debug
add_action('admin_menu', function() {
    if (current_user_can('manage_options')) {
        add_submenu_page(
            'tools.php',
            'MPCC Draft Table Debug',
            'MPCC Draft Debug',
            'manage_options',
            'mpcc-draft-debug',
            function() {
                require_once __DIR__ . '/debug-draft-table.php';
            }
        );
    }
});


/**
 * Plugin activation hook
 *
 * @return void
 */
function memberpress_courses_copilot_activate(): void {
    // Check PHP version
    if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 
            esc_html__( 'MemberPress Courses Copilot requires PHP version 8.0 or higher.', 'memberpress-courses-copilot' ),
            esc_html__( 'Plugin Activation Error', 'memberpress-courses-copilot' ),
            [ 'back_link' => true ]
        );
    }
    
    // Install database tables
    $database_service = new DatabaseService();
    $db_installed = $database_service->installTables();
    
    if ( ! $db_installed ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die( 
            esc_html__( 'Failed to install database tables for MemberPress Courses Copilot.', 'memberpress-courses-copilot' ),
            esc_html__( 'Plugin Activation Error', 'memberpress-courses-copilot' ),
            [ 'back_link' => true ]
        );
    }
    
    // Seed default data
    $database_service->seedDefaultData();
    
    // Set activation transient
    set_transient( 'memberpress_courses_copilot_activated', true, 30 );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'memberpress_courses_copilot_activate' );

/**
 * Plugin deactivation hook
 *
 * @return void
 */
function memberpress_courses_copilot_deactivate(): void {
    // Clean up transients
    delete_transient( 'memberpress_courses_copilot_activated' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Optional: Clean up temporary data (keeping permanent data intact)
    // This preserves user conversations and templates for reactivation
}
register_deactivation_hook( __FILE__, 'memberpress_courses_copilot_deactivate' );

/**
 * Plugin uninstall hook - only runs when plugin is deleted
 *
 * @return void
 */
function memberpress_courses_copilot_uninstall(): void {
    // Only run during actual uninstall, not deactivation
    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
        return;
    }
    
    // Remove all plugin data including database tables
    // This is permanent and cannot be undone
    $database_service = new DatabaseService();
    $database_service->dropTables();
    
    // Clean up any remaining options
    delete_option( 'mpcc_db_version' );
    delete_option( 'memberpress_courses_copilot_settings' );
    
    // Clean up any cached data
    wp_cache_flush();
}
register_uninstall_hook( __FILE__, 'memberpress_courses_copilot_uninstall' );

/**
 * Load plugin text domain
 *
 * @return void
 */
function memberpress_courses_copilot_load_textdomain(): void {
    load_plugin_textdomain(
        'memberpress-courses-copilot',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages'
    );
}
add_action( 'init', 'memberpress_courses_copilot_load_textdomain' );