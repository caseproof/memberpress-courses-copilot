<?php
/**
 * Settings Page Handler
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Admin
 */

namespace MemberPressCoursesCopilot\Admin;

/**
 * SettingsPage class handles the plugin settings page
 */
class SettingsPage {
    /**
     * Settings option group
     */
    private const OPTION_GROUP = 'mpcc_settings';

    /**
     * Settings option name
     */
    private const OPTION_NAME = 'mpcc_settings';

    /**
     * Initialize settings page
     *
     * @return void
     */
    public function init(): void {
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_post_mpcc_save_settings', [$this, 'handleSaveSettings']);
    }

    /**
     * Register settings
     *
     * @return void
     */
    public function registerSettings(): void {
        // Register setting
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->getDefaultSettings(),
            ]
        );

        // Add settings section
        add_settings_section(
            'mpcc_proxy_settings',
            __('LiteLLM Proxy Configuration', 'memberpress-courses-copilot'),
            [$this, 'renderProxySettingsSection'],
            'mpcc-settings'
        );

        // Add proxy URL field
        add_settings_field(
            'proxy_url',
            __('Proxy URL', 'memberpress-courses-copilot'),
            [$this, 'renderProxyUrlField'],
            'mpcc-settings',
            'mpcc_proxy_settings'
        );

        // Add master key field
        add_settings_field(
            'master_key',
            __('Master Key', 'memberpress-courses-copilot'),
            [$this, 'renderMasterKeyField'],
            'mpcc-settings',
            'mpcc_proxy_settings'
        );

        // Add timeout field
        add_settings_field(
            'timeout',
            __('Request Timeout (seconds)', 'memberpress-courses-copilot'),
            [$this, 'renderTimeoutField'],
            'mpcc-settings',
            'mpcc_proxy_settings'
        );

        // Add temperature field
        add_settings_field(
            'default_temperature',
            __('Default Temperature', 'memberpress-courses-copilot'),
            [$this, 'renderTemperatureField'],
            'mpcc-settings',
            'mpcc_proxy_settings'
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function render(): void {
        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'memberpress-courses-copilot'));
        }

        // Include template
        include MPCC_PLUGIN_DIR . 'templates/admin/settings.php';
    }

    /**
     * Render proxy settings section
     *
     * @return void
     */
    public function renderProxySettingsSection(): void {
        echo '<p>' . esc_html__('Configure your LiteLLM proxy settings for AI-powered course generation.', 'memberpress-courses-copilot') . '</p>';
    }

    /**
     * Render proxy URL field
     *
     * @return void
     */
    public function renderProxyUrlField(): void {
        $settings = $this->getSettings();
        $value = esc_attr($settings['proxy_url']);
        
        echo '<input type="url" name="' . esc_attr(self::OPTION_NAME) . '[proxy_url]" value="' . $value . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('The URL of your LiteLLM proxy server.', 'memberpress-courses-copilot') . '</p>';
    }

    /**
     * Render master key field
     *
     * @return void
     */
    public function renderMasterKeyField(): void {
        $settings = $this->getSettings();
        $value = esc_attr($settings['master_key']);
        
        echo '<input type="password" name="' . esc_attr(self::OPTION_NAME) . '[master_key]" value="' . $value . '" class="regular-text" required />';
        echo '<p class="description">' . esc_html__('Your LiteLLM proxy master key for authentication.', 'memberpress-courses-copilot') . '</p>';
    }

    /**
     * Render timeout field
     *
     * @return void
     */
    public function renderTimeoutField(): void {
        $settings = $this->getSettings();
        $value = (int) $settings['timeout'];
        
        echo '<input type="number" name="' . esc_attr(self::OPTION_NAME) . '[timeout]" value="' . $value . '" min="10" max="300" class="small-text" required />';
        echo '<p class="description">' . esc_html__('Request timeout in seconds (10-300).', 'memberpress-courses-copilot') . '</p>';
    }

    /**
     * Render temperature field
     *
     * @return void
     */
    public function renderTemperatureField(): void {
        $settings = $this->getSettings();
        $value = (float) $settings['default_temperature'];
        
        echo '<input type="number" name="' . esc_attr(self::OPTION_NAME) . '[default_temperature]" value="' . $value . '" min="0" max="2" step="0.1" class="small-text" required />';
        echo '<p class="description">' . esc_html__('Default temperature for AI responses (0.0-2.0). Lower values are more focused, higher values are more creative.', 'memberpress-courses-copilot') . '</p>';
    }

    /**
     * Handle settings save
     *
     * @return void
     */
    public function handleSaveSettings(): void {
        // Check nonce
        if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'mpcc_save_settings')) {
            wp_die(__('Security check failed.', 'memberpress-courses-copilot'));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to save settings.', 'memberpress-courses-copilot'));
        }

        // Get and sanitize settings
        $settings = $_POST[self::OPTION_NAME] ?? [];
        $sanitized = $this->sanitizeSettings($settings);

        // Save settings
        update_option(self::OPTION_NAME, $sanitized);

        // Test connection if enabled
        if (isset($_POST['test_connection'])) {
            $this->testConnection($sanitized);
        }

        // Redirect with success message
        wp_redirect(add_query_arg([
            'page' => 'mpcc-settings',
            'settings-updated' => 'true',
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Sanitize settings
     *
     * @param array $settings Raw settings data
     * @return array
     */
    public function sanitizeSettings(array $settings): array {
        return [
            'proxy_url' => esc_url_raw($settings['proxy_url'] ?? ''),
            'master_key' => sanitize_text_field($settings['master_key'] ?? ''),
            'timeout' => min(300, max(10, (int) ($settings['timeout'] ?? 60))),
            'default_temperature' => min(2.0, max(0.0, (float) ($settings['default_temperature'] ?? 0.7))),
        ];
    }

    /**
     * Get current settings
     *
     * @return array
     */
    public function getSettings(): array {
        return get_option(self::OPTION_NAME, $this->getDefaultSettings());
    }

    /**
     * Get default settings
     *
     * @return array
     */
    private function getDefaultSettings(): array {
        return [
            'proxy_url' => 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com',
            'master_key' => '',
            'timeout' => 60,
            'default_temperature' => 0.7,
        ];
    }

    /**
     * Test connection to proxy
     *
     * @param array $settings Settings to test
     * @return void
     */
    private function testConnection(array $settings): void {
        $response = wp_remote_post($settings['proxy_url'] . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $settings['master_key'],
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([
                'model' => 'anthropic/claude-3-sonnet-20240229',
                'messages' => [
                    ['role' => 'user', 'content' => 'Test connection'],
                ],
                'max_tokens' => 10,
            ]),
            'timeout' => $settings['timeout'],
        ]);

        if (is_wp_error($response)) {
            add_settings_error(
                self::OPTION_NAME,
                'connection_failed',
                sprintf(
                    /* translators: %s: Error message */
                    __('Connection test failed: %s', 'memberpress-courses-copilot'),
                    $response->get_error_message()
                ),
                'error'
            );
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                add_settings_error(
                    self::OPTION_NAME,
                    'connection_success',
                    __('Connection test successful!', 'memberpress-courses-copilot'),
                    'success'
                );
            } else {
                add_settings_error(
                    self::OPTION_NAME,
                    'connection_failed',
                    sprintf(
                        /* translators: %d: HTTP status code */
                        __('Connection test failed with status code: %d', 'memberpress-courses-copilot'),
                        $status_code
                    ),
                    'error'
                );
            }
        }
    }
}