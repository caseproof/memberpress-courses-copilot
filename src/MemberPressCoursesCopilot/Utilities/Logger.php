<?php
/**
 * Logger Utility for MemberPress Courses Copilot
 *
 * Provides comprehensive logging for API calls, error tracking,
 * cost monitoring, and debug support.
 *
 * @package MemberPressCoursesCopilot
 */

namespace MemberPressCoursesCopilot\Utilities;

/**
 * Logger utility class for tracking API usage and debugging
 */
class Logger {
    /**
     * Log levels
     */
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Current log level
     *
     * @var string
     */
    private $logLevel;

    /**
     * Whether debug mode is enabled
     *
     * @var bool
     */
    private $debugMode;

    /**
     * Log file path
     *
     * @var string
     */
    private $logFile;

    /**
     * Cost tracking data
     *
     * @var array
     */
    private $costTracker = [];

    /**
     * API usage statistics
     *
     * @var array
     */
    private $apiStats = [];

    /**
     * Maximum log file size in bytes (10MB)
     *
     * @var int
     */
    private $maxLogSize = 10485760;

    /**
     * Log rotation count
     *
     * @var int
     */
    private $rotationCount = 5;

    /**
     * Constructor
     *
     * @param string $logLevel Minimum log level to record
     * @param bool $debugMode Whether debug mode is enabled
     */
    public function __construct(string $logLevel = self::LEVEL_INFO, bool $debugMode = false) {
        $this->logLevel = $logLevel;
        $this->debugMode = $debugMode || defined('WP_DEBUG') && WP_DEBUG;
        
        $uploadDir = wp_upload_dir();
        $logDir = $uploadDir['basedir'] . '/memberpress-courses-copilot/logs';
        
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
        }
        
        $this->logFile = $logDir . '/copilot.log';
        
        $this->loadCostTracker();
        $this->loadApiStats();
    }

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function debug(string $message, array $context = []): void {
        if ($this->shouldLog(self::LEVEL_DEBUG)) {
            $this->log(self::LEVEL_DEBUG, $message, $context);
        }
    }

    /**
     * Log an info message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function info(string $message, array $context = []): void {
        if ($this->shouldLog(self::LEVEL_INFO)) {
            $this->log(self::LEVEL_INFO, $message, $context);
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function warning(string $message, array $context = []): void {
        if ($this->shouldLog(self::LEVEL_WARNING)) {
            $this->log(self::LEVEL_WARNING, $message, $context);
        }
    }

    /**
     * Log an error message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function error(string $message, array $context = []): void {
        if ($this->shouldLog(self::LEVEL_ERROR)) {
            $this->log(self::LEVEL_ERROR, $message, $context);
        }
    }

    /**
     * Log a critical message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function critical(string $message, array $context = []): void {
        if ($this->shouldLog(self::LEVEL_CRITICAL)) {
            $this->log(self::LEVEL_CRITICAL, $message, $context);
        }
    }

    /**
     * Log an API call with cost tracking
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @param array $usage Usage statistics
     * @param float $cost Estimated cost
     * @param array $context Additional context
     * @return void
     */
    public function logApiCall(string $provider, string $model, array $usage, float $cost, array $context = []): void {
        $apiCallData = [
            'provider' => $provider,
            'model' => $model,
            'usage' => $usage,
            'cost' => $cost,
            'timestamp' => time(),
            'context' => $context
        ];

        $this->info('API call completed', $apiCallData);
        $this->trackCost($provider, $model, $cost, $usage);
        $this->updateApiStats($provider, $model, $usage);
    }

    /**
     * Track API costs
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @param float $cost Cost amount
     * @param array $usage Usage statistics
     * @return void
     */
    private function trackCost(string $provider, string $model, float $cost, array $usage): void {
        $date = date('Y-m-d');
        
        if (!isset($this->costTracker[$date])) {
            $this->costTracker[$date] = [
                'total_cost' => 0.0,
                'providers' => []
            ];
        }

        if (!isset($this->costTracker[$date]['providers'][$provider])) {
            $this->costTracker[$date]['providers'][$provider] = [
                'cost' => 0.0,
                'calls' => 0,
                'tokens' => 0,
                'models' => []
            ];
        }

        if (!isset($this->costTracker[$date]['providers'][$provider]['models'][$model])) {
            $this->costTracker[$date]['providers'][$provider]['models'][$model] = [
                'cost' => 0.0,
                'calls' => 0,
                'tokens' => 0
            ];
        }

        // Update totals
        $this->costTracker[$date]['total_cost'] += $cost;
        $this->costTracker[$date]['providers'][$provider]['cost'] += $cost;
        $this->costTracker[$date]['providers'][$provider]['calls']++;
        $this->costTracker[$date]['providers'][$provider]['models'][$model]['cost'] += $cost;
        $this->costTracker[$date]['providers'][$provider]['models'][$model]['calls']++;

        // Track tokens if available
        $totalTokens = ($usage['total_tokens'] ?? 0) ?: 
                      (($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0));
        
        if ($totalTokens > 0) {
            $this->costTracker[$date]['providers'][$provider]['tokens'] += $totalTokens;
            $this->costTracker[$date]['providers'][$provider]['models'][$model]['tokens'] += $totalTokens;
        }

        $this->saveCostTracker();
    }

    /**
     * Update API usage statistics
     *
     * @param string $provider Provider name
     * @param string $model Model name
     * @param array $usage Usage statistics
     * @return void
     */
    private function updateApiStats(string $provider, string $model, array $usage): void {
        if (!isset($this->apiStats[$provider])) {
            $this->apiStats[$provider] = [
                'total_calls' => 0,
                'total_tokens' => 0,
                'models' => [],
                'last_used' => null
            ];
        }

        if (!isset($this->apiStats[$provider]['models'][$model])) {
            $this->apiStats[$provider]['models'][$model] = [
                'calls' => 0,
                'tokens' => 0,
                'last_used' => null
            ];
        }

        $totalTokens = ($usage['total_tokens'] ?? 0) ?: 
                      (($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0));

        $this->apiStats[$provider]['total_calls']++;
        $this->apiStats[$provider]['total_tokens'] += $totalTokens;
        $this->apiStats[$provider]['last_used'] = time();
        $this->apiStats[$provider]['models'][$model]['calls']++;
        $this->apiStats[$provider]['models'][$model]['tokens'] += $totalTokens;
        $this->apiStats[$provider]['models'][$model]['last_used'] = time();

        $this->saveApiStats();
    }

    /**
     * Get cost tracking data
     *
     * @param string $date Optional date (Y-m-d format)
     * @return array Cost tracking data
     */
    public function getCostData(string $date = null): array {
        if ($date === null) {
            return $this->costTracker;
        }

        return $this->costTracker[$date] ?? [];
    }

    /**
     * Get API usage statistics
     *
     * @param string $provider Optional provider filter
     * @return array API statistics
     */
    public function getApiStats(string $provider = null): array {
        if ($provider === null) {
            return $this->apiStats;
        }

        return $this->apiStats[$provider] ?? [];
    }

    /**
     * Get daily cost summary
     *
     * @param int $days Number of days to include
     * @return array Daily cost summary
     */
    public function getDailyCostSummary(int $days = 30): array {
        $summary = [];
        $today = time();

        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', $today - ($i * 86400));
            $dayData = $this->costTracker[$date] ?? null;
            
            $summary[$date] = [
                'total_cost' => $dayData['total_cost'] ?? 0.0,
                'total_calls' => 0,
                'providers' => []
            ];

            if ($dayData) {
                foreach ($dayData['providers'] as $provider => $providerData) {
                    $summary[$date]['total_calls'] += $providerData['calls'];
                    $summary[$date]['providers'][$provider] = [
                        'cost' => $providerData['cost'],
                        'calls' => $providerData['calls'],
                        'tokens' => $providerData['tokens']
                    ];
                }
            }
        }

        return array_reverse($summary, true);
    }

    /**
     * Get monthly cost summary
     *
     * @param string $month Month in Y-m format
     * @return array Monthly cost summary
     */
    public function getMonthlyCostSummary(string $month = null): array {
        if ($month === null) {
            $month = date('Y-m');
        }

        $monthlyCost = 0.0;
        $monthlyCalls = 0;
        $monthlyTokens = 0;
        $providers = [];

        foreach ($this->costTracker as $date => $dayData) {
            if (strpos($date, $month) === 0) {
                $monthlyCost += $dayData['total_cost'];
                
                foreach ($dayData['providers'] as $provider => $providerData) {
                    if (!isset($providers[$provider])) {
                        $providers[$provider] = [
                            'cost' => 0.0,
                            'calls' => 0,
                            'tokens' => 0
                        ];
                    }
                    
                    $providers[$provider]['cost'] += $providerData['cost'];
                    $providers[$provider]['calls'] += $providerData['calls'];
                    $providers[$provider]['tokens'] += $providerData['tokens'];
                    
                    $monthlyCalls += $providerData['calls'];
                    $monthlyTokens += $providerData['tokens'];
                }
            }
        }

        return [
            'month' => $month,
            'total_cost' => $monthlyCost,
            'total_calls' => $monthlyCalls,
            'total_tokens' => $monthlyTokens,
            'providers' => $providers
        ];
    }

    /**
     * Clean old log entries
     *
     * @param int $daysToKeep Number of days to keep
     * @return void
     */
    public function cleanOldLogs(int $daysToKeep = 90): void {
        $cutoffDate = date('Y-m-d', time() - ($daysToKeep * 86400));
        
        // Clean cost tracker
        foreach (array_keys($this->costTracker) as $date) {
            if ($date < $cutoffDate) {
                unset($this->costTracker[$date]);
            }
        }
        
        $this->saveCostTracker();
        
        // Rotate log file if needed
        $this->rotateLogFile();
        
        $this->info('Log cleanup completed', [
            'days_kept' => $daysToKeep,
            'cutoff_date' => $cutoffDate
        ]);
    }

    /**
     * Core logging method
     *
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] [{$level}] {$message}{$contextString}" . PHP_EOL;

        // Write to file
        if ($this->logFile && is_writable(dirname($this->logFile))) {
            error_log($logEntry, 3, $this->logFile);
        }

        // Also log to WordPress debug log if debug mode is enabled
        if ($this->debugMode && function_exists('error_log')) {
            error_log("MPC-Copilot [{$level}]: {$message}" . $contextString);
        }

        // Check for log rotation
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $this->rotateLogFile();
        }
    }

    /**
     * Check if message should be logged based on log level
     *
     * @param string $level Message level
     * @return bool Whether to log the message
     */
    private function shouldLog(string $level): bool {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3,
            self::LEVEL_CRITICAL => 4
        ];

        $currentLevelValue = $levels[$this->logLevel] ?? 1;
        $messageLevelValue = $levels[$level] ?? 1;

        return $messageLevelValue >= $currentLevelValue;
    }

    /**
     * Rotate log file when it gets too large
     *
     * @return void
     */
    private function rotateLogFile(): void {
        if (!file_exists($this->logFile)) {
            return;
        }

        $logDir = dirname($this->logFile);
        $logBasename = basename($this->logFile, '.log');

        // Rotate existing log files
        for ($i = $this->rotationCount - 1; $i > 0; $i--) {
            $oldFile = $logDir . '/' . $logBasename . '.' . $i . '.log';
            $newFile = $logDir . '/' . $logBasename . '.' . ($i + 1) . '.log';
            
            if (file_exists($oldFile)) {
                if ($i == $this->rotationCount - 1) {
                    unlink($oldFile);
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }

        // Move current log to .1
        $firstRotation = $logDir . '/' . $logBasename . '.1.log';
        rename($this->logFile, $firstRotation);
    }

    /**
     * Load cost tracker from WordPress options
     *
     * @return void
     */
    private function loadCostTracker(): void {
        $this->costTracker = get_option('mpc_copilot_cost_tracker', []);
    }

    /**
     * Save cost tracker to WordPress options
     *
     * @return void
     */
    private function saveCostTracker(): void {
        update_option('mpc_copilot_cost_tracker', $this->costTracker);
    }

    /**
     * Load API stats from WordPress options
     *
     * @return void
     */
    private function loadApiStats(): void {
        $this->apiStats = get_option('mpc_copilot_api_stats', []);
    }

    /**
     * Save API stats to WordPress options
     *
     * @return void
     */
    private function saveApiStats(): void {
        update_option('mpc_copilot_api_stats', $this->apiStats);
    }

    /**
     * Set log level
     *
     * @param string $level Log level
     * @return void
     */
    public function setLogLevel(string $level): void {
        $this->logLevel = $level;
    }

    /**
     * Get current log level
     *
     * @return string Current log level
     */
    public function getLogLevel(): string {
        return $this->logLevel;
    }

    /**
     * Enable or disable debug mode
     *
     * @param bool $enabled Whether debug mode is enabled
     * @return void
     */
    public function setDebugMode(bool $enabled): void {
        $this->debugMode = $enabled;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool Whether debug mode is enabled
     */
    public function isDebugMode(): bool {
        return $this->debugMode;
    }

    /**
     * Get log file path
     *
     * @return string Log file path
     */
    public function getLogFile(): string {
        return $this->logFile;
    }
}