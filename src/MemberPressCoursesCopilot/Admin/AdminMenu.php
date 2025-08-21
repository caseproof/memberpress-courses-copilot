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
 * Simplified to work with existing MemberPress Copilot plugin
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

        // Only add course generator if MemberPress Copilot is active
        if (!$this->isCopilotActive()) {
            return;
        }

        // Add submenu under MemberPress Courses for the course generator
        add_submenu_page(
            'edit.php?post_type=mpcs-course',
            __('AI Course Generator', 'memberpress-courses-copilot'),
            __('AI Course Generator', 'memberpress-courses-copilot'),
            'edit_posts',
            'mpcc-course-generator',
            [$this, 'renderCourseGenerator']
        );

        // Add status page as submenu
        add_submenu_page(
            'edit.php?post_type=mpcs-course',
            __('AI Copilot Status', 'memberpress-courses-copilot'),
            __('AI Copilot Status', 'memberpress-courses-copilot'),
            'manage_options',
            'mpcc-status',
            [$this->settingsPage, 'render']
        );
    }

    /**
     * Check if MemberPress Copilot is active
     *
     * @return bool
     */
    private function isCopilotActive(): bool {
        return class_exists('MemberpressAiAssistant') && 
               function_exists('is_plugin_active') && 
               is_plugin_active('memberpress-copilot/memberpress-ai-assistant.php');
    }

    /**
     * Render course generator page
     *
     * @return void
     */
    public function renderCourseGenerator(): void {
        // Check capabilities
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'memberpress-courses-copilot'));
        }

        // Check if MemberPress Copilot is active
        if (!$this->isCopilotActive()) {
            $this->renderCopilotRequiredNotice();
            return;
        }

        // Include template
        include MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/admin/course-generator.php';
    }

    /**
     * Render copilot required notice
     *
     * @return void
     */
    private function renderCopilotRequiredNotice(): void {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('AI Course Generator', 'memberpress-courses-copilot'); ?></h1>
            <div class="notice notice-error">
                <p>
                    <strong><?php esc_html_e('MemberPress Copilot Required', 'memberpress-courses-copilot'); ?></strong>
                </p>
                <p>
                    <?php 
                    printf(
                        __('The AI Course Generator requires %s to be installed and activated. Please install and activate MemberPress Copilot first.', 'memberpress-courses-copilot'),
                        '<strong>MemberPress Copilot</strong>'
                    ); 
                    ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook Current admin page hook
     * @return void
     */
    public function enqueueAssets(string $hook): void {
        // Only load on our pages
        if (!in_array($hook, ['mpcs-course_page_mpcc-course-generator', 'mpcs-course_page_mpcc-status'], true)) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'mpcc-admin',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/courses-integration.css',
            ['wp-components'],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );

        // Enqueue scripts for course generator page
        if ($hook === 'mpcs-course_page_mpcc-course-generator') {
            // Explicitly enqueue dashicons
            wp_enqueue_style('dashicons');
            
            // Enqueue AI Copilot CSS
            wp_enqueue_style(
                'mpcc-ai-copilot',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/ai-copilot.css',
                ['dashicons'],
                MEMBERPRESS_COURSES_COPILOT_VERSION
            );
            
            wp_enqueue_script(
                'mpcc-course-generator',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/courses-integration.js',
                ['jquery', 'wp-element', 'wp-components'],
                MEMBERPRESS_COURSES_COPILOT_VERSION,
                true
            );
            
            // AI Copilot JavaScript removed - functionality merged into simple-ai-chat.js

            // Localize script
            wp_localize_script('mpcc-course-generator', 'mpccCoursesIntegration', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('mpcc_courses_integration'),
                'strings' => [
                    'generating' => __('Generating course...', 'memberpress-courses-copilot'),
                    'error' => __('An error occurred. Please try again.', 'memberpress-courses-copilot'),
                    'success' => __('Course generated successfully!', 'memberpress-courses-copilot'),
                    'createWithAI' => __('Create with AI', 'memberpress-courses-copilot'),
                    'aiAssistant' => __('AI Assistant', 'memberpress-courses-copilot'),
                    'loading' => __('Loading...', 'memberpress-courses-copilot'),
                ],
                'templates' => $this->getCourseTemplates(),
            ]);
            
            // Localization moved to simple-ai-chat.js
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