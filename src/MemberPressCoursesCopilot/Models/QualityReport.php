<?php


namespace MemberPressCoursesCopilot\Models;

/**
 * Quality Report Model
 *
 * Represents the detailed quality assessment results for a generated course.
 * Includes scoring breakdown by category, improvement recommendations,
 * historical tracking capabilities, and comparative analysis.
 *
 * @package MemberPressCoursesCopilot\Models
 * @since   1.0.0
 */
class QualityReport extends BaseModel
{
    /**
     * Quality levels for score interpretation
     */
    private const QUALITY_LEVELS = [
        'excellent' => [
            'min'   => 90,
            'max'   => 100,
            'color' => '#28a745',
        ],
        'good'      => [
            'min'   => 80,
            'max'   => 89,
            'color' => '#17a2b8',
        ],
        'fair'      => [
            'min'   => 70,
            'max'   => 79,
            'color' => '#ffc107',
        ],
        'poor'      => [
            'min'   => 50,
            'max'   => 69,
            'color' => '#fd7e14',
        ],
        'critical'  => [
            'min'   => 0,
            'max'   => 49,
            'color' => '#dc3545',
        ],
    ];

    /**
     * Recommendation priority levels
     */
    private const PRIORITY_LEVELS = [
        'critical' => 4,
        'high'     => 3,
        'medium'   => 2,
        'low'      => 1,
    ];

    /**
     * Validate the quality report data
     */
    public function validate(): bool
    {
        $required = ['course_title', 'assessment_date', 'overall_score', 'dimension_scores'];

        foreach ($required as $field) {
            if (!$this->has($field)) {
                return false;
            }
        }

        // Validate score ranges
        $overallScore = $this->get('overall_score');
        if (!is_numeric($overallScore) || $overallScore < 0 || $overallScore > 100) {
            return false;
        }

        // Validate dimension scores
        $dimensionScores    = $this->get('dimension_scores', []);
        $requiredDimensions = ['pedagogical', 'content', 'structural', 'accessibility', 'technical'];

        foreach ($requiredDimensions as $dimension) {
            if (
                !isset($dimensionScores[$dimension]) ||
                !is_array($dimensionScores[$dimension]) ||
                !isset($dimensionScores[$dimension]['score']) ||
                !is_numeric($dimensionScores[$dimension]['score']) ||
                $dimensionScores[$dimension]['score'] < 0 ||
                $dimensionScores[$dimension]['score'] > 100
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save the quality report to WordPress database
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        $reportData = $this->toArray();
        $reportId   = $this->get('id');

        if ($reportId) {
            // Update existing report
            $updated = update_option("mpcc_quality_report_{$reportId}", $reportData);
            if ($updated) {
                $this->syncOriginal();
            }
            return $updated;
        } else {
            // Create new report
            $reportId = 'qr_' . wp_generate_uuid4() . '_' . time();
            $this->set('id', $reportId);
            $this->set('created_at', current_time('mysql'));

            $reportData = $this->toArray();
            $saved      = add_option("mpcc_quality_report_{$reportId}", $reportData, '', 'no');

            if ($saved) {
                $this->syncOriginal();
                $this->updateReportIndex($reportId);
            }

            return $saved;
        }
    }

    /**
     * Delete the quality report
     */
    public function delete(): bool
    {
        $reportId = $this->get('id');
        if (!$reportId) {
            return false;
        }

        $deleted = delete_option("mpcc_quality_report_{$reportId}");
        if ($deleted) {
            $this->removeFromReportIndex($reportId);
        }

        return $deleted;
    }

    /**
     * Load quality report by ID
     */
    public static function find(string $reportId): ?self
    {
        $reportData = get_option("mpcc_quality_report_{$reportId}");

        if ($reportData === false) {
            return null;
        }

        return new self($reportData);
    }

    /**
     * Get all quality reports for a course
     */
    public static function findByCourse(string $courseTitle): array
    {
        $allReports    = self::getAll();
        $courseReports = [];

        foreach ($allReports as $report) {
            if ($report->get('course_title') === $courseTitle) {
                $courseReports[] = $report;
            }
        }

        // Sort by assessment date (newest first)
        usort($courseReports, function ($a, $b) {
            return strtotime($b->get('assessment_date')) - strtotime($a->get('assessment_date'));
        });

        return $courseReports;
    }

    /**
     * Get all quality reports
     */
    public static function getAll(): array
    {
        $reportIndex = get_option('mpcc_quality_reports_index', []);
        $reports     = [];

        foreach ($reportIndex as $reportId) {
            $report = self::find($reportId);
            if ($report) {
                $reports[] = $report;
            }
        }

        return $reports;
    }

    /**
     * Get quality level based on score
     */
    public function getQualityLevel(int $score = null): array
    {
        $scoreToCheck = $score ?? $this->get('overall_score');

        foreach (self::QUALITY_LEVELS as $level => $config) {
            if ($scoreToCheck >= $config['min'] && $scoreToCheck <= $config['max']) {
                return array_merge(['level' => $level], $config);
            }
        }

        return [
            'level' => 'unknown',
            'min'   => 0,
            'max'   => 0,
            'color' => '#6c757d',
        ];
    }

    /**
     * Get overall quality level
     */
    public function getOverallQualityLevel(): array
    {
        return $this->getQualityLevel($this->get('overall_score'));
    }

    /**
     * Get quality level for specific dimension
     */
    public function getDimensionQualityLevel(string $dimension): array
    {
        $dimensionScores = $this->get('dimension_scores', []);
        $score           = $dimensionScores[$dimension]['score'] ?? 0;
        return $this->getQualityLevel($score);
    }

    /**
     * Get recommendations sorted by priority
     */
    public function getRecommendationsByPriority(): array
    {
        $recommendations = $this->get('recommendations', []);

        usort($recommendations, function ($a, $b) {
            $priorityA = self::PRIORITY_LEVELS[$a['priority']] ?? 0;
            $priorityB = self::PRIORITY_LEVELS[$b['priority']] ?? 0;
            return $priorityB - $priorityA; // Descending order (highest priority first)
        });

        return $recommendations;
    }

    /**
     * Get recommendations by category
     */
    public function getRecommendationsByCategory(): array
    {
        $recommendations = $this->get('recommendations', []);
        $categorized     = [];

        foreach ($recommendations as $recommendation) {
            $category = $recommendation['category'] ?? 'general';
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][] = $recommendation;
        }

        return $categorized;
    }

    /**
     * Get critical recommendations only
     */
    public function getCriticalRecommendations(): array
    {
        $recommendations = $this->get('recommendations', []);

        return array_filter($recommendations, function ($rec) {
            return $rec['priority'] === 'critical';
        });
    }

    /**
     * Get high priority recommendations
     */
    public function getHighPriorityRecommendations(): array
    {
        $recommendations = $this->get('recommendations', []);

        return array_filter($recommendations, function ($rec) {
            return in_array($rec['priority'], ['critical', 'high']);
        });
    }

    /**
     * Get improvement score (difference from perfect score)
     */
    public function getImprovementScore(): int
    {
        return 100 - $this->get('overall_score', 0);
    }

    /**
     * Get dimension that needs most improvement
     */
    public function getWorstPerformingDimension(): array
    {
        $dimensionScores = $this->get('dimension_scores', []);
        $lowestScore     = 100;
        $worstDimension  = null;

        foreach ($dimensionScores as $dimension => $scoreData) {
            $score = $scoreData['score'] ?? 100;
            if ($score < $lowestScore) {
                $lowestScore    = $score;
                $worstDimension = $dimension;
            }
        }

        return [
            'dimension'          => $worstDimension,
            'score'              => $lowestScore,
            'improvement_needed' => 100 - $lowestScore,
        ];
    }

    /**
     * Get dimension that performs best
     */
    public function getBestPerformingDimension(): array
    {
        $dimensionScores = $this->get('dimension_scores', []);
        $highestScore    = 0;
        $bestDimension   = null;

        foreach ($dimensionScores as $dimension => $scoreData) {
            $score = $scoreData['score'] ?? 0;
            if ($score > $highestScore) {
                $highestScore  = $score;
                $bestDimension = $dimension;
            }
        }

        return [
            'dimension' => $bestDimension,
            'score'     => $highestScore,
        ];
    }

    /**
     * Get score distribution summary
     */
    public function getScoreDistribution(): array
    {
        $dimensionScores = $this->get('dimension_scores', []);
        $distribution    = [];

        foreach ($dimensionScores as $dimension => $scoreData) {
            $score = $scoreData['score'] ?? 0;
            $level = $this->getQualityLevel($score);

            $distribution[$dimension] = [
                'score' => $score,
                'level' => $level['level'],
                'color' => $level['color'],
            ];
        }

        return $distribution;
    }

    /**
     * Generate improvement progress comparison
     */
    public function compareWithPrevious(QualityReport $previousReport): array
    {
        $currentScores  = $this->get('dimension_scores', []);
        $previousScores = $previousReport->get('dimension_scores', []);
        $comparison     = [];

        foreach ($currentScores as $dimension => $currentData) {
            $currentScore  = $currentData['score'] ?? 0;
            $previousScore = $previousScores[$dimension]['score'] ?? 0;
            $change        = $currentScore - $previousScore;

            $comparison[$dimension] = [
                'current_score'     => $currentScore,
                'previous_score'    => $previousScore,
                'change'            => $change,
                'improvement'       => $change > 0,
                'change_percentage' => $previousScore > 0 ? round(($change / $previousScore) * 100, 1) : 0,
            ];
        }

        // Overall comparison
        $currentOverall  = $this->get('overall_score', 0);
        $previousOverall = $previousReport->get('overall_score', 0);
        $overallChange   = $currentOverall - $previousOverall;

        $comparison['overall'] = [
            'current_score'     => $currentOverall,
            'previous_score'    => $previousOverall,
            'change'            => $overallChange,
            'improvement'       => $overallChange > 0,
            'change_percentage' => $previousOverall > 0 ? round(($overallChange / $previousOverall) * 100, 1) : 0,
        ];

        return $comparison;
    }

    /**
     * Get quality trend analysis
     */
    public function getTrendAnalysis(array $historicalReports): array
    {
        if (empty($historicalReports)) {
            return ['trend' => 'insufficient_data'];
        }

        // Sort reports by date
        usort($historicalReports, function ($a, $b) {
            return strtotime($a->get('assessment_date')) - strtotime($b->get('assessment_date'));
        });

        $scores = [];
        foreach ($historicalReports as $report) {
            $scores[] = $report->get('overall_score', 0);
        }

        // Add current score
        $scores[] = $this->get('overall_score', 0);

        $trend      = $this->calculateTrend($scores);
        $volatility = $this->calculateVolatility($scores);

        return [
            'trend'            => $trend,
            'volatility'       => $volatility,
            'score_history'    => $scores,
            'improvement_rate' => $this->calculateImprovementRate($scores),
            'best_score'       => max($scores),
            'worst_score'      => min($scores),
            'average_score'    => round(array_sum($scores) / count($scores), 1),
        ];
    }

    /**
     * Export report as array for API responses
     */
    public function toApiResponse(): array
    {
        $data = $this->toArray();

        // Add computed fields
        $data['overall_quality_level']    = $this->getOverallQualityLevel();
        $data['dimension_quality_levels'] = [];

        $dimensionScores = $this->get('dimension_scores', []);
        foreach ($dimensionScores as $dimension => $scoreData) {
            $data['dimension_quality_levels'][$dimension] = $this->getDimensionQualityLevel($dimension);
        }

        $data['recommendations_by_priority'] = $this->getRecommendationsByPriority();
        $data['critical_issues_count']       = count($this->getCriticalRecommendations());
        $data['high_priority_issues_count']  = count($this->getHighPriorityRecommendations());
        $data['improvement_score']           = $this->getImprovementScore();
        $data['worst_performing_dimension']  = $this->getWorstPerformingDimension();
        $data['best_performing_dimension']   = $this->getBestPerformingDimension();

        return $data;
    }

    /**
     * Generate summary statistics
     */
    public function getSummaryStats(): array
    {
        $dimensionScores = $this->get('dimension_scores', []);
        $recommendations = $this->get('recommendations', []);

        return [
            'overall_score'                 => $this->get('overall_score', 0),
            'passes_quality_gates'          => $this->get('passes_quality_gates', false),
            'total_dimensions'              => count($dimensionScores),
            'dimensions_above_80'           => count(array_filter($dimensionScores, fn($d) => ($d['score'] ?? 0) >= 80)),
            'dimensions_below_70'           => count(array_filter($dimensionScores, fn($d) => ($d['score'] ?? 0) < 70)),
            'total_recommendations'         => count($recommendations),
            'critical_recommendations'      => count(array_filter($recommendations, fn($r) => $r['priority'] === 'critical')),
            'high_priority_recommendations' => count(array_filter($recommendations, fn($r) => $r['priority'] === 'high')),
            'assessment_date'               => $this->get('assessment_date'),
            'course_title'                  => $this->get('course_title'),
        ];
    }

    /**
     * Update the report index for efficient lookups
     */
    private function updateReportIndex(string $reportId): void
    {
        $index = get_option('mpcc_quality_reports_index', []);
        if (!in_array($reportId, $index)) {
            $index[] = $reportId;
            update_option('mpcc_quality_reports_index', $index);
        }
    }

    /**
     * Remove report from index
     */
    private function removeFromReportIndex(string $reportId): void
    {
        $index = get_option('mpcc_quality_reports_index', []);
        $index = array_filter($index, function ($id) use ($reportId) {
            return $id !== $reportId;
        });
        update_option('mpcc_quality_reports_index', array_values($index));
    }

    /**
     * Calculate trend from score array
     */
    private function calculateTrend(array $scores): string
    {
        if (count($scores) < 2) {
            return 'insufficient_data';
        }

        $n     = count($scores);
        $sumX  = array_sum(range(1, $n));
        $sumY  = array_sum($scores);
        $sumXY = 0;
        $sumX2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $x      = $i + 1;
            $y      = $scores[$i];
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);

        if ($slope > 2) {
            return 'strong_improvement';
        } elseif ($slope > 0.5) {
            return 'improvement';
        } elseif ($slope > -0.5) {
            return 'stable';
        } elseif ($slope > -2) {
            return 'decline';
        } else {
            return 'strong_decline';
        }
    }

    /**
     * Calculate score volatility
     */
    private function calculateVolatility(array $scores): string
    {
        if (count($scores) < 2) {
            return 'insufficient_data';
        }

        $mean     = array_sum($scores) / count($scores);
        $variance = array_sum(array_map(function ($score) use ($mean) {
            return pow($score - $mean, 2);
        }, $scores)) / count($scores);

        $standardDeviation = sqrt($variance);

        if ($standardDeviation < 5) {
            return 'low';
        } elseif ($standardDeviation < 10) {
            return 'moderate';
        } else {
            return 'high';
        }
    }

    /**
     * Calculate improvement rate
     */
    private function calculateImprovementRate(array $scores): float
    {
        if (count($scores) < 2) {
            return 0.0;
        }

        $firstScore = $scores[0];
        $lastScore  = end($scores);
        $periods    = count($scores) - 1;

        if ($firstScore <= 0 || $periods <= 0) {
            return 0.0;
        }

        return round((($lastScore / $firstScore) - 1) / $periods * 100, 2);
    }
}
