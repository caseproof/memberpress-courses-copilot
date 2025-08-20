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
     * Singleton instance
     *
     * @var Logger|null
     */
    private static $instance = null;

    /**
     * Whether logging is enabled
     *
     * @var bool
     */
    private $loggingEnabled;

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
     * Cost tracking data (lazy loaded)
     *
     * @var array|null
     */
    private $costTracker = null;

    /**
     * API usage statistics (lazy loaded)
     *
     * @var array|null
     */
    private $apiStats = null;

    /**
     * Whether cost tracker has been loaded
     *
     * @var bool
     */
    private $costTrackerLoaded = false;

    /**
     * Whether API stats have been loaded
     *
     * @var bool
     */
    private $apiStatsLoaded = false;

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
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor for singleton
     *
     * @param string $logLevel Minimum log level to record
     * @param bool $debugMode Whether debug mode is enabled
     */
    private function __construct(string $logLevel = self::LEVEL_INFO, bool $debugMode = false) {
        $this->loggingEnabled = $this->determineLoggingState();
        
        // Check for log level configuration in wp-config.php
        if (defined('MPCC_LOG_LEVEL')) {
            $validLevels = [self::LEVEL_DEBUG, self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR, self::LEVEL_CRITICAL];
            if (in_array(MPCC_LOG_LEVEL, $validLevels)) {
                $logLevel = MPCC_LOG_LEVEL;
            }
        }
        
        $this->logLevel = $logLevel;
        $this->debugMode = $debugMode || defined('WP_DEBUG') && WP_DEBUG;
        
        // Only initialize log file if logging is enabled
        if ($this->loggingEnabled) {
            $this->initializeLogFile();
        }
    }

    /**
     * Prevent cloning of singleton
     */
    private function __clone() {}

    /**
     * Prevent unserialization of singleton
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Determine if logging should be enabled based on configuration
     *
     * @return bool
     */
    private function determineLoggingState(): bool {
        // Only enable logging if WP_DEBUG is active
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Initialize log file path and directory
     *
     * @return void
     */
    private function initializeLogFile(): void {
        $uploadDir = wp_upload_dir();
        $logDir = $uploadDir['basedir'] . '/memberpress-courses-copilot/logs';
        
        if (!file_exists($logDir)) {
            wp_mkdir_p($logDir);
            $this->protectLogFiles($logDir);
        }
        
        $this->logFile = $logDir . '/copilot.log';
    }

    /**
     * Protect log files from web access
     *
     * @param string $logDir Log directory path
     * @return void
     */
    private function protectLogFiles(string $logDir): void {
        // Create .htaccess to prevent web access
        $htaccessPath = $logDir . '/.htaccess';
        $htaccessContent = "Order Deny,Allow\nDeny from all\n";
        
        if (!file_exists($htaccessPath)) {
            @file_put_contents($htaccessPath, $htaccessContent);
        }
        
        // Create index.php to prevent directory listing
        $indexPath = $logDir . '/index.php';
        if (!file_exists($indexPath)) {
            @file_put_contents($indexPath, '<?php // Silence is golden');
        }
    }

    /**
     * Log a debug message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     * @return void
     */
    public function debug(string $message, array $context = []): void {
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
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
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
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
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
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
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
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
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
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
        // Early return if logging is disabled
        if (!$this->loggingEnabled) {
            return;
        }
        
        // Use lazy loading to get cost tracker data
        $costTracker = $this->getCostTrackerData();
        $date = date('Y-m-d');
        
        if (!isset($costTracker[$date])) {
            $costTracker[$date] = [
                'total_cost' => 0.0,
                'providers' => []
            ];
        }

        if (!isset($costTracker[$date]['providers'][$provider])) {
            $costTracker[$date]['providers'][$provider] = [
                'cost' => 0.0,
                'calls' => 0,
                'tokens' => 0,
                'models' => []
            ];
        }

        if (!isset($costTracker[$date]['providers'][$provider]['models'][$model])) {
            $costTracker[$date]['providers'][$provider]['models'][$model] = [
                'cost' => 0.0,
                'calls' => 0,
                'tokens' => 0
            ];
        }

        // Update totals
        $costTracker[$date]['total_cost'] += $cost;
        $costTracker[$date]['providers'][$provider]['cost'] += $cost;
        $costTracker[$date]['providers'][$provider]['calls']++;
        $costTracker[$date]['providers'][$provider]['models'][$model]['cost'] += $cost;
        $costTracker[$date]['providers'][$provider]['models'][$model]['calls']++;

        // Track tokens if available
        $totalTokens = ($usage['total_tokens'] ?? 0) ?: 
                      (($usage['prompt_tokens'] ?? 0) + ($usage['completion_tokens'] ?? 0));
        
        if ($totalTokens > 0) {
            $costTracker[$date]['providers'][$provider]['tokens'] += $totalTokens;
            $costTracker[$date]['providers'][$provider]['models'][$model]['tokens'] += $totalTokens;
        }

        // Update the instance variable and save
        $this->costTracker = $costTracker;
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
        // Early return for performance when logging is disabled
        if (!$this->loggingEnabled) {
            return false;
        }
        
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
     * Load cost tracker from WordPress options (lazy loaded)
     *
     * @return array
     */
    private function getCostTrackerData(): array {
        if (!$this->costTrackerLoaded) {
            $this->costTracker = get_option('mpc_copilot_cost_tracker', []);
            $this->costTrackerLoaded = true;
            
            // Implement size limits to prevent memory issues
            if (strlen(serialize($this->costTracker)) > 1048576) { // 1MB limit
                $this->rotateCostData();
            }
        }
        
        return $this->costTracker ?? [];
    }

    /**
     * Save cost tracker to WordPress options
     *
     * @return void
     */
    private function saveCostTracker(): void {
        if ($this->costTracker !== null) {
            update_option('mpc_copilot_cost_tracker', $this->costTracker);
        }
    }

    /**
     * Load API stats from WordPress options (lazy loaded)
     *
     * @return array
     */
    private function getApiStatsData(): array {
        if (!$this->apiStatsLoaded) {
            $this->apiStats = get_option('mpc_copilot_api_stats', []);
            $this->apiStatsLoaded = true;
        }
        
        return $this->apiStats ?? [];
    }

    /**
     * Save API stats to WordPress options
     *
     * @return void
     */
    private function saveApiStats(): void {
        if ($this->apiStats !== null) {
            update_option('mpc_copilot_api_stats', $this->apiStats);
        }
    }

    /**
     * Rotate cost data when it gets too large
     *
     * @return void
     */
    private function rotateCostData(): void {
        if (!$this->costTracker) {
            return;
        }
        
        // Keep only last 30 days of data
        $cutoffDate = date('Y-m-d', time() - (30 * 86400));
        
        foreach (array_keys($this->costTracker) as $date) {
            if ($date < $cutoffDate) {
                unset($this->costTracker[$date]);
            }
        }
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

    /**
     * Enable or disable logging (only works if WP_DEBUG is active)
     *
     * @param bool $enabled Whether logging should be enabled
     * @return void
     */
    public function setLoggingEnabled(bool $enabled): void {
        // Only allow enabling if WP_DEBUG is active
        $this->loggingEnabled = $enabled && defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Check if logging is enabled
     *
     * @return bool Whether logging is enabled
     */
    public function isLoggingEnabled(): bool {
        return $this->loggingEnabled;
    }

    /**
     * Static method to check if logging is enabled globally
     * Useful for quick checks without instantiating the logger
     *
     * @return bool Whether logging is enabled
     */
    public static function isLoggingEnabledGlobally(): bool {
        // Only enable logging if WP_DEBUG is active
        return defined('WP_DEBUG') && WP_DEBUG;
    }
}