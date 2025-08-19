<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot;

/**
 * Main Plugin class
 * 
 * Singleton pattern implementation for the MemberPress Courses Copilot plugin
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
final class Plugin
{
    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Plugin version
     *
     * @var string
     */
    public const VERSION = MEMBERPRESS_COURSES_COPILOT_VERSION;

    /**
     * Minimum required MemberPress version
     *
     * @var string
     */
    public const MIN_MEMBERPRESS_VERSION = '1.11.0';

    /**
     * Minimum required MemberPress Courses version
     *
     * @var string
     */
    public const MIN_COURSES_VERSION = '1.4.0';

    /**
     * Private constructor to prevent instantiation
     */
    private function __construct()
    {
        $this->init();
    }

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function instance(): Plugin
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the plugin
     *
     * @return void
     */
    private function init(): void
    {
        // Hook into WordPress initialization
        add_action('init', [$this, 'initializeComponents']);
        add_action('admin_init', [$this, 'initializeAdmin']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . MEMBERPRESS_COURSES_COPILOT_PLUGIN_BASENAME, [$this, 'addActionLinks']);
        
        // Show activation notice
        add_action('admin_notices', [$this, 'showActivationNotice']);
    }

    /**
     * Initialize core components
     *
     * @return void
     */
    public function initializeComponents(): void
    {
        // Initialize LLM service as global
        global $mpcc_llm_service;
        $mpcc_llm_service = new \MemberPressCoursesCopilot\Services\LLMService();
        
        // Initialize the course integration service for MemberPress Courses UI integration
        $course_integration_service = new \MemberPressCoursesCopilot\Services\CourseIntegrationService();
        $course_integration_service->init();
        
        /**
         * Fires after plugin components are initialized
         *
         * @since 1.0.0
         */
        do_action('memberpress_courses_copilot_components_initialized', $course_integration_service);
    }

    /**
     * Initialize admin components
     *
     * @return void
     */
    public function initializeAdmin(): void
    {
        if (!is_admin()) {
            return;
        }

        // Initialize admin menu and settings
        $settings_page = new \MemberPressCoursesCopilot\Admin\SettingsPage();
        $settings_page->init();
        
        $admin_menu = new \MemberPressCoursesCopilot\Admin\AdminMenu($settings_page);
        $admin_menu->init();
        
        /**
         * Fires after admin components are initialized
         *
         * @since 1.0.0
         */
        do_action('memberpress_courses_copilot_admin_initialized', $admin_menu, $settings_page);
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @return void
     */
    public function enqueueScripts(): void
    {
        // Enqueue frontend assets here when needed
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueueAdminScripts(string $hook_suffix): void
    {
        // Enqueue admin assets here when needed
    }

    /**
     * Add plugin action links
     *
     * @param array<string> $links Existing action links
     * @return array<string> Modified action links
     */
    public function addActionLinks(array $links): array
    {
        $plugin_links = [
            '<a href="' . esc_url(admin_url('admin.php?page=memberpress-courses-copilot')) . '">' . 
            esc_html__('Settings', 'memberpress-courses-copilot') . '</a>',
        ];

        return array_merge($plugin_links, $links);
    }

    /**
     * Show activation notice
     *
     * @return void
     */
    public function showActivationNotice(): void
    {
        if (!get_transient('memberpress_courses_copilot_activated')) {
            return;
        }

        $class = 'notice notice-success is-dismissible';
        $message = sprintf(
            /* translators: %s: plugin name */
            esc_html__('%s has been activated successfully!', 'memberpress-courses-copilot'),
            '<strong>' . esc_html__('MemberPress Courses Copilot', 'memberpress-courses-copilot') . '</strong>'
        );

        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));

        // Delete the transient so the notice doesn't show again
        delete_transient('memberpress_courses_copilot_activated');
    }

    /**
     * Get plugin directory path
     *
     * @return string
     */
    public function getPluginDir(): string
    {
        return MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR;
    }

    /**
     * Get plugin directory URL
     *
     * @return string
     */
    public function getPluginUrl(): string
    {
        return MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL;
    }

    /**
     * Get plugin version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }

    /**
     * Check if MemberPress version meets minimum requirements
     *
     * @return bool
     */
    public function isMemberPressVersionSupported(): bool
    {
        if (!defined('MEPR_VERSION')) {
            return false;
        }

        return version_compare(MEPR_VERSION, self::MIN_MEMBERPRESS_VERSION, '>=');
    }

    /**
     * Check if MemberPress Courses version meets minimum requirements
     *
     * @return bool
     */
    public function isCoursesVersionSupported(): bool
    {
        if (!defined('MPCS_VERSION')) {
            return false;
        }

        return version_compare(MPCS_VERSION, self::MIN_COURSES_VERSION, '>=');
    }

    /**
     * Prevent cloning
     *
     * @return void
     */
    private function __clone()
    {
        // Prevent cloning
    }

    /**
     * Prevent unserialization
     *
     * @return void
     */
    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}