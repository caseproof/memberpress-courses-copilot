<?php

namespace MemberPressCoursesCopilot\Interfaces;

/**
 * Interface for AI-powered course generation
 */
interface ICourseGenerator {
    /**
     * Generate a complete course based on provided parameters
     *
     * @param array $courseParams Course generation parameters (title, description, etc.)
     * @return array Generated course data including lessons and sections
     */
    public function generateCourse(array $courseParams): array;

    /**
     * Generate additional lessons for an existing course
     *
     * @param int $courseId Existing course ID
     * @param array $lessonParams Parameters for the new lessons
     * @return array Generated lesson data
     */
    public function generateLessons(int $courseId, array $lessonParams): array;

    /**
     * Validate course generation parameters
     *
     * @param array $courseParams Parameters to validate
     * @return bool True if valid, false otherwise
     */
    public function validateParams(array $courseParams): bool;
}