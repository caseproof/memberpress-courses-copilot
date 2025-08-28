<?php

namespace MemberPressCoursesCopilot\Services;

use memberpress\courses\models\Course;
use memberpress\courses\models\Section;
use memberpress\courses\models\Lesson;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use WP_Error;
use MemberPressCoursesCopilot\Interfaces\ICourseGenerator;

/**
 * Simple Course Generator Service
 * 
 * KISS Principle: Direct WordPress post creation for courses
 * No complex abstractions, just straightforward course generation
 */
class CourseGeneratorService extends BaseService implements ICourseGenerator
{
    /**
     * Constructor - logger can be injected or will use default
     * 
     * @param Logger|null $logger
     */
    public function __construct(?Logger $logger = null)
    {
        parent::__construct();
        
        // Use injected logger or get from container/singleton
        if ($logger !== null) {
            $this->logger = $logger;
        }
    }
    
    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // No initialization needed for this service
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
            // Log the incoming course data
            $this->logger->info('Course generation started', [
                'course_title' => $courseData['title'] ?? 'No title',
                'sections_count' => count($courseData['sections'] ?? []),
                'course_data_keys' => array_keys($courseData),
                'full_course_data' => json_encode($courseData)
            ]);
            
            // Create the main course
            $courseId = $this->createCourse($courseData);
            
            if (!$courseId) {
                throw new \Exception('Failed to create course');
            }
            
            $this->logger->info('Course post created', ['course_id' => $courseId]);
            
            // Create sections and lessons
            $sectionOrder = 1;
            if (isset($courseData['sections']) && is_array($courseData['sections'])) {
                foreach ($courseData['sections'] as $sectionData) {
                    $this->logger->info('Creating section', [
                        'section_title' => $sectionData['title'] ?? 'No title',
                        'section_order' => $sectionOrder,
                        'lessons_count' => count($sectionData['lessons'] ?? [])
                    ]);
                    
                    $sectionId = $this->createSection($sectionData, $courseId, $sectionOrder++);
                    
                    if ($sectionId && !empty($sectionData['lessons'])) {
                        $lessonOrder = 1;
                        foreach ($sectionData['lessons'] as $lessonData) {
                            $this->logger->info('Creating lesson', [
                                'lesson_title' => $lessonData['title'] ?? 'No title',
                                'lesson_order' => $lessonOrder,
                                'section_id' => $sectionId
                            ]);
                            $this->createLesson($lessonData, $sectionId, $courseId, $lessonOrder++);
                        }
                    }
                }
            } else {
                $this->logger->warning('No sections found in course data');
            }
            
            $this->logger->info('Course generated successfully', ['course_id' => $courseId]);
            
            return [
                'success' => true,
                'course_id' => $courseId,
                'edit_url' => admin_url("post.php?post={$courseId}&action=edit"),
                'preview_url' => get_permalink($courseId)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Course generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
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
        // Log available Section classes for debugging
        $this->logger->debug('Checking for Section class availability', [
            'memberpress\courses\models\Section' => class_exists('\memberpress\courses\models\Section'),
            'Section (imported)' => class_exists('Section'),
            'defined_classes_count' => count(get_declared_classes())
        ]);
        
        // Sections in MemberPress Courses are stored in a custom table, not as posts
        try {
            $section = new Section();
            $section->title = $sectionData['title'] ?? 'Section ' . $order;
            $section->description = $sectionData['description'] ?? '';
            $section->course_id = $courseId;
            $section->section_order = $order;
            $section->created_at = current_time('mysql');
            $section->uuid = wp_generate_uuid4(); // Generate a UUID for the section
            
            $this->logger->debug('Creating section object', [
                'title' => $section->title,
                'description' => $section->description,
                'course_id' => $section->course_id,
                'section_order' => $section->section_order,
                'uuid' => $section->uuid
            ]);
            
            $sectionId = $section->store();
            
            $this->logger->info('Section store result', [
                'section_id' => $sectionId,
                'is_wp_error' => is_wp_error($sectionId),
                'section_data' => (array) $section
            ]);
            
            if (is_wp_error($sectionId)) {
                $this->logger->error('Failed to create section: ' . $sectionId->get_error_message());
                return 0;
            }
            
            if (!$sectionId) {
                $this->logger->error('Section store returned empty ID');
                return 0;
            }
            
            return $sectionId;
        } catch (\Exception $e) {
            $this->logger->error('Exception creating section: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * Create a lesson
     */
    private function createLesson(array $lessonData, int $sectionId, int $courseId, int $order): int
    {
        // Log lesson data for debugging
        $this->logger->debug('Creating lesson with data', [
            'lesson_title' => $lessonData['title'] ?? 'No title',
            'has_content' => !empty($lessonData['content']),
            'content_length' => isset($lessonData['content']) ? strlen($lessonData['content']) : 0,
            'lesson_keys' => array_keys($lessonData)
        ]);
        
        $postData = [
            'post_title' => $lessonData['title'] ?? 'Lesson ' . $order,
            'post_content' => $lessonData['content'] ?? '',
            'post_status' => 'publish',
            'post_type' => 'mpcs-lesson',
            'post_author' => get_current_user_id()
        ];
        
        $lessonId = wp_insert_post($postData);
        
        if (is_wp_error($lessonId)) {
            $this->logger->error('Failed to create lesson: ' . $lessonId->get_error_message());
            return 0;
        }
        
        // Set the section ID and lesson order as post meta
        update_post_meta($lessonId, '_mpcs_lesson_section_id', $sectionId);
        update_post_meta($lessonId, '_mpcs_lesson_lesson_order', $order);
        
        $this->logger->info('Lesson created and metadata set', [
            'lesson_id' => $lessonId,
            'section_id' => $sectionId,
            'lesson_order' => $order
        ]);
        
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
    
    /**
     * Generate additional lessons for an existing course (ICourseGenerator interface)
     *
     * @param int $courseId Existing course ID
     * @param array $lessonParams Parameters for the new lessons
     * @return array Generated lesson data
     */
    public function generateLessons(int $courseId, array $lessonParams): array
    {
        try {
            // Get the course
            $course = get_post($courseId);
            if (!$course || $course->post_type !== Course::$cpt) {
                return [
                    'success' => false,
                    'message' => 'Course not found',
                    'lessons' => []
                ];
            }
            
            // Get or create a section for the new lessons
            $sectionId = $lessonParams['section_id'] ?? null;
            
            if (!$sectionId) {
                // Create a new section if none specified
                $sectionData = [
                    'title' => $lessonParams['section_title'] ?? 'Additional Lessons',
                    'lessons' => []
                ];
                
                // Create the section
                $section = new Section();
                $section->title = $sectionData['title'];
                $section->courses = [$courseId];
                $section->store_meta();
                
                $sectionId = $section->id;
            }
            
            // Generate the lessons
            $generatedLessons = [];
            $lessons = $lessonParams['lessons'] ?? [];
            
            foreach ($lessons as $lessonData) {
                $lesson = new Lesson();
                $lesson->title = $lessonData['title'];
                $lesson->content = $lessonData['content'] ?? '';
                $lesson->sections = [$sectionId];
                $lesson->store_meta();
                
                $generatedLessons[] = [
                    'id' => $lesson->id,
                    'title' => $lesson->title,
                    'url' => get_permalink($lesson->id)
                ];
            }
            
            return [
                'success' => true,
                'section_id' => $sectionId,
                'lessons' => $generatedLessons
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate lessons', [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to generate lessons: ' . $e->getMessage(),
                'lessons' => []
            ];
        }
    }
    
    /**
     * Validate course generation parameters (ICourseGenerator interface)
     *
     * @param array $courseParams Parameters to validate
     * @return bool True if valid, false otherwise
     */
    public function validateParams(array $courseParams): bool
    {
        $validation = $this->validateCourseData($courseParams);
        return $validation['valid'];
    }
}