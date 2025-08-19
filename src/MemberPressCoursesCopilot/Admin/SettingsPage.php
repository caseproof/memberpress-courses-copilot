<?php
/**
 * Settings Page Handler
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Admin
 */

namespace MemberPressCoursesCopilot\Admin;

/**
 * SettingsPage class handles the plugin status page
 * Shows dependency status and proxy configuration from MemberPress Copilot
 */
class SettingsPage {
    /**
     * Initialize settings page
     *
     * @return void
     */
    public function init(): void {
        // No admin_init registration needed for status page
    }

    /**
     * Render status page
     *
     * @return void
     */
    public function render(): void {
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
                            <?php if ($this->isCopilotActive()): ?>
                                <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Active and Ready', 'memberpress-courses-copilot'); ?></span>
                            <?php else: ?>
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
                            <?php if ($this->isCoursesActive()): ?>
                                <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Active and Ready', 'memberpress-courses-copilot'); ?></span>
                            <?php else: ?>
                                <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('Not Active', 'memberpress-courses-copilot'); ?></span>
                                <p class="description" style="color: #d63638;">
                                    <?php esc_html_e('MemberPress Courses is required for course integration. Please install and activate it.', 'memberpress-courses-copilot'); ?>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
                
            <?php if ($this->isCopilotActive()): ?>
            <div class="mpcc-proxy-status">
                <h2><?php esc_html_e('AI Service Configuration', 'memberpress-courses-copilot'); ?></h2>
                <p class="description">
                    <?php esc_html_e('AI proxy settings are automatically inherited from MemberPress Copilot. No additional configuration is needed.', 'memberpress-courses-copilot'); ?>
                </p>
                
                <?php 
                try {
                    $copilot_proxy = new \MemberPressCoursesCopilot\Services\CopilotProxyService();
                    $proxy_config = $copilot_proxy->getProxyConfig();
                    ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Laravel Proxy URL', 'memberpress-courses-copilot'); ?></th>
                            <td>
                                <?php if (!empty($proxy_config['laravel_proxy_url'])): ?>
                                    <code><?php echo esc_html($proxy_config['laravel_proxy_url']); ?></code>
                                    <span style="color: green; margin-left: 10px;">✓ <?php esc_html_e('Configured', 'memberpress-courses-copilot'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;"><?php esc_html_e('Not configured', 'memberpress-courses-copilot'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php esc_html_e('LiteLLM Proxy URL', 'memberpress-courses-copilot'); ?></th>
                            <td>
                                <?php if (!empty($proxy_config['litellm_proxy_url'])): ?>
                                    <code><?php echo esc_html($proxy_config['litellm_proxy_url']); ?></code>
                                    <span style="color: green; margin-left: 10px;">✓ <?php esc_html_e('Configured', 'memberpress-courses-copilot'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;"><?php esc_html_e('Not configured', 'memberpress-courses-copilot'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php esc_html_e('Authentication', 'memberpress-courses-copilot'); ?></th>
                            <td>
                                <?php if (!empty($proxy_config['virtual_key'])): ?>
                                    <?php if ($proxy_config['virtual_key_expired']): ?>
                                        <span style="color: orange; font-weight: bold;">⚠ <?php esc_html_e('Virtual Key Expired', 'memberpress-courses-copilot'); ?></span>
                                        <p class="description"><?php esc_html_e('Will fall back to plugin credentials.', 'memberpress-courses-copilot'); ?></p>
                                    <?php else: ?>
                                        <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Virtual Key Valid', 'memberpress-courses-copilot'); ?></span>
                                    <?php endif; ?>
                                <?php elseif (!empty($proxy_config['plugin_id']) && !empty($proxy_config['plugin_secret'])): ?>
                                    <span style="color: green; font-weight: bold;">✓ <?php esc_html_e('Plugin Credentials Available', 'memberpress-courses-copilot'); ?></span>
                                <?php else: ?>
                                    <span style="color: red; font-weight: bold;">✗ <?php esc_html_e('No Authentication Available', 'memberpress-courses-copilot'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php
                    // Test connections
                    $connection_tests = $copilot_proxy->testConnections();
                    ?>
                    
                    <h3><?php esc_html_e('Connection Tests', 'memberpress-courses-copilot'); ?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Laravel Proxy', 'memberpress-courses-copilot'); ?></th>
                            <td>
                                <?php if ($connection_tests['laravel_proxy']['status'] === 'success'): ?>
                                    <span style="color: green;">✓ <?php echo esc_html($connection_tests['laravel_proxy']['message']); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php echo esc_html($connection_tests['laravel_proxy']['message']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row"><?php esc_html_e('LiteLLM Proxy', 'memberpress-courses-copilot'); ?></th>
                            <td>
                                <?php if ($connection_tests['litellm_proxy']['status'] === 'success'): ?>
                                    <span style="color: green;">✓ <?php echo esc_html($connection_tests['litellm_proxy']['message']); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php echo esc_html($connection_tests['litellm_proxy']['message']); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php
                } catch (\Exception $e) {
                    ?>
                    <div class="notice notice-error inline">
                        <p><?php esc_html_e('Unable to load proxy configuration:', 'memberpress-courses-copilot'); ?> <?php echo esc_html($e->getMessage()); ?></p>
                    </div>
                    <?php
                }
                ?>
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
        
        <style>
        .mpcc-status-section, .mpcc-proxy-status, .mpcc-features-section, .mpcc-usage-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        
        .mpcc-feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .mpcc-feature-card {
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        
        .mpcc-feature-card h3 {
            margin: 0 0 10px 0;
            color: #23282d;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mpcc-feature-card .dashicons {
            color: #0073aa;
        }
        
        .mpcc-usage-steps {
            margin-top: 15px;
        }
        
        .mpcc-usage-steps li {
            margin-bottom: 15px;
            line-height: 1.6;
        }
        </style>
        <?php
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
     * Check if MemberPress Courses is active
     *
     * @return bool
     */
    private function isCoursesActive(): bool {
        return defined('MPCS_VERSION') && 
               class_exists('memberpress\\courses\\models\\Course') && 
               function_exists('is_plugin_active') && 
               is_plugin_active('memberpress-courses/main.php');
    }
}