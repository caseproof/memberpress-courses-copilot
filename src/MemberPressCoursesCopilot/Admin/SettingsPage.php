<?php


/**
 * Settings Page Handler
 *
 * @package    MemberPressCoursesCopilot
 * @subpackage Admin
 */

namespace MemberPressCoursesCopilot\Admin;

/**
 * SettingsPage class handles the plugin status page
 * Shows dependency status and proxy configuration from MemberPress Copilot
 */
class SettingsPage
{
    /**
     * Initialize settings page
     *
     * @return void
     */
    public function init(): void
    {
        // No admin_init registration needed for status page
    }

    /**
     * Render status page
     *
     * @return void
     */
    public function render(): void
    {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'memberpress-courses-copilot'));
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MemberPress Courses Copilot Status', 'memberpress-courses-copilot'); ?></h1>
            
            <div class="mpcc-status-section">
                <h2><?php esc_html_e('Plugin Dependencies', 'memberpress-courses-copilot'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('MemberPress Copilot', 'memberpress-courses-copilot'); ?></th>
                        <td>
                            <?php if ($this->isCopilotActive()) : ?>
                                <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Active and Ready', 'memberpress-courses-copilot'); ?></span>
                            <?php else : ?>
                                <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Not Active', 'memberpress-courses-copilot'); ?></span>
                                <p class="description" style="color: #d63638;">
                                    <?php esc_html_e('MemberPress Copilot is required for AI functionality. Please install and activate it.', 'memberpress-courses-copilot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('MemberPress Courses', 'memberpress-courses-copilot'); ?></th>
                        <td>
                            <?php if ($this->isCoursesActive()) : ?>
                                <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Active and Ready', 'memberpress-courses-copilot'); ?></span>
                            <?php else : ?>
                                <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Not Active', 'memberpress-courses-copilot'); ?></span>
                                <p class="description" style="color: #d63638;">
                                    <?php esc_html_e('MemberPress Courses is required for course integration. Please install and activate it.', 'memberpress-courses-copilot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
                
            <?php if ($this->isCopilotActive()) : ?>
            <div class="mpcc-proxy-status">
                <h2><?php esc_html_e('AI Service Configuration', 'memberpress-courses-copilot'); ?></h2>
                <p class="description">
                    <?php esc_html_e('AI proxy settings are automatically inherited from MemberPress Copilot. No additional configuration is needed.', 'memberpress-courses-copilot'); ?>
                </p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('AI Service Status', 'memberpress-courses-copilot'); ?></th>
                        <td>
                            <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Active and Ready', 'memberpress-courses-copilot'); ?></span>
                            <p class="description"><?php esc_html_e('AI services are pre-configured and ready to use.', 'memberpress-courses-copilot'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Available AI Models', 'memberpress-courses-copilot'); ?></th>
                        <td>
                            <ul style="margin: 0;">
                                <li>• Claude 3.5 Sonnet (Content Generation)</li>
                                <li>• GPT-4 (Structured Data)</li>
                                <li>• GPT-3.5 Turbo (Quick Tasks)</li>
                            </ul>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php esc_html_e('Features', 'memberpress-courses-copilot'); ?></th>
                        <td>
                            <ul style="margin: 0;">
                                <li>✓ <?php esc_html_e('Course structure generation', 'memberpress-courses-copilot'); ?></li>
                                <li>✓ <?php esc_html_e('Lesson content creation', 'memberpress-courses-copilot'); ?></li>
                                <li>✓ <?php esc_html_e('Interactive chat assistance', 'memberpress-courses-copilot'); ?></li>
                                <li>✓ <?php esc_html_e('Content optimization', 'memberpress-courses-copilot'); ?></li>
                            </ul>
                        </td>
                    </tr>
                </table>
                
                <div class="notice notice-info inline">
                    <p>
                        <strong><?php esc_html_e('How to use:', 'memberpress-courses-copilot'); ?></strong><br>
                        <?php esc_html_e('1. Click "Create with AI" on the courses listing page', 'memberpress-courses-copilot'); ?><br>
                        <?php esc_html_e('2. Use the AI Assistant when editing any course', 'memberpress-courses-copilot'); ?><br>
                        <?php esc_html_e('3. Chat with AI to generate course content instantly', 'memberpress-courses-copilot'); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="mpcc-features-section">
                <h2><?php esc_html_e('Available Features', 'memberpress-courses-copilot'); ?></h2>
                
                <div class="mpcc-feature-grid">
                    <div class="mpcc-feature-card">
                        <h3><span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e('AI Course Generator', 'memberpress-courses-copilot'); ?></h3>
                        <p><?php esc_html_e('Create complete courses using AI assistance. Available in Courses → AI Course Generator.', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    
                    <div class="mpcc-feature-card">
                        <h3><span class="dashicons dashicons-superhero-alt"></span> <?php esc_html_e('Course Editor AI Assistant', 'memberpress-courses-copilot'); ?></h3>
                        <p><?php esc_html_e('Get AI help while editing existing courses via the AI Assistant meta box.', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    
                    <div class="mpcc-feature-card">
                        <h3><span class="dashicons dashicons-format-chat"></span> <?php esc_html_e('Integrated AI Chat', 'memberpress-courses-copilot'); ?></h3>
                        <p><?php esc_html_e('Chat with AI directly from the courses listing page using the "Create with AI" button.', 'memberpress-courses-copilot'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="mpcc-usage-section">
                <h2><?php esc_html_e('How to Use', 'memberpress-courses-copilot'); ?></h2>
                
                <ol class="mpcc-usage-steps">
                    <li>
                        <strong><?php esc_html_e('Course Creation from Listing', 'memberpress-courses-copilot'); ?></strong><br>
                        <?php esc_html_e('Go to Courses → All Courses and click the "Create with AI" button next to "Add New Course".', 'memberpress-courses-copilot'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Course Editing Assistant', 'memberpress-courses-copilot'); ?></strong><br>
                        <?php esc_html_e('When editing any course, look for the "AI Assistant" meta box in the sidebar for contextual help.', 'memberpress-courses-copilot'); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e('Dedicated Course Generator', 'memberpress-courses-copilot'); ?></strong><br>
                        <?php esc_html_e('Use Courses → AI Course Generator for a focused course creation experience with templates.', 'memberpress-courses-copilot'); ?>
                    </li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Check if MemberPress Copilot is active
     *
     * @return boolean
     */
    private function isCopilotActive(): bool
    {
        return class_exists('MemberpressAiAssistant') &&
               function_exists('is_plugin_active') &&
               is_plugin_active('memberpress-copilot/memberpress-ai-assistant.php');
    }

    /**
     * Check if MemberPress Courses is active
     *
     * @return boolean
     */
    private function isCoursesActive(): bool
    {
        return defined('MPCS_VERSION') &&
               class_exists('memberpress\\courses\\models\\Course') &&
               function_exists('is_plugin_active') &&
               is_plugin_active('memberpress-courses/main.php');
    }
}