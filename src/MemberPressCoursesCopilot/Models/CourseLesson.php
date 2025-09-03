<?php


namespace MemberPressCoursesCopilot\Models;

/**
 * Course Lesson Model
 *
 * Represents an individual lesson within a course section.
 * Designed to integrate with MemberPress Courses lesson custom post types.
 */
class CourseLesson
{
    private string $title;
    private string $content;
    private array $objectives;
    private ?int $duration; // Duration in minutes
    private int $order;
    private array $metadata;

    public function __construct(
        string $title,
        string $content = '',
        array $objectives = [],
        ?int $duration = null,
        int $order = 0,
        array $metadata = []
    ) {
        $this->title      = $title;
        $this->content    = $content;
        $this->objectives = $objectives;
        $this->duration   = $duration;
        $this->order      = $order;
        $this->metadata   = $metadata;
    }

    /**
     * Comprehensive lesson data validation with business rule enforcement
     *
     * This method validates all lesson properties to ensure data integrity
     * and compliance with business rules before lesson creation or updates.
     * It checks both required fields and field-specific constraints.
     *
     * Validation Categories:
     *
     * Title Validation:
     * - Required field: Every lesson must have an identifying title
     * - Length constraint: 255 characters max (WordPress post_title limit)
     * - Content validation: Must contain actual text (not just whitespace)
     * - trim() removes leading/trailing whitespace before validation
     *
     * Duration Validation:
     * - Optional field: null values are allowed (lessons may not have set duration)
     * - Positive constraint: Duration must be >= 0 if specified
     * - Business rule: Negative durations don't make pedagogical sense
     * - Supports both integer and float values for precise timing
     *
     * Order Validation:
     * - Sequence constraint: Must be non-negative integer
     * - Business rule: Lesson order determines display sequence in course
     * - Zero is valid (allows flexible ordering schemes)
     * - Negative values would break sorting algorithms
     *
     * Objectives Validation:
     * - Array element validation: Each objective must be valid string
     * - Content validation: Objectives cannot be empty or whitespace-only
     * - Type safety: Ensures objectives are strings (not numbers or objects)
     * - Educational standard: Learning objectives must be meaningful text
     *
     * Error Reporting:
     * - Descriptive messages for user-facing display
     * - Specific field identification for form validation
     * - Index-based error reporting for array fields
     * - Aggregated error collection for batch validation
     *
     * Business Rule Enforcement:
     * - Ensures lessons meet minimum quality standards
     * - Prevents creation of invalid course content
     * - Maintains database integrity constraints
     * - Supports educational best practices
     *
     * @return array Array of validation error messages (empty if valid)
     */
    public function validate(): array
    {
        $errors = [];

        // Title Validation: Essential for lesson identification and navigation
        if (empty(trim($this->title))) {
            // trim() ensures whitespace-only titles are caught as invalid
            $errors[] = 'Lesson title is required';
        }

        // Title Length Validation: WordPress database constraint
        if (strlen($this->title) > 255) {
            // WordPress post_title field has 255 character limit
            $errors[] = 'Lesson title must be 255 characters or less';
        }

        // Duration Validation: Optional but must be valid if provided
        if ($this->duration !== null && $this->duration < 0) {
            // Negative durations are logically invalid for educational content
            $errors[] = 'Duration must be a positive number';
        }

        // Order Validation: Sequence positioning constraint
        if ($this->order < 0) {
            // Negative order values break lesson sequencing logic
            $errors[] = 'Order must be a positive number';
        }

        // Objectives Array Validation: Learning objectives quality control
        foreach ($this->objectives as $index => $objective) {
            // Each objective must be a meaningful string
            if (!is_string($objective) || empty(trim($objective))) {
                // Index-based error for specific objective identification
                $errors[] = "Objective {$index} must be a non-empty string";
            }
        }

        // Return all validation errors found (empty array indicates valid lesson)
        return $errors;
    }

    /**
     * Convert lesson to MemberPress lesson format
     */
    public function toMemberPressFormat(): array
    {
        return [
            'post_title'   => $this->title,
            'post_content' => $this->content,
            'post_status'  => 'publish',
            'post_type'    => 'mpcs-lesson', // MemberPress Courses lesson post type
            'menu_order'   => $this->order,
            'meta_input'   => array_merge([
                '_mpcs_lesson_objectives' => $this->objectives,
                '_mpcs_lesson_duration'   => $this->duration,
                '_mpcs_lesson_order'      => $this->order,
            ], $this->formatMetadataForMemberPress()),
        ];
    }

    /**
     * Format metadata for MemberPress custom fields
     */
    private function formatMetadataForMemberPress(): array
    {
        $memberPressMetadata = [];

        foreach ($this->metadata as $key => $value) {
            // Prefix with MemberPress Courses meta prefix
            $memberPressMetadata["_mpcs_lesson_{$key}"] = $value;
        }

        return $memberPressMetadata;
    }

    /**
     * Create lesson from MemberPress post data
     */
    public static function fromMemberPressPost(\WP_Post $post): self
    {
        $objectives = get_post_meta($post->ID, '_mpcs_lesson_objectives', true) ?: [];
        $duration   = get_post_meta($post->ID, '_mpcs_lesson_duration', true) ?: null;
        $order      = get_post_meta($post->ID, '_mpcs_lesson_order', true) ?: $post->menu_order;

        // Extract custom metadata
        $metadata = [];
        $allMeta  = get_post_meta($post->ID);
        foreach ($allMeta as $key => $value) {
            if (strpos($key, '_mpcs_lesson_') === 0 && !in_array($key, ['_mpcs_lesson_objectives', '_mpcs_lesson_duration', '_mpcs_lesson_order'])) {
                $cleanKey            = str_replace('_mpcs_lesson_', '', $key);
                $metadata[$cleanKey] = is_array($value) && count($value) === 1 ? $value[0] : $value;
            }
        }

        return new self(
            $post->post_title,
            $post->post_content,
            $objectives,
            $duration ? (int)$duration : null,
            (int)$order,
            $metadata
        );
    }

    /**
     * Estimate reading time based on content length
     */
    public function estimateReadingTime(): int
    {
        $wordCount      = str_word_count(strip_tags($this->content));
        $wordsPerMinute = 200; // Average reading speed
        return max(1, ceil($wordCount / $wordsPerMinute));
    }

    /**
     * Add or update lesson objective
     */
    public function addObjective(string $objective): void
    {
        if (!empty(trim($objective))) {
            $this->objectives[] = trim($objective);
        }
    }

    /**
     * Remove lesson objective by index
     */
    public function removeObjective(int $index): bool
    {
        if (isset($this->objectives[$index])) {
            array_splice($this->objectives, $index, 1);
            return true;
        }
        return false;
    }

    /**
     * Update lesson objective by index
     */
    public function updateObjective(int $index, string $objective): bool
    {
        if (isset($this->objectives[$index]) && !empty(trim($objective))) {
            $this->objectives[$index] = trim($objective);
            return true;
        }
        return false;
    }

    /**
     * Add metadata field
     */
    public function addMetadata(string $key, $value): void
    {
        $this->metadata[$key] = $value;
    }

    /**
     * Get metadata field
     */
    public function getMetadata(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Check if lesson has video content
     */
    public function hasVideoContent(): bool
    {
        return $this->getMetadata('has_video', false) ||
               strpos($this->content, '<video') !== false ||
               strpos($this->content, 'youtube.com') !== false ||
               strpos($this->content, 'vimeo.com') !== false;
    }

    /**
     * Check if lesson has downloadable resources
     */
    public function hasDownloadableResources(): bool
    {
        return $this->getMetadata('has_downloads', false) ||
               !empty($this->getMetadata('download_urls', []));
    }

    // Getters and Setters
    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getObjectives(): array
    {
        return $this->objectives;
    }

    public function setObjectives(array $objectives): void
    {
        $this->objectives = $objectives;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): void
    {
        $this->duration = $duration;
    }

    public function getOrder(): int
    {
        return $this->order;
    }

    public function setOrder(int $order): void
    {
        $this->order = $order;
    }

    public function getAllMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get estimated duration in minutes
     */
    public function getEstimatedDuration(): int
    {
        if ($this->duration !== null) {
            return $this->duration;
        }

        return $this->estimateReadingTime();
    }

    /**
     * Get lesson as array for JSON serialization
     */
    public function toArray(): array
    {
        return [
            'title'                  => $this->title,
            'content'                => $this->content,
            'objectives'             => $this->objectives,
            'duration'               => $this->duration,
            'order'                  => $this->order,
            'metadata'               => $this->metadata,
            'estimated_reading_time' => $this->estimateReadingTime(),
            'has_video'              => $this->hasVideoContent(),
            'has_downloads'          => $this->hasDownloadableResources(),
        ];
    }
}
