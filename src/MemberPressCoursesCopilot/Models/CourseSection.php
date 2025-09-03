<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Course Section Model
 *
 * Represents a section within a course that contains multiple lessons.
 * Designed to integrate with MemberPress Courses section management.
 */
class CourseSection
{
    private string $title;
    private array $lessons; // Array of CourseLesson objects
    private int $order;
    private array $metadata;

    public function __construct(
        string $title,
        array $lessons = [],
        int $order = 0,
        array $metadata = []
    ) {
        $this->title    = $title;
        $this->lessons  = $lessons;
        $this->order    = $order;
        $this->metadata = $metadata;
    }

    /**
     * Validate section data
     */
    public function validate(): array
    {
        $errors = [];

        if (empty(trim($this->title))) {
            $errors[] = 'Section title is required';
        }

        if (strlen($this->title) > 255) {
            $errors[] = 'Section title must be 255 characters or less';
        }

        if ($this->order < 0) {
            $errors[] = 'Order must be a positive number';
        }

        // Validate each lesson
        foreach ($this->lessons as $index => $lesson) {
            if (!$lesson instanceof CourseLesson) {
                $errors[] = "Lesson {$index} must be a CourseLesson instance";
                continue;
            }

            $lessonErrors = $lesson->validate();
            foreach ($lessonErrors as $lessonError) {
                $errors[] = "Lesson {$index}: {$lessonError}";
            }
        }

        return $errors;
    }

    /**
     * Add a lesson to the section
     */
    public function addLesson(CourseLesson $lesson): void
    {
        // Set the lesson order based on current position
        $lesson->setOrder(count($this->lessons));
        $this->lessons[] = $lesson;
    }

    /**
     * Remove a lesson by index
     */
    public function removeLesson(int $index): bool
    {
        if (isset($this->lessons[$index])) {
            array_splice($this->lessons, $index, 1);
            $this->reorderLessons();
            return true;
        }
        return false;
    }

    /**
     * Update lesson at specific index
     */
    public function updateLesson(int $index, CourseLesson $lesson): bool
    {
        if (isset($this->lessons[$index])) {
            $lesson->setOrder($index);
            $this->lessons[$index] = $lesson;
            return true;
        }
        return false;
    }

    /**
     * Reorder lessons after modification
     */
    private function reorderLessons(): void
    {
        foreach ($this->lessons as $index => $lesson) {
            $lesson->setOrder($index);
        }
    }

    /**
     * Move lesson to new position
     */
    public function moveLessonToPosition(int $fromIndex, int $toIndex): bool
    {
        if (!isset($this->lessons[$fromIndex]) || $toIndex < 0 || $toIndex >= count($this->lessons)) {
            return false;
        }

        $lesson = $this->lessons[$fromIndex];
        array_splice($this->lessons, $fromIndex, 1);
        array_splice($this->lessons, $toIndex, 0, [$lesson]);

        $this->reorderLessons();
        return true;
    }

    /**
     * Get lesson by index
     */
    public function getLesson(int $index): ?CourseLesson
    {
        return $this->lessons[$index] ?? null;
    }

    /**
     * Find lesson by title
     */
    public function findLessonByTitle(string $title): ?CourseLesson
    {
        foreach ($this->lessons as $lesson) {
            if ($lesson->getTitle() === $title) {
                return $lesson;
            }
        }
        return null;
    }

    /**
     * Get total duration of all lessons in section
     */
    public function getTotalDuration(): int
    {
        $totalDuration = 0;
        foreach ($this->lessons as $lesson) {
            $duration = $lesson->getDuration();
            if ($duration !== null) {
                $totalDuration += $duration;
            } else {
                // Use estimated reading time if no duration is set
                $totalDuration += $lesson->estimateReadingTime();
            }
        }
        return $totalDuration;
    }

    /**
     * Get lesson count
     */
    public function getLessonCount(): int
    {
        return count($this->lessons);
    }

    /**
     * Check if section has video lessons
     */
    public function hasVideoLessons(): bool
    {
        foreach ($this->lessons as $lesson) {
            if ($lesson->hasVideoContent()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if section has downloadable resources
     */
    public function hasDownloadableResources(): bool
    {
        foreach ($this->lessons as $lesson) {
            if ($lesson->hasDownloadableResources()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convert section to MemberPress format
     */
    public function toMemberPressFormat(): array
    {
        $lessonsData = [];
        foreach ($this->lessons as $lesson) {
            $lessonsData[] = $lesson->toMemberPressFormat();
        }

        return [
            'section_title' => $this->title,
            'section_order' => $this->order,
            'lessons'       => $lessonsData,
            'metadata'      => $this->formatMetadataForMemberPress(),
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
            $memberPressMetadata["_mpcs_section_{$key}"] = $value;
        }

        // Add computed metadata
        $memberPressMetadata['_mpcs_section_lesson_count']   = $this->getLessonCount();
        $memberPressMetadata['_mpcs_section_total_duration'] = $this->getTotalDuration();
        $memberPressMetadata['_mpcs_section_has_video']      = $this->hasVideoLessons();
        $memberPressMetadata['_mpcs_section_has_downloads']  = $this->hasDownloadableResources();

        return $memberPressMetadata;
    }

    /**
     * Create section from MemberPress data
     */
    public static function fromMemberPressData(array $sectionData): self
    {
        $title    = $sectionData['section_title'] ?? '';
        $order    = $sectionData['section_order'] ?? 0;
        $metadata = $sectionData['metadata'] ?? [];

        $lessons = [];
        if (isset($sectionData['lessons']) && is_array($sectionData['lessons'])) {
            foreach ($sectionData['lessons'] as $lessonData) {
                // Assuming lesson data contains the necessary information
                $lesson    = new CourseLesson(
                    $lessonData['post_title'] ?? '',
                    $lessonData['post_content'] ?? '',
                    $lessonData['meta_input']['_mpcs_lesson_objectives'] ?? [],
                    $lessonData['meta_input']['_mpcs_lesson_duration'] ?? null,
                    $lessonData['menu_order'] ?? 0
                );
                $lessons[] = $lesson;
            }
        }

        return new self($title, $lessons, $order, $metadata);
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
     * Get all learning objectives from lessons in this section
     */
    public function getAllObjectives(): array
    {
        $objectives = [];
        foreach ($this->lessons as $lesson) {
            $objectives = array_merge($objectives, $lesson->getObjectives());
        }
        return array_unique($objectives);
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

    public function getLessons(): array
    {
        return $this->lessons;
    }

    public function setLessons(array $lessons): void
    {
        $this->lessons = $lessons;
        $this->reorderLessons();
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
     * Get section as array for JSON serialization
     */
    public function toArray(): array
    {
        $lessonsArray = [];
        foreach ($this->lessons as $lesson) {
            $lessonsArray[] = $lesson->toArray();
        }

        return [
            'title'          => $this->title,
            'lessons'        => $lessonsArray,
            'order'          => $this->order,
            'metadata'       => $this->metadata,
            'lesson_count'   => $this->getLessonCount(),
            'total_duration' => $this->getTotalDuration(),
            'has_video'      => $this->hasVideoLessons(),
            'has_downloads'  => $this->hasDownloadableResources(),
            'all_objectives' => $this->getAllObjectives(),
        ];
    }
}
