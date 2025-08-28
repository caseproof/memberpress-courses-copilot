<?php
/**
 * Settings Page Template
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Templates
 */

defined('ABSPATH') || exit;

use MemberPressCoursesCopilot\Security\NonceConstants;

// Get current settings
$settings = $this->getSettings();
?>

<div class="wrap mpcc-settings">
    <h1><?php esc_html_e('AI Copilot Settings', 'memberpress-courses-copilot'); ?></h1>

    <?php settings_errors(); ?>

    <div class="mpcc-settings-container">
        <div class="mpcc-settings-main">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mpcc_save_settings">
                <?php NonceConstants::field(NonceConstants::SAVE_SETTINGS); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="proxy_url"><?php esc_html_e('Proxy URL', 'memberpress-courses-copilot'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="url" 
                                    id="proxy_url"
                                    name="mpcc_settings[proxy_url]" 
                                    value="<?php echo esc_attr($settings['proxy_url']); ?>" 
                                    class="regular-text" 
                                    required 
                                />
                                <p class="description">
                                    <?php esc_html_e('The URL of your LiteLLM proxy server.', 'memberpress-courses-copilot'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="master_key"><?php esc_html_e('Master Key', 'memberpress-courses-copilot'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="password" 
                                    id="master_key"
                                    name="mpcc_settings[master_key]" 
                                    value="<?php echo esc_attr($settings['master_key']); ?>" 
                                    class="regular-text" 
                                    required 
                                />
                                <p class="description">
                                    <?php esc_html_e('Your LiteLLM proxy master key for authentication.', 'memberpress-courses-copilot'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="timeout"><?php esc_html_e('Request Timeout', 'memberpress-courses-copilot'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="number" 
                                    id="timeout"
                                    name="mpcc_settings[timeout]" 
                                    value="<?php echo esc_attr($settings['timeout']); ?>" 
                                    min="10" 
                                    max="300" 
                                    class="small-text" 
                                    required 
                                />
                                <span><?php esc_html_e('seconds', 'memberpress-courses-copilot'); ?></span>
                                <p class="description">
                                    <?php esc_html_e('Request timeout in seconds (10-300).', 'memberpress-courses-copilot'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_temperature"><?php esc_html_e('Default Temperature', 'memberpress-courses-copilot'); ?></label>
                            </th>
                            <td>
                                <input 
                                    type="number" 
                                    id="default_temperature"
                                    name="mpcc_settings[default_temperature]" 
                                    value="<?php echo esc_attr($settings['default_temperature']); ?>" 
                                    min="0" 
                                    max="2" 
                                    step="0.1" 
                                    class="small-text" 
                                    required 
                                />
                                <p class="description">
                                    <?php esc_html_e('Default temperature for AI responses (0.0-2.0). Lower values are more focused, higher values are more creative.', 'memberpress-courses-copilot'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mpcc-settings-actions">
                    <?php submit_button(__('Save Settings', 'memberpress-courses-copilot'), 'primary', 'submit', false); ?>
                    
                    <button type="submit" name="test_connection" value="1" class="button button-secondary">
                        <?php esc_html_e('Test Connection', 'memberpress-courses-copilot'); ?>
                    </button>
                </div>
            </form>
        </div>

        <div class="mpcc-settings-sidebar">
            <!-- Connection Status -->
            <div class="mpcc-settings-card">
                <h3><?php esc_html_e('Connection Status', 'memberpress-courses-copilot'); ?></h3>
                <div id="mpcc-connection-status">
                    <?php if (empty($settings['master_key'])): ?>
                        <div class="mpcc-status mpcc-status-warning">
                            <span class="dashicons dashicons-warning"></span>
                            <?php esc_html_e('Configuration Required', 'memberpress-courses-copilot'); ?>
                        </div>
                        <p><?php esc_html_e('Please configure your proxy settings to enable AI course generation.', 'memberpress-courses-copilot'); ?></p>
                    <?php else: ?>
                        <div class="mpcc-status mpcc-status-unknown">
                            <span class="dashicons dashicons-clock"></span>
                            <?php esc_html_e('Unknown', 'memberpress-courses-copilot'); ?>
                        </div>
                        <p><?php esc_html_e('Click "Test Connection" to verify your settings.', 'memberpress-courses-copilot'); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Provider Information -->
            <div class="mpcc-settings-card">
                <h3><?php esc_html_e('Supported Providers', 'memberpress-courses-copilot'); ?></h3>
                <div class="mpcc-provider-list">
                    <div class="mpcc-provider">
                        <strong>Anthropic Claude</strong>
                        <p><?php esc_html_e('Best for course structure and educational content', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    <div class="mpcc-provider">
                        <strong>OpenAI GPT</strong>
                        <p><?php esc_html_e('Excellent for quiz generation and assessments', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    <div class="mpcc-provider">
                        <strong>DocsBot</strong>
                        <p><?php esc_html_e('Specialized for documentation and help content', 'memberpress-courses-copilot'); ?></p>
                    </div>
                </div>
            </div>

            <!-- System Requirements -->
            <div class="mpcc-settings-card">
                <h3><?php esc_html_e('System Requirements', 'memberpress-courses-copilot'); ?></h3>
                <div class="mpcc-requirements">
                    <div class="mpcc-requirement">
                        <span class="dashicons dashicons-<?php echo class_exists('MemberPress') ? 'yes-alt' : 'dismiss'; ?>"></span>
                        <?php esc_html_e('MemberPress Active', 'memberpress-courses-copilot'); ?>
                    </div>
                    <div class="mpcc-requirement">
                        <span class="dashicons dashicons-<?php echo defined('MPCS_VERSION') ? 'yes-alt' : 'dismiss'; ?>"></span>
                        <?php esc_html_e('MemberPress Courses Active', 'memberpress-courses-copilot'); ?>
                    </div>
                    <div class="mpcc-requirement">
                        <span class="dashicons dashicons-<?php echo current_user_can('edit_courses') ? 'yes-alt' : 'dismiss'; ?>"></span>
                        <?php esc_html_e('Course Editing Permissions', 'memberpress-courses-copilot'); ?>
                    </div>
                </div>
            </div>

            <!-- Documentation Links -->
            <div class="mpcc-settings-card">
                <h3><?php esc_html_e('Documentation', 'memberpress-courses-copilot'); ?></h3>
                <ul class="mpcc-docs-links">
                    <li>
                        <a href="#" target="_blank">
                            <?php esc_html_e('Getting Started Guide', 'memberpress-courses-copilot'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </li>
                    <li>
                        <a href="#" target="_blank">
                            <?php esc_html_e('Course Templates', 'memberpress-courses-copilot'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </li>
                    <li>
                        <a href="#" target="_blank">
                            <?php esc_html_e('Troubleshooting', 'memberpress-courses-copilot'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </li>
                    <li>
                        <a href="#" target="_blank">
                            <?php esc_html_e('Support', 'memberpress-courses-copilot'); ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Enqueue settings page styles
wp_enqueue_style('mpcc-admin-settings');
?>