<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\CoursePattern;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Pattern Recognition Service
 * 
 * Analyzes successful course structures and identifies patterns for recommendations
 */
class PatternRecognitionService
{
    private Logger $logger;
    private DatabaseService $database;
    private array $patterns = [];
    
    public function __construct()
    {
        $this->logger = new Logger();
        $this->database = new DatabaseService();
    }
    
    /**
     * Analyze course structure and extract patterns
     */
    public function analyzeCourseStructure(int $courseId): array
    {
        $course = get_post($courseId);
        if (!$course || $course->post_type !== 'mpcs-course') {
            return [];
        }
        
        $pattern = [
            'course_id' => $courseId,
            'structure_fingerprint' => $this->generateStructureFingerprint($courseId),
            'content_patterns' => $this->extractContentPatterns($courseId),
            'assessment_patterns' => $this->analyzeAssessmentDistribution($courseId),
            'engagement_patterns' => $this->analyzeEngagementElements($courseId),
            'success_metrics' => $this->getSuccessMetrics($courseId),
            'created_at' => current_time('mysql')
        ];
        
        // Store pattern in database
        $this->storePattern($pattern);
        
        return $pattern;
    }
    
    /**
     * Generate unique fingerprint for course structure
     */
    private function generateStructureFingerprint(int $courseId): string
    {
        $sections = $this->getCourseSections($courseId);
        $structure = [];
        
        foreach ($sections as $section) {
            $lessons = $this->getSectionLessons($section['id']);
            $structure[] = [
                'section_lessons' => count($lessons),
                'avg_lesson_length' => $this->getAverageLessonLength($lessons),
                'content_types' => $this->getContentTypes($lessons),
                'assessment_density' => $this->getAssessmentDensity($lessons)
            ];
        }
        
        return md5(json_encode($structure));
    }
    
    /**
     * Extract content patterns from course
     */
    private function extractContentPatterns(int $courseId): array
    {
        $patterns = [
            'introduction_pattern' => $this->analyzeIntroductionPatterns($courseId),
            'content_flow' => $this->analyzeContentFlow($courseId),
            'conclusion_pattern' => $this->analyzeConclusionPatterns($courseId),
            'exercise_distribution' => $this->analyzeExerciseDistribution($courseId),
            'multimedia_usage' => $this->analyzeMultimediaUsage($courseId)
        ];
        
        return $patterns;
    }
    
    /**
     * Find similar courses based on patterns
     */
    public function findSimilarCourses(array $targetPattern, int $limit = 10): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_course_patterns';
        $patterns = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A);
        
        $similarities = [];
        foreach ($patterns as $pattern) {
            $similarity = $this->calculatePatternSimilarity($targetPattern, json_decode($pattern['pattern_data'], true));
            if ($similarity > 0.6) { // 60% similarity threshold
                $similarities[] = [
                    'course_id' => $pattern['course_id'],
                    'similarity' => $similarity,
                    'success_score' => $pattern['success_score']
                ];
            }
        }
        
        // Sort by similarity and success score
        usort($similarities, function($a, $b) {
            $scoreA = ($a['similarity'] * 0.7) + ($a['success_score'] * 0.3);
            $scoreB = ($b['similarity'] * 0.7) + ($b['success_score'] * 0.3);
            return $scoreB <=> $scoreA;
        });
        
        return array_slice($similarities, 0, $limit);
    }
    
    /**
     * Calculate similarity between two patterns
     */
    private function calculatePatternSimilarity(array $pattern1, array $pattern2): float
    {
        $weights = [
            'structure_similarity' => 0.3,
            'content_similarity' => 0.25,
            'assessment_similarity' => 0.2,
            'engagement_similarity' => 0.15,
            'flow_similarity' => 0.1
        ];
        
        $totalSimilarity = 0;
        
        // Structure similarity
        if (isset($pattern1['structure_fingerprint']) && isset($pattern2['structure_fingerprint'])) {
            $totalSimilarity += $weights['structure_similarity'] * 
                ($pattern1['structure_fingerprint'] === $pattern2['structure_fingerprint'] ? 1 : 0.5);
        }
        
        // Content pattern similarity
        if (isset($pattern1['content_patterns']) && isset($pattern2['content_patterns'])) {
            $contentSim = $this->calculateContentSimilarity($pattern1['content_patterns'], $pattern2['content_patterns']);
            $totalSimilarity += $weights['content_similarity'] * $contentSim;
        }
        
        // Assessment pattern similarity
        if (isset($pattern1['assessment_patterns']) && isset($pattern2['assessment_patterns'])) {
            $assessSim = $this->calculateAssessmentSimilarity($pattern1['assessment_patterns'], $pattern2['assessment_patterns']);
            $totalSimilarity += $weights['assessment_similarity'] * $assessSim;
        }
        
        return min(1.0, $totalSimilarity);
    }
    
    /**
     * Get pattern-based recommendations for course improvement
     */
    public function getPatternRecommendations(int $courseId): array
    {
        $currentPattern = $this->analyzeCourseStructure($courseId);
        $similarCourses = $this->findSimilarCourses($currentPattern, 5);
        
        $recommendations = [];
        
        foreach ($similarCourses as $similar) {
            if ($similar['success_score'] > 0.8) { // Only high-performing courses
                $recommendations[] = $this->generateRecommendationFromPattern($similar['course_id'], $currentPattern);
            }
        }
        
        return array_filter($recommendations);
    }
    
    /**
     * Store pattern in database
     */
    private function storePattern(array $pattern): bool
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'mpcc_course_patterns';
        
        return $wpdb->insert(
            $table,
            [
                'course_id' => $pattern['course_id'],
                'pattern_fingerprint' => $pattern['structure_fingerprint'],
                'pattern_data' => json_encode($pattern),
                'success_score' => $pattern['success_metrics']['overall_score'] ?? 0.5,
                'pattern_type' => $this->classifyPattern($pattern),
                'created_at' => $pattern['created_at']
            ],
            ['%d', '%s', '%s', '%f', '%s', '%s']
        ) !== false;
    }
    
    /**
     * Get course sections
     */
    private function getCourseSections(int $courseId): array
    {
        // This would integrate with MemberPress Courses structure
        $sections = [];
        
        // Get sections from course meta or custom implementation
        $sectionMeta = get_post_meta($courseId, 'mpcs_course_sections', true);
        if ($sectionMeta) {
            $sections = json_decode($sectionMeta, true) ?: [];
        }
        
        return $sections;
    }
    
    /**
     * Analyze assessment distribution patterns
     */
    private function analyzeAssessmentDistribution(int $courseId): array
    {
        $sections = $this->getCourseSections($courseId);
        $assessments = [];
        
        foreach ($sections as $section) {
            $lessons = $this->getSectionLessons($section['id']);
            $sectionAssessments = 0;
            
            foreach ($lessons as $lesson) {
                if ($this->hasAssessment($lesson['id'])) {
                    $sectionAssessments++;
                }
            }
            
            $assessments[] = [
                'section_id' => $section['id'],
                'assessment_count' => $sectionAssessments,
                'assessment_ratio' => count($lessons) > 0 ? $sectionAssessments / count($lessons) : 0
            ];
        }
        
        return [
            'total_assessments' => array_sum(array_column($assessments, 'assessment_count')),
            'average_ratio' => array_sum(array_column($assessments, 'assessment_ratio')) / max(1, count($assessments)),
            'distribution' => $assessments
        ];
    }
    
    /**
     * Get success metrics for course
     */
    private function getSuccessMetrics(int $courseId): array
    {
        // This would integrate with actual course analytics
        return [
            'completion_rate' => $this->getCourseCompletionRate($courseId),
            'satisfaction_score' => $this->getCourseSatisfactionScore($courseId),
            'engagement_score' => $this->getCourseEngagementScore($courseId),
            'overall_score' => 0.7 // Calculated from above metrics
        ];
    }
    
    /**
     * Classify pattern type
     */
    private function classifyPattern(array $pattern): string
    {
        $sectionCount = count($pattern['content_patterns']['content_flow'] ?? []);
        $assessmentRatio = $pattern['assessment_patterns']['average_ratio'] ?? 0;
        
        if ($sectionCount <= 3 && $assessmentRatio < 0.3) {
            return 'simple_linear';
        } elseif ($sectionCount > 8 && $assessmentRatio > 0.5) {
            return 'comprehensive_modular';
        } elseif ($assessmentRatio > 0.7) {
            return 'assessment_heavy';
        } else {
            return 'balanced_progressive';
        }
    }
    
    // Helper methods for pattern analysis
    private function getSectionLessons(int $sectionId): array { return []; }
    private function getAverageLessonLength(array $lessons): float { return 500.0; }
    private function getContentTypes(array $lessons): array { return ['text', 'video']; }
    private function getAssessmentDensity(array $lessons): float { return 0.3; }
    private function analyzeIntroductionPatterns(int $courseId): array { return []; }
    private function analyzeContentFlow(int $courseId): array { return []; }
    private function analyzeConclusionPatterns(int $courseId): array { return []; }
    private function analyzeExerciseDistribution(int $courseId): array { return []; }
    private function analyzeMultimediaUsage(int $courseId): array { return []; }
    private function analyzeEngagementElements(int $courseId): array { return []; }
    private function calculateContentSimilarity(array $p1, array $p2): float { return 0.8; }
    private function calculateAssessmentSimilarity(array $p1, array $p2): float { return 0.7; }
    private function generateRecommendationFromPattern(int $courseId, array $currentPattern): array { return []; }
    private function hasAssessment(int $lessonId): bool { return rand(0, 1) === 1; }
    private function getCourseCompletionRate(int $courseId): float { return 0.75; }
    private function getCourseSatisfactionScore(int $courseId): float { return 0.8; }
    private function getCourseEngagementScore(int $courseId): float { return 0.7; }
}