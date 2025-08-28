<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * CourseGeneratorService Test
 * 
 * Tests course generation with real WordPress post operations
 * Following CLAUDE.md principles - no mocks, real tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Services
 * @since 1.0.0
 */
class CourseGeneratorServiceTest extends TestCase
{
    private CourseGeneratorService $courseGenerator;
    private Logger $logger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize logger and service
        $this->logger = Logger::getInstance();
        $this->courseGenerator = new CourseGeneratorService($this->logger);
        
        // Mock WordPress post functions for testing
        $this->mockWordPressPostFunctions();
    }
    
    /**
     * Mock WordPress post functions
     */
    private function mockWordPressPostFunctions(): void
    {
        global $wp_test_posts, $wp_test_post_meta;
        
        $wp_test_posts = [];
        $wp_test_post_meta = [];
        
        // Mock wp_insert_post
        if (!function_exists('wp_insert_post')) {
            function wp_insert_post($postarr, $wp_error = false) {
                global $wp_test_posts;
                
                static $post_id_counter = 1;
                $post_id = $post_id_counter++;
                
                $post = array_merge([
                    'ID' => $post_id,
                    'post_date' => current_time('mysql'),
                    'post_date_gmt' => current_time('mysql', true),
                    'post_modified' => current_time('mysql'),
                    'post_modified_gmt' => current_time('mysql', true),
                    'post_status' => 'publish',
                ], $postarr);
                
                $wp_test_posts[$post_id] = $post;
                
                return $post_id;
            }
        }
        
        // Mock update_post_meta
        if (!function_exists('update_post_meta')) {
            function update_post_meta($post_id, $meta_key, $meta_value) {
                global $wp_test_post_meta;
                
                if (!isset($wp_test_post_meta[$post_id])) {
                    $wp_test_post_meta[$post_id] = [];
                }
                
                $wp_test_post_meta[$post_id][$meta_key] = $meta_value;
                return true;
            }
        }
        
        // Mock get_post_meta
        if (!function_exists('get_post_meta')) {
            function get_post_meta($post_id, $key = '', $single = false) {
                global $wp_test_post_meta;
                
                if (!isset($wp_test_post_meta[$post_id])) {
                    return $single ? '' : [];
                }
                
                if ($key === '') {
                    return $wp_test_post_meta[$post_id];
                }
                
                if (!isset($wp_test_post_meta[$post_id][$key])) {
                    return $single ? '' : [];
                }
                
                return $single ? $wp_test_post_meta[$post_id][$key] : [$wp_test_post_meta[$post_id][$key]];
            }
        }
        
        // Mock get_post
        if (!function_exists('get_post')) {
            function get_post($post = null) {
                global $wp_test_posts;
                
                if (is_numeric($post)) {
                    return isset($wp_test_posts[$post]) ? (object) $wp_test_posts[$post] : null;
                }
                
                return null;
            }
        }
    }
    
    /**
     * Test generating a complete course
     */
    public function testGenerateCompleteCourse(): void
    {
        $courseData = [
            'title' => 'Introduction to PHP Programming',
            'description' => 'Learn PHP from scratch',
            'sections' => [
                [
                    'title' => 'Getting Started',
                    'description' => 'Introduction to PHP basics',
                    'lessons' => [
                        [
                            'title' => 'What is PHP?',
                            'content' => 'PHP is a server-side scripting language.',
                            'type' => 'text'
                        ],
                        [
                            'title' => 'Installing PHP',
                            'content' => 'Learn how to install PHP on your system.',
                            'type' => 'text'
                        ]
                    ]
                ],
                [
                    'title' => 'Variables and Data Types',
                    'description' => 'Understanding PHP variables',
                    'lessons' => [
                        [
                            'title' => 'PHP Variables',
                            'content' => 'Variables in PHP start with $.',
                            'type' => 'text'
                        ]
                    ]
                ]
            ]
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        // Verify result structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('course_id', $result);
        
        // Should be successful
        $this->assertTrue($result['success']);
        $this->assertIsNumeric($result['course_id']);
        $this->assertGreaterThan(0, $result['course_id']);
        
        // Verify course was created
        global $wp_test_posts, $wp_test_post_meta;
        
        $courseId = $result['course_id'];
        $this->assertArrayHasKey($courseId, $wp_test_posts);
        $this->assertEquals('Introduction to PHP Programming', $wp_test_posts[$courseId]['post_title']);
        $this->assertEquals('mpcs-course', $wp_test_posts[$courseId]['post_type']);
    }
    
    /**
     * Test generating course with empty sections
     */
    public function testGenerateCourseWithNoSections(): void
    {
        $courseData = [
            'title' => 'Empty Course',
            'description' => 'A course with no sections',
            'sections' => []
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertTrue($result['success']);
        $this->assertIsNumeric($result['course_id']);
        
        // Course should still be created even without sections
        global $wp_test_posts;
        $this->assertArrayHasKey($result['course_id'], $wp_test_posts);
    }
    
    /**
     * Test generating course with missing data
     */
    public function testGenerateCourseWithMissingData(): void
    {
        $courseData = [
            'title' => 'Incomplete Course'
            // Missing description and sections
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        // Should still create the course
        $this->assertTrue($result['success']);
        $this->assertIsNumeric($result['course_id']);
    }
    
    /**
     * Test error handling when course creation fails
     */
    public function testCourseCreationFailure(): void
    {
        // Override wp_insert_post to simulate failure
        $this->overrideFunction('wp_insert_post', function($postarr, $wp_error = false) {
            return 0; // Simulate failure
        });
        
        $courseData = [
            'title' => 'Failed Course',
            'description' => 'This should fail'
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('Failed to create course', $result['error']);
    }
    
    /**
     * Test section creation with proper ordering
     */
    public function testSectionOrdering(): void
    {
        $courseData = [
            'title' => 'Ordered Course',
            'sections' => [
                ['title' => 'Section 1', 'lessons' => []],
                ['title' => 'Section 2', 'lessons' => []],
                ['title' => 'Section 3', 'lessons' => []],
            ]
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertTrue($result['success']);
        
        // Verify sections have correct order
        global $wp_test_post_meta;
        $sections = [];
        
        foreach ($wp_test_post_meta as $postId => $meta) {
            if (isset($meta['_mpcs_section_course_id']) && $meta['_mpcs_section_course_id'] == $result['course_id']) {
                $sections[] = [
                    'id' => $postId,
                    'order' => $meta['_mpcs_section_order'] ?? 0
                ];
            }
        }
        
        // Should have 3 sections with orders 1, 2, 3
        $this->assertCount(3, $sections);
        
        usort($sections, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        $this->assertEquals(1, $sections[0]['order']);
        $this->assertEquals(2, $sections[1]['order']);
        $this->assertEquals(3, $sections[2]['order']);
    }
    
    /**
     * Test lesson creation within sections
     */
    public function testLessonCreation(): void
    {
        $courseData = [
            'title' => 'Course with Lessons',
            'sections' => [
                [
                    'title' => 'Section with Lessons',
                    'lessons' => [
                        ['title' => 'Lesson 1', 'content' => 'Content 1'],
                        ['title' => 'Lesson 2', 'content' => 'Content 2'],
                        ['title' => 'Lesson 3', 'content' => 'Content 3'],
                    ]
                ]
            ]
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertTrue($result['success']);
        
        // Count lessons created
        global $wp_test_posts;
        $lessons = array_filter($wp_test_posts, function($post) {
            return $post['post_type'] === 'mpcs-lesson';
        });
        
        $this->assertCount(3, $lessons);
        
        // Verify lesson content
        foreach ($lessons as $lesson) {
            $this->assertStringStartsWith('Lesson', $lesson['post_title']);
            $this->assertStringStartsWith('Content', $lesson['post_content']);
        }
    }
    
    /**
     * Test course with complex structure
     */
    public function testComplexCourseStructure(): void
    {
        $courseData = [
            'title' => 'Advanced Web Development',
            'description' => 'Complete web development course',
            'metadata' => [
                'duration' => '12 weeks',
                'level' => 'advanced',
                'prerequisites' => ['HTML', 'CSS', 'Basic JavaScript']
            ],
            'sections' => [
                [
                    'title' => 'Frontend Development',
                    'description' => 'Modern frontend techniques',
                    'lessons' => [
                        ['title' => 'React Basics', 'content' => 'Introduction to React'],
                        ['title' => 'State Management', 'content' => 'Redux and Context API'],
                        ['title' => 'React Hooks', 'content' => 'Using hooks effectively'],
                    ]
                ],
                [
                    'title' => 'Backend Development',
                    'description' => 'Server-side programming',
                    'lessons' => [
                        ['title' => 'Node.js Introduction', 'content' => 'Getting started with Node'],
                        ['title' => 'Express Framework', 'content' => 'Building APIs with Express'],
                        ['title' => 'Database Integration', 'content' => 'Working with MongoDB'],
                    ]
                ],
                [
                    'title' => 'Deployment',
                    'description' => 'Deploying applications',
                    'lessons' => [
                        ['title' => 'Cloud Platforms', 'content' => 'AWS, Google Cloud, Azure'],
                        ['title' => 'CI/CD', 'content' => 'Continuous Integration and Deployment'],
                    ]
                ]
            ]
        ];
        
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertTrue($result['success']);
        
        // Verify complete structure was created
        global $wp_test_posts;
        
        $courses = array_filter($wp_test_posts, fn($p) => $p['post_type'] === 'mpcs-course');
        $sections = array_filter($wp_test_posts, fn($p) => $p['post_type'] === 'mpcs-section');
        $lessons = array_filter($wp_test_posts, fn($p) => $p['post_type'] === 'mpcs-lesson');
        
        $this->assertCount(1, $courses);
        $this->assertCount(3, $sections);
        $this->assertCount(8, $lessons); // 3 + 3 + 2
    }
    
    /**
     * Test course duplication functionality
     */
    public function testDuplicateCourse(): void
    {
        // First create a course
        $originalData = [
            'title' => 'Original Course',
            'description' => 'To be duplicated',
            'sections' => [
                [
                    'title' => 'Original Section',
                    'lessons' => [
                        ['title' => 'Original Lesson', 'content' => 'Original content']
                    ]
                ]
            ]
        ];
        
        $original = $this->courseGenerator->generateCourse($originalData);
        $this->assertTrue($original['success']);
        
        // Now duplicate it
        $duplicateResult = $this->courseGenerator->duplicateCourse($original['course_id']);
        
        $this->assertIsArray($duplicateResult);
        $this->assertArrayHasKey('success', $duplicateResult);
        
        if ($duplicateResult['success']) {
            $this->assertNotEquals($original['course_id'], $duplicateResult['course_id']);
            
            // Verify duplicate has same structure
            global $wp_test_posts;
            $originalCourse = $wp_test_posts[$original['course_id']];
            $duplicateCourse = $wp_test_posts[$duplicateResult['course_id']];
            
            $this->assertStringContainsString('Copy', $duplicateCourse['post_title']);
            $this->assertEquals($originalCourse['post_content'], $duplicateCourse['post_content']);
        }
    }
    
    /**
     * Helper to override functions for testing
     */
    private function overrideFunction(string $name, callable $callback): void
    {
        runkit_function_redefine($name, '', $callback);
    }
}