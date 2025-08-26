<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Course Asset Service
 * 
 * Handles all CSS and JavaScript asset management for the plugin
 * Separated from CourseIntegrationService to follow Single Responsibility Principle
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class CourseAssetService extends BaseService
{
    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Hook into admin asset enqueuing
        add_action('admin_enqueue_scripts', [$this, 'enqueueIntegrationAssets']);
    }

    /**
     * Enqueue integration assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueueIntegrationAssets(string $hook_suffix): void
    {
        // Don't load modal assets on standalone page
        if (isset($_GET['page']) && $_GET['page'] === 'mpcc-course-editor') {
            return;
        }
        
        if (!$this->isCoursesAdminPage()) {
            return;
        }
        
        // Check if we're on the course generator page OR the courses list page (where AI interface loads)
        $is_generator_page = (isset($_GET['page']) && $_GET['page'] === 'mpcc-course-generator') || 
                           (isset($_GET['post_type']) && $_GET['post_type'] === 'mpcs-course' && !isset($_GET['post']));
        
        // Explicitly enqueue dashicons
        wp_enqueue_style('dashicons');
        
        // Add font-face fallback for dashicons
        add_action('admin_head', function() {
            ?>
            <style>
                @font-face {
                    font-family: "dashicons";
                    src: url("<?php echo includes_url('fonts/dashicons.eot'); ?>");
                    src: url("<?php echo includes_url('fonts/dashicons.eot?#iefix'); ?>") format("embedded-opentype"),
                         url("<?php echo includes_url('fonts/dashicons.woff'); ?>") format("woff"),
                         url("<?php echo includes_url('fonts/dashicons.ttf'); ?>") format("truetype"),
                         url("<?php echo includes_url('fonts/dashicons.svg#dashicons'); ?>") format("svg");
                    font-weight: normal;
                    font-style: normal;
                }
            </style>
            <?php
        });
        
        // Enqueue AI interface CSS
        wp_enqueue_style(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/courses-integration.css',
            ['dashicons'],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );
        
        // Enqueue AI Copilot CSS
        wp_enqueue_style(
            'mpcc-ai-copilot',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/ai-copilot.css',
            ['dashicons'],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );
        
        // Enqueue course preview CSS if on generator page
        if ($is_generator_page) {
            // Enqueue course preview editor CSS
            wp_enqueue_style(
                'mpcc-course-preview-editor',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/course-preview-editor.css',
                [],
                MEMBERPRESS_COURSES_COPILOT_VERSION
            );
        }
        
        // Enqueue initialization script first
        // Removed mpcc-init.js - functionality moved to simple-ai-chat.js for simplicity
        
        // Enqueue debug helper (only in development)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_enqueue_script(
                'mpcc-debug',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/mpcc-debug.js',
                ['jquery'],
                MEMBERPRESS_COURSES_COPILOT_VERSION,
                true
            );
        }
        
        // Enqueue AI interface JavaScript
        wp_enqueue_script(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/courses-integration.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // AI Copilot JavaScript removed - functionality merged into simple-ai-chat.js
        
        // Enqueue enhanced AI chat with persistence
        wp_enqueue_script(
            'mpcc-simple-ai-chat',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/simple-ai-chat.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Enqueue additional scripts for generator page
        if ($is_generator_page) {
            // Enqueue course preview editor JavaScript
            wp_enqueue_script(
                'mpcc-course-preview-editor',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/course-preview-editor.js',
                ['jquery', 'mpcc-courses-integration'],
                MEMBERPRESS_COURSES_COPILOT_VERSION,
                true
            );
        }
        
        // Localize script with needed data
        wp_localize_script('mpcc-courses-integration', 'mpccCoursesIntegration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::COURSES_INTEGRATION),
            'strings' => [
                'createWithAI' => __('Create with AI', 'memberpress-courses-copilot'),
                'aiAssistant' => __('AI Assistant', 'memberpress-courses-copilot'),
                'loading' => __('Loading...', 'memberpress-courses-copilot'),
                'error' => __('An error occurred. Please try again.', 'memberpress-courses-copilot'),
            ]
        ]);
        
        // Localization moved to simple-ai-chat.js
        
        // Localize simple AI chat script
        wp_localize_script('mpcc-simple-ai-chat', 'mpccAISettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::COURSES_INTEGRATION)
        ]);
    }

    /**
     * Check if we're on a MemberPress Courses admin page
     *
     * @return bool
     */
    private function isCoursesAdminPage(): bool
    {
        global $pagenow, $post_type;
        
        // Check if we're on the AI Course Generator page
        if (isset($_GET['page']) && $_GET['page'] === 'mpcc-course-generator') {
            return true;
        }
        
        // Check if we're on courses listing page
        if ($pagenow === 'edit.php' && $post_type === 'mpcs-course') {
            return true;
        }
        
        // Check if we're on course edit page
        if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'mpcs-course') {
                return true;
            }
            
            // Check if editing existing course
            if (isset($_GET['post'])) {
                $post = get_post((int) $_GET['post']);
                if ($post && $post->post_type === 'mpcs-course') {
                    return true;
                }
            }
        }
        
        return false;
    }
}