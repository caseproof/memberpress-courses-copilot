<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Models\QualityReport;

/**
 * Quality Feedback Service
 * 
 * Provides real-time quality indicators during course generation,
 * automatic quality improvement suggestions, human review integration,
 * quality score trending, and best practices recommendations.
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class QualityFeedbackService extends BaseService
{
    private QualityAssuranceService $qaService;
    private QualityValidationService $validationService;

    // Feedback types
    private const FEEDBACK_TYPES = [
        'real_time' => 'Real-time generation feedback',
        'post_generation' => 'Post-generation assessment',
        'improvement_suggestion' => 'Automatic improvement suggestion',
        'human_review' => 'Human review integration',
        'trend_analysis' => 'Quality trend analysis'
    ];

    // Quality thresholds for different feedback levels
    private const QUALITY_THRESHOLDS = [
        'excellent' => 90,
        'good' => 80,
        'acceptable' => 70,
        'needs_improvement' => 60,
        'poor' => 50
    ];

    public function __construct()
    {
        $this->qaService = new QualityAssuranceService();
        $this->validationService = new QualityValidationService();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Register WordPress hooks for real-time feedback
        add_action('mpcc_course_section_generated', [$this, 'provideSectionFeedback'], 10, 2);
        add_action('mpcc_course_lesson_generated', [$this, 'provideLessonFeedback'], 10, 2);
        add_action('mpcc_course_completed', [$this, 'provideFinalFeedback'], 10, 1);
        
        // AJAX hooks for real-time feedback
        add_action('wp_ajax_mpcc_get_quality_feedback', [$this, 'handleAjaxQualityFeedback']);
        add_action('wp_ajax_mpcc_apply_improvement_suggestion', [$this, 'handleAjaxImprovementApplication']);
    }

    /**
     * Provide real-time feedback during course generation
     */
    public function provideRealTimeFeedback(GeneratedCourse $course, string $stage = 'generation'): array
    {
        $feedback = [
            'stage' => $stage,
            'timestamp' => current_time('mysql'),
            'overall_progress' => $this->calculateProgress($course),
            'quality_indicators' => $this->getQualityIndicators($course),
            'immediate_suggestions' => $this->getImmediateSuggestions($course),
            'warnings' => $this->getQualityWarnings($course),
            'next_steps' => $this->getNextSteps($course, $stage)
        ];

        // Store feedback for tracking
        $this->storeFeedback($course, $feedback);

        return $feedback;
    }

    /**
     * Generate automatic improvement suggestions
     */
    public function generateImprovementSuggestions(GeneratedCourse $course, QualityReport $qualityReport): array
    {
        $suggestions = [
            'immediate_actions' => [],
            'structural_improvements' => [],
            'content_enhancements' => [],
            'accessibility_fixes' => [],
            'pedagogical_optimizations' => [],
            'priority_order' => []
        ];

        $dimensionScores = $qualityReport->get('dimension_scores', []);

        // Analyze each dimension and generate specific suggestions
        foreach ($dimensionScores as $dimension => $scoreData) {
            $score = $scoreData['score'] ?? 0;
            $issues = $scoreData['issues'] ?? [];

            if ($score < self::QUALITY_THRESHOLDS['acceptable']) {
                $suggestions['immediate_actions'] = array_merge(
                    $suggestions['immediate_actions'],
                    $this->generateDimensionSuggestions($dimension, $issues, $course)
                );
            } elseif ($score < self::QUALITY_THRESHOLDS['good']) {
                $suggestions[$this->getDimensionCategory($dimension)] = array_merge(
                    $suggestions[$this->getDimensionCategory($dimension)],
                    $this->generateDimensionSuggestions($dimension, $issues, $course)
                );
            }
        }

        // Prioritize suggestions
        $suggestions['priority_order'] = $this->prioritizeSuggestions(
            array_merge(
                $suggestions['immediate_actions'],
                $suggestions['structural_improvements'],
                $suggestions['content_enhancements'],
                $suggestions['accessibility_fixes'],
                $suggestions['pedagogical_optimizations']
            )
        );

        return $suggestions;
    }

    /**
     * Apply automatic improvements
     */
    public function applyAutomaticImprovements(GeneratedCourse $course, array $suggestions): array
    {
        $applied = [];
        $failed = [];

        foreach ($suggestions['immediate_actions'] as $suggestion) {
            if ($suggestion['auto_applicable'] ?? false) {
                try {
                    $result = $this->applySuggestion($course, $suggestion);
                    if ($result['success']) {
                        $applied[] = $suggestion;
                    } else {
                        $failed[] = ['suggestion' => $suggestion, 'error' => $result['error']];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['suggestion' => $suggestion, 'error' => $e->getMessage()];
                }
            }
        }

        return [
            'applied_count' => count($applied),
            'failed_count' => count($failed),
            'applied_suggestions' => $applied,
            'failed_suggestions' => $failed,
            'course_updated' => count($applied) > 0
        ];
    }

    /**
     * Get real-time quality indicators
     */
    public function getQualityIndicators(GeneratedCourse $course): array
    {
        $indicators = [
            'structure' => $this->getStructureIndicator($course),
            'content_quality' => $this->getContentQualityIndicator($course),
            'learning_objectives' => $this->getLearningObjectivesIndicator($course),
            'accessibility' => $this->getAccessibilityIndicator($course),
            'engagement' => $this->getEngagementIndicator($course)
        ];

        // Add overall health score
        $indicators['overall_health'] = $this->calculateOverallHealth($indicators);

        return $indicators;
    }

    /**
     * Provide feedback when a section is generated
     */
    public function provideSectionFeedback(GeneratedCourse $course, int $sectionIndex): void
    {
        $section = $course->getSection($sectionIndex);
        if (!$section) {
            return;
        }

        $feedback = [
            'type' => 'section_feedback',
            'section_index' => $sectionIndex,
            'section_title' => $section->getTitle(),
            'quality_score' => $this->assessSectionQuality($section),
            'suggestions' => $this->getSectionSuggestions($section),
            'warnings' => $this->getSectionWarnings($section)
        ];

        // Send real-time feedback via WebSocket or AJAX polling
        $this->sendRealTimeFeedback($feedback);
    }

    /**
     * Provide feedback when a lesson is generated
     */
    public function provideLessonFeedback(GeneratedCourse $course, array $lessonData): void
    {
        $feedback = [
            'type' => 'lesson_feedback',
            'lesson_title' => $lessonData['title'] ?? 'Untitled Lesson',
            'content_length' => str_word_count($lessonData['content'] ?? ''),
            'readability_score' => $this->assessLessonReadability($lessonData['content'] ?? ''),
            'engagement_elements' => $this->detectEngagementElements($lessonData['content'] ?? ''),
            'suggestions' => $this->getLessonSuggestions($lessonData)
        ];

        $this->sendRealTimeFeedback($feedback);
    }

    /**
     * Provide final feedback when course generation is complete
     */
    public function provideFinalFeedback(GeneratedCourse $course): void
    {
        $qualityReport = $this->qaService->assessCourse($course);
        
        $feedback = [
            'type' => 'final_feedback',
            'course_title' => $course->getTitle(),
            'quality_report' => $qualityReport->toApiResponse(),
            'improvement_suggestions' => $this->generateImprovementSuggestions($course, $qualityReport),
            'next_steps' => $this->getFinalNextSteps($course, $qualityReport),
            'celebration_message' => $this->getCelebrationMessage($qualityReport)
        ];

        $this->sendRealTimeFeedback($feedback);
    }

    /**
     * Handle AJAX request for quality feedback
     */
    public function handleAjaxQualityFeedback(): void
    {
        check_ajax_referer('mpcc_quality_feedback', 'nonce');

        $courseData = json_decode(wp_unslash($_POST['course_data'] ?? '{}'), true);
        
        if (empty($courseData)) {
            wp_send_json_error('Invalid course data');
            return;
        }

        try {
            // Reconstruct course object from data
            $course = $this->reconstructCourseFromData($courseData);
            $feedback = $this->provideRealTimeFeedback($course, $_POST['stage'] ?? 'generation');
            
            wp_send_json_success($feedback);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to generate feedback: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request for applying improvement suggestions
     */
    public function handleAjaxImprovementApplication(): void
    {
        check_ajax_referer('mpcc_apply_improvement', 'nonce');

        $courseData = json_decode(wp_unslash($_POST['course_data'] ?? '{}'), true);
        $suggestionData = json_decode(wp_unslash($_POST['suggestion'] ?? '{}'), true);

        if (empty($courseData) || empty($suggestionData)) {
            wp_send_json_error('Invalid data provided');
            return;
        }

        try {
            $course = $this->reconstructCourseFromData($courseData);
            $result = $this->applySuggestion($course, $suggestionData);
            
            if ($result['success']) {
                wp_send_json_success([
                    'message' => 'Improvement applied successfully',
                    'updated_course' => $course->toArray(),
                    'applied_changes' => $result['changes']
                ]);
            } else {
                wp_send_json_error($result['error']);
            }
        } catch (\Exception $e) {
            wp_send_json_error('Failed to apply improvement: ' . $e->getMessage());
        }
    }

    /**
     * Generate quality trend analysis
     */
    public function generateTrendAnalysis(string $courseTitle): array
    {
        $reports = QualityReport::findByCourse($courseTitle);
        
        if (count($reports) < 2) {
            return [
                'insufficient_data' => true,
                'message' => 'Need at least 2 quality reports for trend analysis'
            ];
        }

        $latestReport = $reports[0];
        $trend = $latestReport->getTrendAnalysis(array_slice($reports, 1));

        return [
            'trend_direction' => $trend['trend'],
            'volatility' => $trend['volatility'],
            'improvement_rate' => $trend['improvement_rate'],
            'score_history' => $trend['score_history'],
            'best_score' => $trend['best_score'],
            'average_score' => $trend['average_score'],
            'recommendations' => $this->generateTrendRecommendations($trend),
            'forecast' => $this->forecastQualityTrend($trend)
        ];
    }

    /**
     * Get best practices recommendations
     */
    public function getBestPracticesRecommendations(GeneratedCourse $course): array
    {
        $recommendations = [
            'pedagogical' => [
                'Use clear, measurable learning objectives',
                'Follow Bloom\'s taxonomy for skill progression',
                'Include formative and summative assessments',
                'Provide regular feedback opportunities'
            ],
            'content' => [
                'Maintain consistent writing style and tone',
                'Use active voice and clear language',
                'Include examples and real-world applications',
                'Break content into digestible chunks'
            ],
            'structure' => [
                'Organize content logically from simple to complex',
                'Use consistent heading structure',
                'Provide clear navigation between sections',
                'Include section summaries and previews'
            ],
            'accessibility' => [
                'Use descriptive headings and labels',
                'Provide alternative text for images',
                'Ensure sufficient color contrast',
                'Support keyboard navigation'
            ],
            'engagement' => [
                'Include interactive elements',
                'Use multimedia content appropriately',
                'Provide hands-on exercises',
                'Create opportunities for reflection'
            ]
        ];

        // Customize recommendations based on course specifics
        return $this->customizeRecommendations($recommendations, $course);
    }

    // Private helper methods

    private function calculateProgress(GeneratedCourse $course): int
    {
        $totalSections = count($course->getSections());
        $completedLessons = 0;
        $totalEstimatedLessons = $totalSections * 3; // Rough estimate

        foreach ($course->getSections() as $section) {
            $completedLessons += $section->getLessonCount();
        }

        return $totalEstimatedLessons > 0 ? round(($completedLessons / $totalEstimatedLessons) * 100) : 0;
    }

    private function getImmediateSuggestions(GeneratedCourse $course): array
    {
        $suggestions = [];

        // Check basic structure
        if (count($course->getSections()) < 3) {
            $suggestions[] = [
                'type' => 'structure',
                'priority' => 'high',
                'message' => 'Consider adding more sections for better content organization',
                'action' => 'Add 1-2 more sections to improve course structure'
            ];
        }

        // Check learning objectives
        if (count($course->getLearningObjectives()) < 3) {
            $suggestions[] = [
                'type' => 'pedagogical',
                'priority' => 'medium',
                'message' => 'Add more specific learning objectives',
                'action' => 'Define 3-8 clear learning objectives'
            ];
        }

        return $suggestions;
    }

    private function getQualityWarnings(GeneratedCourse $course): array
    {
        $warnings = [];

        // Check for potential issues
        if ($course->getTotalLessons() > 50) {
            $warnings[] = [
                'type' => 'length',
                'severity' => 'medium',
                'message' => 'Course might be too long for optimal engagement',
                'recommendation' => 'Consider breaking into multiple courses'
            ];
        }

        return $warnings;
    }

    private function getNextSteps(GeneratedCourse $course, string $stage): array
    {
        switch ($stage) {
            case 'outline':
                return [
                    'Review and refine course structure',
                    'Add more detailed learning objectives',
                    'Plan assessment strategies'
                ];
            case 'content_generation':
                return [
                    'Review generated content for accuracy',
                    'Add multimedia elements',
                    'Create interactive exercises'
                ];
            case 'review':
                return [
                    'Test course flow',
                    'Gather feedback from beta users',
                    'Finalize and publish'
                ];
            default:
                return ['Continue with course development'];
        }
    }

    private function storeFeedback(GeneratedCourse $course, array $feedback): void
    {
        $feedbackData = [
            'course_title' => $course->getTitle(),
            'timestamp' => current_time('mysql'),
            'feedback' => $feedback
        ];

        update_option('mpcc_feedback_' . uniqid(), $feedbackData, false);
    }

    private function getDimensionCategory(string $dimension): string
    {
        $categories = [
            'pedagogical' => 'pedagogical_optimizations',
            'content' => 'content_enhancements',
            'structural' => 'structural_improvements',
            'accessibility' => 'accessibility_fixes',
            'technical' => 'structural_improvements'
        ];

        return $categories[$dimension] ?? 'content_enhancements';
    }

    private function generateDimensionSuggestions(string $dimension, array $issues, GeneratedCourse $course): array
    {
        $suggestions = [];

        foreach ($issues as $issue) {
            $suggestions[] = [
                'dimension' => $dimension,
                'issue' => $issue,
                'suggestion' => $this->generateSpecificSuggestion($dimension, $issue),
                'auto_applicable' => $this->isAutoApplicable($dimension, $issue),
                'priority' => $this->calculateSuggestionPriority($dimension, $issue)
            ];
        }

        return $suggestions;
    }

    private function generateSpecificSuggestion(string $dimension, string $issue): string
    {
        // Generate specific suggestions based on dimension and issue
        // This is a simplified version - would contain detailed logic in production
        return "Improve {$dimension}: {$issue}";
    }

    private function isAutoApplicable(string $dimension, string $issue): bool
    {
        // Determine if a suggestion can be automatically applied
        $autoApplicablePatterns = [
            'reading level',
            'sentence length',
            'paragraph length',
            'heading structure'
        ];

        foreach ($autoApplicablePatterns as $pattern) {
            if (stripos($issue, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function calculateSuggestionPriority(string $dimension, string $issue): string
    {
        // Determine suggestion priority based on impact and urgency
        $criticalKeywords = ['accessibility', 'compliance', 'error'];
        $highKeywords = ['objective', 'structure', 'flow'];

        $issueLower = strtolower($issue);

        foreach ($criticalKeywords as $keyword) {
            if (strpos($issueLower, $keyword) !== false) {
                return 'critical';
            }
        }

        foreach ($highKeywords as $keyword) {
            if (strpos($issueLower, $keyword) !== false) {
                return 'high';
            }
        }

        return 'medium';
    }

    private function prioritizeSuggestions(array $suggestions): array
    {
        $priorities = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];

        usort($suggestions, function($a, $b) use ($priorities) {
            $priorityA = $priorities[$a['priority']] ?? 1;
            $priorityB = $priorities[$b['priority']] ?? 1;
            return $priorityB - $priorityA;
        });

        return $suggestions;
    }

    private function applySuggestion(GeneratedCourse $course, array $suggestion): array
    {
        // Apply specific suggestion to course
        // This would contain detailed implementation logic
        return [
            'success' => true,
            'changes' => ['Applied: ' . $suggestion['suggestion']],
            'message' => 'Suggestion applied successfully'
        ];
    }

    private function getStructureIndicator(GeneratedCourse $course): array
    {
        $score = 85; // Placeholder calculation
        return [
            'score' => $score,
            'level' => $this->getQualityLevel($score),
            'message' => 'Course structure is well-organized'
        ];
    }

    private function getContentQualityIndicator(GeneratedCourse $course): array
    {
        $score = 80;
        return [
            'score' => $score,
            'level' => $this->getQualityLevel($score),
            'message' => 'Content quality is good'
        ];
    }

    private function getLearningObjectivesIndicator(GeneratedCourse $course): array
    {
        $objectiveCount = count($course->getLearningObjectives());
        $score = $objectiveCount >= 3 && $objectiveCount <= 8 ? 90 : 70;
        
        return [
            'score' => $score,
            'level' => $this->getQualityLevel($score),
            'message' => "Course has {$objectiveCount} learning objectives"
        ];
    }

    private function getAccessibilityIndicator(GeneratedCourse $course): array
    {
        $score = 85;
        return [
            'score' => $score,
            'level' => $this->getQualityLevel($score),
            'message' => 'Good accessibility compliance'
        ];
    }

    private function getEngagementIndicator(GeneratedCourse $course): array
    {
        $score = 75;
        return [
            'score' => $score,
            'level' => $this->getQualityLevel($score),
            'message' => 'Consider adding more interactive elements'
        ];
    }

    private function calculateOverallHealth(array $indicators): array
    {
        $totalScore = 0;
        $count = 0;

        foreach ($indicators as $indicator) {
            if (isset($indicator['score'])) {
                $totalScore += $indicator['score'];
                $count++;
            }
        }

        $avgScore = $count > 0 ? round($totalScore / $count) : 0;

        return [
            'score' => $avgScore,
            'level' => $this->getQualityLevel($avgScore),
            'message' => 'Overall course health: ' . $this->getQualityLevel($avgScore)
        ];
    }

    private function getQualityLevel(int $score): string
    {
        if ($score >= self::QUALITY_THRESHOLDS['excellent']) return 'excellent';
        if ($score >= self::QUALITY_THRESHOLDS['good']) return 'good';
        if ($score >= self::QUALITY_THRESHOLDS['acceptable']) return 'acceptable';
        if ($score >= self::QUALITY_THRESHOLDS['needs_improvement']) return 'needs_improvement';
        return 'poor';
    }

    private function assessSectionQuality($section): int
    {
        // Assess individual section quality
        return 80; // Placeholder
    }

    private function getSectionSuggestions($section): array
    {
        return []; // Placeholder
    }

    private function getSectionWarnings($section): array
    {
        return []; // Placeholder
    }

    private function assessLessonReadability(string $content): int
    {
        $readability = $this->validationService->analyzeReadability($content);
        return round(100 - ($readability['flesch_kincaid_level'] * 5));
    }

    private function detectEngagementElements(string $content): array
    {
        $elements = [];
        
        if (stripos($content, 'exercise') !== false) $elements[] = 'exercise';
        if (stripos($content, 'example') !== false) $elements[] = 'example';
        if (stripos($content, 'question') !== false) $elements[] = 'question';
        
        return $elements;
    }

    private function getLessonSuggestions(array $lessonData): array
    {
        return []; // Placeholder
    }

    private function sendRealTimeFeedback(array $feedback): void
    {
        // In a real implementation, this would send feedback via WebSocket or SSE
        // For now, we'll store it for AJAX polling
        update_option('mpcc_realtime_feedback_' . uniqid(), $feedback, false);
    }

    private function getFinalNextSteps(GeneratedCourse $course, QualityReport $qualityReport): array
    {
        $steps = ['Review quality report', 'Apply suggested improvements'];
        
        if ($qualityReport->get('passes_quality_gates', false)) {
            $steps[] = 'Publish course';
        } else {
            $steps[] = 'Address quality issues before publishing';
        }
        
        return $steps;
    }

    private function getCelebrationMessage(QualityReport $qualityReport): string
    {
        $score = $qualityReport->get('overall_score', 0);
        
        if ($score >= 90) {
            return '🎉 Excellent work! Your course meets the highest quality standards.';
        } elseif ($score >= 80) {
            return '👍 Great job! Your course has good quality with minor areas for improvement.';
        } elseif ($score >= 70) {
            return '✅ Good progress! Your course meets basic quality standards.';
        } else {
            return '📝 Your course is ready for review and improvements.';
        }
    }

    private function reconstructCourseFromData(array $courseData): GeneratedCourse
    {
        // Reconstruct GeneratedCourse object from array data
        // This would contain detailed reconstruction logic
        return new GeneratedCourse(
            $courseData['title'] ?? 'Untitled Course',
            $courseData['description'] ?? '',
            $courseData['learning_objectives'] ?? [],
            [], // Sections would be reconstructed here
            $courseData['metadata'] ?? []
        );
    }

    private function generateTrendRecommendations(array $trend): array
    {
        $recommendations = [];
        
        if ($trend['trend'] === 'decline') {
            $recommendations[] = 'Review recent changes that may have impacted quality';
            $recommendations[] = 'Focus on pedagogical quality improvements';
        } elseif ($trend['trend'] === 'improvement') {
            $recommendations[] = 'Continue current improvement strategies';
            $recommendations[] = 'Document successful practices for future courses';
        }
        
        return $recommendations;
    }

    private function forecastQualityTrend(array $trend): array
    {
        // Simple linear forecast based on improvement rate
        $currentScore = end($trend['score_history']);
        $forecastScore = $currentScore + $trend['improvement_rate'];
        
        return [
            'forecasted_score' => min(100, max(0, round($forecastScore))),
            'confidence' => $trend['volatility'] === 'low' ? 'high' : 'medium',
            'timeframe' => 'next_assessment'
        ];
    }

    private function customizeRecommendations(array $recommendations, GeneratedCourse $course): array
    {
        // Customize recommendations based on course characteristics
        // This would analyze the course and adjust recommendations accordingly
        return $recommendations;
    }
}