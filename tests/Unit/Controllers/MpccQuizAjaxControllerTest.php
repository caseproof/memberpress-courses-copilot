<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Unit\Controllers;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\MpccQuizAjaxController;
use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Security\NonceConstants;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Comprehensive tests for MpccQuizAjaxController
 * 
 * Tests all AJAX endpoints, security checks, sanitization, and error handling
 * 
 * @package MemberPressCoursesCopilot\Tests\Unit\Controllers
 * @since 1.0.0
 */
class MpccQuizAjaxControllerTest extends TestCase
{
    private MpccQuizAjaxController $controller;
    private MockObject $mockQuizService;
    private MockObject $mockLogger;

    /**
     * Set up test fixtures before each test
     * 
     * Creates mocked dependencies and controller instance
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $this->mockQuizService = $this->createMock(MpccQuizAIService::class);
        $this->mockLogger = $this->createMock(Logger::class);
        
        // Create controller with mocked dependencies
        $this->controller = new MpccQuizAjaxController($this->mockQuizService, $this->mockLogger);
        
        // Mock WordPress functions that aren't covered by bootstrap
        $this->mockWordPressFunctions();
        
        // Set default user capabilities
        $this->setCurrentUserCaps(['edit_posts' => true]);
    }

    /**
     * Mock WordPress functions needed for tests
     */
    private function mockWordPressFunctions(): void
    {
        // Mock functions that return posts
        if (!function_exists('get_post')) {
            function get_post($id) {
                // Return mock post data based on ID
                switch ($id) {
                    case 123:
                        return (object) [
                            'ID' => 123,
                            'post_title' => 'Test Lesson',
                            'post_content' => 'This is test lesson content',
                            'post_type' => 'mpcs-lesson',
                            'post_excerpt' => 'Test excerpt'
                        ];
                    case 456:
                        return (object) [
                            'ID' => 456,
                            'post_title' => 'Test Course',
                            'post_content' => 'This is test course content',
                            'post_type' => 'mpcs-course'
                        ];
                    default:
                        return null;
                }
            }
        }

        if (!function_exists('get_post_meta')) {
            function get_post_meta($id, $key, $single = false) {
                // Mock post meta based on ID and key
                $meta = [
                    123 => [
                        '_mpcs_course_id' => 456,
                        '_mpcs_lesson_section_id' => 789,
                        '_mpcs_lesson_lesson_order' => 1
                    ]
                ];
                
                if (isset($meta[$id][$key])) {
                    return $single ? $meta[$id][$key] : [$meta[$id][$key]];
                }
                
                return $single ? '' : [];
            }
        }

        if (!function_exists('update_post_meta')) {
            function update_post_meta($id, $key, $value) {
                return true;
            }
        }

        if (!function_exists('wp_insert_post')) {
            function wp_insert_post($data) {
                return 999; // Mock quiz ID
            }
        }

        if (!function_exists('get_posts')) {
            function get_posts($args) {
                // Return mock lessons for course
                if (isset($args['post_type']) && $args['post_type'] === 'mpcs-lesson') {
                    return [
                        (object) [
                            'ID' => 123,
                            'post_title' => 'Lesson 1',
                            'post_content' => 'Lesson 1 content'
                        ],
                        (object) [
                            'ID' => 124,
                            'post_title' => 'Lesson 2', 
                            'post_content' => 'Lesson 2 content'
                        ]
                    ];
                }
                return [];
            }
        }

        if (!function_exists('wp_strip_all_tags')) {
            function wp_strip_all_tags($text) {
                return strip_tags($text);
            }
        }

        if (!function_exists('sanitize_textarea_field')) {
            function sanitize_textarea_field($text) {
                return trim(strip_tags($text));
            }
        }

        if (!function_exists('absint')) {
            function absint($value) {
                return abs(intval($value));
            }
        }

        if (!function_exists('add_query_arg')) {
            function add_query_arg($args, $url) {
                return $url . '?' . http_build_query($args);
            }
        }
    }

    /**
     * Test successful quiz generation with valid input
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::extractAndSanitizeInput
     * @covers MpccQuizAjaxController::parseQuizOptions
     * @covers MpccQuizAjaxController::getQuizContent
     */
    public function testGenerateQuizSuccess(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123,
            'course_id' => 456,
            'content' => 'Test lesson content',
            'options' => json_encode([
                'num_questions' => 5,
                'question_type' => 'multiple_choice',
                'difficulty' => 'medium'
            ])
        ]);

        // Mock quiz service response
        $mockQuestions = [
            [
                'question' => 'Test question?',
                'options' => ['A' => 'Option 1', 'B' => 'Option 2', 'C' => 'Option 3', 'D' => 'Option 4'],
                'correct_answer' => 'A',
                'explanation' => 'Test explanation'
            ]
        ];

        $this->mockQuizService
            ->expects($this->once())
            ->method('generateQuestions')
            ->with(
                $this->equalTo('This is test lesson content'),
                $this->equalTo([
                    'type' => 'multiple_choice',
                    'count' => 5,
                    'difficulty' => 'medium',
                    'custom_prompt' => ''
                ])
            )
            ->willReturn($mockQuestions);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('questions', $response['data']);
        $this->assertCount(1, $response['data']['questions']);
        $this->assertEquals('multiple_choice', $response['data']['type']);
    }

    /**
     * Test quiz generation fails with invalid nonce
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::verifyQuizNonce
     */
    public function testGenerateQuizFailsWithInvalidNonce(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => 'invalid-nonce',
            'lesson_id' => 123,
            'content' => 'Test content'
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Security check failed', $response['data']['message']);
    }

    /**
     * Test quiz generation fails with insufficient permissions
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testGenerateQuizFailsWithInsufficientPermissions(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123,
            'content' => 'Test content'
        ]);
        
        // Remove edit_posts capability
        $this->setCurrentUserCaps(['read' => true]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['data']['message']);
    }

    /**
     * Test quiz generation handles empty content
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::getQuizContent
     */
    public function testGenerateQuizHandlesEmptyContent(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'content' => '',
            'lesson_id' => 0,
            'course_id' => 0
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('No content available', $response['data']['message']);
    }

    /**
     * Test quiz generation handles AI service error response
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     */
    public function testGenerateQuizHandlesAIServiceError(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123,
            'options' => json_encode(['num_questions' => 5])
        ]);

        // Mock service to return error
        $this->mockQuizService
            ->expects($this->once())
            ->method('generateQuestions')
            ->willReturn([
                'error' => true,
                'message' => 'Content too short',
                'suggestion' => 'Please provide more detailed content'
            ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Content too short', $response['data']['message']);
        $this->assertArrayHasKey('data', $response['data']);
        $this->assertEquals('Please provide more detailed content', $response['data']['data']['suggestion']);
    }

    /**
     * Test sanitizeArray method with different data types
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayWithDifferentTypes(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        // Test text sanitization
        $textData = [
            'name' => '  John Doe  <script>alert("xss")</script>',
            'message' => '<p>Hello <b>World</b></p>',
            'nested' => [
                'value' => '  Nested <script>Value</script>  '
            ]
        ];

        $sanitized = $method->invoke($this->controller, $textData, 'text');
        
        $this->assertEquals('John Doe', $sanitized['name']);
        $this->assertEquals('Hello World', $sanitized['message']);
        $this->assertEquals('Nested Value', $sanitized['nested']['value']);

        // Test integer sanitization
        $intData = ['count' => '123.45', 'invalid' => 'abc'];
        $sanitizedInt = $method->invoke($this->controller, $intData, 'int');
        
        $this->assertEquals(123, $sanitizedInt['count']);
        $this->assertEquals(0, $sanitizedInt['invalid']);

        // Test email sanitization
        $emailData = ['email' => 'test@example.com<script>'];
        $sanitizedEmail = $method->invoke($this->controller, $emailData, 'email');
        
        $this->assertEquals('test@example.com', $sanitizedEmail['email']);
    }

    /**
     * Test parseQuizOptions with different input formats
     * 
     * @covers MpccQuizAjaxController::parseQuizOptions
     */
    public function testParseQuizOptionsWithDifferentFormats(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('parseQuizOptions');
        $method->setAccessible(true);

        // Test array input
        $arrayOptions = [
            'num_questions' => 10,
            'question_type' => 'multiple_choice',
            'difficulty' => 'hard'
        ];
        
        $parsed = $method->invoke($this->controller, $arrayOptions);
        $this->assertEquals(10, $parsed['num_questions']);
        $this->assertEquals('multiple_choice', $parsed['question_type']);

        // Test JSON string input
        $jsonOptions = json_encode($arrayOptions);
        $parsedJson = $method->invoke($this->controller, $jsonOptions);
        $this->assertEquals($arrayOptions['num_questions'], $parsedJson['num_questions']);

        // Test invalid input
        $invalidOptions = 'invalid-json-string';
        $parsedInvalid = $method->invoke($this->controller, $invalidOptions);
        $this->assertEquals([], $parsedInvalid);
    }

    /**
     * Test regenerate_question method success scenario
     * 
     * @covers MpccQuizAjaxController::regenerate_question
     */
    public function testRegenerateQuestionSuccess(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'question' => json_encode([
                'type' => 'multiple_choice',
                'question' => 'Original question?'
            ]),
            'content' => 'Test content for regeneration',
            'options' => json_encode(['type' => 'multiple_choice'])
        ]);

        // Mock service response
        $mockNewQuestions = [
            [
                'question' => 'New regenerated question?',
                'options' => ['A' => 'New Option 1', 'B' => 'New Option 2'],
                'correct_answer' => 'A'
            ]
        ];

        $this->mockQuizService
            ->expects($this->once())
            ->method('generateQuestions')
            ->with(
                $this->equalTo('Test content for regeneration'),
                $this->equalTo([
                    'type' => 'multiple_choice',
                    'count' => 5
                ])
            )
            ->willReturn($mockNewQuestions);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->regenerate_question();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals('New regenerated question?', $response['data']['question']);
    }

    /**
     * Test regenerate_question with missing parameters
     * 
     * @covers MpccQuizAjaxController::regenerate_question
     */
    public function testRegenerateQuestionWithMissingParameters(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI)
            // Missing question and content
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->regenerate_question();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Question and content are required', $response['data']['message']);
    }

    /**
     * Test validate_quiz method with valid quiz data
     * 
     * @covers MpccQuizAjaxController::validate_quiz
     * @covers MpccQuizAjaxController::validateQuizData
     */
    public function testValidateQuizWithValidData(): void
    {
        // Arrange
        $validQuizData = [
            'title' => 'Test Quiz',
            'questions' => [
                [
                    'question' => 'What is 2+2?',
                    'type' => 'multiple_choice',
                    'options' => ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6'],
                    'correct_answer' => 'B',
                    'points' => 1
                ],
                [
                    'statement' => 'The sky is blue',
                    'type' => 'true_false',
                    'correct_answer' => true,
                    'points' => 1
                ]
            ]
        ];

        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'quiz_data' => json_encode($validQuizData)
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        $this->assertTrue($response['success']);
        $this->assertTrue($response['data']['valid']);
        $this->assertEmpty($response['data']['errors']);
        $this->assertEquals(2, $response['data']['summary']['total_questions']);
        $this->assertEquals(2, $response['data']['summary']['total_points']);
    }

    /**
     * Test validate_quiz with invalid quiz data
     * 
     * @covers MpccQuizAjaxController::validateQuizData
     */
    public function testValidateQuizWithInvalidData(): void
    {
        // Arrange
        $invalidQuizData = [
            'questions' => [
                [
                    // Missing question text
                    'type' => 'multiple_choice',
                    'options' => ['A' => 'Option 1'], // Too few options
                    'correct_answer' => 'C' // Answer not in options
                ],
                [
                    'question' => 'Valid question?',
                    'type' => 'true_false'
                    // Missing correct_answer
                ]
            ]
        ];

        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'quiz_data' => json_encode($invalidQuizData)
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        $this->assertTrue($response['success']);
        $this->assertFalse($response['data']['valid']);
        $this->assertNotEmpty($response['data']['errors']);
        
        // Should have multiple validation errors
        $errors = $response['data']['errors'];
        $this->assertGreaterThan(2, count($errors));
    }

    /**
     * Test create_quiz_from_lesson success scenario
     * 
     * @covers MpccQuizAjaxController::create_quiz_from_lesson
     */
    public function testCreateQuizFromLessonSuccess(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123,
            'course_id' => 456
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->create_quiz_from_lesson();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals(999, $response['data']['quiz_id']); // Mock quiz ID
        $this->assertEquals(123, $response['data']['lesson_id']);
        $this->assertEquals(456, $response['data']['course_id']);
        $this->assertStringContains('post.php?post=999', $response['data']['edit_url']);
    }

    /**
     * Test create_quiz_from_lesson with invalid lesson ID
     * 
     * @covers MpccQuizAjaxController::create_quiz_from_lesson
     */
    public function testCreateQuizFromLessonWithInvalidLessonId(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 999 // Non-existent lesson
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->create_quiz_from_lesson();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Invalid lesson ID', $response['data']['message']);
    }

    /**
     * Test get_lesson_course method
     * 
     * @covers MpccQuizAjaxController::get_lesson_course
     */
    public function testGetLessonCourseSuccess(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->get_lesson_course();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals(123, $response['data']['lesson_id']);
        $this->assertEquals(456, $response['data']['course_id']);
        $this->assertEquals('Test Course', $response['data']['course_title']);
    }

    /**
     * Test get_course_lessons method
     * 
     * @covers MpccQuizAjaxController::get_course_lessons
     */
    public function testGetCourseLessonsSuccess(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'course_id' => 456
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->get_course_lessons();
        });

        $this->assertTrue($response['success']);
        $this->assertEquals(456, $response['data']['course_id']);
        $this->assertEquals('Test Course', $response['data']['course_title']);
        $this->assertEquals(2, $response['data']['lesson_count']);
        $this->assertCount(2, $response['data']['lessons']);
    }

    /**
     * Test extractAndSanitizeInput method
     * 
     * @covers MpccQuizAjaxController::extractAndSanitizeInput
     */
    public function testExtractAndSanitizeInput(): void
    {
        // Arrange
        $this->setPostData([
            'lesson_id' => '123.45', // Float should become int
            'course_id' => 'invalid', // Invalid should become 0
            'content' => '  <script>alert("xss")</script>Test content  ',
            'options' => ['test' => 'value']
        ]);

        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractAndSanitizeInput');
        $method->setAccessible(true);

        // Act
        $result = $method->invoke($this->controller);

        // Assert
        $this->assertEquals(123, $result['lessonId']);
        $this->assertEquals(0, $result['courseId']);
        $this->assertEquals('Test content', $result['content']);
        $this->assertIsArray($result['options']);
    }

    /**
     * Test prepareGenerationOptions method
     * 
     * @covers MpccQuizAjaxController::prepareGenerationOptions
     */
    public function testPrepareGenerationOptions(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('prepareGenerationOptions');
        $method->setAccessible(true);

        // Test with complete options
        $options = [
            'question_type' => 'true_false',
            'num_questions' => '15',
            'difficulty' => 'hard',
            'custom_prompt' => 'Focus on key concepts'
        ];

        $result = $method->invoke($this->controller, $options);

        $this->assertEquals('true_false', $result['type']);
        $this->assertEquals(15, $result['count']);
        $this->assertEquals('hard', $result['difficulty']);
        $this->assertEquals('Focus on key concepts', $result['custom_prompt']);

        // Test with default values
        $emptyOptions = [];
        $resultDefaults = $method->invoke($this->controller, $emptyOptions);

        $this->assertEquals('multiple_choice', $resultDefaults['type']);
        $this->assertEquals(10, $resultDefaults['count']);
        $this->assertEquals('medium', $resultDefaults['difficulty']);
        $this->assertEquals('', $resultDefaults['custom_prompt']);
    }

    /**
     * Test getLessonContent method
     * 
     * @covers MpccQuizAjaxController::getLessonContent
     */
    public function testGetLessonContent(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getLessonContent');
        $method->setAccessible(true);

        // Test valid lesson
        $content = $method->invoke($this->controller, 123);
        $this->assertEquals('This is test lesson content', $content);

        // Test non-existent lesson
        $emptyContent = $method->invoke($this->controller, 999);
        $this->assertEquals('', $emptyContent);
    }

    /**
     * Test getCourseContent method
     * 
     * @covers MpccQuizAjaxController::getCourseContent
     */
    public function testGetCourseContent(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getCourseContent');
        $method->setAccessible(true);

        // Test valid course
        $content = $method->invoke($this->controller, 456);
        $this->assertStringContains('This is test course content', $content);
        $this->assertStringContains('Lesson 1 content', $content);
        $this->assertStringContains('Lesson 2 content', $content);

        // Test non-existent course
        $emptyContent = $method->invoke($this->controller, 999);
        $this->assertEquals('', $emptyContent);
    }

    /**
     * Test formatSuccessfulQuizResponse method
     * 
     * @covers MpccQuizAjaxController::formatSuccessfulQuizResponse
     */
    public function testFormatSuccessfulQuizResponse(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('formatSuccessfulQuizResponse');
        $method->setAccessible(true);

        // Test with questions array directly
        $result = [
            [
                'question' => 'Test question?',
                'type' => 'multiple_choice'
            ],
            [
                'question' => 'Another question?',
                'type' => 'true_false'
            ]
        ];

        $formatted = $method->invoke($this->controller, $result, 'multiple_choice', 123, 456);

        $this->assertArrayHasKey('questions', $formatted);
        $this->assertArrayHasKey('total', $formatted);
        $this->assertArrayHasKey('type', $formatted);
        $this->assertEquals(2, $formatted['total']);
        $this->assertEquals('multiple_choice', $formatted['type']);
        $this->assertCount(2, $formatted['questions']);

        // Test with wrapped questions
        $wrappedResult = [
            'questions' => $result,
            'suggestion' => 'Test suggestion'
        ];

        $formattedWrapped = $method->invoke($this->controller, $wrappedResult, 'multiple_choice', 123, 456);
        $this->assertEquals('Test suggestion', $formattedWrapped['suggestion']);
    }

    /**
     * Test formatSuccessfulQuizResponse throws exception for empty questions
     * 
     * @covers MpccQuizAjaxController::formatSuccessfulQuizResponse
     */
    public function testFormatSuccessfulQuizResponseThrowsExceptionForEmptyQuestions(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('formatSuccessfulQuizResponse');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to generate quiz questions');

        $method->invoke($this->controller, [], 'multiple_choice', 123, 456);
    }

    /**
     * Test handleAjaxError method
     * 
     * @covers MpccQuizAjaxController::handleAjaxError
     */
    public function testHandleAjaxError(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('handleAjaxError');
        $method->setAccessible(true);

        // Expect logger to be called
        $this->mockLogger
            ->expects($this->once())
            ->method('error')
            ->with(
                $this->equalTo('Test error context'),
                $this->arrayHasKey('error')
            );

        $testException = new \Exception('Test error message');

        // Act & Assert
        $response = $this->captureJsonOutput(function() use ($method, $testException) {
            $method->invoke($this->controller, $testException, 'Test error context');
        });

        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test verifyQuizNonce method
     * 
     * @covers MpccQuizAjaxController::verifyQuizNonce
     */
    public function testVerifyQuizNonce(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyQuizNonce');
        $method->setAccessible(true);

        // Test valid nonce
        $validNonce = wp_create_nonce(NonceConstants::QUIZ_AI);
        $this->setPostData(['nonce' => $validNonce]);
        
        $result = $method->invoke($this->controller);
        $this->assertTrue($result);

        // Test invalid nonce
        $this->setPostData(['nonce' => 'invalid-nonce']);
        $result = $method->invoke($this->controller);
        $this->assertFalse($result);

        // Test missing nonce
        $this->setPostData([]);
        $result = $method->invoke($this->controller);
        $this->assertFalse($result);
    }

    /**
     * Test verifyUserPermissions method
     * 
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testVerifyUserPermissions(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        // Test with edit_posts capability
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $result = $method->invoke($this->controller);
        $this->assertTrue($result);

        // Test without edit_posts capability
        $this->setCurrentUserCaps(['read' => true]);
        $result = $method->invoke($this->controller);
        $this->assertFalse($result);
    }

    /**
     * Test quiz validation with different question types
     * 
     * @covers MpccQuizAjaxController::validateQuizData
     * @dataProvider questionTypeValidationProvider
     */
    public function testQuestionTypeValidation(array $question, bool $expectedValid, array $expectedErrors): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateQuizData');
        $method->setAccessible(true);

        $quizData = ['questions' => [$question]];
        $result = $method->invoke($this->controller, $quizData);

        $this->assertEquals($expectedValid, $result['valid']);
        
        if (!$expectedValid) {
            foreach ($expectedErrors as $expectedError) {
                $this->assertContains($expectedError, $result['errors']);
            }
        }
    }

    /**
     * Data provider for question type validation tests
     * 
     * @return array Test cases with question data, expected validity, and expected errors
     */
    public function questionTypeValidationProvider(): array
    {
        return [
            'valid_multiple_choice' => [
                [
                    'question' => 'What is 2+2?',
                    'type' => 'multiple_choice',
                    'options' => ['A' => '3', 'B' => '4', 'C' => '5', 'D' => '6'],
                    'correct_answer' => 'B'
                ],
                true,
                []
            ],
            'invalid_multiple_choice_few_options' => [
                [
                    'question' => 'What is 2+2?',
                    'type' => 'multiple_choice',
                    'options' => ['A' => '4'], // Only one option
                    'correct_answer' => 'A'
                ],
                false,
                ['Question 1: At least 2 options are required']
            ],
            'invalid_multiple_choice_wrong_answer' => [
                [
                    'question' => 'What is 2+2?',
                    'type' => 'multiple_choice',
                    'options' => ['A' => '3', 'B' => '4'],
                    'correct_answer' => 'C' // Not in options
                ],
                false,
                ['Question 1: Correct answer is not in options']
            ],
            'valid_true_false' => [
                [
                    'statement' => 'The sky is blue',
                    'type' => 'true_false',
                    'correct_answer' => true
                ],
                true,
                []
            ],
            'invalid_true_false_missing_statement' => [
                [
                    'type' => 'true_false',
                    'correct_answer' => true
                ],
                false,
                ['Question 1: Statement is missing']
            ],
            'valid_text_answer' => [
                [
                    'question' => 'What is the capital of France?',
                    'type' => 'text_answer',
                    'correct_answer' => 'Paris'
                ],
                true,
                []
            ],
            'invalid_text_answer_missing_answer' => [
                [
                    'question' => 'What is the capital of France?',
                    'type' => 'text_answer'
                ],
                false,
                ['Question 1: Correct answer is missing']
            ],
            'valid_multiple_select' => [
                [
                    'question' => 'Which are programming languages?',
                    'type' => 'multiple_select',
                    'options' => ['A' => 'PHP', 'B' => 'JavaScript', 'C' => 'HTML', 'D' => 'Python'],
                    'correct_answers' => ['A', 'B', 'D']
                ],
                true,
                []
            ],
            'invalid_multiple_select_few_options' => [
                [
                    'question' => 'Which are programming languages?',
                    'type' => 'multiple_select',
                    'options' => ['A' => 'PHP', 'B' => 'JavaScript'], // Only 2 options
                    'correct_answers' => ['A', 'B']
                ],
                false,
                ['Question 1: At least 3 options are required for multiple select']
            ]
        ];
    }

    /**
     * Test getQuizContent method with different parameters
     * 
     * @covers MpccQuizAjaxController::getQuizContent
     */
    public function testGetQuizContentWithDifferentParameters(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('getQuizContent');
        $method->setAccessible(true);

        // Test with direct content
        $directContent = $method->invoke($this->controller, 'Direct content', 0, 0);
        $this->assertEquals('Direct content', $directContent);

        // Test with lesson ID
        $lessonContent = $method->invoke($this->controller, '', 123, 0);
        $this->assertEquals('This is test lesson content', $lessonContent);

        // Test with course ID
        $courseContent = $method->invoke($this->controller, '', 0, 456);
        $this->assertStringContains('This is test course content', $courseContent);

        // Test with no parameters
        $emptyContent = $method->invoke($this->controller, '', 0, 0);
        $this->assertEquals('', $emptyContent);
    }

    /**
     * Test multiple_choice question validation edge cases
     * 
     * @covers MpccQuizAjaxController::validateQuizData
     */
    public function testMultipleChoiceValidationEdgeCases(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('validateQuizData');
        $method->setAccessible(true);

        // Test with associative array options
        $question = [
            'question' => 'Test question?',
            'type' => 'multiple_choice',
            'options' => [
                'option_1' => 'First option',
                'option_2' => 'Second option'
            ],
            'correct_answer' => 'First option' // Value instead of key
        ];

        $result = $method->invoke($this->controller, ['questions' => [$question]]);
        
        // Should validate that correct answer is in option values
        $this->assertTrue($result['valid']);
    }

    /**
     * Test exception handling in generate_quiz
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     */
    public function testGenerateQuizHandlesExceptions(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123
        ]);

        // Mock service to throw exception
        $this->mockQuizService
            ->expects($this->once())
            ->method('generateQuestions')
            ->willThrowException(new \Exception('Test exception'));

        // Expect error logging
        $this->mockLogger
            ->expects($this->once())
            ->method('error');

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test validate_quiz with missing quiz questions
     * 
     * @covers MpccQuizAjaxController::validate_quiz
     */
    public function testValidateQuizWithMissingQuestions(): void
    {
        // Arrange
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'quiz_data' => json_encode(['title' => 'Test Quiz']) // No questions
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Quiz questions are required', $response['data']['message']);
    }

    /**
     * Test that nonce verification uses correct action
     * 
     * @covers MpccQuizAjaxController::verifyQuizNonce
     */
    public function testNonceVerificationUsesCorrectAction(): void
    {
        // This test ensures the nonce is verified against the correct action constant
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyQuizNonce');
        $method->setAccessible(true);

        // Create nonce with the expected action
        $correctNonce = wp_create_nonce(NonceConstants::QUIZ_AI);
        $this->setPostData(['nonce' => $correctNonce]);
        
        $result = $method->invoke($this->controller);
        $this->assertTrue($result);

        // Create nonce with different action
        $wrongNonce = wp_create_nonce('different_action');
        $this->setPostData(['nonce' => $wrongNonce]);
        
        $result = $method->invoke($this->controller);
        $this->assertFalse($result);
    }

    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Clear any global state that might affect other tests
        global $test_user_caps;
        $test_user_caps = ['read' => true];
    }
}