<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

/**
 * Copilot Proxy Service
 * 
 * Integrates with the existing MemberPress Copilot plugin to reuse its
 * proxy settings and configuration instead of duplicating them
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class CopilotProxyService extends BaseService
{
    /**
     * MemberPress Copilot settings
     *
     * @var array|null
     */
    private ?array $copilotSettings = null;

    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Check if MemberPress Copilot is available
        add_action('plugins_loaded', [$this, 'checkCopilotAvailability'], 5);
    }

    /**
     * Check if MemberPress Copilot plugin is available and get settings
     *
     * @return void
     */
    public function checkCopilotAvailability(): void
    {
        // Check if MemberPress Copilot is active
        if (!$this->isCopilotActive()) {
            add_action('admin_notices', [$this, 'showCopilotRequiredNotice']);
            return;
        }

        // Load copilot settings
        $this->loadCopilotSettings();
    }

    /**
     * Check if MemberPress Copilot plugin is active
     *
     * @return bool
     */
    public function isCopilotActive(): bool
    {
        // Check if the main copilot class exists
        if (!class_exists('MemberpressAiAssistant')) {
            return false;
        }

        // Check if plugin is active
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active('memberpress-copilot/memberpress-ai-assistant.php');
    }

    /**
     * Load settings from MemberPress Copilot plugin
     *
     * @return void
     */
    private function loadCopilotSettings(): void
    {
        // Get settings from the copilot plugin
        $this->copilotSettings = get_option('mpai_settings', []);
    }

    /**
     * Get proxy URL for Laravel backend
     *
     * @return string|null
     */
    public function getLaravelProxyUrl(): ?string
    {
        if (!$this->copilotSettings) {
            return null;
        }

        return $this->copilotSettings['laravel_proxy_url'] ?? null;
    }

    /**
     * Get proxy URL for LiteLLM
     *
     * @return string|null
     */
    public function getLiteLLMProxyUrl(): ?string
    {
        if (!$this->copilotSettings) {
            return null;
        }

        return $this->copilotSettings['litellm_proxy_url'] ?? null;
    }

    /**
     * Get plugin ID for authentication
     *
     * @return string|null
     */
    public function getPluginId(): ?string
    {
        if (!$this->copilotSettings) {
            return null;
        }

        return $this->copilotSettings['plugin_id'] ?? null;
    }

    /**
     * Get plugin secret for authentication
     *
     * @return string|null
     */
    public function getPluginSecret(): ?string
    {
        if (!$this->copilotSettings) {
            return null;
        }

        return $this->copilotSettings['plugin_secret'] ?? null;
    }

    /**
     * Get virtual key for API requests
     *
     * @return string|null
     */
    public function getVirtualKey(): ?string
    {
        if (!$this->copilotSettings) {
            return null;
        }

        return $this->copilotSettings['virtual_key'] ?? null;
    }

    /**
     * Check if virtual key is expired
     *
     * @return bool
     */
    public function isVirtualKeyExpired(): bool
    {
        if (!$this->copilotSettings || empty($this->copilotSettings['virtual_key_expires'])) {
            return true;
        }

        $expires = strtotime($this->copilotSettings['virtual_key_expires']);
        return $expires <= time();
    }

    /**
     * Get authentication headers for API requests
     *
     * @return array
     */
    public function getAuthHeaders(): array
    {
        $virtualKey = $this->getVirtualKey();
        
        if (!$virtualKey || $this->isVirtualKeyExpired()) {
            // Fall back to basic auth with plugin credentials
            $pluginId = $this->getPluginId();
            $pluginSecret = $this->getPluginSecret();
            
            if ($pluginId && $pluginSecret) {
                return [
                    'Authorization' => 'Basic ' . base64_encode($pluginId . ':' . $pluginSecret),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ];
            }
        } else {
            // Use virtual key authentication
            return [
                'Authorization' => 'Bearer ' . $virtualKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ];
        }

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];
    }

    /**
     * Make authenticated request to Laravel proxy
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    public function makeProxyRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $laravelUrl = $this->getLaravelProxyUrl();
        
        if (!$laravelUrl) {
            return new \WP_Error('no_proxy_url', 'Laravel proxy URL not configured');
        }

        $url = rtrim($laravelUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = $this->getAuthHeaders();

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => false // Allow staging environments
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        return wp_remote_request($url, $args);
    }

    /**
     * Make request to LiteLLM proxy
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param string $method HTTP method
     * @return array|WP_Error
     */
    public function makeLLMRequest(string $endpoint, array $data = [], string $method = 'POST')
    {
        $llmUrl = $this->getLiteLLMProxyUrl();
        
        if (!$llmUrl) {
            return new \WP_Error('no_llm_url', 'LiteLLM proxy URL not configured');
        }

        $url = rtrim($llmUrl, '/') . '/' . ltrim($endpoint, '/');
        $headers = $this->getAuthHeaders();

        $args = [
            'method' => $method,
            'headers' => $headers,
            'timeout' => 60, // Longer timeout for LLM requests
            'sslverify' => false // Allow staging environments
        ];

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($data);
        }

        return wp_remote_request($url, $args);
    }

    /**
     * Show admin notice when copilot plugin is required but not active
     *
     * @return void
     */
    public function showCopilotRequiredNotice(): void
    {
        $class = 'notice notice-error';
        $message = sprintf(
            __('%s requires %s to be installed and activated for AI functionality.', 'memberpress-courses-copilot'),
            '<strong>MemberPress Courses Copilot</strong>',
            '<strong>MemberPress Copilot</strong>'
        );
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
    }

    /**
     * Get all proxy configuration as array
     *
     * @return array
     */
    public function getProxyConfig(): array
    {
        return [
            'laravel_proxy_url' => $this->getLaravelProxyUrl(),
            'litellm_proxy_url' => $this->getLiteLLMProxyUrl(),
            'plugin_id' => $this->getPluginId(),
            'plugin_secret' => $this->getPluginSecret(),
            'virtual_key' => $this->getVirtualKey(),
            'virtual_key_expired' => $this->isVirtualKeyExpired(),
            'auth_headers' => $this->getAuthHeaders()
        ];
    }

    /**
     * Test connection to proxy services
     *
     * @return array Test results
     */
    public function testConnections(): array
    {
        $results = [
            'laravel_proxy' => ['status' => 'untested', 'message' => ''],
            'litellm_proxy' => ['status' => 'untested', 'message' => '']
        ];

        // Test Laravel proxy
        $response = $this->makeProxyRequest('/api/v1/health');
        if (is_wp_error($response)) {
            $results['laravel_proxy']['status'] = 'error';
            $results['laravel_proxy']['message'] = $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                $results['laravel_proxy']['status'] = 'success';
                $results['laravel_proxy']['message'] = 'Connection successful';
            } else {
                $results['laravel_proxy']['status'] = 'error';
                $results['laravel_proxy']['message'] = 'HTTP ' . $code;
            }
        }

        // Test LiteLLM proxy
        $response = $this->makeLLMRequest('/health');
        if (is_wp_error($response)) {
            $results['litellm_proxy']['status'] = 'error';
            $results['litellm_proxy']['message'] = $response->get_error_message();
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ($code === 200) {
                $results['litellm_proxy']['status'] = 'success';
                $results['litellm_proxy']['message'] = 'Connection successful';
            } else {
                $results['litellm_proxy']['status'] = 'error';
                $results['litellm_proxy']['message'] = 'HTTP ' . $code;
            }
        }

        return $results;
    }
}