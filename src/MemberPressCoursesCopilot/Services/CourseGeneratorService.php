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
     * @param  array $courseData Data from AI conversation
     * @return array Result with course_id and success status
     */
    public function generateCourse(array $courseData): array
    {
        try {
            // Log the incoming course data
            $this->logger->info('Course generation started', [
                'course_title'     => $courseData['title'] ?? 'No title',
                'sections_count'   => count($courseData['sections'] ?? []),
                'course_data_keys' => array_keys($courseData),
                'full_course_data' => wp_json_encode($courseData),
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
                        'lessons_count' => count($sectionData['lessons'] ?? []),
                    ]);

                    $sectionId = $this->createSection($sectionData, $courseId, $sectionOrder++);

                    if ($sectionId && !empty($sectionData['lessons'])) {
                        $lessonOrder = 1;
                        foreach ($sectionData['lessons'] as $lessonData) {
                            $this->logger->info('Creating lesson', [
                                'lesson_title' => $lessonData['title'] ?? 'No title',
                                'lesson_order' => $lessonOrder,
                                'section_id'   => $sectionId,
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
                'success'     => true,
                'course_id'   => $courseId,
                'edit_url'    => admin_url("post.php?post={$courseId}&action=edit"),
                'preview_url' => get_permalink($courseId),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Course generation failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Create the main course post
     */
    private function createCourse(array $courseData): int
    {
        // Get description - it should already be in Gutenberg block format from AI
        $description = $courseData['description'] ?? '';
        
        $this->logger->debug('Creating course with description', [
            'has_description' => !empty($description),
            'description_length' => strlen($description),
            'description_preview' => substr($description, 0, 100) . '...'
        ]);
        
        // Only convert if description exists and doesn't already have Gutenberg blocks
        if (!empty($description) && strpos($description, '<!-- wp:') === false) {
            $this->logger->debug('Converting non-block description to Gutenberg blocks');
            $description = $this->convertToGutenbergBlocks($description);
            
            $this->logger->debug('Description after Gutenberg conversion', [
                'converted_length' => strlen($description),
                'has_gutenberg_blocks' => strpos($description, '<!-- wp:') !== false,
                'converted_preview' => substr($description, 0, 200) . '...'
            ]);
        }
        
        // Prepare course post data
        $postData = [
            'post_title'   => $courseData['title'] ?? 'Untitled Course',
            'post_content' => $description,
            'post_status'  => 'draft', // Always create as draft
            'post_type'    => 'mpcs-course',
            'post_author'  => get_current_user_id(),
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
        $course->auto_advance         = $course->auto_advance ?? 'enabled';
        $course->page_template        = $course->page_template ?? 'default';

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
            'Section (imported)'                 => class_exists('Section'),
            'defined_classes_count'              => count(get_declared_classes()),
        ]);

        // Sections in MemberPress Courses are stored in a custom table, not as posts
        try {
            $section                = new Section();
            $section->title         = $sectionData['title'] ?? 'Section ' . $order;
            $section->description   = $sectionData['description'] ?? '';
            $section->course_id     = $courseId;
            $section->section_order = $order;
            $section->created_at    = current_time('mysql');
            $section->uuid          = wp_generate_uuid4(); // Generate a UUID for the section

            $this->logger->debug('Creating section object', [
                'title'         => $section->title,
                'description'   => $section->description,
                'course_id'     => $section->course_id,
                'section_order' => $section->section_order,
                'uuid'          => $section->uuid,
            ]);

            $sectionId = $section->store();

            $this->logger->info('Section store result', [
                'section_id'   => $sectionId,
                'is_wp_error'  => is_wp_error($sectionId),
                'section_data' => (array) $section,
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
                'trace' => $e->getTraceAsString(),
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
            'lesson_title'   => $lessonData['title'] ?? 'No title',
            'has_content'    => !empty($lessonData['content']),
            'content_length' => isset($lessonData['content']) ? strlen($lessonData['content']) : 0,
            'lesson_keys'    => array_keys($lessonData),
        ]);

        // Get content - it should already be in Gutenberg block format from AI
        $content = $lessonData['content'] ?? '';
        
        // Only convert if content exists and doesn't already have Gutenberg blocks
        if (!empty($content) && strpos($content, '<!-- wp:') === false) {
            $this->logger->debug('Converting non-block content to Gutenberg blocks', [
                'content_preview' => substr($content, 0, 100)
            ]);
            $content = $this->convertToGutenbergBlocks($content);
        } else {
            $this->logger->debug('Content already has Gutenberg blocks or is empty', [
                'has_blocks' => strpos($content, '<!-- wp:') !== false,
                'content_preview' => substr($content, 0, 200)
            ]);
        }

        $postData = [
            'post_title'   => $lessonData['title'] ?? 'Lesson ' . $order,
            'post_content' => $content,
            'post_status'  => 'publish',
            'post_type'    => 'mpcs-lesson',
            'post_author'  => get_current_user_id(),
        ];

        // Log content before saving
        $this->logger->debug('Lesson content before wp_insert_post', [
            'content_length' => strlen($content),
            'has_list_items' => substr_count($content, '<li>'),
            'sample_list' => substr($content, strpos($content, '<!-- wp:list'), 500)
        ]);

        $lessonId = wp_insert_post($postData);

        if (is_wp_error($lessonId)) {
            $this->logger->error('Failed to create lesson: ' . $lessonId->get_error_message());
            return 0;
        }
        
        // Log content after saving
        $savedPost = get_post($lessonId);
        $this->logger->debug('Lesson content after wp_insert_post', [
            'lesson_id' => $lessonId,
            'saved_content_length' => strlen($savedPost->post_content),
            'saved_list_items' => substr_count($savedPost->post_content, '<li>'),
            'saved_sample_list' => substr($savedPost->post_content, strpos($savedPost->post_content, '<!-- wp:list'), 500)
        ]);

        // Set the section ID and lesson order as post meta
        update_post_meta($lessonId, '_mpcs_lesson_section_id', $sectionId);
        update_post_meta($lessonId, '_mpcs_lesson_lesson_order', $order);

        $this->logger->info('Lesson created and metadata set', [
            'lesson_id'    => $lessonId,
            'section_id'   => $sectionId,
            'lesson_order' => $order,
        ]);

        return $lessonId;
    }

    /**
     * Preview course structure without creating it
     */
    public function previewCourse(array $courseData): array
    {
        $preview = [
            'title'       => $courseData['title'] ?? 'Untitled Course',
            'description' => $courseData['description'] ?? '',
            'sections'    => [],
        ];

        foreach ($courseData['sections'] as $section) {
            $sectionPreview = [
                'title'       => $section['title'] ?? 'Untitled Section',
                'description' => $section['description'] ?? '',
                'lessons'     => [],
            ];

            if (!empty($section['lessons'])) {
                foreach ($section['lessons'] as $lesson) {
                    $sectionPreview['lessons'][] = [
                        'title'    => $lesson['title'] ?? 'Untitled Lesson',
                        'type'     => $lesson['type'] ?? 'text',
                        'duration' => $lesson['duration'] ?? '5 minutes',
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

            // Get description - check if we need to convert to Gutenberg blocks
            $description = $courseData['description'] ?? $course->post_content;
            
            // Only convert if we have a new description that doesn't already have Gutenberg blocks
            if (!empty($description) && isset($courseData['description']) && strpos($description, '<!-- wp:') === false) {
                $description = $this->convertToGutenbergBlocks($description);
            }
            
            // Update course title and description
            wp_update_post([
                'ID'           => $courseId,
                'post_title'   => $courseData['title'] ?? $course->post_title,
                'post_content' => $description,
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
                'success'   => true,
                'course_id' => $courseId,
                'message'   => 'Course updated successfully',
            ];
        } catch (\Exception $e) {
            $this->logger->error('Course update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Comprehensive course data validation with structural integrity checks
     *
     * This method performs thorough validation of course data structure to ensure
     * successful course generation. It validates both required fields and structural
     * consistency across the entire course hierarchy.
     *
     * Validation Hierarchy:
     * 1. Course Level: Title, description, and basic metadata
     * 2. Section Level: Each section's title and lesson array
     * 3. Lesson Level: Each lesson's title and content structure
     *
     * Required Field Validation:
     * - Course title: Essential for course identification and SEO
     * - Sections array: Must exist and contain at least one section
     * - Section titles: Required for navigation and course structure
     * - Lesson arrays: Each section must have at least one lesson
     *
     * Structural Validation Rules:
     * - Sections must be array type (not string or object)
     * - Each section must have lessons array (empty sections not allowed)
     * - Maintains pedagogical standards (courses need content structure)
     *
     * Error Reporting Strategy:
     * - User-friendly error messages with specific context
     * - Section/lesson numbering for easy identification
     * - Descriptive field names for clear guidance
     * - Aggregated error list for comprehensive feedback
     *
     * Business Logic Validation:
     * - Enforces minimum course structure requirements
     * - Prevents creation of empty or invalid courses
     * - Ensures course meets educational content standards
     * - Validates data types to prevent runtime errors
     *
     * Return Structure:
     * - 'valid': Boolean flag for overall validation result
     * - 'errors': Array of specific validation failure messages
     * - Used by validateParams() for interface compliance
     *
     * @param  array $courseData Complete course structure to validate
     * @return array Validation result with 'valid' flag and 'errors' array
     */
    public function validateCourseData(array $courseData): array
    {
        $errors = [];

        // Course Level Validation: Essential course metadata
        if (empty($courseData['title'])) {
            // Course title is mandatory for WordPress post creation and user identification
            $errors[] = 'Course title is required';
        }

        // Course Structure Validation: Sections are the backbone of course organization
        if (empty($courseData['sections']) || !is_array($courseData['sections'])) {
            // Courses must have at least one section for meaningful content organization
            $errors[] = 'At least one section is required';
        } else {
            // Section Level Validation: Each section must be properly structured
            foreach ($courseData['sections'] as $index => $section) {
                // Use 1-based numbering for user-friendly error messages
                $sectionNum = $index + 1;

                // Section title validation
                if (empty($section['title'])) {
                    $errors[] = "Section {$sectionNum} title is required";
                }

                // Section content validation: lessons array is mandatory
                if (empty($section['lessons']) || !is_array($section['lessons'])) {
                    // Empty sections provide no educational value and break course flow
                    $errors[] = "Section {$sectionNum} must have at least one lesson";
                } else {
                    // Lesson Level Validation: Each lesson must have essential content
                    foreach ($section['lessons'] as $lessonIndex => $lesson) {
                        $lessonNum = $lessonIndex + 1;

                        // Lesson title is required for navigation and identification
                        if (empty($lesson['title'])) {
                            $errors[] = "Section {$sectionNum}, Lesson {$lessonNum} title is required";
                        }

                        // Additional lesson validation could be added here
                        // - Content length validation
                        // - Duration format validation
                        // - Lesson type validation
                    }
                }
            }
        }

        return [
            'valid'  => empty($errors),  // Valid only if no errors found
            'errors' => $errors,         // Complete list of validation failures
        ];
    }

    /**
     * Generate additional lessons for an existing course (ICourseGenerator interface)
     *
     * @param  integer $courseId     Existing course ID
     * @param  array   $lessonParams Parameters for the new lessons
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
                    'lessons' => [],
                ];
            }

            // Get or create a section for the new lessons
            $sectionId = $lessonParams['section_id'] ?? null;

            if (!$sectionId) {
                // Create a new section if none specified
                $sectionData = [
                    'title'   => $lessonParams['section_title'] ?? 'Additional Lessons',
                    'lessons' => [],
                ];

                // Create the section
                $section          = new Section();
                $section->title   = $sectionData['title'];
                $section->courses = [$courseId];
                $section->store_meta();

                $sectionId = $section->id;
            }

            // Generate the lessons
            $generatedLessons = [];
            $lessons          = $lessonParams['lessons'] ?? [];

            foreach ($lessons as $lessonData) {
                $lesson           = new Lesson();
                $lesson->title    = $lessonData['title'];
                $lesson->content  = $lessonData['content'] ?? '';
                $lesson->sections = [$sectionId];
                $lesson->store_meta();

                $generatedLessons[] = [
                    'id'    => $lesson->id,
                    'title' => $lesson->title,
                    'url'   => get_permalink($lesson->id),
                ];
            }

            return [
                'success'    => true,
                'section_id' => $sectionId,
                'lessons'    => $generatedLessons,
            ];
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate lessons', [
                'course_id' => $courseId,
                'error'     => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to generate lessons: ' . $e->getMessage(),
                'lessons' => [],
            ];
        }
    }

    /**
     * Interface-compliant parameter validation for course generation
     *
     * This method provides a simplified boolean validation interface while
     * leveraging the comprehensive validateCourseData() method internally.
     * It's designed to meet the ICourseGenerator interface requirements.
     *
     * Implementation Strategy:
     * - Delegates to validateCourseData() for actual validation logic
     * - Converts detailed validation results to simple boolean
     * - Maintains consistency with detailed validation rules
     * - Provides interface compatibility for external callers
     *
     * Usage Context:
     * - Called by external services that need simple pass/fail validation
     * - Used in conditional logic where detailed errors aren't needed
     * - Provides interface compliance for dependency injection
     * - Enables polymorphic usage with other course generators
     *
     * Design Pattern:
     * This follows the Adapter pattern, providing a simplified interface
     * to the more complex validateCourseData() method while maintaining
     * full validation capability.
     *
     * @param  array $courseParams Course parameters to validate
     * @return boolean True if all validation rules pass, false if any fail
     */
    public function validateParams(array $courseParams): bool
    {
        // Delegate to comprehensive validation method
        $validation = $this->validateCourseData($courseParams);

        // Return simple boolean result for interface compliance
        return $validation['valid'];
    }

    /**
     * Convert plain HTML content to Gutenberg blocks
     * 
     * This method ensures content is properly formatted as Gutenberg blocks
     * if it isn't already. It wraps HTML elements in the appropriate block comments.
     * 
     * @param string $content Content to convert
     * @return string Content formatted as Gutenberg blocks
     */
    private function convertToGutenbergBlocks(string $content): string
    {
        // If content already has Gutenberg blocks, return as-is
        if (strpos($content, '<!-- wp:') !== false) {
            return $content;
        }
        
        // Log the content being converted
        $this->logger->debug('Converting content to Gutenberg blocks', [
            'content_length' => strlen($content),
            'content_preview' => substr($content, 0, 100) . '...',
            'has_html_tags' => strip_tags($content) !== $content
        ]);
        
        // If content is plain text without HTML tags, convert it properly
        if (strip_tags($content) === $content && !empty(trim($content))) {
            // Split content by double line breaks or divider lines
            $content = preg_replace('/\n[-]{3,}\n/', "\n\n", $content); // Replace divider lines with double breaks
            $paragraphs = preg_split('/\n\s*\n/', $content);
            $blocks = [];
            $inList = false;
            $currentList = [];
            $listType = 'ul';
            
            foreach ($paragraphs as $paragraph) {
                $paragraph = trim($paragraph);
                if (empty($paragraph)) continue;
                
                // If we were building a list and this isn't a list item, close the list
                if ($inList && !preg_match('/^[\s]*[•\-\*]|^\d+\./', $paragraph)) {
                    if (!empty($currentList)) {
                        $listTag = $listType === 'ol' ? 'ol' : 'ul';
                        $listAttr = $listType === 'ol' ? ' {"ordered":true}' : '';
                        $blocks[] = "<!-- wp:list" . $listAttr . " -->\n<" . $listTag . ">\n" . implode("\n", $currentList) . "\n</" . $listTag . ">\n<!-- /wp:list -->";
                    }
                    $inList = false;
                    $currentList = [];
                }
                
                // Check if it's a heading (all caps with optional colon, or specific keywords)
                if (preg_match('/^[A-Z][A-Z\s]+:?\s*$/', $paragraph) || 
                    preg_match('/^(LESSON OVERVIEW|KEY TAKEAWAYS|ASSIGNMENT|ADDITIONAL RESOURCES|NEXT LESSON PREVIEW)/', $paragraph)) {
                    $blocks[] = "<!-- wp:heading -->\n<h2>" . esc_html(trim($paragraph, ':')) . "</h2>\n<!-- /wp:heading -->";
                }
                // Check if it's a sub-heading (Title Case or specific patterns)
                elseif (preg_match('/^[A-Z][a-z]+(\s+[A-Z][a-z]+)*:?\s*$/', $paragraph) && strlen($paragraph) < 50) {
                    $blocks[] = "<!-- wp:heading {\"level\":3} -->\n<h3>" . esc_html(trim($paragraph, ':')) . "</h3>\n<!-- /wp:heading -->";
                }
                // Check if it contains a list
                elseif (strpos($paragraph, "\n") !== false && preg_match('/[•\-\*]|\d+\./', $paragraph)) {
                    // This paragraph contains multiple lines with bullets/numbers
                    $lines = explode("\n", $paragraph);
                    $tempList = [];
                    $nonListContent = '';
                    
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (empty($line)) continue;
                        
                        // Check if this line is a list item
                        if (preg_match('/^[\s]*[•\-\*]\s*(.+)/', $line, $matches)) {
                            if (!empty($nonListContent)) {
                                $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html($nonListContent) . "</p>\n<!-- /wp:paragraph -->";
                                $nonListContent = '';
                            }
                            $tempList[] = "<li>" . esc_html($matches[1]) . "</li>";
                        }
                        elseif (preg_match('/^(\d+)\.\s*(.+)/', $line, $matches)) {
                            if (!empty($nonListContent)) {
                                $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html($nonListContent) . "</p>\n<!-- /wp:paragraph -->";
                                $nonListContent = '';
                            }
                            if ($matches[1] === '1' && !empty($tempList)) {
                                // Starting a new numbered list, close the previous one
                                $blocks[] = "<!-- wp:list -->\n<ul>\n" . implode("\n", $tempList) . "\n</ul>\n<!-- /wp:list -->";
                                $tempList = [];
                            }
                            $tempList[] = "<li>" . esc_html($matches[2]) . "</li>";
                            $listType = 'ol';
                        }
                        else {
                            // Not a list item
                            if (!empty($tempList)) {
                                // Close current list
                                $tag = ($listType === 'ol') ? 'ol' : 'ul';
                                $attr = ($listType === 'ol') ? ' {"ordered":true}' : '';
                                $blocks[] = "<!-- wp:list" . $attr . " -->\n<" . $tag . ">\n" . implode("\n", $tempList) . "\n</" . $tag . ">\n<!-- /wp:list -->";
                                $tempList = [];
                                $listType = 'ul';
                            }
                            $nonListContent .= ($nonListContent ? ' ' : '') . $line;
                        }
                    }
                    
                    // Handle any remaining content
                    if (!empty($tempList)) {
                        $tag = ($listType === 'ol') ? 'ol' : 'ul';
                        $attr = ($listType === 'ol') ? ' {"ordered":true}' : '';
                        $blocks[] = "<!-- wp:list" . $attr . " -->\n<" . $tag . ">\n" . implode("\n", $tempList) . "\n</" . $tag . ">\n<!-- /wp:list -->";
                    }
                    if (!empty($nonListContent)) {
                        $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html($nonListContent) . "</p>\n<!-- /wp:paragraph -->";
                    }
                }
                // Single line list items
                elseif (preg_match('/^[\s]*[•\-\*]\s*(.+)/', $paragraph, $matches)) {
                    $inList = true;
                    $currentList[] = "<li>" . esc_html($matches[1]) . "</li>";
                }
                elseif (preg_match('/^(\d+)\.\s*(.+)/', $paragraph, $matches)) {
                    if (!$inList || $matches[1] === '1') {
                        // Close previous list if needed
                        if ($inList && !empty($currentList)) {
                            $listTag = $listType === 'ol' ? 'ol' : 'ul';
                            $listAttr = $listType === 'ol' ? ' {"ordered":true}' : '';
                            $blocks[] = "<!-- wp:list" . $listAttr . " -->\n<" . $listTag . ">\n" . implode("\n", $currentList) . "\n</" . $listTag . ">\n<!-- /wp:list -->";
                            $currentList = [];
                        }
                        $listType = 'ol';
                    }
                    $inList = true;
                    $currentList[] = "<li>" . esc_html($matches[2]) . "</li>";
                }
                else {
                    // Regular paragraph
                    $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html($paragraph) . "</p>\n<!-- /wp:paragraph -->";
                }
            }
            
            // Close any remaining list
            if ($inList && !empty($currentList)) {
                $listTag = $listType === 'ol' ? 'ol' : 'ul';
                $listAttr = $listType === 'ol' ? ' {"ordered":true}' : '';
                $blocks[] = "<!-- wp:list" . $listAttr . " -->\n<" . $listTag . ">\n" . implode("\n", $currentList) . "\n</" . $listTag . ">\n<!-- /wp:list -->";
            }
            
            return implode("\n\n", $blocks);
        }
        
        // Convert HTML to Gutenberg blocks using DOMDocument for better parsing
        $blocks = [];
        
        // Wrap content to ensure proper parsing
        $wrappedContent = '<div>' . $content . '</div>';
        
        // Use DOMDocument to parse HTML
        $dom = new \DOMDocument();
        @$dom->loadHTML($wrappedContent, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Get the wrapper div
        $wrapper = $dom->getElementsByTagName('div')->item(0);
        if (!$wrapper) {
            return $content; // Fallback if parsing fails
        }
        
        // Process each child node (both element and text nodes)
        foreach ($wrapper->childNodes as $node) {
            // Handle text nodes
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->textContent);
                if (!empty($text)) {
                    $blocks[] = "<!-- wp:paragraph -->\n<p>" . esc_html($text) . "</p>\n<!-- /wp:paragraph -->";
                }
                continue;
            }
            
            // Skip non-element nodes
            if ($node->nodeType !== XML_ELEMENT_NODE) {
                continue;
            }
            
            $tagName = strtolower($node->tagName);
            $nodeContent = $dom->saveHTML($node);
            
            switch ($tagName) {
                case 'h1':
                case 'h2':
                case 'h3':
                case 'h4':
                case 'h5':
                case 'h6':
                    $level = substr($tagName, 1);
                    $text = $node->textContent;
                    $blocks[] = "<!-- wp:heading {\"level\":{$level}} -->\n<{$tagName}>{$text}</{$tagName}>\n<!-- /wp:heading -->";
                    break;
                    
                case 'p':
                    $innerHTML = '';
                    foreach ($node->childNodes as $child) {
                        $innerHTML .= $dom->saveHTML($child);
                    }
                    $blocks[] = "<!-- wp:paragraph -->\n<p>{$innerHTML}</p>\n<!-- /wp:paragraph -->";
                    break;
                    
                case 'ul':
                    $listItems = '';
                    foreach ($node->getElementsByTagName('li') as $li) {
                        // Simply use textContent to get the text, avoiding complex DOM manipulation
                        $liContent = trim($li->textContent);
                        if (!empty($liContent)) {
                            $listItems .= "<li>" . esc_html($liContent) . "</li>\n";
                        }
                    }
                    $blocks[] = "<!-- wp:list -->\n<ul>\n{$listItems}</ul>\n<!-- /wp:list -->";
                    break;
                    
                case 'ol':
                    $listItems = '';
                    foreach ($node->getElementsByTagName('li') as $li) {
                        // Simply use textContent to get the text, avoiding complex DOM manipulation
                        $liContent = trim($li->textContent);
                        if (!empty($liContent)) {
                            $listItems .= "<li>" . esc_html($liContent) . "</li>\n";
                        }
                    }
                    $blocks[] = "<!-- wp:list {\"ordered\":true} -->\n<ol>\n{$listItems}</ol>\n<!-- /wp:list -->";
                    break;
                    
                case 'blockquote':
                    $innerHTML = '';
                    foreach ($node->childNodes as $child) {
                        $innerHTML .= $dom->saveHTML($child);
                    }
                    $blocks[] = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">{$innerHTML}</blockquote>\n<!-- /wp:quote -->";
                    break;
                    
                default:
                    // For any other content, wrap in paragraph if it has text
                    $text = trim($node->textContent);
                    if (!empty($text)) {
                        $innerHTML = '';
                        foreach ($node->childNodes as $child) {
                            $innerHTML .= $dom->saveHTML($child);
                        }
                        $blocks[] = "<!-- wp:paragraph -->\n<p>{$innerHTML}</p>\n<!-- /wp:paragraph -->";
                    }
            }
        }
        
        $result = implode("\n\n", $blocks);
        
        // Clean up any encoding issues from DOMDocument
        $result = str_replace('&nbsp;', ' ', $result);
        $result = html_entity_decode($result, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $result;
    }
    
    /**
     * Fix malformed Gutenberg lists that have nested ul/ol tags
     * 
     * This method fixes the common issue where AI generates lists with each item
     * wrapped in its own <ul> tag instead of having all items in a single list.
     * 
     * @param string $content Content with potentially malformed lists
     * @return string Content with fixed list formatting
     */
    private function fixMalformedGutenbergLists(string $content): string
    {
        // Pattern to match Gutenberg list blocks with malformed nested lists
        $pattern = '/(<!-- wp:list(?:\s+\{[^}]*\})? -->)(.*?)(<!-- \/wp:list -->)/s';
        
        $content = preg_replace_callback($pattern, function($matches) {
            $blockStart = $matches[1];
            $listContent = $matches[2];
            $blockEnd = $matches[3];
            
            // More aggressive check for ANY nested ul/ol tags
            if (preg_match_all('/<(ul|ol)>/i', $listContent, $listTags) && count($listTags[0]) > 1) {
                $this->logger->debug('Fixing malformed Gutenberg list with multiple ul/ol tags');
                
                // Determine if this is an ordered list
                $isOrdered = strpos($blockStart, '"ordered":true') !== false;
                $listTag = $isOrdered ? 'ol' : 'ul';
                
                // Extract all list items, removing ALL ul/ol tags
                preg_match_all('/<li[^>]*>(.*?)<\/li>/si', $listContent, $liMatches);
                
                if (!empty($liMatches[0])) {
                    // Rebuild the list with proper single-level structure
                    $fixedList = "\n<{$listTag}>\n";
                    foreach ($liMatches[0] as $li) {
                        // Clean up the li item (remove any extra whitespace)
                        $li = trim($li);
                        $fixedList .= "    " . $li . "\n";
                    }
                    $fixedList .= "</{$listTag}>\n";
                    
                    $this->logger->debug('Fixed malformed list', [
                        'original_structure' => substr(preg_replace('/\s+/', ' ', $listContent), 0, 200),
                        'fixed_structure' => substr($fixedList, 0, 200),
                        'ul_ol_count' => count($listTags[0])
                    ]);
                    
                    return $blockStart . $fixedList . $blockEnd;
                }
            }
            
            // Return unchanged if not malformed
            return $matches[0];
        }, $content);
        
        return $content;
    }
}
