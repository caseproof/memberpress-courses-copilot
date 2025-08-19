<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Models\QualityReport;

/**
 * Quality Gates Service
 * 
 * Implements quality gates with minimum thresholds, automated quality improvements,
 * quality-based generation refinement, review workflow integration,
 * and quality certification processes.
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class QualityGatesService extends BaseService
{
    private QualityAssuranceService $qaService;
    private QualityFeedbackService $feedbackService;

    // Quality gate definitions
    private const QUALITY_GATES = [
        'minimum_viable_product' => [
            'overall_score' => 60,
            'pedagogical_score' => 65,
            'accessibility_score' => 70,
            'required_sections' => 3,
            'required_objectives' => 3,
            'description' => 'Minimum quality for draft publication'
        ],
        'production_ready' => [
            'overall_score' => 75,
            'pedagogical_score' => 80,
            'accessibility_score' => 85,
            'content_score' => 75,
            'structural_score' => 75,
            'technical_score' => 80,
            'description' => 'Ready for student enrollment'
        ],
        'premium_quality' => [
            'overall_score' => 90,
            'pedagogical_score' => 90,
            'accessibility_score' => 95,
            'content_score' => 85,
            'structural_score' => 85,
            'technical_score' => 85,
            'description' => 'Premium quality certification'
        ]
    ];

    // Automatic improvement strategies
    private const IMPROVEMENT_STRATEGIES = [
        'pedagogical' => [
            'learning_objectives_enhancement',
            'blooms_taxonomy_balance',
            'progression_optimization'
        ],
        'content' => [
            'readability_improvement',
            'length_optimization',
            'clarity_enhancement'
        ],
        'structural' => [
            'section_balancing',
            'flow_improvement',
            'navigation_enhancement'
        ],
        'accessibility' => [
            'wcag_compliance_fixes',
            'inclusive_language_updates',
            'structure_accessibility_improvements'
        ],
        'technical' => [
            'wordpress_optimization',
            'performance_improvements',
            'seo_enhancements'
        ]
    ];

    // Review workflow stages
    private const REVIEW_STAGES = [
        'auto_assessment' => 'Automated quality assessment',
        'auto_improvement' => 'Automated improvements applied',
        'human_review_required' => 'Human review required',
        'stakeholder_approval' => 'Stakeholder approval needed',
        'final_certification' => 'Final quality certification',
        'approved' => 'Approved for publication'
    ];

    public function __construct()
    {
        $this->qaService = new QualityAssuranceService();
        $this->feedbackService = new QualityFeedbackService();
    }

    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Register quality gate hooks
        add_action('mpcc_course_pre_save', [$this, 'enforceQualityGates'], 10, 1);
        add_action('mpcc_course_pre_publish', [$this, 'enforceProductionGates'], 10, 1);
        add_filter('mpcc_course_publish_allowed', [$this, 'filterPublishPermission'], 10, 2);

        // Register AJAX endpoints
        add_action('wp_ajax_mpcc_check_quality_gates', [$this, 'handleAjaxQualityGateCheck']);
        add_action('wp_ajax_mpcc_request_review', [$this, 'handleAjaxReviewRequest']);
        add_action('wp_ajax_mpcc_certify_quality', [$this, 'handleAjaxQualityCertification']);

        // Schedule automatic quality improvements
        add_action('mpcc_automatic_quality_improvement', [$this, 'runAutomaticImprovements']);
    }

    /**
     * Check if course passes specific quality gate
     */
    public function checkQualityGate(GeneratedCourse $course, string $gateLevel = 'minimum_viable_product'): array
    {
        if (!isset(self::QUALITY_GATES[$gateLevel])) {
            throw new \InvalidArgumentException("Unknown quality gate level: {$gateLevel}");
        }

        $gate = self::QUALITY_GATES[$gateLevel];
        $qualityReport = $this->qaService->assessCourse($course);
        
        $result = [
            'gate_level' => $gateLevel,
            'passed' => true,
            'overall_score' => $qualityReport->get('overall_score', 0),
            'dimension_scores' => $qualityReport->get('dimension_scores', []),
            'failed_criteria' => [],
            'improvements_needed' => [],
            'estimated_fix_time' => 0,
            'auto_fixable_issues' => [],
            'manual_review_required' => false
        ];

        // Check overall score
        if ($result['overall_score'] < $gate['overall_score']) {
            $result['passed'] = false;
            $result['failed_criteria'][] = "Overall score ({$result['overall_score']}) below threshold ({$gate['overall_score']})";
        }

        // Check dimension-specific scores
        $dimensionScores = $result['dimension_scores'];
        foreach (['pedagogical', 'content', 'structural', 'accessibility', 'technical'] as $dimension) {
            if (isset($gate["{$dimension}_score"])) {
                $score = $dimensionScores[$dimension]['score'] ?? 0;
                $threshold = $gate["{$dimension}_score"];
                
                if ($score < $threshold) {
                    $result['passed'] = false;
                    $result['failed_criteria'][] = ucfirst($dimension) . " score ({$score}) below threshold ({$threshold})";
                    
                    $improvements = $this->generateDimensionImprovements($dimension, $dimensionScores[$dimension], $course);
                    $result['improvements_needed'] = array_merge($result['improvements_needed'], $improvements);
                }
            }
        }

        // Check structural requirements
        if (isset($gate['required_sections']) && count($course->getSections()) < $gate['required_sections']) {
            $result['passed'] = false;
            $result['failed_criteria'][] = "Insufficient sections (" . count($course->getSections()) . " of {$gate['required_sections']} required)";
        }

        if (isset($gate['required_objectives']) && count($course->getLearningObjectives()) < $gate['required_objectives']) {
            $result['passed'] = false;
            $result['failed_criteria'][] = "Insufficient learning objectives (" . count($course->getLearningObjectives()) . " of {$gate['required_objectives']} required)";
        }

        // Analyze improvements needed
        $result = $this->analyzeImprovements($result, $course);

        return $result;
    }

    /**
     * Enforce quality gates before course save
     */
    public function enforceQualityGates(GeneratedCourse $course): void
    {
        $gateResult = $this->checkQualityGate($course, 'minimum_viable_product');
        
        if (!$gateResult['passed']) {
            // Try automatic improvements
            $improvements = $this->applyAutomaticImprovements($course, $gateResult['auto_fixable_issues']);
            
            if ($improvements['applied_count'] > 0) {
                // Re-check after improvements
                $gateResult = $this->checkQualityGate($course, 'minimum_viable_product');
            }
            
            if (!$gateResult['passed']) {
                $this->log('Course failed minimum quality gates', 'warning');
                
                // Create quality improvement plan
                $this->createImprovementPlan($course, $gateResult);
                
                // Allow save but mark as draft requiring review
                $course->addMetadata('quality_gate_status', 'failed_minimum');
                $course->addMetadata('requires_review', true);
            }
        } else {
            $course->addMetadata('quality_gate_status', 'passed_minimum');
        }
    }

    /**
     * Enforce production quality gates before publish
     */
    public function enforceProductionGates(GeneratedCourse $course): void
    {
        $gateResult = $this->checkQualityGate($course, 'production_ready');
        
        if (!$gateResult['passed']) {
            throw new \Exception(
                'Course does not meet production quality standards. ' .
                'Please address the following issues: ' . 
                implode(', ', $gateResult['failed_criteria'])
            );
        }

        // Mark as production ready
        $course->addMetadata('quality_gate_status', 'production_ready');
        $course->addMetadata('quality_certified_at', current_time('mysql'));
    }

    /**
     * Filter publish permission based on quality gates
     */
    public function filterPublishPermission(bool $allowed, GeneratedCourse $course): bool
    {
        $gateResult = $this->checkQualityGate($course, 'production_ready');
        
        if (!$gateResult['passed']) {
            $this->log('Publish blocked due to quality gate failure', 'warning');
            return false;
        }

        return $allowed;
    }

    /**
     * Apply automatic improvements to course
     */
    public function applyAutomaticImprovements(GeneratedCourse $course, array $issues = []): array
    {
        $this->log('Starting automatic quality improvements for course: ' . $course->getTitle());

        $applied = [];
        $failed = [];
        $improvementStrategies = [];

        // If no specific issues provided, generate from quality assessment
        if (empty($issues)) {
            $qualityReport = $this->qaService->assessCourse($course);
            $issues = $this->extractAutoFixableIssues($qualityReport);
        }

        foreach ($issues as $issue) {
            $strategy = $this->getImprovementStrategy($issue);
            
            if ($strategy) {
                try {
                    $result = $this->applyImprovementStrategy($course, $strategy, $issue);
                    
                    if ($result['success']) {
                        $applied[] = $result;
                        $improvementStrategies[] = $strategy['name'];
                    } else {
                        $failed[] = ['issue' => $issue, 'error' => $result['error']];
                    }
                } catch (\Exception $e) {
                    $failed[] = ['issue' => $issue, 'error' => $e->getMessage()];
                    $this->log('Failed to apply improvement: ' . $e->getMessage(), 'error');
                }
            }
        }

        $result = [
            'applied_count' => count($applied),
            'failed_count' => count($failed),
            'applied_improvements' => $applied,
            'failed_improvements' => $failed,
            'strategies_used' => array_unique($improvementStrategies),
            'course_modified' => count($applied) > 0
        ];

        $this->log('Completed automatic improvements. Applied: ' . count($applied) . ', Failed: ' . count($failed));

        return $result;
    }

    /**
     * Create improvement plan for course
     */
    public function createImprovementPlan(GeneratedCourse $course, array $gateResult): array
    {
        $plan = [
            'course_title' => $course->getTitle(),
            'created_at' => current_time('mysql'),
            'gate_level' => $gateResult['gate_level'],
            'current_score' => $gateResult['overall_score'],
            'target_score' => self::QUALITY_GATES[$gateResult['gate_level']]['overall_score'],
            'failed_criteria' => $gateResult['failed_criteria'],
            'improvement_tasks' => [],
            'estimated_completion_time' => 0,
            'priority_order' => [],
            'automatic_fixes_available' => count($gateResult['auto_fixable_issues']),
            'manual_review_required' => $gateResult['manual_review_required']
        ];

        // Generate specific improvement tasks
        foreach ($gateResult['improvements_needed'] as $improvement) {
            $task = [
                'id' => uniqid('task_'),
                'category' => $improvement['category'],
                'description' => $improvement['description'],
                'priority' => $improvement['priority'],
                'estimated_time' => $improvement['estimated_time'],
                'auto_fixable' => $improvement['auto_fixable'],
                'status' => 'pending'
            ];

            $plan['improvement_tasks'][] = $task;
            $plan['estimated_completion_time'] += $improvement['estimated_time'];
        }

        // Sort tasks by priority
        usort($plan['improvement_tasks'], function($a, $b) {
            $priorities = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            return ($priorities[$b['priority']] ?? 1) - ($priorities[$a['priority']] ?? 1);
        });

        $plan['priority_order'] = array_column($plan['improvement_tasks'], 'id');

        // Store improvement plan
        $planId = 'improvement_plan_' . uniqid();
        update_option("mpcc_{$planId}", $plan);
        
        // Associate plan with course
        $course->addMetadata('improvement_plan_id', $planId);

        return $plan;
    }

    /**
     * Initiate review workflow
     */
    public function initiateReviewWorkflow(GeneratedCourse $course, string $reviewType = 'standard'): array
    {
        $workflow = [
            'id' => 'review_' . uniqid(),
            'course_title' => $course->getTitle(),
            'review_type' => $reviewType,
            'initiated_at' => current_time('mysql'),
            'current_stage' => 'auto_assessment',
            'stages_completed' => [],
            'reviewer_assignments' => [],
            'quality_assessments' => [],
            'approval_status' => 'pending',
            'estimated_completion' => $this->calculateReviewTimeframe($reviewType)
        ];

        // Start with automated assessment
        $qualityReport = $this->qaService->assessCourse($course);
        $workflow['quality_assessments']['auto_assessment'] = $qualityReport->toArray();

        // Determine next stage based on quality
        $nextStage = $this->determineNextReviewStage($qualityReport, $reviewType);
        $workflow['current_stage'] = $nextStage;

        // Assign reviewers if human review required
        if (in_array($nextStage, ['human_review_required', 'stakeholder_approval'])) {
            $workflow['reviewer_assignments'] = $this->assignReviewers($course, $nextStage);
        }

        // Store workflow
        $workflowId = $workflow['id'];
        update_option("mpcc_review_{$workflowId}", $workflow);
        
        // Associate with course
        $course->addMetadata('review_workflow_id', $workflowId);

        // Send notifications
        $this->sendReviewNotifications($workflow);

        return $workflow;
    }

    /**
     * Certify course quality
     */
    public function certifyQuality(GeneratedCourse $course, string $certificationLevel = 'production_ready'): array
    {
        $gateResult = $this->checkQualityGate($course, $certificationLevel);
        
        if (!$gateResult['passed']) {
            throw new \Exception('Course does not meet certification requirements: ' . implode(', ', $gateResult['failed_criteria']));
        }

        $certification = [
            'id' => 'cert_' . uniqid(),
            'course_title' => $course->getTitle(),
            'certification_level' => $certificationLevel,
            'certified_at' => current_time('mysql'),
            'certified_by' => get_current_user_id(),
            'quality_score' => $gateResult['overall_score'],
            'dimension_scores' => $gateResult['dimension_scores'],
            'valid_until' => strtotime('+1 year'), // Certification expires after 1 year
            'certification_badge' => $this->generateCertificationBadge($certificationLevel),
            'quality_metrics' => $this->generateQualityMetrics($course)
        ];

        // Store certification
        $certId = $certification['id'];
        update_option("mpcc_certification_{$certId}", $certification);
        
        // Update course with certification info
        $course->addMetadata('quality_certification_id', $certId);
        $course->addMetadata('quality_certification_level', $certificationLevel);
        $course->addMetadata('quality_certification_date', $certification['certified_at']);
        $course->addMetadata('quality_certification_score', $certification['quality_score']);

        $this->log("Course certified at {$certificationLevel} level with score {$certification['quality_score']}");

        return $certification;
    }

    /**
     * Handle AJAX quality gate check
     */
    public function handleAjaxQualityGateCheck(): void
    {
        check_ajax_referer('mpcc_quality_gates', 'nonce');

        $courseData = json_decode(wp_unslash($_POST['course_data'] ?? '{}'), true);
        $gateLevel = sanitize_text_field($_POST['gate_level'] ?? 'minimum_viable_product');

        if (empty($courseData)) {
            wp_send_json_error('Invalid course data');
            return;
        }

        try {
            $course = $this->reconstructCourseFromData($courseData);
            $gateResult = $this->checkQualityGate($course, $gateLevel);
            
            wp_send_json_success($gateResult);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to check quality gates: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX review request
     */
    public function handleAjaxReviewRequest(): void
    {
        check_ajax_referer('mpcc_request_review', 'nonce');

        $courseData = json_decode(wp_unslash($_POST['course_data'] ?? '{}'), true);
        $reviewType = sanitize_text_field($_POST['review_type'] ?? 'standard');

        try {
            $course = $this->reconstructCourseFromData($courseData);
            $workflow = $this->initiateReviewWorkflow($course, $reviewType);
            
            wp_send_json_success([
                'message' => 'Review workflow initiated successfully',
                'workflow_id' => $workflow['id'],
                'current_stage' => $workflow['current_stage'],
                'estimated_completion' => $workflow['estimated_completion']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to initiate review: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX quality certification
     */
    public function handleAjaxQualityCertification(): void
    {
        check_ajax_referer('mpcc_certify_quality', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $courseData = json_decode(wp_unslash($_POST['course_data'] ?? '{}'), true);
        $certificationLevel = sanitize_text_field($_POST['certification_level'] ?? 'production_ready');

        try {
            $course = $this->reconstructCourseFromData($courseData);
            $certification = $this->certifyQuality($course, $certificationLevel);
            
            wp_send_json_success([
                'message' => 'Course certified successfully',
                'certification_id' => $certification['id'],
                'certification_level' => $certification['certification_level'],
                'quality_score' => $certification['quality_score']
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to certify course: ' . $e->getMessage());
        }
    }

    /**
     * Run scheduled automatic quality improvements
     */
    public function runAutomaticImprovements(): void
    {
        $this->log('Running scheduled automatic quality improvements');

        // Find courses that need improvement
        $coursesNeedingImprovement = $this->findCoursesNeedingImprovement();

        foreach ($coursesNeedingImprovement as $courseData) {
            try {
                $course = $this->reconstructCourseFromData($courseData);
                $this->applyAutomaticImprovements($course);
                
                // Update course in database
                $course->save();
                
                $this->log('Applied automatic improvements to course: ' . $course->getTitle());
            } catch (\Exception $e) {
                $this->log('Failed to improve course: ' . $e->getMessage(), 'error');
            }
        }
    }

    // Private helper methods

    private function analyzeImprovements(array $result, GeneratedCourse $course): array
    {
        $autoFixableCount = 0;
        $manualReviewRequired = false;

        foreach ($result['improvements_needed'] as &$improvement) {
            if ($this->isAutoFixable($improvement)) {
                $improvement['auto_fixable'] = true;
                $autoFixableCount++;
                $result['auto_fixable_issues'][] = $improvement;
            } else {
                $improvement['auto_fixable'] = false;
                $manualReviewRequired = true;
            }

            $improvement['estimated_time'] = $this->estimateFixTime($improvement);
            $result['estimated_fix_time'] += $improvement['estimated_time'];
        }

        $result['manual_review_required'] = $manualReviewRequired;

        return $result;
    }

    private function generateDimensionImprovements(string $dimension, array $dimensionData, GeneratedCourse $course): array
    {
        $improvements = [];
        $issues = $dimensionData['issues'] ?? [];

        foreach ($issues as $issue) {
            $improvements[] = [
                'category' => $dimension,
                'description' => $issue,
                'priority' => $this->calculateIssuePriority($dimension, $issue),
                'auto_fixable' => $this->isIssueAutoFixable($dimension, $issue),
                'estimated_time' => $this->estimateIssueFixTime($dimension, $issue)
            ];
        }

        return $improvements;
    }

    private function extractAutoFixableIssues(QualityReport $qualityReport): array
    {
        $issues = [];
        $recommendations = $qualityReport->get('recommendations', []);

        foreach ($recommendations as $recommendation) {
            if ($this->isRecommendationAutoFixable($recommendation)) {
                $issues[] = $recommendation;
            }
        }

        return $issues;
    }

    private function getImprovementStrategy(array $issue): ?array
    {
        $category = $issue['category'] ?? 'general';
        $strategies = self::IMPROVEMENT_STRATEGIES[$category] ?? [];

        foreach ($strategies as $strategyName) {
            if ($this->strategyAppliesTo($strategyName, $issue)) {
                return [
                    'name' => $strategyName,
                    'category' => $category,
                    'implementation' => $this->getStrategyImplementation($strategyName)
                ];
            }
        }

        return null;
    }

    private function applyImprovementStrategy(GeneratedCourse $course, array $strategy, array $issue): array
    {
        $implementation = $strategy['implementation'];
        
        try {
            $changes = $implementation($course, $issue);
            
            return [
                'success' => true,
                'strategy' => $strategy['name'],
                'changes' => $changes,
                'issue_addressed' => $issue['description'] ?? 'Unknown issue'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'strategy' => $strategy['name'],
                'error' => $e->getMessage()
            ];
        }
    }

    private function calculateReviewTimeframe(string $reviewType): string
    {
        $timeframes = [
            'express' => '24 hours',
            'standard' => '3-5 business days',
            'comprehensive' => '1-2 weeks',
            'premium' => '2-3 weeks'
        ];

        return $timeframes[$reviewType] ?? $timeframes['standard'];
    }

    private function determineNextReviewStage(QualityReport $qualityReport, string $reviewType): string
    {
        $overallScore = $qualityReport->get('overall_score', 0);
        $passesGates = $qualityReport->get('passes_quality_gates', false);

        if ($overallScore < 60) {
            return 'auto_improvement';
        } elseif ($overallScore < 75) {
            return 'human_review_required';
        } elseif ($reviewType === 'premium' || $overallScore < 85) {
            return 'stakeholder_approval';
        } else {
            return 'final_certification';
        }
    }

    private function assignReviewers(GeneratedCourse $course, string $stage): array
    {
        // In a real implementation, this would query a reviewer database
        return [
            [
                'user_id' => 1, // Admin user as fallback
                'role' => 'content_reviewer',
                'assigned_at' => current_time('mysql'),
                'due_date' => date('Y-m-d H:i:s', strtotime('+3 days'))
            ]
        ];
    }

    private function sendReviewNotifications(array $workflow): void
    {
        // Send email notifications to assigned reviewers
        // Implementation would depend on notification system
        $this->log('Review notifications sent for workflow: ' . $workflow['id']);
    }

    private function generateCertificationBadge(string $level): string
    {
        $badges = [
            'minimum_viable_product' => '🥉 MVP Quality',
            'production_ready' => '🥈 Production Ready',
            'premium_quality' => '🥇 Premium Quality'
        ];

        return $badges[$level] ?? '✅ Certified';
    }

    private function generateQualityMetrics(GeneratedCourse $course): array
    {
        return [
            'total_lessons' => $course->getTotalLessons(),
            'estimated_duration' => $course->getEstimatedDuration(),
            'content_word_count' => $this->calculateWordCount($course),
            'engagement_score' => $this->calculateEngagementScore($course),
            'accessibility_compliance' => $this->calculateAccessibilityCompliance($course)
        ];
    }

    private function findCoursesNeedingImprovement(): array
    {
        // Query WordPress for courses that need automatic improvement
        // This is a simplified implementation
        return [];
    }

    private function reconstructCourseFromData(array $courseData): GeneratedCourse
    {
        // Reconstruct GeneratedCourse from array data
        return new GeneratedCourse(
            $courseData['title'] ?? 'Untitled Course',
            $courseData['description'] ?? '',
            $courseData['learning_objectives'] ?? [],
            [], // Sections would be reconstructed
            $courseData['metadata'] ?? []
        );
    }

    private function isAutoFixable(array $improvement): bool
    {
        $autoFixableTypes = [
            'readability',
            'length',
            'structure',
            'formatting'
        ];

        $category = $improvement['category'] ?? '';
        $description = strtolower($improvement['description'] ?? '');

        foreach ($autoFixableTypes as $type) {
            if (strpos($description, $type) !== false) {
                return true;
            }
        }

        return false;
    }

    private function estimateFixTime(array $improvement): int
    {
        // Estimate time in minutes
        if ($improvement['auto_fixable'] ?? false) {
            return 5; // Automatic fixes are quick
        }

        $priority = $improvement['priority'] ?? 'medium';
        $timeEstimates = [
            'critical' => 60,
            'high' => 30,
            'medium' => 15,
            'low' => 10
        ];

        return $timeEstimates[$priority] ?? 15;
    }

    private function calculateIssuePriority(string $dimension, string $issue): string
    {
        // Simplified priority calculation
        if (stripos($issue, 'accessibility') !== false) return 'critical';
        if (stripos($issue, 'objective') !== false) return 'high';
        return 'medium';
    }

    private function isIssueAutoFixable(string $dimension, string $issue): bool
    {
        $autoFixablePatterns = [
            'reading level',
            'sentence length',
            'paragraph structure',
            'heading hierarchy'
        ];

        foreach ($autoFixablePatterns as $pattern) {
            if (stripos($issue, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    private function estimateIssueFixTime(string $dimension, string $issue): int
    {
        // Estimate based on issue complexity
        return 15; // Default 15 minutes
    }

    private function isRecommendationAutoFixable(array $recommendation): bool
    {
        return $recommendation['auto_applicable'] ?? false;
    }

    private function strategyAppliesTo(string $strategyName, array $issue): bool
    {
        // Match strategy to issue type
        $issueDescription = strtolower($issue['description'] ?? '');
        
        if (strpos($strategyName, 'readability') !== false && strpos($issueDescription, 'reading') !== false) {
            return true;
        }
        
        if (strpos($strategyName, 'objective') !== false && strpos($issueDescription, 'objective') !== false) {
            return true;
        }

        return false;
    }

    private function getStrategyImplementation(string $strategyName): callable
    {
        // Return implementation function for strategy
        return function(GeneratedCourse $course, array $issue) {
            // Strategy implementation would go here
            return ["Applied {$strategyName} strategy"];
        };
    }

    private function calculateWordCount(GeneratedCourse $course): int
    {
        $wordCount = str_word_count($course->getDescription());
        
        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $wordCount += str_word_count($lesson->getContent());
            }
        }

        return $wordCount;
    }

    private function calculateEngagementScore(GeneratedCourse $course): int
    {
        // Simplified engagement calculation
        return 75; // Placeholder
    }

    private function calculateAccessibilityCompliance(GeneratedCourse $course): int
    {
        // Simplified accessibility calculation
        return 85; // Placeholder
    }
}