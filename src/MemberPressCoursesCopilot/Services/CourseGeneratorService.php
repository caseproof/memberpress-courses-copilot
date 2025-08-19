<?php

namespace MemberPressCoursesCopilot\Services;

use memberpress\courses\models\Course;
use memberpress\courses\models\Section;
use memberpress\courses\models\Lesson;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Simple Course Generator Service
 * 
 * KISS Principle: Direct WordPress post creation for courses
 * No complex abstractions, just straightforward course generation
 */
class CourseGeneratorService
{
    private Logger $logger;
    
    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * Generate course from AI conversation data
     * 
     * @param array $courseData Data from AI conversation
     * @return array Result with course_id and success status
     */
    public function generateCourse(array $courseData): array
    {
        try {
            // Create the main course
            $courseId = $this->createCourse($courseData);
            
            if (!$courseId) {
                throw new \Exception('Failed to create course');
            }
            
            // Create sections and lessons
            $sectionOrder = 1;
            foreach ($courseData['sections'] as $sectionData) {
                $sectionId = $this->createSection($sectionData, $courseId, $sectionOrder++);
                
                if ($sectionId && !empty($sectionData['lessons'])) {
                    $lessonOrder = 1;
                    foreach ($sectionData['lessons'] as $lessonData) {
                        $this->createLesson($lessonData, $sectionId, $courseId, $lessonOrder++);
                    }
                }
            }
            
            $this->logger->info('Course generated successfully', ['course_id' => $courseId]);
            
            return [
                'success' => true,
                'course_id' => $courseId,
                'edit_url' => admin_url("post.php?post={$courseId}&action=edit"),
                'preview_url' => get_permalink($courseId)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Course generation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create the main course post
     */
    private function createCourse(array $courseData): int
    {
        // Prepare course post data
        $postData = [
            'post_title' => $courseData['title'] ?? 'Untitled Course',
            'post_content' => $courseData['description'] ?? '',
            'post_status' => 'draft', // Always create as draft
            'post_type' => 'mpcs-course',
            'post_author' => get_current_user_id()
        ];
        
        // Create the course
        $courseId = wp_insert_post($postData);
        
        if (is_wp_error($courseId)) {
            throw new \Exception('Failed to create course: ' . $courseId->get_error_message());
        }
        
        // Set course meta data
        $course = new Course($courseId);
        
        // Set basic course settings
        if (!empty($courseData['settings'])) {
            $settings = $courseData['settings'];
            
            if (isset($settings['course_progress'])) {
                $course->show_course_progress = $settings['course_progress'];
            }
            
            if (isset($settings['auto_advance'])) {
                $course->auto_advance = $settings['auto_advance'];
            }
            
            if (isset($settings['instructor_name'])) {
                $course->instructor_name = $settings['instructor_name'];
            }
        }
        
        // Set default values for required fields
        $course->show_course_progress = $course->show_course_progress ?? 'enabled';
        $course->auto_advance = $course->auto_advance ?? 'enabled';
        $course->page_template = $course->page_template ?? 'default';
        
        // Save course meta
        $course->store();
        
        // Add categories and tags if provided
        if (!empty($courseData['categories'])) {
            wp_set_object_terms($courseId, $courseData['categories'], 'mpcs-course-categories');
        }
        
        if (!empty($courseData['tags'])) {
            wp_set_object_terms($courseId, $courseData['tags'], 'mpcs-course-tags');
        }
        
        return $courseId;
    }
    
    /**
     * Create a course section
     */
    private function createSection(array $sectionData, int $courseId, int $order): int
    {
        $postData = [
            'post_title' => $sectionData['title'] ?? 'Section ' . $order,
            'post_content' => $sectionData['description'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'mpcs-section',
            'post_parent' => $courseId,
            'menu_order' => $order,
            'post_author' => get_current_user_id()
        ];
        
        $sectionId = wp_insert_post($postData);
        
        if (is_wp_error($sectionId)) {
            $this->logger->error('Failed to create section: ' . $sectionId->get_error_message());
            return 0;
        }
        
        // Initialize Section model to ensure meta fields are set
        $section = new Section($sectionId);
        $section->course_id = $courseId;
        $section->store();
        
        return $sectionId;
    }
    
    /**
     * Create a lesson
     */
    private function createLesson(array $lessonData, int $sectionId, int $courseId, int $order): int
    {
        $postData = [
            'post_title' => $lessonData['title'] ?? 'Lesson ' . $order,
            'post_content' => $lessonData['content'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'mpcs-lesson',
            'post_parent' => $sectionId,
            'menu_order' => $order,
            'post_author' => get_current_user_id()
        ];
        
        $lessonId = wp_insert_post($postData);
        
        if (is_wp_error($lessonId)) {
            $this->logger->error('Failed to create lesson: ' . $lessonId->get_error_message());
            return 0;
        }
        
        // Initialize Lesson model
        $lesson = new Lesson($lessonId);
        $lesson->course_id = $courseId;
        $lesson->section_id = $sectionId;
        
        // Set lesson type and duration
        $lesson->lesson_type = $lessonData['type'] ?? 'text';
        $lesson->duration = $lessonData['duration'] ?? '5';
        
        // Add video URL if it's a video lesson
        if ($lesson->lesson_type === 'video' && !empty($lessonData['video_url'])) {
            $lesson->lesson_video_url = $lessonData['video_url'];
            $lesson->lesson_video_type = $lessonData['video_type'] ?? 'youtube';
        }
        
        $lesson->store();
        
        return $lessonId;
    }
    
    /**
     * Preview course structure without creating it
     */
    public function previewCourse(array $courseData): array
    {
        $preview = [
            'title' => $courseData['title'] ?? 'Untitled Course',
            'description' => $courseData['description'] ?? '',
            'sections' => []
        ];
        
        foreach ($courseData['sections'] as $section) {
            $sectionPreview = [
                'title' => $section['title'] ?? 'Untitled Section',
                'description' => $section['description'] ?? '',
                'lessons' => []
            ];
            
            if (!empty($section['lessons'])) {
                foreach ($section['lessons'] as $lesson) {
                    $sectionPreview['lessons'][] = [
                        'title' => $lesson['title'] ?? 'Untitled Lesson',
                        'type' => $lesson['type'] ?? 'text',
                        'duration' => $lesson['duration'] ?? '5 minutes'
                    ];
                }
            }
            
            $preview['sections'][] = $sectionPreview;
        }
        
        return $preview;
    }
    
    /**
     * Update existing course from AI data
     */
    public function updateCourse(int $courseId, array $courseData): array
    {
        try {
            $course = new Course($courseId);
            
            if (!$course->ID) {
                throw new \Exception('Course not found');
            }
            
            // Update course title and description
            wp_update_post([
                'ID' => $courseId,
                'post_title' => $courseData['title'] ?? $course->post_title,
                'post_content' => $courseData['description'] ?? $course->post_content
            ]);
            
            // Update course meta if provided
            if (!empty($courseData['settings'])) {
                foreach ($courseData['settings'] as $key => $value) {
                    if (property_exists($course, $key)) {
                        $course->$key = $value;
                    }
                }
                $course->store();
            }
            
            return [
                'success' => true,
                'course_id' => $courseId,
                'message' => 'Course updated successfully'
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Course update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate course data before generation
     */
    public function validateCourseData(array $courseData): array
    {
        $errors = [];
        
        if (empty($courseData['title'])) {
            $errors[] = 'Course title is required';
        }
        
        if (empty($courseData['sections']) || !is_array($courseData['sections'])) {
            $errors[] = 'At least one section is required';
        } else {
            foreach ($courseData['sections'] as $index => $section) {
                if (empty($section['title'])) {
                    $errors[] = "Section " . ($index + 1) . " title is required";
                }
                
                if (empty($section['lessons']) || !is_array($section['lessons'])) {
                    $errors[] = "Section " . ($index + 1) . " must have at least one lesson";
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}