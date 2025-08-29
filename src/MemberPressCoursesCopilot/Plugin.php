<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot;

use MemberPressCoursesCopilot\Container\Container;
use MemberPressCoursesCopilot\Container\ServiceProvider;

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
     * DI Container instance
     *
     * @var Container
     */
    private Container $container;

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
        $this->container = Container::getInstance();
        $this->registerServices();
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
        // Initialize AssetManager early so assets are registered before enqueuing
        $asset_manager = $this->container->get(\MemberPressCoursesCopilot\Services\AssetManager::class);
        $asset_manager->init();
        
        // Initialize EditorAIIntegrationService early to register hooks before they fire
        $editor_ai_integration = $this->container->get(\MemberPressCoursesCopilot\Services\EditorAIIntegrationService::class);
        $editor_ai_integration->init();
        
        // Hook into WordPress initialization
        add_action('init', [$this, 'initializeComponents']);
        add_action('admin_menu', [$this, 'initializeAdmin']); // Changed from admin_init to admin_menu
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . MEMBERPRESS_COURSES_COPILOT_PLUGIN_BASENAME, [$this, 'addActionLinks']);
        
        // Show activation notice
        add_action('admin_notices', [$this, 'showActivationNotice']);
    }

    /**
     * Register services with the DI container
     *
     * @return void
     */
    private function registerServices(): void
    {
        ServiceProvider::register($this->container);
    }

    /**
     * Get the DI container instance
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Initialize core components
     *
     * @return void
     */
    public function initializeComponents(): void
    {
        // Get services from container
        $simple_ajax_controller = $this->container->get(\MemberPressCoursesCopilot\Controllers\SimpleAjaxController::class);
        $course_integration_service = $this->container->get(\MemberPressCoursesCopilot\Services\CourseIntegrationService::class);
        $course_ajax_service = $this->container->get(\MemberPressCoursesCopilot\Services\CourseAjaxService::class);
        $quiz_ajax_controller = $this->container->get(\MemberPressCoursesCopilot\Controllers\MpccQuizAjaxController::class);
        
        // Initialize services
        $simple_ajax_controller->init();
        $course_integration_service->init();
        $course_ajax_service->init();
        $quiz_ajax_controller->init();
        // Note: EditorAIIntegrationService is initialized early in init() method
        
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

        // Get services from container
        $settings_page = $this->container->get(\MemberPressCoursesCopilot\Admin\SettingsPage::class);
        $admin_menu = $this->container->get(\MemberPressCoursesCopilot\Admin\AdminMenu::class);
        $course_editor_page = $this->container->get(\MemberPressCoursesCopilot\Admin\CourseEditorPage::class);
        
        // Initialize services
        $settings_page->init();
        $admin_menu->init();
        $course_editor_page->init();
        $course_editor_page->addMenuPage(); // Register the menu page
        
        /**
         * Fires after admin components are initialized
         *
         * @since 1.0.0
         */
        do_action('memberpress_courses_copilot_admin_initialized', $admin_menu, $settings_page, $course_editor_page);
    }

    /**
     * Enqueue frontend scripts and styles
     *
     * @return void
     */
    public function enqueueScripts(): void
    {
        // Asset management handled by AssetManager service
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueueAdminScripts(string $hook_suffix): void
    {
        // Asset management handled by AssetManager service
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