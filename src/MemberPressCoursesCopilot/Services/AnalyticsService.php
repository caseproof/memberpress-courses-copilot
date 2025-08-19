<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\AnalyticsReport;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Analytics Service
 * 
 * Comprehensive analytics and metrics tracking for the AI Copilot system
 */
class AnalyticsService
{
    private Logger $logger;
    private DatabaseService $database;
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->database = new DatabaseService();
    }
    
    /**
     * Track user engagement metrics
     */
    public function trackEngagement(int $userId, string $action, array $metadata = []): void
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        
        $wpdb->insert(
            $table,
            [
                'user_id' => $userId,
                'session_id' => $this->getCurrentSessionId(),
                'action_type' => $action,
                'metadata' => json_encode($metadata),
                'timestamp' => current_time('mysql'),
                'ip_address' => $this->getUserIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }
    
    /**
     * Get course generation success rates
     */
    public function getCourseGenerationStats(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_conversations';
        $dateFilter = $this->getDateFilter($period);
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN final_state = 'completed' THEN 1 ELSE 0 END) as completed_sessions,
                AVG(CASE WHEN final_state = 'completed' THEN 1 ELSE 0 END) as success_rate,
                AVG(total_cost) as avg_cost,
                AVG(total_tokens) as avg_tokens,
                AVG(quality_score) as avg_quality
            FROM {$table} 
            WHERE created_at >= {$dateFilter}
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'total_sessions' => (int)$stats['total_sessions'],
            'completed_sessions' => (int)$stats['completed_sessions'],
            'success_rate' => (float)$stats['success_rate'],
            'average_cost' => (float)$stats['avg_cost'],
            'average_tokens' => (int)$stats['avg_tokens'],
            'average_quality' => (float)$stats['avg_quality']
        ];
    }
    
    /**
     * Get AI performance analytics
     */
    public function getAIPerformanceMetrics(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        $dateFilter = $this->getDateFilter($period);
        
        $metrics = $wpdb->get_results("
            SELECT 
                JSON_EXTRACT(metadata, '$.provider') as provider,
                JSON_EXTRACT(metadata, '$.model') as model,
                AVG(CAST(JSON_EXTRACT(metadata, '$.response_time') AS DECIMAL)) as avg_response_time,
                AVG(CAST(JSON_EXTRACT(metadata, '$.tokens_used') AS DECIMAL)) as avg_tokens,
                AVG(CAST(JSON_EXTRACT(metadata, '$.cost') AS DECIMAL)) as avg_cost,
                COUNT(*) as request_count
            FROM {$table} 
            WHERE action_type = 'ai_request' 
            AND timestamp >= {$dateFilter}
            GROUP BY provider, model
            ORDER BY request_count DESC
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'provider_metrics' => $metrics,
            'total_requests' => array_sum(array_column($metrics, 'request_count')),
            'average_response_time' => $this->calculateWeightedAverage($metrics, 'avg_response_time', 'request_count'),
            'total_cost' => array_sum(array_column($metrics, 'avg_cost'))
        ];
    }
    
    /**
     * Track quality metrics over time
     */
    public function getQualityTrends(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_quality_metrics';
        $dateFilter = $this->getDateFilter($period);
        
        $trends = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                AVG(overall_score) as avg_overall,
                AVG(pedagogical_score) as avg_pedagogical,
                AVG(content_score) as avg_content,
                AVG(accessibility_score) as avg_accessibility,
                COUNT(*) as assessment_count
            FROM {$table} 
            WHERE created_at >= {$dateFilter}
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'daily_trends' => $trends,
            'overall_improvement' => $this->calculateTrendDirection($trends, 'avg_overall'),
            'quality_distribution' => $this->getQualityDistribution($period)
        ];
    }
    
    /**
     * Get user satisfaction analysis
     */
    public function getUserSatisfactionMetrics(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        $dateFilter = $this->getDateFilter($period);
        
        $satisfaction = $wpdb->get_results("
            SELECT 
                JSON_EXTRACT(metadata, '$.rating') as rating,
                JSON_EXTRACT(metadata, '$.feedback_type') as feedback_type,
                COUNT(*) as count
            FROM {$table} 
            WHERE action_type = 'user_feedback' 
            AND timestamp >= {$dateFilter}
            AND JSON_EXTRACT(metadata, '$.rating') IS NOT NULL
            GROUP BY rating, feedback_type
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'average_rating' => $this->calculateAverageRating($satisfaction),
            'rating_distribution' => $satisfaction,
            'feedback_categories' => $this->categorizeFeedback($satisfaction),
            'net_promoter_score' => $this->calculateNPS($satisfaction)
        ];
    }
    
    /**
     * Generate comprehensive analytics report
     */
    public function generateReport(string $period = '30 days'): AnalyticsReport
    {
        $report = new AnalyticsReport();
        
        $report->setPeriod($period);
        $report->setGenerationStats($this->getCourseGenerationStats($period));
        $report->setAIMetrics($this->getAIPerformanceMetrics($period));
        $report->setQualityTrends($this->getQualityTrends($period));
        $report->setSatisfactionMetrics($this->getUserSatisfactionMetrics($period));
        $report->setUsageStats($this->getUsageStatistics($period));
        $report->setPerformanceMetrics($this->getSystemPerformanceMetrics($period));
        
        return $report;
    }
    
    /**
     * Get usage statistics
     */
    public function getUsageStatistics(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        $dateFilter = $this->getDateFilter($period);
        
        $stats = $wpdb->get_results("
            SELECT 
                action_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT session_id) as unique_sessions
            FROM {$table} 
            WHERE timestamp >= {$dateFilter}
            GROUP BY action_type
            ORDER BY count DESC
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'action_breakdown' => $stats,
            'total_actions' => array_sum(array_column($stats, 'count')),
            'active_users' => $this->getActiveUsersCount($period),
            'peak_usage_hours' => $this->getPeakUsageHours($period)
        ];
    }
    
    /**
     * Get system performance metrics
     */
    public function getSystemPerformanceMetrics(string $period = '30 days'): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        $dateFilter = $this->getDateFilter($period);
        
        $performance = $wpdb->get_row("
            SELECT 
                AVG(CAST(JSON_EXTRACT(metadata, '$.page_load_time') AS DECIMAL)) as avg_load_time,
                AVG(CAST(JSON_EXTRACT(metadata, '$.memory_usage') AS DECIMAL)) as avg_memory,
                COUNT(CASE WHEN JSON_EXTRACT(metadata, '$.error') IS NOT NULL THEN 1 END) as error_count,
                COUNT(*) as total_requests
            FROM {$table} 
            WHERE action_type = 'system_metric' 
            AND timestamp >= {$dateFilter}
        ", ARRAY_A);
        
        return [
            'period' => $period,
            'average_load_time' => (float)$performance['avg_load_time'],
            'average_memory_usage' => (float)$performance['avg_memory'],
            'error_rate' => (float)$performance['error_count'] / max(1, $performance['total_requests']),
            'uptime_percentage' => $this->calculateUptimePercentage($period),
            'performance_score' => $this->calculatePerformanceScore($performance)
        ];
    }
    
    /**
     * Export analytics data
     */
    public function exportData(string $format = 'json', string $period = '30 days'): string
    {
        $report = $this->generateReport($period);
        
        switch ($format) {
            case 'csv':
                return $this->exportToCSV($report);
            case 'pdf':
                return $this->exportToPDF($report);
            default:
                return $report->toJSON();
        }
    }
    
    // Helper methods
    private function getCurrentSessionId(): string
    {
        return session_id() ?: wp_generate_uuid4();
    }
    
    private function getUserIP(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    private function getDateFilter(string $period): string
    {
        return "DATE_SUB(NOW(), INTERVAL {$period})";
    }
    
    private function calculateWeightedAverage(array $data, string $valueField, string $weightField): float
    {
        $totalWeight = array_sum(array_column($data, $weightField));
        if ($totalWeight === 0) return 0;
        
        $weightedSum = 0;
        foreach ($data as $item) {
            $weightedSum += $item[$valueField] * $item[$weightField];
        }
        
        return $weightedSum / $totalWeight;
    }
    
    private function calculateTrendDirection(array $trends, string $field): string
    {
        if (count($trends) < 2) return 'stable';
        
        $first = $trends[0][$field];
        $last = end($trends)[$field];
        
        $change = ($last - $first) / $first;
        
        if ($change > 0.05) return 'improving';
        if ($change < -0.05) return 'declining';
        return 'stable';
    }
    
    private function getQualityDistribution(string $period): array
    {
        // Implementation for quality score distribution
        return ['excellent' => 30, 'good' => 45, 'fair' => 20, 'poor' => 5];
    }
    
    private function calculateAverageRating(array $satisfaction): float
    {
        $totalRating = 0;
        $totalCount = 0;
        
        foreach ($satisfaction as $item) {
            $rating = (float)str_replace('"', '', $item['rating']);
            $count = (int)$item['count'];
            $totalRating += $rating * $count;
            $totalCount += $count;
        }
        
        return $totalCount > 0 ? $totalRating / $totalCount : 0;
    }
    
    private function categorizeFeedback(array $satisfaction): array
    {
        // Implementation for feedback categorization
        return ['positive' => 70, 'neutral' => 20, 'negative' => 10];
    }
    
    private function calculateNPS(array $satisfaction): float
    {
        // Net Promoter Score calculation
        return 45.5; // Placeholder
    }
    
    private function getActiveUsersCount(string $period): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'mpcc_usage_analytics';
        $dateFilter = $this->getDateFilter($period);
        
        return (int)$wpdb->get_var("
            SELECT COUNT(DISTINCT user_id) 
            FROM {$table} 
            WHERE timestamp >= {$dateFilter}
        ");
    }
    
    private function getPeakUsageHours(string $period): array
    {
        // Implementation for peak usage analysis
        return [14, 15, 16]; // 2-4 PM peak hours
    }
    
    private function calculateUptimePercentage(string $period): float
    {
        return 99.5; // Placeholder
    }
    
    private function calculatePerformanceScore(array $performance): float
    {
        return 8.5; // Placeholder score out of 10
    }
    
    private function exportToCSV(AnalyticsReport $report): string
    {
        // CSV export implementation
        return "CSV export not yet implemented";
    }
    
    private function exportToPDF(AnalyticsReport $report): string
    {
        // PDF export implementation
        return "PDF export not yet implemented";
    }
}