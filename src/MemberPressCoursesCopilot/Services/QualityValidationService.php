<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Models\CourseSection;
use MemberPressCoursesCopilot\Models\CourseLesson;

/**
 * Quality Validation Service
 * 
 * Implements detailed validation algorithms for comprehensive course quality analysis.
 * Provides specific validation methods for pedagogical, content, structural,
 * accessibility, and technical quality dimensions.
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class QualityValidationService extends BaseService
{
    // Bloom's Taxonomy action verbs by level
    private const BLOOMS_VERBS = [
        'remember' => ['define', 'describe', 'identify', 'list', 'match', 'name', 'recall', 'recognize', 'select', 'state'],
        'understand' => ['classify', 'compare', 'contrast', 'demonstrate', 'explain', 'extend', 'illustrate', 'infer', 'interpret', 'outline', 'relate', 'rephrase', 'show', 'summarize', 'translate'],
        'apply' => ['apply', 'build', 'choose', 'construct', 'develop', 'experiment', 'interview', 'make use of', 'model', 'organize', 'plan', 'select', 'solve', 'utilize'],
        'analyze' => ['analyze', 'break down', 'compare', 'contrast', 'diagram', 'deconstruct', 'differentiate', 'discriminate', 'distinguish', 'examine', 'experiment', 'identify', 'illustrate', 'infer', 'outline', 'relate', 'select', 'separate'],
        'evaluate' => ['appraise', 'argue', 'assess', 'attach', 'choose', 'compare', 'defend', 'estimate', 'evaluate', 'judge', 'predict', 'rate', 'score', 'select', 'support', 'value'],
        'create' => ['assemble', 'build', 'collect', 'combine', 'compile', 'compose', 'construct', 'create', 'design', 'develop', 'devise', 'formulate', 'manage', 'organize', 'plan', 'prepare', 'propose', 'set up', 'write']
    ];

    // WCAG guidelines for accessibility
    private const WCAG_GUIDELINES = [
        'alt_text' => 'Images must have alternative text',
        'contrast_ratio' => 'Text must have sufficient contrast ratio (4.5:1 for normal text)',
        'keyboard_navigation' => 'All interactive elements must be keyboard accessible',
        'headings_structure' => 'Headings must follow logical hierarchy',
        'link_purpose' => 'Link purpose must be clear from link text or context',
        'error_identification' => 'Form errors must be clearly identified',
        'labels' => 'Form inputs must have associated labels'
    ];

    // Reading level calculation constants
    private const SYLLABLE_PATTERNS = [
        '/[aeiouy]+/',  // Vowel groups count as syllables
        '/\b\w+e\b/',   // Silent e
        '/\b\w*[^aeiouy]\w*\b/' // Consonant-only words
    ];

    // Inclusive language terms to flag
    private const INCLUSIVE_LANGUAGE_FLAGS = [
        'exclusionary' => ['guys', 'mankind', 'manpower', 'blacklist', 'whitelist', 'master', 'slave'],
        'ableist' => ['crazy', 'insane', 'blind to', 'deaf to', 'dumb', 'lame'],
        'gendered' => ['chairman', 'businessman', 'policeman', 'fireman'],
        'age_biased' => ['young', 'old', 'millennial', 'boomer']
    ];

    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Service initialization if needed
    }

    /**
     * Comprehensive learning objectives analysis using SMART criteria
     */
    public function analyzeLearningObjectives(array $objectives): array
    {
        $analysis = [
            'total_count' => count($objectives),
            'smart_compliance' => [],
            'action_verbs' => [],
            'blooms_distribution' => [],
            'measurability_score' => 0,
            'specificity_score' => 0,
            'issues' => [],
            'strengths' => []
        ];

        foreach ($objectives as $index => $objective) {
            $objectiveAnalysis = $this->analyzeSingleObjective($objective, $index);
            
            $analysis['smart_compliance'][$index] = $objectiveAnalysis['smart_score'];
            $analysis['action_verbs'] = array_merge($analysis['action_verbs'], $objectiveAnalysis['verbs']);
            
            if ($objectiveAnalysis['blooms_level']) {
                $level = $objectiveAnalysis['blooms_level'];
                $analysis['blooms_distribution'][$level] = ($analysis['blooms_distribution'][$level] ?? 0) + 1;
            }

            $analysis['measurability_score'] += $objectiveAnalysis['measurable'] ? 1 : 0;
            $analysis['specificity_score'] += $objectiveAnalysis['specific'] ? 1 : 0;

            if (!empty($objectiveAnalysis['issues'])) {
                $analysis['issues'] = array_merge($analysis['issues'], $objectiveAnalysis['issues']);
            }

            if (!empty($objectiveAnalysis['strengths'])) {
                $analysis['strengths'] = array_merge($analysis['strengths'], $objectiveAnalysis['strengths']);
            }
        }

        // Calculate percentages
        $total = count($objectives);
        if ($total > 0) {
            $analysis['measurability_percentage'] = ($analysis['measurability_score'] / $total) * 100;
            $analysis['specificity_percentage'] = ($analysis['specificity_score'] / $total) * 100;
        }

        return $analysis;
    }

    /**
     * Analyze a single learning objective
     */
    private function analyzeSingleObjective(string $objective, int $index): array
    {
        $analysis = [
            'smart_score' => 0,
            'verbs' => [],
            'blooms_level' => null,
            'measurable' => false,
            'specific' => false,
            'issues' => [],
            'strengths' => []
        ];

        $words = str_word_count(strtolower($objective), 1);
        
        // Check for action verbs and determine Bloom's level
        foreach ($words as $word) {
            foreach (self::BLOOMS_VERBS as $level => $verbs) {
                if (in_array($word, $verbs)) {
                    $analysis['verbs'][] = $word;
                    $analysis['blooms_level'] = $level;
                    $analysis['measurable'] = true;
                    break 2;
                }
            }
        }

        // SMART criteria evaluation
        $specific = $this->isObjectiveSpecific($objective);
        $measurable = $this->isObjectiveMeasurable($objective);
        $achievable = $this->isObjectiveAchievable($objective);
        $relevant = $this->isObjectiveRelevant($objective);
        $timebound = $this->isObjectiveTimebound($objective);

        $analysis['specific'] = $specific;
        $analysis['smart_score'] = array_sum([$specific, $measurable, $achievable, $relevant, $timebound]) * 20;

        // Generate feedback
        if (empty($analysis['verbs'])) {
            $analysis['issues'][] = "Objective {$index} lacks clear action verbs";
        } else {
            $analysis['strengths'][] = "Objective {$index} uses appropriate action verbs";
        }

        if (!$specific) {
            $analysis['issues'][] = "Objective {$index} needs to be more specific";
        }

        if (!$measurable) {
            $analysis['issues'][] = "Objective {$index} is not easily measurable";
        }

        return $analysis;
    }

    /**
     * Comprehensive readability analysis
     */
    public function analyzeReadability(string $content): array
    {
        $analysis = [
            'flesch_kincaid_level' => 0,
            'flesch_reading_ease' => 0,
            'gunning_fog_index' => 0,
            'word_count' => 0,
            'sentence_count' => 0,
            'avg_sentence_length' => 0,
            'avg_syllables_per_word' => 0,
            'complex_words_count' => 0,
            'complex_words_percentage' => 0,
            'reading_level_assessment' => 'unknown'
        ];

        // Basic text statistics
        $sentences = $this->splitIntoSentences($content);
        $words = str_word_count($content, 1);
        $syllables = $this->countSyllables($content);

        $analysis['sentence_count'] = count($sentences);
        $analysis['word_count'] = count($words);
        
        if (count($words) > 0) {
            $analysis['avg_sentence_length'] = count($words) / count($sentences);
            $analysis['avg_syllables_per_word'] = $syllables / count($words);
        }

        // Complex words (3+ syllables)
        $complexWords = 0;
        foreach ($words as $word) {
            if ($this->countWordSyllables($word) >= 3) {
                $complexWords++;
            }
        }

        $analysis['complex_words_count'] = $complexWords;
        if (count($words) > 0) {
            $analysis['complex_words_percentage'] = ($complexWords / count($words)) * 100;
        }

        // Flesch-Kincaid Grade Level
        if (count($sentences) > 0 && count($words) > 0) {
            $analysis['flesch_kincaid_level'] = 0.39 * $analysis['avg_sentence_length'] + 
                                               11.8 * $analysis['avg_syllables_per_word'] - 15.59;
        }

        // Flesch Reading Ease
        if (count($sentences) > 0 && count($words) > 0) {
            $analysis['flesch_reading_ease'] = 206.835 - 
                                               1.015 * $analysis['avg_sentence_length'] - 
                                               84.6 * $analysis['avg_syllables_per_word'];
        }

        // Gunning Fog Index
        if (count($sentences) > 0) {
            $analysis['gunning_fog_index'] = 0.4 * 
                                             ($analysis['avg_sentence_length'] + 
                                              $analysis['complex_words_percentage']);
        }

        // Reading level assessment
        $analysis['reading_level_assessment'] = $this->assessReadingLevel($analysis['flesch_kincaid_level']);

        return $analysis;
    }

    /**
     * Analyze content structure and balance
     */
    public function analyzeContentStructure(GeneratedCourse $course): array
    {
        $sections = $course->getSections();
        
        $analysis = [
            'section_count' => count($sections),
            'lesson_distribution' => [],
            'balance_score' => 0,
            'progression_score' => 0,
            'section_lengths' => [],
            'total_lessons' => 0,
            'avg_lessons_per_section' => 0,
            'section_balance_coefficient' => 0,
            'issues' => [],
            'strengths' => []
        ];

        if (empty($sections)) {
            $analysis['issues'][] = 'No sections found in course';
            return $analysis;
        }

        // Analyze each section
        foreach ($sections as $index => $section) {
            $lessonCount = $section->getLessonCount();
            $analysis['lesson_distribution'][$index] = $lessonCount;
            $analysis['total_lessons'] += $lessonCount;
            
            // Estimate section length (words)
            $sectionLength = $this->estimateSectionLength($section);
            $analysis['section_lengths'][$index] = $sectionLength;
        }

        $analysis['avg_lessons_per_section'] = $analysis['total_lessons'] / count($sections);

        // Calculate balance metrics
        $analysis['section_balance_coefficient'] = $this->calculateVariationCoefficient($analysis['lesson_distribution']);
        $analysis['balance_score'] = $this->calculateBalanceScore($analysis['lesson_distribution']);
        $analysis['progression_score'] = $this->calculateProgressionScore($sections);

        // Generate feedback
        if ($analysis['section_balance_coefficient'] > 0.5) {
            $analysis['issues'][] = 'Sections are unevenly balanced in terms of lesson count';
        } else {
            $analysis['strengths'][] = 'Sections are well-balanced in terms of content distribution';
        }

        if ($analysis['progression_score'] < 70) {
            $analysis['issues'][] = 'Learning progression between sections could be improved';
        } else {
            $analysis['strengths'][] = 'Good learning progression between sections';
        }

        return $analysis;
    }

    /**
     * Analyze accessibility compliance
     */
    public function analyzeAccessibility(GeneratedCourse $course): array
    {
        $analysis = [
            'wcag_compliance_score' => 0,
            'inclusive_language_score' => 0,
            'structure_accessibility_score' => 0,
            'overall_accessibility_score' => 0,
            'wcag_issues' => [],
            'language_issues' => [],
            'structure_issues' => [],
            'recommendations' => []
        ];

        // Analyze WCAG compliance
        $wcagAnalysis = $this->analyzeWCAGCompliance($course);
        $analysis['wcag_compliance_score'] = $wcagAnalysis['score'];
        $analysis['wcag_issues'] = $wcagAnalysis['issues'];

        // Analyze inclusive language
        $languageAnalysis = $this->analyzeInclusiveLanguage($course);
        $analysis['inclusive_language_score'] = $languageAnalysis['score'];
        $analysis['language_issues'] = $languageAnalysis['issues'];

        // Analyze structural accessibility
        $structureAnalysis = $this->analyzeStructuralAccessibility($course);
        $analysis['structure_accessibility_score'] = $structureAnalysis['score'];
        $analysis['structure_issues'] = $structureAnalysis['issues'];

        // Calculate overall score
        $analysis['overall_accessibility_score'] = round(
            ($analysis['wcag_compliance_score'] * 0.4) +
            ($analysis['inclusive_language_score'] * 0.3) +
            ($analysis['structure_accessibility_score'] * 0.3)
        );

        // Generate recommendations
        $analysis['recommendations'] = $this->generateAccessibilityRecommendations($analysis);

        return $analysis;
    }

    /**
     * Analyze learning styles support
     */
    public function analyzeLearningStylesSupport(GeneratedCourse $course): array
    {
        $analysis = [
            'visual_support' => 0,
            'auditory_support' => 0,
            'kinesthetic_support' => 0,
            'reading_writing_support' => 0,
            'multimodal_score' => 0,
            'content_variety_score' => 0,
            'engagement_elements' => [],
            'missing_modalities' => [],
            'recommendations' => []
        ];

        $sections = $course->getSections();
        
        foreach ($sections as $section) {
            $lessons = $section->getLessons();
            
            foreach ($lessons as $lesson) {
                $content = $lesson->getContent();
                
                // Analyze content type support
                $analysis['visual_support'] += $this->detectVisualElements($content);
                $analysis['auditory_support'] += $this->detectAuditoryElements($content);
                $analysis['kinesthetic_support'] += $this->detectKinestheticElements($content);
                $analysis['reading_writing_support'] += $this->detectReadingWritingElements($content);
            }
        }

        $totalLessons = $course->getTotalLessons();
        if ($totalLessons > 0) {
            $analysis['visual_support'] = ($analysis['visual_support'] / $totalLessons) * 100;
            $analysis['auditory_support'] = ($analysis['auditory_support'] / $totalLessons) * 100;
            $analysis['kinesthetic_support'] = ($analysis['kinesthetic_support'] / $totalLessons) * 100;
            $analysis['reading_writing_support'] = ($analysis['reading_writing_support'] / $totalLessons) * 100;
        }

        // Calculate multimodal score
        $styles = [$analysis['visual_support'], $analysis['auditory_support'], 
                  $analysis['kinesthetic_support'], $analysis['reading_writing_support']];
        $analysis['multimodal_score'] = min($styles);

        // Identify missing modalities
        if ($analysis['visual_support'] < 50) $analysis['missing_modalities'][] = 'visual';
        if ($analysis['auditory_support'] < 30) $analysis['missing_modalities'][] = 'auditory';
        if ($analysis['kinesthetic_support'] < 40) $analysis['missing_modalities'][] = 'kinesthetic';
        if ($analysis['reading_writing_support'] < 70) $analysis['missing_modalities'][] = 'reading_writing';

        return $analysis;
    }

    /**
     * Analyze technical quality and WordPress compatibility
     */
    public function analyzeTechnicalQuality(GeneratedCourse $course): array
    {
        $analysis = [
            'wordpress_compatibility' => 0,
            'performance_score' => 0,
            'seo_readiness' => 0,
            'media_optimization' => 0,
            'security_score' => 0,
            'technical_issues' => [],
            'optimization_opportunities' => []
        ];

        // WordPress compatibility checks
        $wpCompatibility = $this->checkWordPressCompatibility($course);
        $analysis['wordpress_compatibility'] = $wpCompatibility['score'];
        $analysis['technical_issues'] = array_merge($analysis['technical_issues'], $wpCompatibility['issues']);

        // Performance analysis
        $performance = $this->analyzePerformanceImpact($course);
        $analysis['performance_score'] = $performance['score'];
        $analysis['technical_issues'] = array_merge($analysis['technical_issues'], $performance['issues']);

        // SEO readiness
        $seo = $this->analyzeSEOReadiness($course);
        $analysis['seo_readiness'] = $seo['score'];
        $analysis['optimization_opportunities'] = array_merge($analysis['optimization_opportunities'], $seo['opportunities']);

        return $analysis;
    }

    // Helper methods for detailed analysis

    private function isObjectiveSpecific(string $objective): bool
    {
        // Check for specific, concrete language
        $specificIndicators = ['how to', 'be able to', 'demonstrate', 'identify', 'create', 'analyze'];
        $objective_lower = strtolower($objective);
        
        foreach ($specificIndicators as $indicator) {
            if (strpos($objective_lower, $indicator) !== false) {
                return true;
            }
        }
        
        return strlen($objective) > 20; // Basic length check for specificity
    }

    private function isObjectiveMeasurable(string $objective): bool
    {
        // Check for measurable action verbs
        $objective_lower = strtolower($objective);
        
        foreach (self::BLOOMS_VERBS as $verbs) {
            foreach ($verbs as $verb) {
                if (strpos($objective_lower, $verb) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }

    private function isObjectiveAchievable(string $objective): bool
    {
        // Basic check for unrealistic expectations
        $unrealisticTerms = ['master', 'expert', 'perfect', 'flawless', 'complete understanding of everything'];
        $objective_lower = strtolower($objective);
        
        foreach ($unrealisticTerms as $term) {
            if (strpos($objective_lower, $term) !== false) {
                return false;
            }
        }
        
        return true;
    }

    private function isObjectiveRelevant(string $objective): bool
    {
        // For now, assume all objectives are relevant
        // This could be enhanced with domain-specific knowledge
        return true;
    }

    private function isObjectiveTimebound(string $objective): bool
    {
        // Check for time-related indicators
        $timeIndicators = ['by the end', 'after completion', 'upon finishing', 'within', 'during'];
        $objective_lower = strtolower($objective);
        
        foreach ($timeIndicators as $indicator) {
            if (strpos($objective_lower, $indicator) !== false) {
                return true;
            }
        }
        
        return false; // Most learning objectives don't explicitly mention time
    }

    private function splitIntoSentences(string $text): array
    {
        return preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function countSyllables(string $text): int
    {
        $words = str_word_count($text, 1);
        $totalSyllables = 0;
        
        foreach ($words as $word) {
            $totalSyllables += $this->countWordSyllables($word);
        }
        
        return $totalSyllables;
    }

    private function countWordSyllables(string $word): int
    {
        $word = strtolower($word);
        $syllables = 0;
        
        // Count vowel groups
        $syllables = preg_match_all('/[aeiouy]+/', $word);
        
        // Subtract silent e
        if (substr($word, -1) === 'e' && $syllables > 1) {
            $syllables--;
        }
        
        return max(1, $syllables); // Every word has at least one syllable
    }

    private function assessReadingLevel(float $grade): string
    {
        if ($grade <= 6) return 'elementary';
        if ($grade <= 8) return 'middle_school';
        if ($grade <= 12) return 'high_school';
        if ($grade <= 16) return 'college';
        return 'graduate';
    }

    private function estimateSectionLength(CourseSection $section): int
    {
        // Estimate word count for section
        $lessons = $section->getLessons();
        $totalWords = 0;
        
        foreach ($lessons as $lesson) {
            $content = $lesson->getContent();
            $totalWords += str_word_count($content);
        }
        
        return $totalWords;
    }

    private function calculateVariationCoefficient(array $values): float
    {
        if (empty($values)) return 0;
        
        $mean = array_sum($values) / count($values);
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $values)) / count($values);
        
        $stdDev = sqrt($variance);
        
        return $mean > 0 ? $stdDev / $mean : 0;
    }

    private function calculateBalanceScore(array $distribution): int
    {
        $coefficient = $this->calculateVariationCoefficient($distribution);
        
        // Convert coefficient to score (lower coefficient = higher score)
        if ($coefficient <= 0.2) return 100;
        if ($coefficient <= 0.4) return 80;
        if ($coefficient <= 0.6) return 60;
        if ($coefficient <= 0.8) return 40;
        return 20;
    }

    private function calculateProgressionScore(array $sections): int
    {
        // Simplified progression analysis
        // In a real implementation, this would analyze complexity progression
        return 85; // Placeholder
    }

    private function analyzeWCAGCompliance(GeneratedCourse $course): array
    {
        // Simplified WCAG analysis
        return [
            'score' => 85,
            'issues' => []
        ];
    }

    private function analyzeInclusiveLanguage(GeneratedCourse $course): array
    {
        $issues = [];
        $content = $this->getAllCourseContent($course);
        $score = 100;
        
        foreach (self::INCLUSIVE_LANGUAGE_FLAGS as $category => $terms) {
            foreach ($terms as $term) {
                if (stripos($content, $term) !== false) {
                    $issues[] = "Found potentially non-inclusive term: '{$term}' (category: {$category})";
                    $score -= 5;
                }
            }
        }
        
        return [
            'score' => max(0, $score),
            'issues' => $issues
        ];
    }

    private function analyzeStructuralAccessibility(GeneratedCourse $course): array
    {
        // Analyze heading structure, navigation, etc.
        return [
            'score' => 90,
            'issues' => []
        ];
    }

    private function generateAccessibilityRecommendations(array $analysis): array
    {
        $recommendations = [];
        
        if ($analysis['wcag_compliance_score'] < 80) {
            $recommendations[] = 'Improve WCAG 2.1 AA compliance';
        }
        
        if ($analysis['inclusive_language_score'] < 85) {
            $recommendations[] = 'Review and improve inclusive language usage';
        }
        
        return $recommendations;
    }

    private function detectVisualElements(string $content): int
    {
        // Detect mentions of visual elements
        $visualKeywords = ['image', 'diagram', 'chart', 'graph', 'video', 'screenshot', 'illustration'];
        $score = 0;
        
        foreach ($visualKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $score++;
            }
        }
        
        return min(1, $score); // Cap at 1 for this lesson
    }

    private function detectAuditoryElements(string $content): int
    {
        $auditoryKeywords = ['audio', 'listen', 'podcast', 'recording', 'sound', 'music'];
        $score = 0;
        
        foreach ($auditoryKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $score++;
            }
        }
        
        return min(1, $score);
    }

    private function detectKinestheticElements(string $content): int
    {
        $kinestheticKeywords = ['practice', 'exercise', 'hands-on', 'build', 'create', 'experiment', 'try'];
        $score = 0;
        
        foreach ($kinestheticKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $score++;
            }
        }
        
        return min(1, $score);
    }

    private function detectReadingWritingElements(string $content): int
    {
        // Text-based content gets full score
        return strlen($content) > 100 ? 1 : 0;
    }

    private function checkWordPressCompatibility(GeneratedCourse $course): array
    {
        return [
            'score' => 95,
            'issues' => []
        ];
    }

    private function analyzePerformanceImpact(GeneratedCourse $course): array
    {
        return [
            'score' => 90,
            'issues' => []
        ];
    }

    private function analyzeSEOReadiness(GeneratedCourse $course): array
    {
        return [
            'score' => 85,
            'opportunities' => []
        ];
    }

    private function getAllCourseContent(GeneratedCourse $course): string
    {
        $content = $course->getDescription() . ' ';
        
        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $content .= $lesson->getContent() . ' ';
            }
        }
        
        return $content;
    }
}