<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CourseTemplate;

/**
 * Template Engine Service
 * 
 * Provides sophisticated template selection logic, dynamic adaptation,
 * template mixing and hybridization, custom template creation,
 * and template performance analytics.
 */
class TemplateEngine
{
    private array $templateCache = [];
    private array $selectionHistory = [];
    private array $performanceMetrics = [];
    private ?LLMService $llmService;

    public function __construct(?LLMService $llmService = null)
    {
        $this->llmService = $llmService;
        $this->initializeTemplateCache();
    }

    /**
     * Initialize template cache with all available templates
     */
    private function initializeTemplateCache(): void
    {
        $this->templateCache = CourseTemplate::getPredefinedTemplates();
    }

    /**
     * Intelligent template selection based on course description and context
     */
    public function selectOptimalTemplate(
        string $courseDescription,
        array $userPreferences = [],
        array $context = []
    ): array {
        // Get AI-powered recommendations
        $aiRecommendations = $this->getAIRecommendations($courseDescription, $context);
        
        // Get keyword-based recommendations
        $keywordRecommendations = CourseTemplate::recommendTemplate($courseDescription);
        
        // Combine and weight recommendations
        $combinedRecommendations = $this->combineRecommendations(
            $aiRecommendations,
            $keywordRecommendations,
            $userPreferences
        );
        
        // Apply selection history and performance metrics
        $optimizedRecommendations = $this->applyHistoricalOptimization($combinedRecommendations, $context);
        
        // Log selection for analytics
        $this->logTemplateSelection($courseDescription, $optimizedRecommendations, $context);
        
        return [
            'primary_recommendation' => $optimizedRecommendations[0] ?? null,
            'alternative_recommendations' => array_slice($optimizedRecommendations, 1, 3),
            'mixing_suggestions' => $this->getMixingSuggestions($optimizedRecommendations),
            'confidence_score' => $this->calculateConfidenceScore($optimizedRecommendations),
            'reasoning' => $this->generateSelectionReasoning($optimizedRecommendations, $courseDescription)
        ];
    }

    /**
     * Get AI-powered template recommendations using LLM
     */
    private function getAIRecommendations(string $description, array $context): array
    {
        if (!$this->llmService) {
            return [];
        }

        $prompt = $this->buildAIRecommendationPrompt($description, $context);
        
        try {
            $aiResponse = $this->llmService->generateResponse($prompt);
            return $this->parseAIRecommendations($aiResponse);
        } catch (\Exception $e) {
            error_log("AI template recommendation failed: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build AI prompt for template recommendation
     */
    private function buildAIRecommendationPrompt(string $description, array $context): string
    {
        $templateTypes = CourseTemplate::getTemplateTypes();
        $templateDescriptions = array_map(function($type) {
            return $type['name'] . ': ' . $type['description'];
        }, $templateTypes);

        $prompt = "Analyze this course description and recommend the most suitable template types:\n\n";
        $prompt .= "Course Description: {$description}\n\n";
        $prompt .= "Available Templates:\n" . implode("\n", $templateDescriptions) . "\n\n";
        
        if (!empty($context['target_audience'])) {
            $prompt .= "Target Audience: {$context['target_audience']}\n";
        }
        
        if (!empty($context['learning_objectives'])) {
            $prompt .= "Learning Objectives: " . implode(', ', $context['learning_objectives']) . "\n";
        }

        $prompt .= "\nProvide recommendations in JSON format with template type, confidence score (0-1), and reasoning.";
        
        return $prompt;
    }

    /**
     * Parse AI recommendations from LLM response
     */
    private function parseAIRecommendations(string $response): array
    {
        try {
            $decoded = json_decode($response, true);
            
            if (!$decoded || !is_array($decoded)) {
                return [];
            }
            
            $recommendations = [];
            foreach ($decoded as $recommendation) {
                if (isset($recommendation['type'], $recommendation['confidence'])) {
                    $recommendations[] = [
                        'type' => $recommendation['type'],
                        'confidence' => (float) $recommendation['confidence'],
                        'reasoning' => $recommendation['reasoning'] ?? '',
                        'source' => 'ai'
                    ];
                }
            }
            
            return $recommendations;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Combine AI and keyword-based recommendations
     */
    private function combineRecommendations(
        array $aiRecommendations,
        array $keywordRecommendations,
        array $userPreferences
    ): array {
        $combined = [];
        
        // Add AI recommendations with higher weight
        foreach ($aiRecommendations as $rec) {
            $combined[$rec['type']] = [
                'type' => $rec['type'],
                'confidence' => $rec['confidence'] * 0.7, // AI weight
                'sources' => ['ai'],
                'reasoning' => [$rec['reasoning']]
            ];
        }
        
        // Add keyword recommendations
        foreach ($keywordRecommendations as $rec) {
            if (isset($combined[$rec['type']])) {
                $combined[$rec['type']]['confidence'] += $rec['confidence'] * 0.3; // Keyword weight
                $combined[$rec['type']]['sources'][] = 'keyword';
            } else {
                $combined[$rec['type']] = [
                    'type' => $rec['type'],
                    'confidence' => $rec['confidence'] * 0.3,
                    'sources' => ['keyword'],
                    'reasoning' => []
                ];
            }
        }
        
        // Apply user preferences
        foreach ($userPreferences as $preference) {
            if (isset($combined[$preference['type']])) {
                $combined[$preference['type']]['confidence'] += 0.2; // User preference boost
                $combined[$preference['type']]['sources'][] = 'user_preference';
            }
        }
        
        // Sort by confidence
        uasort($combined, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return array_values($combined);
    }

    /**
     * Apply historical optimization based on past performance
     */
    private function applyHistoricalOptimization(array $recommendations, array $context): array
    {
        foreach ($recommendations as &$rec) {
            $historicalPerformance = $this->getHistoricalPerformance($rec['type'], $context);
            
            // Adjust confidence based on historical success
            if ($historicalPerformance['success_rate'] > 0.8) {
                $rec['confidence'] *= 1.1; // Boost for high-performing templates
            } elseif ($historicalPerformance['success_rate'] < 0.6) {
                $rec['confidence'] *= 0.9; // Reduce for poor-performing templates
            }
            
            $rec['historical_performance'] = $historicalPerformance;
        }
        
        // Re-sort after adjustments
        usort($recommendations, function($a, $b) {
            return $b['confidence'] <=> $a['confidence'];
        });
        
        return $recommendations;
    }

    /**
     * Get historical performance metrics for template type
     */
    private function getHistoricalPerformance(string $templateType, array $context): array
    {
        // In a real implementation, this would query a database
        // For now, return simulated metrics
        return [
            'success_rate' => 0.75,
            'average_completion_rate' => 0.82,
            'user_satisfaction' => 4.2,
            'usage_count' => 150,
            'context_matches' => $this->countContextMatches($templateType, $context)
        ];
    }

    /**
     * Count how well the context matches this template type
     */
    private function countContextMatches(string $templateType, array $context): int
    {
        $matches = 0;
        
        // Check target audience alignment
        if (!empty($context['target_audience'])) {
            $template = $this->templateCache[$templateType] ?? null;
            if ($template) {
                // Simplified matching logic
                $matches += 1;
            }
        }
        
        return $matches;
    }

    /**
     * Get template mixing suggestions for hybrid approaches
     */
    private function getMixingSuggestions(array $recommendations): array
    {
        if (empty($recommendations)) {
            return [];
        }
        
        $primaryTemplate = $this->templateCache[$recommendations[0]['type']] ?? null;
        if (!$primaryTemplate) {
            return [];
        }
        
        $mixingRecommendations = $primaryTemplate->getMixingRecommendations();
        $suggestions = [];
        
        foreach ($mixingRecommendations as $templateType => $benefit) {
            $suggestions[] = [
                'secondary_template' => $templateType,
                'benefit' => $benefit,
                'compatibility_score' => $this->calculateCompatibilityScore(
                    $recommendations[0]['type'],
                    $templateType
                )
            ];
        }
        
        return $suggestions;
    }

    /**
     * Calculate compatibility score between two template types
     */
    private function calculateCompatibilityScore(string $primaryType, string $secondaryType): float
    {
        // Compatibility matrix - higher scores mean better compatibility
        $compatibilityMatrix = [
            CourseTemplate::TEMPLATE_TECHNICAL => [
                CourseTemplate::TEMPLATE_SKILL_BASED => 0.9,
                CourseTemplate::TEMPLATE_CERTIFICATION => 0.8,
                CourseTemplate::TEMPLATE_BUSINESS => 0.6,
                CourseTemplate::TEMPLATE_ACADEMIC => 0.7,
                CourseTemplate::TEMPLATE_CREATIVE => 0.5
            ],
            CourseTemplate::TEMPLATE_BUSINESS => [
                CourseTemplate::TEMPLATE_ACADEMIC => 0.8,
                CourseTemplate::TEMPLATE_SKILL_BASED => 0.7,
                CourseTemplate::TEMPLATE_CERTIFICATION => 0.6,
                CourseTemplate::TEMPLATE_TECHNICAL => 0.6,
                CourseTemplate::TEMPLATE_CREATIVE => 0.7
            ],
            CourseTemplate::TEMPLATE_CREATIVE => [
                CourseTemplate::TEMPLATE_SKILL_BASED => 0.8,
                CourseTemplate::TEMPLATE_BUSINESS => 0.7,
                CourseTemplate::TEMPLATE_TECHNICAL => 0.5,
                CourseTemplate::TEMPLATE_ACADEMIC => 0.6,
                CourseTemplate::TEMPLATE_CERTIFICATION => 0.4
            ],
            CourseTemplate::TEMPLATE_ACADEMIC => [
                CourseTemplate::TEMPLATE_BUSINESS => 0.8,
                CourseTemplate::TEMPLATE_SKILL_BASED => 0.6,
                CourseTemplate::TEMPLATE_TECHNICAL => 0.7,
                CourseTemplate::TEMPLATE_CREATIVE => 0.6,
                CourseTemplate::TEMPLATE_CERTIFICATION => 0.7
            ],
            CourseTemplate::TEMPLATE_SKILL_BASED => [
                CourseTemplate::TEMPLATE_CERTIFICATION => 0.9,
                CourseTemplate::TEMPLATE_TECHNICAL => 0.9,
                CourseTemplate::TEMPLATE_BUSINESS => 0.7,
                CourseTemplate::TEMPLATE_CREATIVE => 0.8,
                CourseTemplate::TEMPLATE_ACADEMIC => 0.6
            ],
            CourseTemplate::TEMPLATE_CERTIFICATION => [
                CourseTemplate::TEMPLATE_SKILL_BASED => 0.9,
                CourseTemplate::TEMPLATE_TECHNICAL => 0.8,
                CourseTemplate::TEMPLATE_BUSINESS => 0.6,
                CourseTemplate::TEMPLATE_ACADEMIC => 0.7,
                CourseTemplate::TEMPLATE_CREATIVE => 0.4
            ]
        ];
        
        return $compatibilityMatrix[$primaryType][$secondaryType] ?? 0.5;
    }

    /**
     * Calculate overall confidence score for recommendations
     */
    private function calculateConfidenceScore(array $recommendations): float
    {
        if (empty($recommendations)) {
            return 0.0;
        }
        
        $topConfidence = $recommendations[0]['confidence'] ?? 0;
        $sourceCount = count($recommendations[0]['sources'] ?? []);
        
        // Boost confidence if multiple sources agree
        $sourceBonus = min($sourceCount * 0.1, 0.3);
        
        return min($topConfidence + $sourceBonus, 1.0);
    }

    /**
     * Generate human-readable reasoning for template selection
     */
    private function generateSelectionReasoning(array $recommendations, string $description): string
    {
        if (empty($recommendations)) {
            return "No suitable template found for the given description.";
        }
        
        $primary = $recommendations[0];
        $reasoning = "Selected {$primary['type']} template ";
        
        if (isset($primary['confidence'])) {
            $confidencePercent = round($primary['confidence'] * 100);
            $reasoning .= "with {$confidencePercent}% confidence ";
        }
        
        $sources = $primary['sources'] ?? [];
        if (!empty($sources)) {
            $reasoning .= "based on " . implode(', ', $sources) . " analysis. ";
        }
        
        if (!empty($primary['reasoning'])) {
            $reasoning .= implode(' ', $primary['reasoning']);
        }
        
        return $reasoning;
    }

    /**
     * Create hybrid template by mixing multiple templates
     */
    public function createHybridTemplate(
        string $primaryTemplateType,
        array $secondaryTemplates,
        array $customizations = []
    ): CourseTemplate {
        $primaryTemplate = $this->templateCache[$primaryTemplateType] ?? null;
        
        if (!$primaryTemplate) {
            throw new \InvalidArgumentException("Primary template type '{$primaryTemplateType}' not found");
        }
        
        $hybridData = $primaryTemplate->toArray();
        
        // Merge secondary template features
        foreach ($secondaryTemplates as $secondaryType => $mixingRatio) {
            $secondaryTemplate = $this->templateCache[$secondaryType] ?? null;
            
            if ($secondaryTemplate) {
                $hybridData = $this->mergeTemplateFeatures(
                    $hybridData,
                    $secondaryTemplate->toArray(),
                    $mixingRatio
                );
            }
        }
        
        // Apply customizations
        if (!empty($customizations)) {
            $hybridData = array_merge_recursive($hybridData, $customizations);
        }
        
        // Create new template with hybrid data
        return new CourseTemplate(
            $primaryTemplateType . '_hybrid',
            $hybridData['default_structure'] ?? [],
            $hybridData['suggested_questions'] ?? [],
            $hybridData['quality_checks'] ?? [],
            $hybridData['ai_prompts'] ?? [],
            $hybridData['learning_progression'] ?? [],
            $hybridData['assessment_methods'] ?? [],
            $hybridData['prerequisites'] ?? [],
            $hybridData['customization_options'] ?? [],
            $hybridData['industry_terminology'] ?? [],
            $hybridData['resource_recommendations'] ?? [],
            $hybridData['optimal_lesson_length'] ?? 30,
            $hybridData['pacing_guidelines'] ?? []
        );
    }

    /**
     * Merge features from two templates based on mixing ratio
     */
    private function mergeTemplateFeatures(array $primary, array $secondary, float $ratio): array
    {
        $merged = $primary;
        
        // Merge sections with ratio-based selection
        if (isset($secondary['default_structure']['sections'])) {
            $secondarySections = $secondary['default_structure']['sections'];
            $sectionsToAdd = ceil(count($secondarySections) * $ratio);
            
            $selectedSections = array_slice($secondarySections, 0, $sectionsToAdd);
            $merged['default_structure']['sections'] = array_merge(
                $merged['default_structure']['sections'] ?? [],
                $selectedSections
            );
        }
        
        // Merge questions
        if (isset($secondary['suggested_questions'])) {
            $questionsToAdd = ceil(count($secondary['suggested_questions']) * $ratio);
            $selectedQuestions = array_slice($secondary['suggested_questions'], 0, $questionsToAdd);
            
            $merged['suggested_questions'] = array_merge(
                $merged['suggested_questions'] ?? [],
                $selectedQuestions
            );
        }
        
        // Merge assessment methods
        if (isset($secondary['assessment_methods'])) {
            $merged['assessment_methods'] = array_merge(
                $merged['assessment_methods'] ?? [],
                $secondary['assessment_methods']
            );
        }
        
        return $merged;
    }

    /**
     * Adapt template dynamically based on user feedback
     */
    public function adaptTemplate(
        CourseTemplate $template,
        array $userFeedback,
        array $performanceData = []
    ): CourseTemplate {
        $adaptations = [];
        
        // Analyze feedback for common patterns
        if (isset($userFeedback['difficulty_level'])) {
            $adaptations['learning_progression'] = $this->adjustDifficultyProgression(
                $template->getLearningProgression(),
                $userFeedback['difficulty_level']
            );
        }
        
        if (isset($userFeedback['preferred_lesson_length'])) {
            $adaptations['optimal_lesson_length'] = $userFeedback['preferred_lesson_length'];
        }
        
        if (isset($userFeedback['assessment_preferences'])) {
            $adaptations['assessment_methods'] = $this->adjustAssessmentMethods(
                $template->getAssessmentMethods(),
                $userFeedback['assessment_preferences']
            );
        }
        
        // Apply performance-based adaptations
        if (!empty($performanceData)) {
            $performanceAdaptations = $this->generatePerformanceAdaptations($performanceData);
            $adaptations = array_merge($adaptations, $performanceAdaptations);
        }
        
        return $template->customize($adaptations);
    }

    /**
     * Adjust difficulty progression based on feedback
     */
    private function adjustDifficultyProgression(array $progression, string $feedbackLevel): array
    {
        $adjusted = $progression;
        
        switch ($feedbackLevel) {
            case 'too_easy':
                // Increase complexity and reduce duration
                foreach ($adjusted as $level => &$data) {
                    if (isset($data['duration_weeks'])) {
                        $data['duration_weeks'] = max(2, $data['duration_weeks'] - 1);
                    }
                }
                break;
                
            case 'too_hard':
                // Reduce complexity and increase duration
                foreach ($adjusted as $level => &$data) {
                    if (isset($data['duration_weeks'])) {
                        $data['duration_weeks'] += 2;
                    }
                }
                break;
        }
        
        return $adjusted;
    }

    /**
     * Adjust assessment methods based on preferences
     */
    private function adjustAssessmentMethods(array $methods, array $preferences): array
    {
        $adjusted = $methods;
        
        // Add preferred assessment types
        foreach ($preferences['preferred'] ?? [] as $preferredType) {
            if (!in_array($preferredType, $adjusted)) {
                $adjusted[] = $preferredType;
            }
        }
        
        // Remove disliked assessment types
        foreach ($preferences['avoid'] ?? [] as $avoidType) {
            $adjusted = array_filter($adjusted, function($method) use ($avoidType) {
                return $method !== $avoidType;
            });
        }
        
        return array_values($adjusted);
    }

    /**
     * Generate adaptations based on performance data
     */
    private function generatePerformanceAdaptations(array $performanceData): array
    {
        $adaptations = [];
        
        // Adjust based on completion rates
        if (isset($performanceData['lesson_completion_rates'])) {
            $avgCompletion = array_sum($performanceData['lesson_completion_rates']) / 
                           count($performanceData['lesson_completion_rates']);
            
            if ($avgCompletion < 0.7) {
                // Low completion rate - reduce lesson length
                $adaptations['optimal_lesson_length'] = 20;
            }
        }
        
        // Adjust based on quiz scores
        if (isset($performanceData['average_quiz_score']) && $performanceData['average_quiz_score'] < 0.6) {
            // Poor quiz performance - add more practice assessments
            $adaptations['assessment_methods'] = [
                CourseTemplate::ASSESSMENT_QUIZ => 'Additional practice quizzes',
                CourseTemplate::ASSESSMENT_PRACTICAL => 'More hands-on exercises'
            ];
        }
        
        return $adaptations;
    }

    /**
     * Log template selection for analytics
     */
    private function logTemplateSelection(string $description, array $recommendations, array $context): void
    {
        $logEntry = [
            'timestamp' => current_time('timestamp'),
            'description' => $description,
            'recommendations' => $recommendations,
            'context' => $context,
            'session_id' => $context['session_id'] ?? 'unknown'
        ];
        
        $this->selectionHistory[] = $logEntry;
        
        // In a real implementation, this would be stored in a database
        // For now, we'll just keep it in memory for the session
    }

    /**
     * Get template performance analytics
     */
    public function getTemplateAnalytics(?string $templateType = null, int $days = 30): array
    {
        // In a real implementation, this would query actual usage data
        // For now, return simulated analytics
        
        $analytics = [
            'usage_statistics' => [
                'total_selections' => 250,
                'successful_completions' => 180,
                'average_satisfaction' => 4.2,
                'completion_rate' => 0.72
            ],
            'popular_combinations' => [
                [
                    'primary' => CourseTemplate::TEMPLATE_TECHNICAL,
                    'secondary' => CourseTemplate::TEMPLATE_SKILL_BASED,
                    'frequency' => 45
                ],
                [
                    'primary' => CourseTemplate::TEMPLATE_BUSINESS,
                    'secondary' => CourseTemplate::TEMPLATE_ACADEMIC,
                    'frequency' => 32
                ]
            ],
            'performance_trends' => [
                'improving_templates' => [CourseTemplate::TEMPLATE_CERTIFICATION],
                'declining_templates' => [],
                'stable_templates' => [
                    CourseTemplate::TEMPLATE_TECHNICAL,
                    CourseTemplate::TEMPLATE_BUSINESS,
                    CourseTemplate::TEMPLATE_CREATIVE
                ]
            ]
        ];
        
        if ($templateType) {
            $analytics['template_specific'] = $this->getTemplateSpecificAnalytics($templateType);
        }
        
        return $analytics;
    }

    /**
     * Get analytics for a specific template type
     */
    private function getTemplateSpecificAnalytics(string $templateType): array
    {
        return [
            'selection_frequency' => 85,
            'average_course_length' => 24,
            'common_customizations' => [
                'lesson_length_adjustments' => 0.6,
                'assessment_modifications' => 0.4,
                'structure_changes' => 0.3
            ],
            'user_feedback_summary' => [
                'average_rating' => 4.3,
                'common_praise' => ['well-structured', 'practical', 'comprehensive'],
                'common_complaints' => ['too long', 'needs more examples']
            ]
        ];
    }

    /**
     * Get template recommendations for course improvement
     */
    public function getImprovementRecommendations(
        CourseTemplate $template,
        array $performanceData,
        array $userFeedback = []
    ): array {
        $recommendations = [];
        
        // Analyze performance data
        if (isset($performanceData['completion_rate']) && $performanceData['completion_rate'] < 0.7) {
            $recommendations[] = [
                'type' => 'structure_adjustment',
                'suggestion' => 'Consider breaking down longer lessons into smaller segments',
                'impact' => 'high',
                'implementation' => 'Reduce optimal lesson length and increase lesson count'
            ];
        }
        
        if (isset($performanceData['engagement_metrics']['time_spent']) && 
            $performanceData['engagement_metrics']['time_spent'] < 15) {
            $recommendations[] = [
                'type' => 'content_enhancement',
                'suggestion' => 'Add more interactive elements and practical exercises',
                'impact' => 'medium',
                'implementation' => 'Increase hands-on practice ratio'
            ];
        }
        
        // Analyze user feedback
        foreach ($userFeedback as $feedback) {
            if (stripos($feedback, 'difficult') !== false || stripos($feedback, 'hard') !== false) {
                $recommendations[] = [
                    'type' => 'difficulty_adjustment',
                    'suggestion' => 'Add more foundational content and prerequisite guidance',
                    'impact' => 'high',
                    'implementation' => 'Enhance prerequisites and add beginner-friendly sections'
                ];
            }
        }
        
        return $recommendations;
    }

    /**
     * Export template configuration for sharing or backup
     */
    public function exportTemplate(CourseTemplate $template): array
    {
        return [
            'export_version' => '1.0',
            'export_timestamp' => current_time('Y-m-d H:i:s'),
            'template_data' => $template->toArray(),
            'metadata' => [
                'created_by' => get_current_user_id(),
                'export_reason' => 'template_sharing'
            ]
        ];
    }

    /**
     * Import template from exported configuration
     */
    public function importTemplate(array $exportData): CourseTemplate
    {
        if (!isset($exportData['template_data'])) {
            throw new \InvalidArgumentException('Invalid export data format');
        }
        
        $data = $exportData['template_data'];
        
        return new CourseTemplate(
            $data['template_type'] ?? 'imported',
            $data['default_structure'] ?? [],
            $data['suggested_questions'] ?? [],
            $data['quality_checks'] ?? [],
            $data['ai_prompts'] ?? [],
            $data['learning_progression'] ?? [],
            $data['assessment_methods'] ?? [],
            $data['prerequisites'] ?? [],
            $data['customization_options'] ?? [],
            $data['industry_terminology'] ?? [],
            $data['resource_recommendations'] ?? [],
            $data['optimal_lesson_length'] ?? 30,
            $data['pacing_guidelines'] ?? []
        );
    }
}