<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Analytics Report Model
 * 
 * Comprehensive analytics data structure for reporting and visualization
 */
class AnalyticsReport
{
    private string $period;
    private array $generationStats = [];
    private array $aiMetrics = [];
    private array $qualityTrends = [];
    private array $satisfactionMetrics = [];
    private array $usageStats = [];
    private array $performanceMetrics = [];
    private string $generatedAt;
    
    public function __construct()
    {
        $this->generatedAt = current_time('mysql');
    }
    
    // Setters
    public function setPeriod(string $period): void
    {
        $this->period = $period;
    }
    
    public function setGenerationStats(array $stats): void
    {
        $this->generationStats = $stats;
    }
    
    public function setAIMetrics(array $metrics): void
    {
        $this->aiMetrics = $metrics;
    }
    
    public function setQualityTrends(array $trends): void
    {
        $this->qualityTrends = $trends;
    }
    
    public function setSatisfactionMetrics(array $metrics): void
    {
        $this->satisfactionMetrics = $metrics;
    }
    
    public function setUsageStats(array $stats): void
    {
        $this->usageStats = $stats;
    }
    
    public function setPerformanceMetrics(array $metrics): void
    {
        $this->performanceMetrics = $metrics;
    }
    
    // Getters
    public function getPeriod(): string
    {
        return $this->period;
    }
    
    public function getGenerationStats(): array
    {
        return $this->generationStats;
    }
    
    public function getAIMetrics(): array
    {
        return $this->aiMetrics;
    }
    
    public function getQualityTrends(): array
    {
        return $this->qualityTrends;
    }
    
    public function getSatisfactionMetrics(): array
    {
        return $this->satisfactionMetrics;
    }
    
    public function getUsageStats(): array
    {
        return $this->usageStats;
    }
    
    public function getPerformanceMetrics(): array
    {
        return $this->performanceMetrics;
    }
    
    /**
     * Convert report to JSON format
     */
    public function toJSON(): string
    {
        return json_encode([
            'report_metadata' => [
                'period' => $this->period,
                'generated_at' => $this->generatedAt,
                'report_version' => '1.0'
            ],
            'generation_stats' => $this->generationStats,
            'ai_metrics' => $this->aiMetrics,
            'quality_trends' => $this->qualityTrends,
            'satisfaction_metrics' => $this->satisfactionMetrics,
            'usage_stats' => $this->usageStats,
            'performance_metrics' => $this->performanceMetrics
        ], JSON_PRETTY_PRINT);
    }
    
    /**
     * Convert report to array format
     */
    public function toArray(): array
    {
        return [
            'report_metadata' => [
                'period' => $this->period,
                'generated_at' => $this->generatedAt,
                'report_version' => '1.0'
            ],
            'generation_stats' => $this->generationStats,
            'ai_metrics' => $this->aiMetrics,
            'quality_trends' => $this->qualityTrends,
            'satisfaction_metrics' => $this->satisfactionMetrics,
            'usage_stats' => $this->usageStats,
            'performance_metrics' => $this->performanceMetrics
        ];
    }
    
    /**
     * Generate executive summary
     */
    public function getExecutiveSummary(): array
    {
        return [
            'key_metrics' => [
                'total_courses_generated' => $this->generationStats['completed_sessions'] ?? 0,
                'success_rate' => round(($this->generationStats['success_rate'] ?? 0) * 100, 1) . '%',
                'average_quality_score' => round($this->generationStats['average_quality'] ?? 0, 2),
                'user_satisfaction' => round($this->satisfactionMetrics['average_rating'] ?? 0, 1) . '/5',
                'system_uptime' => round($this->performanceMetrics['uptime_percentage'] ?? 0, 1) . '%'
            ],
            'highlights' => $this->generateHighlights(),
            'recommendations' => $this->generateRecommendations()
        ];
    }
    
    /**
     * Generate report highlights
     */
    private function generateHighlights(): array
    {
        $highlights = [];
        
        // Success rate highlight
        $successRate = $this->generationStats['success_rate'] ?? 0;
        if ($successRate > 0.8) {
            $highlights[] = "Excellent success rate of " . round($successRate * 100, 1) . "% for course generation";
        }
        
        // Quality improvement
        $qualityTrend = $this->qualityTrends['overall_improvement'] ?? 'stable';
        if ($qualityTrend === 'improving') {
            $highlights[] = "Quality scores are trending upward over the analysis period";
        }
        
        // User satisfaction
        $satisfaction = $this->satisfactionMetrics['average_rating'] ?? 0;
        if ($satisfaction > 4.0) {
            $highlights[] = "High user satisfaction with an average rating of " . round($satisfaction, 1) . "/5";
        }
        
        return $highlights;
    }
    
    /**
     * Generate actionable recommendations
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];
        
        // Performance recommendations
        $avgLoadTime = $this->performanceMetrics['average_load_time'] ?? 0;
        if ($avgLoadTime > 3.0) {
            $recommendations[] = "Consider optimizing system performance - average load time is {$avgLoadTime}s";
        }
        
        // Cost optimization
        $avgCost = $this->aiMetrics['total_cost'] ?? 0;
        if ($avgCost > 100) {
            $recommendations[] = "Review AI usage patterns to optimize costs - current spend is $" . round($avgCost, 2);
        }
        
        // Quality improvements
        $qualityTrend = $this->qualityTrends['overall_improvement'] ?? 'stable';
        if ($qualityTrend === 'declining') {
            $recommendations[] = "Investigate declining quality trends and implement improvement measures";
        }
        
        return $recommendations;
    }
}