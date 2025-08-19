<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Models;

/**
 * Course Pattern Model
 * 
 * Represents identified patterns in course structure and content
 * for machine learning and recommendation purposes
 * 
 * @package MemberPressCoursesCopilot\Models
 * @since 1.0.0
 */
class CoursePattern extends BaseModel
{
    /**
     * Pattern type constants
     */
    public const TYPE_STRUCTURAL = 'structural';
    public const TYPE_CONTENT = 'content';
    public const TYPE_PROGRESSION = 'progression';
    public const TYPE_ENGAGEMENT = 'engagement';
    public const TYPE_ASSESSMENT = 'assessment';

    /**
     * Success level constants
     */
    public const SUCCESS_HIGH = 'high';
    public const SUCCESS_MEDIUM = 'medium';
    public const SUCCESS_LOW = 'low';
    public const SUCCESS_UNKNOWN = 'unknown';

    /**
     * Pattern category constants
     */
    public const CATEGORY_INTRO_STRUCTURE = 'intro_structure';
    public const CATEGORY_SECTION_FLOW = 'section_flow';
    public const CATEGORY_LESSON_PACING = 'lesson_pacing';
    public const CATEGORY_CONTENT_MIX = 'content_mix';
    public const CATEGORY_ASSESSMENT_PLACEMENT = 'assessment_placement';
    public const CATEGORY_CONCLUSION_STYLE = 'conclusion_style';

    /**
     * Default pattern data structure
     *
     * @var array<string, mixed>
     */
    protected array $data = [
        'pattern_id' => null,
        'pattern_type' => self::TYPE_STRUCTURAL,
        'category' => self::CATEGORY_SECTION_FLOW,
        'name' => '',
        'description' => '',
        'fingerprint' => '',
        'features' => [],
        'metadata' => [],
        'success_metrics' => [
            'completion_rate' => 0.0,
            'engagement_score' => 0.0,
            'satisfaction_rating' => 0.0,
            'success_level' => self::SUCCESS_UNKNOWN
        ],
        'usage_statistics' => [
            'times_used' => 0,
            'courses_with_pattern' => [],
            'last_used' => null,
            'first_identified' => null
        ],
        'similarity_threshold' => 0.8,
        'embeddings' => [],
        'version' => '1.0',
        'created_at' => null,
        'updated_at' => null,
        'created_by' => null
    ];

    /**
     * Pattern similarity scoring
     *
     * @param CoursePattern $otherPattern
     * @return float Similarity score between 0.0 and 1.0
     */
    public function calculateSimilarity(CoursePattern $otherPattern): float
    {
        $thisFeatures = $this->get('features', []);
        $otherFeatures = $otherPattern->get('features', []);

        if (empty($thisFeatures) || empty($otherFeatures)) {
            return 0.0;
        }

        // Calculate feature similarity
        $featureSimilarity = $this->calculateFeatureSimilarity($thisFeatures, $otherFeatures);
        
        // Calculate embedding similarity if available
        $embeddingSimilarity = $this->calculateEmbeddingSimilarity($otherPattern);
        
        // Weight the similarities
        $finalSimilarity = ($featureSimilarity * 0.6) + ($embeddingSimilarity * 0.4);
        
        return round($finalSimilarity, 4);
    }

    /**
     * Calculate feature-based similarity
     *
     * @param array<string, mixed> $features1
     * @param array<string, mixed> $features2
     * @return float
     */
    private function calculateFeatureSimilarity(array $features1, array $features2): float
    {
        $allKeys = array_unique(array_merge(array_keys($features1), array_keys($features2)));
        $matches = 0;
        $total = count($allKeys);

        foreach ($allKeys as $key) {
            $value1 = $features1[$key] ?? null;
            $value2 = $features2[$key] ?? null;

            if ($value1 === $value2) {
                $matches++;
            } elseif (is_numeric($value1) && is_numeric($value2)) {
                // For numeric values, consider them similar if within 20%
                $diff = abs($value1 - $value2);
                $avg = ($value1 + $value2) / 2;
                if ($avg > 0 && ($diff / $avg) <= 0.2) {
                    $matches += 0.8;
                }
            } elseif (is_string($value1) && is_string($value2)) {
                // For strings, use Levenshtein distance
                $maxLen = max(strlen($value1), strlen($value2));
                if ($maxLen > 0) {
                    $distance = levenshtein($value1, $value2);
                    $similarity = 1 - ($distance / $maxLen);
                    $matches += max(0, $similarity);
                }
            }
        }

        return $total > 0 ? $matches / $total : 0.0;
    }

    /**
     * Calculate embedding-based similarity using cosine similarity
     *
     * @param CoursePattern $otherPattern
     * @return float
     */
    private function calculateEmbeddingSimilarity(CoursePattern $otherPattern): float
    {
        $thisEmbeddings = $this->get('embeddings', []);
        $otherEmbeddings = $otherPattern->get('embeddings', []);

        if (empty($thisEmbeddings) || empty($otherEmbeddings)) {
            return 0.0;
        }

        return $this->cosineSimilarity($thisEmbeddings, $otherEmbeddings);
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array<float> $vectorA
     * @param array<float> $vectorB
     * @return float
     */
    private function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0.0 || $magnitudeB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Check if pattern matches a course structure
     *
     * @param array<string, mixed> $courseData
     * @return bool
     */
    public function matchesCourse(array $courseData): bool
    {
        $similarity = $this->calculateCourseSimilarity($courseData);
        return $similarity >= $this->get('similarity_threshold', 0.8);
    }

    /**
     * Calculate similarity to a course structure
     *
     * @param array<string, mixed> $courseData
     * @return float
     */
    public function calculateCourseSimilarity(array $courseData): float
    {
        $patternFeatures = $this->get('features', []);
        $courseFeatures = $this->extractCourseFeatures($courseData);

        return $this->calculateFeatureSimilarity($patternFeatures, $courseFeatures);
    }

    /**
     * Extract features from course data for comparison
     *
     * @param array<string, mixed> $courseData
     * @return array<string, mixed>
     */
    private function extractCourseFeatures(array $courseData): array
    {
        return [
            'section_count' => count($courseData['sections'] ?? []),
            'lesson_count' => $this->countLessons($courseData['sections'] ?? []),
            'has_video' => $this->hasVideoContent($courseData['sections'] ?? []),
            'has_quiz' => $this->hasQuizContent($courseData['sections'] ?? []),
            'has_downloads' => $this->hasDownloads($courseData['sections'] ?? []),
            'difficulty_level' => $courseData['metadata']['difficulty_level'] ?? 'beginner',
            'estimated_duration' => $courseData['estimated_duration'] ?? 0,
            'intro_section_present' => $this->hasIntroSection($courseData['sections'] ?? []),
            'conclusion_section_present' => $this->hasConclusionSection($courseData['sections'] ?? [])
        ];
    }

    /**
     * Count total lessons in sections
     */
    private function countLessons(array $sections): int
    {
        $count = 0;
        foreach ($sections as $section) {
            $count += count($section['lessons'] ?? []);
        }
        return $count;
    }

    /**
     * Check if course has video content
     */
    private function hasVideoContent(array $sections): bool
    {
        foreach ($sections as $section) {
            foreach ($section['lessons'] ?? [] as $lesson) {
                if (($lesson['type'] ?? '') === 'video') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if course has quiz content
     */
    private function hasQuizContent(array $sections): bool
    {
        foreach ($sections as $section) {
            foreach ($section['lessons'] ?? [] as $lesson) {
                if (($lesson['type'] ?? '') === 'quiz') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if course has downloadable content
     */
    private function hasDownloads(array $sections): bool
    {
        foreach ($sections as $section) {
            foreach ($section['lessons'] ?? [] as $lesson) {
                if (!empty($lesson['downloadable_resources'] ?? [])) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if course has introduction section
     */
    private function hasIntroSection(array $sections): bool
    {
        if (empty($sections)) {
            return false;
        }
        
        $firstSection = $sections[0];
        $title = strtolower($firstSection['title'] ?? '');
        
        return strpos($title, 'intro') !== false || 
               strpos($title, 'welcome') !== false ||
               strpos($title, 'getting started') !== false;
    }

    /**
     * Check if course has conclusion section
     */
    private function hasConclusionSection(array $sections): bool
    {
        if (empty($sections)) {
            return false;
        }
        
        $lastSection = end($sections);
        $title = strtolower($lastSection['title'] ?? '');
        
        return strpos($title, 'conclusion') !== false || 
               strpos($title, 'wrap up') !== false ||
               strpos($title, 'next steps') !== false ||
               strpos($title, 'summary') !== false;
    }

    /**
     * Update success metrics
     *
     * @param array<string, mixed> $metrics
     * @return static
     */
    public function updateSuccessMetrics(array $metrics): static
    {
        $currentMetrics = $this->get('success_metrics', []);
        $updatedMetrics = array_merge($currentMetrics, $metrics);
        
        // Determine success level based on metrics
        $updatedMetrics['success_level'] = $this->calculateSuccessLevel($updatedMetrics);
        
        return $this->set('success_metrics', $updatedMetrics);
    }

    /**
     * Calculate success level based on metrics
     *
     * @param array<string, mixed> $metrics
     * @return string
     */
    private function calculateSuccessLevel(array $metrics): string
    {
        $completionRate = $metrics['completion_rate'] ?? 0.0;
        $engagementScore = $metrics['engagement_score'] ?? 0.0;
        $satisfactionRating = $metrics['satisfaction_rating'] ?? 0.0;

        $averageScore = ($completionRate + $engagementScore + $satisfactionRating) / 3;

        if ($averageScore >= 0.8) {
            return self::SUCCESS_HIGH;
        } elseif ($averageScore >= 0.6) {
            return self::SUCCESS_MEDIUM;
        } else {
            return self::SUCCESS_LOW;
        }
    }

    /**
     * Record pattern usage
     *
     * @param int $courseId
     * @return static
     */
    public function recordUsage(int $courseId): static
    {
        $stats = $this->get('usage_statistics', []);
        $stats['times_used'] = ($stats['times_used'] ?? 0) + 1;
        $stats['last_used'] = current_time('mysql');
        
        $courses = $stats['courses_with_pattern'] ?? [];
        if (!in_array($courseId, $courses)) {
            $courses[] = $courseId;
        }
        $stats['courses_with_pattern'] = $courses;

        return $this->set('usage_statistics', $stats);
    }

    /**
     * Generate pattern fingerprint
     *
     * @return string
     */
    public function generateFingerprint(): string
    {
        $features = $this->get('features', []);
        $type = $this->get('pattern_type', '');
        $category = $this->get('category', '');
        
        $fingerprintData = [
            'type' => $type,
            'category' => $category,
            'features_hash' => md5(serialize($features))
        ];
        
        $fingerprint = md5(serialize($fingerprintData));
        $this->set('fingerprint', $fingerprint);
        
        return $fingerprint;
    }

    /**
     * Export pattern for machine learning
     *
     * @return array<string, mixed>
     */
    public function exportForML(): array
    {
        return [
            'pattern_id' => $this->get('pattern_id'),
            'type' => $this->get('pattern_type'),
            'category' => $this->get('category'),
            'features' => $this->get('features', []),
            'embeddings' => $this->get('embeddings', []),
            'success_metrics' => $this->get('success_metrics', []),
            'usage_count' => $this->get('usage_statistics.times_used', 0),
            'fingerprint' => $this->get('fingerprint')
        ];
    }

    /**
     * Import pattern from machine learning data
     *
     * @param array<string, mixed> $mlData
     * @return static
     */
    public function importFromML(array $mlData): static
    {
        $this->fill([
            'pattern_id' => $mlData['pattern_id'] ?? null,
            'pattern_type' => $mlData['type'] ?? self::TYPE_STRUCTURAL,
            'category' => $mlData['category'] ?? self::CATEGORY_SECTION_FLOW,
            'features' => $mlData['features'] ?? [],
            'embeddings' => $mlData['embeddings'] ?? [],
            'success_metrics' => $mlData['success_metrics'] ?? [],
            'fingerprint' => $mlData['fingerprint'] ?? ''
        ]);

        if (isset($mlData['usage_count'])) {
            $stats = $this->get('usage_statistics', []);
            $stats['times_used'] = $mlData['usage_count'];
            $this->set('usage_statistics', $stats);
        }

        return $this;
    }

    /**
     * Create a new version of this pattern
     *
     * @param array<string, mixed> $changes
     * @return static
     */
    public function createVersion(array $changes): static
    {
        $newPattern = new static($this->toArray());
        $newPattern->fill($changes);
        
        $currentVersion = $this->get('version', '1.0');
        $versionParts = explode('.', $currentVersion);
        $versionParts[1] = ((int)$versionParts[1]) + 1;
        $newVersion = implode('.', $versionParts);
        
        $newPattern->set('version', $newVersion);
        $newPattern->set('created_at', current_time('mysql'));
        $newPattern->generateFingerprint();
        
        return $newPattern;
    }

    /**
     * Validate pattern data
     *
     * @return bool
     */
    public function validate(): bool
    {
        $errors = [];

        if (empty($this->get('name'))) {
            $errors[] = 'Pattern name is required';
        }

        if (empty($this->get('pattern_type'))) {
            $errors[] = 'Pattern type is required';
        }

        if (empty($this->get('category'))) {
            $errors[] = 'Pattern category is required';
        }

        if (empty($this->get('features'))) {
            $errors[] = 'Pattern features are required';
        }

        $threshold = $this->get('similarity_threshold', 0.8);
        if (!is_numeric($threshold) || $threshold < 0 || $threshold > 1) {
            $errors[] = 'Similarity threshold must be between 0 and 1';
        }

        return empty($errors);
    }

    /**
     * Save pattern to database
     *
     * @return bool
     */
    public function save(): bool
    {
        global $wpdb;

        if (!$this->validate()) {
            return false;
        }

        $tableName = $wpdb->prefix . 'mp_copilot_course_patterns';
        $now = current_time('mysql');

        $data = [
            'pattern_type' => $this->get('pattern_type'),
            'category' => $this->get('category'),
            'name' => $this->get('name'),
            'description' => $this->get('description'),
            'fingerprint' => $this->get('fingerprint') ?: $this->generateFingerprint(),
            'features' => wp_json_encode($this->get('features', [])),
            'metadata' => wp_json_encode($this->get('metadata', [])),
            'success_metrics' => wp_json_encode($this->get('success_metrics', [])),
            'usage_statistics' => wp_json_encode($this->get('usage_statistics', [])),
            'similarity_threshold' => $this->get('similarity_threshold', 0.8),
            'embeddings' => wp_json_encode($this->get('embeddings', [])),
            'version' => $this->get('version', '1.0'),
            'created_by' => $this->get('created_by') ?: get_current_user_id(),
            'updated_at' => $now
        ];

        if ($this->get('pattern_id')) {
            // Update existing pattern
            $result = $wpdb->update(
                $tableName,
                $data,
                ['id' => $this->get('pattern_id')],
                null,
                ['%d']
            );
        } else {
            // Insert new pattern
            $data['created_at'] = $now;
            $result = $wpdb->insert($tableName, $data);
            
            if ($result !== false) {
                $this->set('pattern_id', $wpdb->insert_id);
            }
        }

        if ($result !== false) {
            $this->syncOriginal();
            return true;
        }

        return false;
    }

    /**
     * Delete pattern from database
     *
     * @return bool
     */
    public function delete(): bool
    {
        global $wpdb;

        $patternId = $this->get('pattern_id');
        if (!$patternId) {
            return false;
        }

        $tableName = $wpdb->prefix . 'mp_copilot_course_patterns';
        $result = $wpdb->delete(
            $tableName,
            ['id' => $patternId],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Find pattern by ID
     *
     * @param int $patternId
     * @return static|null
     */
    public static function find(int $patternId): ?static
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'mp_copilot_course_patterns';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$tableName} WHERE id = %d", $patternId),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        return static::fromDatabaseRow($row);
    }

    /**
     * Find patterns by type
     *
     * @param string $type
     * @return static[]
     */
    public static function findByType(string $type): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'mp_copilot_course_patterns';
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$tableName} WHERE pattern_type = %s ORDER BY updated_at DESC", $type),
            ARRAY_A
        );

        $patterns = [];
        foreach ($rows as $row) {
            $patterns[] = static::fromDatabaseRow($row);
        }

        return $patterns;
    }

    /**
     * Find patterns by success level
     *
     * @param string $successLevel
     * @return static[]
     */
    public static function findBySuccessLevel(string $successLevel): array
    {
        global $wpdb;

        $tableName = $wpdb->prefix . 'mp_copilot_course_patterns';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE JSON_EXTRACT(success_metrics, '$.success_level') = %s ORDER BY updated_at DESC",
                $successLevel
            ),
            ARRAY_A
        );

        $patterns = [];
        foreach ($rows as $row) {
            $patterns[] = static::fromDatabaseRow($row);
        }

        return $patterns;
    }

    /**
     * Create pattern instance from database row
     *
     * @param array<string, mixed> $row
     * @return static
     */
    private static function fromDatabaseRow(array $row): static
    {
        $pattern = new static();
        
        $pattern->fill([
            'pattern_id' => (int)$row['id'],
            'pattern_type' => $row['pattern_type'],
            'category' => $row['category'],
            'name' => $row['name'],
            'description' => $row['description'],
            'fingerprint' => $row['fingerprint'],
            'features' => json_decode($row['features'], true) ?: [],
            'metadata' => json_decode($row['metadata'], true) ?: [],
            'success_metrics' => json_decode($row['success_metrics'], true) ?: [],
            'usage_statistics' => json_decode($row['usage_statistics'], true) ?: [],
            'similarity_threshold' => (float)$row['similarity_threshold'],
            'embeddings' => json_decode($row['embeddings'], true) ?: [],
            'version' => $row['version'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'created_by' => (int)$row['created_by']
        ]);

        $pattern->syncOriginal();
        return $pattern;
    }
}