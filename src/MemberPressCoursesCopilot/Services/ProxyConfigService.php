<?php
/**
 * Proxy Configuration Service for MemberPress Courses Copilot
 *
 * Manages LiteLLM proxy configuration, authentication, and provider
 * model mapping for course generation.
 *
 * @package MemberPressCoursesCopilot
 */

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Service for managing LiteLLM proxy configuration
 */
class ProxyConfigService {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Proxy URL
     *
     * @var string
     */
    private $proxyUrl;

    /**
     * Master key for authentication
     *
     * @var string
     */
    private $masterKey;

    /**
     * MemberPress Copilot proxy service
     *
     * @var CopilotProxyService
     */
    private $copilotProxy;

    /**
     * Provider model mappings
     *
     * @var array
     */
    private $providerModels = [
        'openai' => [
            'gpt-4' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.03,
                'supports_functions' => true,
                'context_window' => 8192
            ],
            'gpt-4-turbo-preview' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.01,
                'supports_functions' => true,
                'context_window' => 128000
            ],
            'gpt-3.5-turbo' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.002,
                'supports_functions' => true,
                'context_window' => 16385
            ]
        ],
        'anthropic' => [
            'claude-3-opus-20240229' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.075,
                'supports_functions' => false,
                'context_window' => 200000
            ],
            'claude-3-sonnet-20240229' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.015,
                'supports_functions' => false,
                'context_window' => 200000
            ],
            'claude-3-haiku-20240307' => [
                'max_tokens' => 4096,
                'cost_per_1k_tokens' => 0.0025,
                'supports_functions' => false,
                'context_window' => 200000
            ]
        ]
    ];

    /**
     * Available providers and their status
     *
     * @var array
     */
    private $providerStatus = [];

    /**
     * Configuration cache
     *
     * @var array
     */
    private $configCache = [];

    /**
     * Cache expiration time in seconds
     *
     * @var int
     */
    private $cacheExpiration = 300; // 5 minutes

    /**
     * Constructor
     *
     * @param CopilotProxyService $copilotProxy MemberPress Copilot proxy service
     * @param Logger $logger Logger instance
     * @param string $masterKey Optional master key override
     */
    public function __construct(CopilotProxyService $copilotProxy, Logger $logger, string $masterKey = null) {
        $this->logger = $logger;
        
        // Use existing MemberPress Copilot configuration
        $this->copilotProxy = $copilotProxy;
        $this->proxyUrl = $copilotProxy->getLiteLLMProxyUrl() ?: 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com';
        $this->masterKey = $masterKey ?: $copilotProxy->getVirtualKey() ?: '';
        
        $this->loadConfiguration();
        $this->validateCopilotIntegration();
    }"

    /**
     * Get available providers from the proxy
     *
     * @param bool $refresh Force refresh from proxy
     * @return array Available provider names
     */
    public function getAvailableProviders(bool $refresh = false): array {
        if (!$refresh && !empty($this->providerStatus)) {
            return array_keys(array_filter($this->providerStatus, function($status) {
                return $status['available'];
            }));
        }

        try {
            $this->checkProviderAvailability();
            
            $availableProviders = array_keys(array_filter($this->providerStatus, function($status) {
                return $status['available'];
            }));

            $this->logger->debug('ProxyConfigService: Retrieved available providers', [
                'available_providers' => $availableProviders,
                'total_providers' => count($this->providerStatus)
            ]);

            return $availableProviders;

        } catch (\Exception $e) {
            $this->logger->error('ProxyConfigService: Failed to get available providers', [
                'error' => $e->getMessage()
            ]);

            // Return default providers as fallback
            return ['openai', 'anthropic'];
        }
    }

    /**
     * Get available models for a specific provider
     *
     * @param string $provider Provider name
     * @return array Available models
     */
    public function getAvailableModels(string $provider): array {
        if (!isset($this->providerModels[$provider])) {
            $this->logger->warning('ProxyConfigService: Unknown provider requested', [
                'provider' => $provider
            ]);
            return [];
        }

        $models = array_keys($this->providerModels[$provider]);
        
        $this->logger->debug('ProxyConfigService: Retrieved models for provider', [
            'provider' => $provider,
            'models' => $models
        ]);

        return $models;
    }

    /**
     * Get model information
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @return array Model information
     */
    public function getModelInfo(string $provider, string $model): array {
        if (!isset($this->providerModels[$provider][$model])) {
            $this->logger->warning('ProxyConfigService: Unknown model requested', [
                'provider' => $provider,
                'model' => $model
            ]);
            return [];
        }

        return $this->providerModels[$provider][$model];
    }

    /**
     * Get optimal model for a specific task
     *
     * @param string $provider Provider name
     * @param string $taskType Task type (creative|analytical|structured|general)
     * @param array $requirements Task requirements
     * @return string Optimal model name
     */
    public function getOptimalModel(string $provider, string $taskType, array $requirements = []): string {
        $availableModels = $this->getAvailableModels($provider);
        
        if (empty($availableModels)) {
            throw new \Exception("No models available for provider: {$provider}");
        }

        $optimalModel = $this->selectModelByTask($provider, $taskType, $requirements, $availableModels);

        $this->logger->debug('ProxyConfigService: Selected optimal model', [
            'provider' => $provider,
            'task_type' => $taskType,
            'selected_model' => $optimalModel,
            'requirements' => $requirements
        ]);

        return $optimalModel;
    }

    /**
     * Check provider availability
     *
     * @return void
     */
    private function checkProviderAvailability(): void {
        $cacheKey = 'provider_status';
        $cached = $this->getCachedConfig($cacheKey);
        
        if ($cached !== null) {
            $this->providerStatus = $cached;
            return;
        }

        foreach (array_keys($this->providerModels) as $provider) {
            $this->providerStatus[$provider] = $this->testProviderConnection($provider);
        }

        $this->setCachedConfig($cacheKey, $this->providerStatus);
    }

    /**
     * Test connection to a specific provider
     *
     * @param string $provider Provider name
     * @return array Provider status information
     */
    private function testProviderConnection(string $provider): array {
        $status = [
            'available' => false,
            'last_checked' => time(),
            'response_time' => null,
            'error' => null
        ];

        try {
            $models = $this->getAvailableModels($provider);
            if (empty($models)) {
                throw new \Exception("No models configured for provider: {$provider}");
            }

            $testModel = $models[0]; // Use first available model for testing
            $startTime = microtime(true);
            
            // Make a minimal test request
            $testResponse = $this->makeTestRequest($provider, $testModel);
            
            $status['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            $status['available'] = true;

            $this->logger->debug('ProxyConfigService: Provider test successful', [
                'provider' => $provider,
                'model' => $testModel,
                'response_time' => $status['response_time']
            ]);

        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
            
            $this->logger->warning('ProxyConfigService: Provider test failed', [
                'provider' => $provider,
                'error' => $e->getMessage()
            ]);
        }

        return $status;
    }

    /**
     * Make a test request to verify provider availability using MemberPress Copilot
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @return array Response data
     * @throws \Exception If test fails
     */
    private function makeTestRequest(string $provider, string $model): array {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => 'Test connection']
            ],
            'max_tokens' => 5,
            'temperature' => 0.1
        ];

        try {
            // Use copilot proxy service for the request
            $response = $this->copilotProxy->makeLLMRequest('/chat/completions', $payload);

            if (is_wp_error($response)) {
                throw new \Exception('Test request failed: ' . $response->get_error_message());
            }

            $responseCode = wp_remote_retrieve_response_code($response);
            if ($responseCode >= 400) {
                $responseBody = wp_remote_retrieve_body($response);
                
                // Try to refresh credentials if unauthorized
                if ($responseCode === 401 && $this->refreshCredentials()) {
                    $response = $this->copilotProxy->makeLLMRequest('/chat/completions', $payload);
                    $responseCode = wp_remote_retrieve_response_code($response);
                    $responseBody = wp_remote_retrieve_body($response);
                }
                
                if ($responseCode >= 400) {
                    throw new \Exception("Test request failed with status {$responseCode}: {$responseBody}");
                }
            }

            $responseData = json_decode(wp_remote_retrieve_body($response), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from test request');
            }

            return $responseData;
            
        } catch (\Exception $e) {
            $this->logger->error('ProxyConfigService: Test request failed', [
                'provider' => $provider,
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            
            throw $e;
        }
    }

    /**
     * Select optimal model based on task requirements
     *
     * @param string $provider Provider name
     * @param string $taskType Task type
     * @param array $requirements Task requirements
     * @param array $availableModels Available models
     * @return string Selected model name
     */
    private function selectModelByTask(string $provider, string $taskType, array $requirements, array $availableModels): string {
        $scores = [];

        foreach ($availableModels as $model) {
            $modelInfo = $this->getModelInfo($provider, $model);
            $score = $this->calculateModelScore($taskType, $requirements, $modelInfo);
            $scores[$model] = $score;
        }

        arsort($scores);
        $selectedModel = array_key_first($scores);

        $this->logger->debug('ProxyConfigService: Model selection scores', [
            'provider' => $provider,
            'task_type' => $taskType,
            'scores' => $scores,
            'selected' => $selectedModel
        ]);

        return $selectedModel;
    }

    /**
     * Calculate model score for a specific task
     *
     * @param string $taskType Task type
     * @param array $requirements Task requirements
     * @param array $modelInfo Model information
     * @return float Model score
     */
    private function calculateModelScore(string $taskType, array $requirements, array $modelInfo): float {
        $score = 0.0;

        // Base score based on task type
        switch ($taskType) {
            case 'creative':
                $score += 0.4; // All models get base score
                if ($modelInfo['context_window'] > 50000) $score += 0.3;
                break;
            case 'analytical':
                $score += 0.3;
                if ($modelInfo['supports_functions']) $score += 0.4;
                if ($modelInfo['max_tokens'] >= 4096) $score += 0.2;
                break;
            case 'structured':
                $score += 0.2;
                if ($modelInfo['supports_functions']) $score += 0.5;
                if ($modelInfo['cost_per_1k_tokens'] < 0.01) $score += 0.2;
                break;
            default: // general
                $score += 0.5;
                if ($modelInfo['cost_per_1k_tokens'] < 0.01) $score += 0.3;
                break;
        }

        // Adjust based on requirements
        if (isset($requirements['max_budget'])) {
            $budgetScore = 1.0 - ($modelInfo['cost_per_1k_tokens'] / 0.1);
            $score += $budgetScore * 0.2;
        }

        if (isset($requirements['requires_functions']) && $requirements['requires_functions']) {
            if ($modelInfo['supports_functions']) {
                $score += 0.3;
            } else {
                $score -= 0.5;
            }
        }

        if (isset($requirements['context_size']) && $requirements['context_size'] > 10000) {
            if ($modelInfo['context_window'] >= $requirements['context_size']) {
                $score += 0.2;
            } else {
                $score -= 0.3;
            }
        }

        return max(0.0, min(1.0, $score));
    }

    /**
     * Get authentication headers with automatic refresh
     *
     * @return array Authentication headers
     */
    public function getAuthHeaders(): array {
        // Check if credentials need refresh
        if ($this->copilotProxy->isVirtualKeyExpired()) {
            $this->refreshCredentials();
        }

        $headers = [
            'Content-Type' => 'application/json',
            'User-Agent' => 'MemberPress-Courses-Copilot/1.0'
        ];

        if (!empty($this->masterKey)) {
            $headers['Authorization'] = 'Bearer ' . $this->masterKey;
        } else {
            // Fallback to copilot proxy headers
            $copilotHeaders = $this->copilotProxy->getAuthHeaders();
            if (isset($copilotHeaders['Authorization'])) {
                $headers['Authorization'] = $copilotHeaders['Authorization'];
            }
        }

        return $headers;
    }

    /**
     * Load configuration from WordPress options
     *
     * @return void
     */
    private function loadConfiguration(): void {
        $config = get_option('mpc_copilot_config', []);

        if (isset($config['proxy_url'])) {
            $this->proxyUrl = $config['proxy_url'];
        }

        if (isset($config['provider_models'])) {
            $this->providerModels = array_merge($this->providerModels, $config['provider_models']);
        }

        $this->logger->debug('ProxyConfigService: Configuration loaded', [
            'proxy_url' => $this->proxyUrl,
            'providers' => array_keys($this->providerModels)
        ]);
    }

    /**
     * Save configuration to WordPress options
     *
     * @return void
     */
    public function saveConfiguration(): void {
        $config = [
            'proxy_url' => $this->proxyUrl,
            'provider_models' => $this->providerModels,
            'last_updated' => time()
        ];

        update_option('mpc_copilot_config', $config);

        $this->logger->info('ProxyConfigService: Configuration saved', [
            'providers' => array_keys($this->providerModels)
        ]);
    }

    /**
     * Get cached configuration value
     *
     * @param string $key Cache key
     * @return mixed Cached value or null
     */
    private function getCachedConfig(string $key) {
        if (!isset($this->configCache[$key])) {
            return null;
        }

        $cached = $this->configCache[$key];
        if (time() - $cached['timestamp'] > $this->cacheExpiration) {
            unset($this->configCache[$key]);
            return null;
        }

        return $cached['data'];
    }

    /**
     * Set cached configuration value
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     * @return void
     */
    private function setCachedConfig(string $key, $data): void {
        $this->configCache[$key] = [
            'data' => $data,
            'timestamp' => time()
        ];
    }

    /**
     * Get provider status information
     *
     * @return array Provider status information
     */
    public function getProviderStatus(): array {
        return $this->providerStatus;
    }

    /**
     * Get cost estimate for a request
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @param int $inputTokens Input token count
     * @param int $outputTokens Output token count
     * @return float Estimated cost in USD
     */
    public function estimateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float {
        $modelInfo = $this->getModelInfo($provider, $model);
        
        if (empty($modelInfo) || !isset($modelInfo['cost_per_1k_tokens'])) {
            return 0.0;
        }

        $totalTokens = $inputTokens + $outputTokens;
        $cost = ($totalTokens / 1000) * $modelInfo['cost_per_1k_tokens'];

        $this->logger->debug('ProxyConfigService: Cost estimated', [
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'estimated_cost' => $cost
        ]);

        return round($cost, 6);
    }

    /**
     * Get proxy URL
     *
     * @return string Proxy URL
     */
    public function getProxyUrl(): string {
        return $this->proxyUrl;
    }

    /**
     * Set proxy URL
     *
     * @param string $url Proxy URL
     * @return void
     */
    public function setProxyUrl(string $url): void {
        $this->proxyUrl = rtrim($url, '/');
        $this->saveConfiguration();
    }

    /**
     * Get master key (masked for logging)
     *
     * @return string Masked master key
     */
    public function getMaskedMasterKey(): string {
        return substr($this->masterKey, 0, 8) . '...' . substr($this->masterKey, -4);
    }

    /**
     * Validate MemberPress Copilot integration
     *
     * @return void
     */
    private function validateCopilotIntegration(): void {
        if (!$this->copilotProxy->isCopilotActive()) {
            $this->logger->warning('ProxyConfigService: MemberPress Copilot is not active', [
                'fallback_url' => $this->proxyUrl
            ]);
            return;
        }

        // Check for virtual key expiration
        if ($this->copilotProxy->isVirtualKeyExpired()) {
            $this->logger->warning('ProxyConfigService: Virtual key is expired', [
                'requires_refresh' => true
            ]);
        }

        // Test connection to ensure proxy is accessible
        $connectionTest = $this->copilotProxy->testConnections();
        
        if ($connectionTest['litellm_proxy']['status'] !== 'success') {
            $this->logger->error('ProxyConfigService: LiteLLM proxy connection failed', [
                'error' => $connectionTest['litellm_proxy']['message']
            ]);
        } else {
            $this->logger->info('ProxyConfigService: Successfully connected to MemberPress Copilot proxy');
        }
    }

    /**
     * Refresh authentication credentials from MemberPress Copilot
     *
     * @return bool True if refresh was successful
     */
    public function refreshCredentials(): bool {
        try {
            // Reload copilot settings
            $this->copilotProxy->checkCopilotAvailability();
            
            // Update proxy URL and credentials
            $newProxyUrl = $this->copilotProxy->getLiteLLMProxyUrl();
            $newVirtualKey = $this->copilotProxy->getVirtualKey();
            
            if ($newProxyUrl && $newVirtualKey && !$this->copilotProxy->isVirtualKeyExpired()) {
                $this->proxyUrl = $newProxyUrl;
                $this->masterKey = $newVirtualKey;
                
                $this->logger->info('ProxyConfigService: Credentials refreshed successfully');
                return true;
            }
            
            $this->logger->warning('ProxyConfigService: Could not refresh credentials', [
                'has_proxy_url' => !empty($newProxyUrl),
                'has_virtual_key' => !empty($newVirtualKey),
                'key_expired' => $this->copilotProxy->isVirtualKeyExpired()
            ]);
            
            return false;
            
        } catch (\Exception $e) {
            $this->logger->error('ProxyConfigService: Credential refresh failed', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get provider health status with detailed diagnostics
     *
     * @return array Provider health information
     */
    public function getProviderHealthStatus(): array {
        $health = [
            'overall_status' => 'unknown',
            'copilot_integration' => 'unknown',
            'providers' => [],
            'last_checked' => time()
        ];

        try {
            // Check MemberPress Copilot integration
            if ($this->copilotProxy->isCopilotActive()) {
                $health['copilot_integration'] = 'active';
                
                if ($this->copilotProxy->isVirtualKeyExpired()) {
                    $health['copilot_integration'] = 'expired_key';
                }
            } else {
                $health['copilot_integration'] = 'inactive';
            }

            // Test each provider
            foreach (array_keys($this->providerModels) as $provider) {
                $health['providers'][$provider] = $this->testProviderConnection($provider);
            }

            // Determine overall status
            $availableCount = count(array_filter($health['providers'], function($status) {
                return $status['available'];
            }));

            if ($availableCount === 0) {
                $health['overall_status'] = 'critical';
            } elseif ($availableCount < count($health['providers'])) {
                $health['overall_status'] = 'degraded';
            } else {
                $health['overall_status'] = 'healthy';
            }

        } catch (\Exception $e) {
            $health['overall_status'] = 'error';
            $health['error'] = $e->getMessage();
            
            $this->logger->error('ProxyConfigService: Health check failed', [
                'error' => $e->getMessage()
            ]);
        }

        return $health;
    }

    /**
     * Get comprehensive proxy configuration status
     *
     * @return array Configuration status
     */
    public function getConfigurationStatus(): array {
        return [
            'proxy_url' => $this->proxyUrl,
            'has_master_key' => !empty($this->masterKey),
            'master_key_masked' => $this->getMaskedMasterKey(),
            'copilot_active' => $this->copilotProxy->isCopilotActive(),
            'virtual_key_expired' => $this->copilotProxy->isVirtualKeyExpired(),
            'provider_count' => count($this->providerModels),
            'available_providers' => $this->getAvailableProviders(),
            'configuration_valid' => !empty($this->proxyUrl) && !empty($this->masterKey),
            'last_updated' => get_option('mpc_copilot_config', [])['last_updated'] ?? null
        ];
    }
}