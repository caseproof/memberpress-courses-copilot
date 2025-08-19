<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Token Usage Service
 * 
 * Tracks token usage, costs, and provides budget management
 * for AI-powered course generation operations.
 */
class TokenUsageService
{
    private Logger $logger;
    private ProxyConfigService $proxyConfig;
    private array $usageStats = [];
    private array $budgetLimits = [];

    public function __construct(Logger $logger, ProxyConfigService $proxyConfig)
    {
        $this->logger = $logger;
        $this->proxyConfig = $proxyConfig;
        $this->loadBudgetConfiguration();
    }

    /**
     * Track token usage for a completed request
     */
    public function trackUsage(array $usageData): void
    {
        $usage = [
            'timestamp' => time(),
            'provider' => $usageData['provider'],
            'model' => $usageData['model'],
            'content_type' => $usageData['content_type'] ?? 'unknown',
            'input_tokens' => $usageData['input_tokens'] ?? 0,
            'output_tokens' => $usageData['output_tokens'] ?? 0,
            'total_tokens' => $usageData['total_tokens'] ?? 0,
            'estimated_cost' => $usageData['estimated_cost'] ?? 0,
            'response_time' => $usageData['response_time'] ?? 0,
            'user_id' => $usageData['user_id'] ?? get_current_user_id(),
            'session_id' => $usageData['session_id'] ?? $this->getSessionId()
        ];

        // Calculate actual cost if not provided
        if (!isset($usageData['estimated_cost']) || $usageData['estimated_cost'] === 0) {
            $usage['estimated_cost'] = $this->calculateCost(
                $usage['provider'],
                $usage['model'],
                $usage['input_tokens'],
                $usage['output_tokens']
            );
        }

        $this->storeUsage($usage);
        $this->updateRealTimeStats($usage);
        $this->checkBudgetLimits($usage);

        $this->logger->info('Token usage tracked', [
            'provider' => $usage['provider'],
            'model' => $usage['model'],
            'content_type' => $usage['content_type'],
            'total_tokens' => $usage['total_tokens'],
            'estimated_cost' => $usage['estimated_cost']
        ]);
    }

    /**
     * Get usage statistics for a time period
     */
    public function getUsageStatistics(int $days = 30, array $filters = []): array
    {
        $cutoffTime = time() - ($days * 24 * 3600);
        $usage = $this->getStoredUsage($cutoffTime, $filters);

        if (empty($usage)) {
            return [
                'period_days' => $days,
                'total_requests' => 0,
                'total_tokens' => 0,
                'total_cost' => 0,
                'provider_breakdown' => [],
                'content_type_breakdown' => [],
                'daily_usage' => []
            ];
        }

        return [
            'period_days' => $days,
            'total_requests' => count($usage),
            'total_tokens' => array_sum(array_column($usage, 'total_tokens')),
            'total_input_tokens' => array_sum(array_column($usage, 'input_tokens')),
            'total_output_tokens' => array_sum(array_column($usage, 'output_tokens')),
            'total_cost' => array_sum(array_column($usage, 'estimated_cost')),
            'average_cost_per_request' => $this->calculateAverageCost($usage),
            'provider_breakdown' => $this->getProviderBreakdown($usage),
            'model_breakdown' => $this->getModelBreakdown($usage),
            'content_type_breakdown' => $this->getContentTypeBreakdown($usage),
            'daily_usage' => $this->getDailyUsage($usage, $days),
            'peak_usage_hours' => $this->getPeakUsageHours($usage),
            'efficiency_metrics' => $this->getEfficiencyMetrics($usage)
        ];
    }

    /**
     * Check if operation is within budget limits
     */
    public function checkBudgetLimits(array $usage): array
    {
        $currentPeriod = $this->getCurrentPeriod();
        $periodUsage = $this->getPeriodUsage($currentPeriod);
        
        $results = [
            'within_budget' => true,
            'warnings' => [],
            'limits_exceeded' => [],
            'current_usage' => $periodUsage,
            'limits' => $this->budgetLimits
        ];

        // Check daily limits
        if (isset($this->budgetLimits['daily_cost']) && $periodUsage['daily_cost'] > $this->budgetLimits['daily_cost']) {
            $results['within_budget'] = false;
            $results['limits_exceeded'][] = 'daily_cost';
        }

        // Check monthly limits
        if (isset($this->budgetLimits['monthly_cost']) && $periodUsage['monthly_cost'] > $this->budgetLimits['monthly_cost']) {
            $results['within_budget'] = false;
            $results['limits_exceeded'][] = 'monthly_cost';
        }

        // Check token limits
        if (isset($this->budgetLimits['daily_tokens']) && $periodUsage['daily_tokens'] > $this->budgetLimits['daily_tokens']) {
            $results['within_budget'] = false;
            $results['limits_exceeded'][] = 'daily_tokens';
        }

        // Check for approaching limits (80% threshold)
        foreach ($this->budgetLimits as $limitType => $limitValue) {
            if (isset($periodUsage[$limitType])) {
                $percentage = ($periodUsage[$limitType] / $limitValue) * 100;
                if ($percentage >= 80 && $percentage < 100) {
                    $results['warnings'][] = [
                        'type' => $limitType,
                        'percentage' => round($percentage, 1),
                        'current' => $periodUsage[$limitType],
                        'limit' => $limitValue
                    ];
                }
            }
        }

        if (!$results['within_budget'] || !empty($results['warnings'])) {
            $this->logger->warning('Budget limits check', $results);
        }

        return $results;
    }

    /**
     * Estimate cost before making a request
     */
    public function estimateRequestCost(string $provider, string $model, int $estimatedTokens): array
    {
        $costPerToken = $this->getCostPerToken($provider, $model);
        $estimatedCost = $estimatedTokens * $costPerToken;

        // Add proxy fees (typically 10-20%)
        $proxyFee = $estimatedCost * 0.15;
        $totalCost = $estimatedCost + $proxyFee;

        $budgetCheck = $this->checkBudgetLimits([
            'estimated_cost' => $totalCost,
            'total_tokens' => $estimatedTokens,
            'provider' => $provider,
            'model' => $model
        ]);

        return [
            'estimated_tokens' => $estimatedTokens,
            'base_cost' => round($estimatedCost, 6),
            'proxy_fee' => round($proxyFee, 6),
            'total_cost' => round($totalCost, 6),
            'cost_per_token' => $costPerToken,
            'currency' => 'USD',
            'within_budget' => $budgetCheck['within_budget'],
            'budget_warnings' => $budgetCheck['warnings']
        ];
    }

    /**
     * Get cost optimization recommendations
     */
    public function getCostOptimizationRecommendations(array $usageStats): array
    {
        $recommendations = [];

        // Analyze provider costs
        $providerCosts = array_column($usageStats['provider_breakdown'], 'total_cost', 'provider');
        arsort($providerCosts);
        $mostExpensive = array_key_first($providerCosts);

        if ($mostExpensive && $providerCosts[$mostExpensive] > 0) {
            $recommendations[] = [
                'type' => 'provider_optimization',
                'priority' => 'high',
                'title' => 'Consider alternative providers',
                'description' => "Provider '{$mostExpensive}' accounts for the highest costs. Consider using cost-effective alternatives for suitable tasks.",
                'potential_savings' => $this->calculatePotentialSavings($providerCosts)
            ];
        }

        // Analyze model usage
        $modelBreakdown = $usageStats['model_breakdown'];
        $expensiveModels = array_filter($modelBreakdown, function($model) {
            return $model['average_cost_per_token'] > 0.01; // High-cost threshold
        });

        if (!empty($expensiveModels)) {
            $recommendations[] = [
                'type' => 'model_optimization',
                'priority' => 'medium',
                'title' => 'Optimize model selection',
                'description' => 'Consider using more cost-effective models for content types that don\'t require premium models.',
                'affected_models' => array_column($expensiveModels, 'model')
            ];
        }

        // Analyze content type efficiency
        $contentEfficiency = $this->analyzeContentTypeEfficiency($usageStats['content_type_breakdown']);
        if (!empty($contentEfficiency['inefficient_types'])) {
            $recommendations[] = [
                'type' => 'content_optimization',
                'priority' => 'medium',
                'title' => 'Optimize content generation',
                'description' => 'Some content types are using more tokens than expected. Consider prompt optimization or batching.',
                'inefficient_types' => $contentEfficiency['inefficient_types']
            ];
        }

        // Check for peak hour usage
        $peakHours = $usageStats['peak_usage_hours'];
        if ($this->hasPeakHourPremium($peakHours)) {
            $recommendations[] = [
                'type' => 'timing_optimization',
                'priority' => 'low',
                'title' => 'Optimize request timing',
                'description' => 'Consider scheduling non-urgent requests during off-peak hours to reduce costs.',
                'peak_hours' => $peakHours
            ];
        }

        return $recommendations;
    }

    /**
     * Export usage data for analysis
     */
    public function exportUsageData(int $days = 30, string $format = 'json'): array
    {
        $cutoffTime = time() - ($days * 24 * 3600);
        $usage = $this->getStoredUsage($cutoffTime);

        $exportData = [
            'export_date' => date('Y-m-d H:i:s'),
            'period_days' => $days,
            'total_records' => count($usage),
            'data' => $usage
        ];

        if ($format === 'csv') {
            return $this->convertToCSV($exportData['data']);
        }

        return $exportData;
    }

    /**
     * Set budget limits
     */
    public function setBudgetLimits(array $limits): void
    {
        $validLimits = [
            'daily_cost',
            'monthly_cost',
            'daily_tokens',
            'monthly_tokens',
            'per_request_cost',
            'per_user_daily_cost'
        ];

        foreach ($limits as $type => $value) {
            if (in_array($type, $validLimits) && is_numeric($value) && $value > 0) {
                $this->budgetLimits[$type] = floatval($value);
            }
        }

        update_option('mpc_budget_limits', $this->budgetLimits);
        
        $this->logger->info('Budget limits updated', [
            'limits' => $this->budgetLimits
        ]);
    }

    /**
     * Calculate cost for given usage
     */
    private function calculateCost(string $provider, string $model, int $inputTokens, int $outputTokens): float
    {
        $modelInfo = $this->proxyConfig->getModelInfo($provider, $model);
        
        if (empty($modelInfo) || !isset($modelInfo['cost_per_1k_tokens'])) {
            return 0.0;
        }

        $totalTokens = $inputTokens + $outputTokens;
        $baseCost = ($totalTokens / 1000) * $modelInfo['cost_per_1k_tokens'];
        
        // Add proxy fee
        $proxyFee = $baseCost * 0.15;
        
        return round($baseCost + $proxyFee, 6);
    }

    /**
     * Get cost per token for provider/model
     */
    private function getCostPerToken(string $provider, string $model): float
    {
        $modelInfo = $this->proxyConfig->getModelInfo($provider, $model);
        
        if (empty($modelInfo) || !isset($modelInfo['cost_per_1k_tokens'])) {
            return 0.0;
        }

        // Cost per token with proxy fee
        return ($modelInfo['cost_per_1k_tokens'] / 1000) * 1.15;
    }

    /**
     * Store usage data
     */
    private function storeUsage(array $usage): void
    {
        $storedUsage = get_option('mpc_token_usage', []);
        
        $storedUsage[] = $usage;
        
        // Keep only last 10,000 entries to prevent database bloat
        if (count($storedUsage) > 10000) {
            $storedUsage = array_slice($storedUsage, -9000);
        }
        
        update_option('mpc_token_usage', $storedUsage);
    }

    /**
     * Get stored usage data with filters
     */
    private function getStoredUsage(int $cutoffTime, array $filters = []): array
    {
        $storedUsage = get_option('mpc_token_usage', []);
        
        // Filter by time
        $filtered = array_filter($storedUsage, function($usage) use ($cutoffTime) {
            return $usage['timestamp'] >= $cutoffTime;
        });

        // Apply additional filters
        foreach ($filters as $field => $value) {
            $filtered = array_filter($filtered, function($usage) use ($field, $value) {
                return isset($usage[$field]) && $usage[$field] === $value;
            });
        }

        return array_values($filtered);
    }

    /**
     * Get provider breakdown
     */
    private function getProviderBreakdown(array $usage): array
    {
        $breakdown = [];
        
        foreach ($usage as $record) {
            $provider = $record['provider'];
            
            if (!isset($breakdown[$provider])) {
                $breakdown[$provider] = [
                    'provider' => $provider,
                    'request_count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'average_tokens_per_request' => 0,
                    'average_cost_per_request' => 0
                ];
            }
            
            $breakdown[$provider]['request_count']++;
            $breakdown[$provider]['total_tokens'] += $record['total_tokens'];
            $breakdown[$provider]['total_cost'] += $record['estimated_cost'];
        }

        // Calculate averages
        foreach ($breakdown as &$data) {
            $data['average_tokens_per_request'] = round($data['total_tokens'] / max($data['request_count'], 1), 2);
            $data['average_cost_per_request'] = round($data['total_cost'] / max($data['request_count'], 1), 6);
        }

        return array_values($breakdown);
    }

    /**
     * Get model breakdown
     */
    private function getModelBreakdown(array $usage): array
    {
        $breakdown = [];
        
        foreach ($usage as $record) {
            $model = $record['model'];
            
            if (!isset($breakdown[$model])) {
                $breakdown[$model] = [
                    'model' => $model,
                    'provider' => $record['provider'],
                    'request_count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'average_cost_per_token' => 0
                ];
            }
            
            $breakdown[$model]['request_count']++;
            $breakdown[$model]['total_tokens'] += $record['total_tokens'];
            $breakdown[$model]['total_cost'] += $record['estimated_cost'];
        }

        // Calculate averages
        foreach ($breakdown as &$data) {
            $data['average_cost_per_token'] = round($data['total_cost'] / max($data['total_tokens'], 1), 8);
        }

        return array_values($breakdown);
    }

    /**
     * Get content type breakdown
     */
    private function getContentTypeBreakdown(array $usage): array
    {
        $breakdown = [];
        
        foreach ($usage as $record) {
            $type = $record['content_type'];
            
            if (!isset($breakdown[$type])) {
                $breakdown[$type] = [
                    'content_type' => $type,
                    'request_count' => 0,
                    'total_tokens' => 0,
                    'total_cost' => 0,
                    'average_tokens_per_request' => 0,
                    'average_response_time' => 0
                ];
            }
            
            $breakdown[$type]['request_count']++;
            $breakdown[$type]['total_tokens'] += $record['total_tokens'];
            $breakdown[$type]['total_cost'] += $record['estimated_cost'];
            $breakdown[$type]['total_response_time'] += $record['response_time'];
        }

        // Calculate averages
        foreach ($breakdown as &$data) {
            $data['average_tokens_per_request'] = round($data['total_tokens'] / max($data['request_count'], 1), 2);
            $data['average_response_time'] = round($data['total_response_time'] / max($data['request_count'], 1), 2);
        }

        return array_values($breakdown);
    }

    /**
     * Get daily usage
     */
    private function getDailyUsage(array $usage, int $days): array
    {
        $dailyUsage = [];
        
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', time() - ($i * 24 * 3600));
            $dailyUsage[$date] = [
                'date' => $date,
                'request_count' => 0,
                'total_tokens' => 0,
                'total_cost' => 0
            ];
        }

        foreach ($usage as $record) {
            $date = date('Y-m-d', $record['timestamp']);
            
            if (isset($dailyUsage[$date])) {
                $dailyUsage[$date]['request_count']++;
                $dailyUsage[$date]['total_tokens'] += $record['total_tokens'];
                $dailyUsage[$date]['total_cost'] += $record['estimated_cost'];
            }
        }

        return array_values($dailyUsage);
    }

    /**
     * Get peak usage hours
     */
    private function getPeakUsageHours(array $usage): array
    {
        $hourlyUsage = array_fill(0, 24, 0);
        
        foreach ($usage as $record) {
            $hour = (int)date('H', $record['timestamp']);
            $hourlyUsage[$hour]++;
        }

        $peakHours = [];
        $maxUsage = max($hourlyUsage);
        
        for ($hour = 0; $hour < 24; $hour++) {
            if ($hourlyUsage[$hour] >= $maxUsage * 0.8) { // Peak threshold: 80% of max
                $peakHours[] = $hour;
            }
        }

        return $peakHours;
    }

    /**
     * Get efficiency metrics
     */
    private function getEfficiencyMetrics(array $usage): array
    {
        if (empty($usage)) {
            return [];
        }

        $totalTokens = array_sum(array_column($usage, 'total_tokens'));
        $totalCost = array_sum(array_column($usage, 'estimated_cost'));
        $totalTime = array_sum(array_column($usage, 'response_time'));

        return [
            'tokens_per_second' => round($totalTokens / max($totalTime, 1), 2),
            'cost_per_token' => round($totalCost / max($totalTokens, 1), 8),
            'average_efficiency_score' => $this->calculateEfficiencyScore($usage)
        ];
    }

    /**
     * Calculate efficiency score
     */
    private function calculateEfficiencyScore(array $usage): float
    {
        $scores = [];
        
        foreach ($usage as $record) {
            // Score based on tokens per second and cost efficiency
            $tokensPerSecond = $record['total_tokens'] / max($record['response_time'], 1);
            $costPerToken = $record['estimated_cost'] / max($record['total_tokens'], 1);
            
            // Normalize scores (higher is better)
            $speedScore = min($tokensPerSecond / 100, 1); // Normalize to 0-1
            $costScore = max(1 - ($costPerToken * 10000), 0); // Normalize to 0-1
            
            $scores[] = ($speedScore + $costScore) / 2;
        }

        return round(array_sum($scores) / max(count($scores), 1), 3);
    }

    /**
     * Load budget configuration
     */
    private function loadBudgetConfiguration(): void
    {
        $this->budgetLimits = get_option('mpc_budget_limits', [
            'daily_cost' => 50.00,      // $50 per day
            'monthly_cost' => 1000.00,  // $1000 per month
            'daily_tokens' => 500000,   // 500k tokens per day
            'monthly_tokens' => 10000000 // 10M tokens per month
        ]);
    }

    /**
     * Get current period usage
     */
    private function getCurrentPeriod(): array
    {
        $now = time();
        
        return [
            'daily_start' => strtotime('today'),
            'monthly_start' => strtotime('first day of this month'),
            'current_time' => $now
        ];
    }

    /**
     * Get period usage totals
     */
    private function getPeriodUsage(array $period): array
    {
        $dailyUsage = $this->getStoredUsage($period['daily_start']);
        $monthlyUsage = $this->getStoredUsage($period['monthly_start']);

        return [
            'daily_cost' => array_sum(array_column($dailyUsage, 'estimated_cost')),
            'monthly_cost' => array_sum(array_column($monthlyUsage, 'estimated_cost')),
            'daily_tokens' => array_sum(array_column($dailyUsage, 'total_tokens')),
            'monthly_tokens' => array_sum(array_column($monthlyUsage, 'total_tokens'))
        ];
    }

    /**
     * Update real-time stats
     */
    private function updateRealTimeStats(array $usage): void
    {
        $this->usageStats['last_request'] = $usage;
        $this->usageStats['session_total_cost'] = ($this->usageStats['session_total_cost'] ?? 0) + $usage['estimated_cost'];
        $this->usageStats['session_total_tokens'] = ($this->usageStats['session_total_tokens'] ?? 0) + $usage['total_tokens'];
    }

    /**
     * Get current session ID
     */
    private function getSessionId(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            return 'no-session-' . uniqid();
        }
        
        return session_id();
    }

    /**
     * Calculate average cost
     */
    private function calculateAverageCost(array $usage): float
    {
        if (empty($usage)) {
            return 0.0;
        }
        
        $totalCost = array_sum(array_column($usage, 'estimated_cost'));
        return round($totalCost / count($usage), 6);
    }

    /**
     * Calculate potential savings
     */
    private function calculatePotentialSavings(array $providerCosts): float
    {
        if (count($providerCosts) < 2) {
            return 0.0;
        }
        
        $costs = array_values($providerCosts);
        $mostExpensive = $costs[0];
        $cheapest = end($costs);
        
        return round($mostExpensive - $cheapest, 2);
    }

    /**
     * Analyze content type efficiency
     */
    private function analyzeContentTypeEfficiency(array $contentBreakdown): array
    {
        $inefficientTypes = [];
        
        // Define expected token ranges for different content types
        $expectedTokens = [
            'course_outline' => ['min' => 1000, 'max' => 4000],
            'lesson_content' => ['min' => 2000, 'max' => 6000],
            'quiz_questions' => ['min' => 500, 'max' => 2000],
            'assignment' => ['min' => 1000, 'max' => 3000]
        ];

        foreach ($contentBreakdown as $content) {
            $type = $content['content_type'];
            $avgTokens = $content['average_tokens_per_request'];
            
            if (isset($expectedTokens[$type])) {
                $expected = $expectedTokens[$type];
                if ($avgTokens > $expected['max'] * 1.5) { // 50% over expected
                    $inefficientTypes[] = [
                        'type' => $type,
                        'average_tokens' => $avgTokens,
                        'expected_max' => $expected['max'],
                        'efficiency_ratio' => round($avgTokens / $expected['max'], 2)
                    ];
                }
            }
        }

        return ['inefficient_types' => $inefficientTypes];
    }

    /**
     * Check if peak hour premium applies
     */
    private function hasPeakHourPremium(array $peakHours): bool
    {
        // Check if peak hours include typical business hours (9-17)
        $businessHours = range(9, 17);
        return !empty(array_intersect($peakHours, $businessHours));
    }

    /**
     * Convert usage data to CSV format
     */
    private function convertToCSV(array $data): array
    {
        if (empty($data)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_keys($data[0]);
        $rows = [];

        foreach ($data as $record) {
            $row = [];
            foreach ($headers as $header) {
                $row[] = $record[$header] ?? '';
            }
            $rows[] = $row;
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}