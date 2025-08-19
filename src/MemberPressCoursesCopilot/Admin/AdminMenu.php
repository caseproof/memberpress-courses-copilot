<?php
/**
 * Admin Menu Handler
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Admin
 */

namespace MemberPressCoursesCopilot\Admin;

/**
 * AdminMenu class handles the WordPress admin menu integration
 */
class AdminMenu {
    /**
     * Settings page instance
     *
     * @var SettingsPage
     */
    private $settingsPage;

    /**
     * Constructor
     *
     * @param SettingsPage $settingsPage Settings page instance
     */
    public function __construct(SettingsPage $settingsPage) {
        $this->settingsPage = $settingsPage;
    }

    /**
     * Initialize admin menu
     *
     * @return void
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'registerMenus'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Register admin menus
     *
     * @return void
     */
    public function registerMenus(): void {
        // Check if MemberPress Courses is active
        if (!defined('MPCS_VERSION')) {
            return;
        }

        // Add submenu under MemberPress Courses
        add_submenu_page(
            'memberpress-courses',
            __('AI Copilot', 'memberpress-courses-copilot'),
            __('AI Copilot', 'memberpress-courses-copilot'),
            'edit_courses',
            'mpcc-course-generator',
            [$this, 'renderCourseGenerator']
        );

        // Add settings submenu
        add_submenu_page(
            'memberpress-courses',
            __('AI Copilot Settings', 'memberpress-courses-copilot'),
            __('AI Copilot Settings', 'memberpress-courses-copilot'),
            'manage_options',
            'mpcc-settings',
            [$this->settingsPage, 'render']
        );
    }

    /**
     * Render course generator page
     *
     * @return void
     */
    public function renderCourseGenerator(): void {
        // Check capabilities
        if (!current_user_can('edit_courses')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'memberpress-courses-copilot'));
        }

        // Include template
        include MPCC_PLUGIN_DIR . 'templates/admin/course-generator.php';
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void {
        // Only load on our pages
        if (!in_array($hook, ['memberpress-courses_page_mpcc-course-generator', 'memberpress-courses_page_mpcc-settings'], true)) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'mpcc-admin',
            MPCC_PLUGIN_URL . 'assets/css/admin.css',
            ['wp-components'],
            MPCC_VERSION
        );

        // Enqueue scripts for course generator page
        if ($hook === 'memberpress-courses_page_mpcc-course-generator') {
            wp_enqueue_script(
                'mpcc-course-generator',
                MPCC_PLUGIN_URL . 'assets/js/course-generator.js',
                ['jquery', 'wp-element', 'wp-components', 'wp-api-fetch'],
                MPCC_VERSION,
                true
            );

            // Localize script
            wp_localize_script('mpcc-course-generator', 'mpccAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mpcc_generate_course'),
                'strings' => [
                    'generating' => __('Generating course...', 'memberpress-courses-copilot'),
                    'error' => __('An error occurred. Please try again.', 'memberpress-courses-copilot'),
                    'success' => __('Course generated successfully!', 'memberpress-courses-copilot'),
                ],
                'templates' => $this->getCourseTemplates(),
            ]);
        }
    }

    /**
     * Get available course templates
     *
     * @return array
     */
    private function getCourseTemplates(): array {
        return [
            'technical' => [
                'label' => __('Technical Training', 'memberpress-courses-copilot'),
                'description' => __('Programming, software, and technical skills', 'memberpress-courses-copilot'),
                'icon' => 'laptop',
            ],
            'business' => [
                'label' => __('Business & Professional', 'memberpress-courses-copilot'),
                'description' => __('Business skills, management, and professional development', 'memberpress-courses-copilot'),
                'icon' => 'businessperson',
            ],
            'creative' => [
                'label' => __('Creative Arts', 'memberpress-courses-copilot'),
                'description' => __('Design, arts, music, and creative skills', 'memberpress-courses-copilot'),
                'icon' => 'art',
            ],
            'academic' => [
                'label' => __('Academic & Educational', 'memberpress-courses-copilot'),
                'description' => __('Academic subjects, research, and educational content', 'memberpress-courses-copilot'),
                'icon' => 'welcome-learn-more',
            ],
        ];
    }
}