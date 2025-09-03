<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Unit\Security;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\MpccQuizAjaxController;
use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Comprehensive Variable Sanitization Tests for Quiz Functionality
 * 
 * Tests all sanitization methods in the quiz controller to ensure
 * proper input validation and XSS prevention
 * 
 * @package MemberPressCoursesCopilot\Tests\Unit\Security
 * @since 1.0.0
 */
class QuizSanitizationTest extends TestCase
{
    private MpccQuizAjaxController $controller;

    /**
     * Set up test fixtures
     * 
     * Creates controller instance with mocked dependencies for testing
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $mockQuizService = $this->createMock(MpccQuizAIService::class);
        $mockLogger = $this->createMock(Logger::class);
        
        // Create controller with mocked dependencies
        $this->controller = new MpccQuizAjaxController($mockQuizService, $mockLogger);
        
        // Mock additional WordPress functions needed for sanitization tests
        $this->mockSanitizationFunctions();
    }

    /**
     * Mock WordPress sanitization functions
     */
    private function mockSanitizationFunctions(): void
    {
        // Functions are now defined in bootstrap.php to avoid redeclaration
    }

    /**
     * Test sanitizeArray method with text sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayTextSanitization(): void
    {
        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        // Test data with various XSS attempts and formatting issues
        $maliciousData = [
            'name' => '  John Doe  <script>alert("xss")</script>',
            'description' => '<p>Safe content</p><script>evil()</script>',
            'simple_text' => 'Normal text with   extra   spaces',
            'nested' => [
                'value' => '<b>Bold text</b><script>nested_xss()</script>',
                'deep_nested' => [
                    'content' => '  Deeply nested <img src="x" onerror="alert()">content  '
                ]
            ],
            'numeric_string' => '123<script>',
            'empty_value' => '',
            'null_value' => null
        ];

        $sanitized = $method->invoke($this->controller, $maliciousData, 'text');

        // Assert XSS attempts are removed
        $this->assertEquals('John Doe', $sanitized['name']);
        $this->assertStringNotContains('<script>', $sanitized['name']);
        
        $this->assertEquals('Safe content', $sanitized['description']);
        $this->assertStringNotContains('evil()', $sanitized['description']);
        
        $this->assertEquals('Normal text with   extra   spaces', $sanitized['simple_text']);
        
        // Test nested sanitization
        $this->assertEquals('Bold text', $sanitized['nested']['value']);
        $this->assertStringNotContains('nested_xss', $sanitized['nested']['value']);
        
        $this->assertEquals('Deeply nested content', $sanitized['nested']['deep_nested']['content']);
        $this->assertStringNotContains('onerror', $sanitized['nested']['deep_nested']['content']);
        
        // Test numeric and edge cases
        $this->assertEquals('123', $sanitized['numeric_string']);
        $this->assertEquals('', $sanitized['empty_value']);
        $this->assertEquals('', $sanitized['null_value']);
    }

    /**
     * Test sanitizeArray method with textarea sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayTextareaSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $textareaData = [
            'content' => "Line 1\nLine 2\r\nLine 3<script>alert('xss')</script>",
            'message' => "Multi-line\ncontent with <b>formatting</b>",
            'nested' => [
                'description' => "Nested\nmulti-line\rcontent"
            ]
        ];

        $sanitized = $method->invoke($this->controller, $textareaData, 'textarea');

        // Should preserve line breaks but remove scripts
        $this->assertStringContains("Line 1\nLine 2", $sanitized['content']);
        $this->assertStringNotContains('<script>', $sanitized['content']);
        $this->assertStringNotContains('alert', $sanitized['content']);
        
        $this->assertStringContains("Multi-line\ncontent", $sanitized['message']);
        $this->assertStringNotContains('<b>', $sanitized['message']);
    }

    /**
     * Test sanitizeArray method with email sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayEmailSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $emailData = [
            'valid_email' => 'user@example.com',
            'email_with_script' => 'user@example.com<script>alert("xss")</script>',
            'invalid_email' => 'not-an-email',
            'nested' => [
                'contact_email' => 'admin@test.com"onclick="alert()"'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $emailData, 'email');

        $this->assertEquals('user@example.com', $sanitized['valid_email']);
        $this->assertEquals('user@example.com', $sanitized['email_with_script']);
        $this->assertEquals('', $sanitized['invalid_email']); // Invalid emails become empty
        $this->assertEquals('admin@test.com', $sanitized['nested']['contact_email']);
    }

    /**
     * Test sanitizeArray method with URL sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayUrlSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $urlData = [
            'valid_url' => 'https://example.com/path',
            'url_with_script' => 'https://example.com/<script>alert("xss")</script>',
            'javascript_url' => 'javascript:alert("xss")',
            'relative_url' => '/wp-admin/admin.php',
            'nested' => [
                'redirect_url' => 'https://safe.com"onload="alert()"'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $urlData, 'url');

        $this->assertEquals('https://example.com/path', $sanitized['valid_url']);
        $this->assertStringNotContains('<script>', $sanitized['url_with_script']);
        $this->assertStringNotContains('javascript:', $sanitized['javascript_url']);
        $this->assertEquals('/wp-admin/admin.php', $sanitized['relative_url']);
        $this->assertStringNotContains('onload', $sanitized['nested']['redirect_url']);
    }

    /**
     * Test sanitizeArray method with integer sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayIntegerSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $intData = [
            'valid_int' => '123',
            'float_value' => '123.456',
            'negative_int' => '-456',
            'string_with_numbers' => 'abc123def',
            'pure_string' => 'hello',
            'empty_string' => '',
            'zero' => '0',
            'nested' => [
                'count' => '789.123'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $intData, 'int');

        $this->assertIsInt($sanitized['valid_int']);
        $this->assertEquals(123, $sanitized['valid_int']);
        
        $this->assertIsInt($sanitized['float_value']);
        $this->assertEquals(123, $sanitized['float_value']); // Truncated
        
        $this->assertEquals(-456, $sanitized['negative_int']);
        $this->assertEquals(123, $sanitized['string_with_numbers']); // Extracts number
        $this->assertEquals(0, $sanitized['pure_string']);
        $this->assertEquals(0, $sanitized['empty_string']);
        $this->assertEquals(0, $sanitized['zero']);
        
        $this->assertEquals(789, $sanitized['nested']['count']);
    }

    /**
     * Test sanitizeArray method with float sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayFloatSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $floatData = [
            'valid_float' => '123.456',
            'integer_value' => '789',
            'negative_float' => '-12.34',
            'string_value' => 'abc12.5def',
            'nested' => [
                'percentage' => '99.9'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $floatData, 'float');

        $this->assertIsFloat($sanitized['valid_float']);
        $this->assertEquals(123.456, $sanitized['valid_float']);
        
        $this->assertEquals(789.0, $sanitized['integer_value']);
        $this->assertEquals(-12.34, $sanitized['negative_float']);
        $this->assertEquals(12.5, $sanitized['string_value']);
        $this->assertEquals(99.9, $sanitized['nested']['percentage']);
    }

    /**
     * Test sanitizeArray method with boolean sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayBooleanSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $boolData = [
            'true_string' => 'true',
            'false_string' => 'false',
            'one_string' => '1',
            'zero_string' => '0',
            'yes_string' => 'yes',
            'no_string' => 'no',
            'on_string' => 'on',
            'off_string' => 'off',
            'empty_string' => '',
            'random_string' => 'random',
            'nested' => [
                'enabled' => 'true',
                'disabled' => 'false'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $boolData, 'boolean');

        $this->assertTrue($sanitized['true_string']);
        $this->assertFalse($sanitized['false_string']);
        $this->assertTrue($sanitized['one_string']);
        $this->assertFalse($sanitized['zero_string']);
        $this->assertTrue($sanitized['yes_string']);
        $this->assertFalse($sanitized['no_string']);
        $this->assertTrue($sanitized['on_string']);
        $this->assertFalse($sanitized['off_string']);
        $this->assertFalse($sanitized['empty_string']);
        $this->assertFalse($sanitized['random_string']);
        
        $this->assertTrue($sanitized['nested']['enabled']);
        $this->assertFalse($sanitized['nested']['disabled']);
    }

    /**
     * Test sanitizeArray method with HTML sanitization
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizeArrayHtmlSanitization(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $htmlData = [
            'safe_html' => '<p>This is <strong>safe</strong> content</p>',
            'unsafe_html' => '<p>Content</p><script>alert("xss")</script><p>More content</p>',
            'mixed_content' => '<div onclick="evil()">Click me</div><p>Safe paragraph</p>',
            'nested' => [
                'content' => '<ul><li>Item 1</li><li>Item 2<script>bad()</script></li></ul>'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $htmlData, 'html');

        // Should keep safe HTML tags
        $this->assertStringContains('<p>', $sanitized['safe_html']);
        $this->assertStringContains('<strong>', $sanitized['safe_html']);
        
        // Should remove scripts but keep safe content
        $this->assertStringContains('<p>Content</p>', $sanitized['unsafe_html']);
        $this->assertStringNotContains('<script>', $sanitized['unsafe_html']);
        $this->assertStringContains('<p>More content</p>', $sanitized['unsafe_html']);
        
        // Should remove onclick handlers
        $this->assertStringNotContains('onclick', $sanitized['mixed_content']);
        $this->assertStringContains('<p>Safe paragraph</p>', $sanitized['mixed_content']);
        
        // Test nested HTML sanitization
        $this->assertStringContains('<ul>', $sanitized['nested']['content']);
        $this->assertStringContains('<li>Item 1</li>', $sanitized['nested']['content']);
        $this->assertStringNotContains('<script>', $sanitized['nested']['content']);
    }

    /**
     * Test extractAndSanitizeInput method with malicious input
     * 
     * @covers MpccQuizAjaxController::extractAndSanitizeInput
     */
    public function testExtractAndSanitizeInputWithMaliciousInput(): void
    {
        // Set malicious POST data
        $this->setPostData([
            'lesson_id' => '123<script>alert("xss")</script>',
            'course_id' => '456.789', // Float should become int
            'content' => "<p>Safe content</p>\n<script>alert('xss')</script>\n<p>More content</p>",
            'options' => [
                'num_questions' => '10<script>',
                'difficulty' => 'medium<img src="x" onerror="alert()">',
                'custom_prompt' => '<p>Custom instructions</p><script>evil()</script>'
            ]
        ]);

        // Get protected method via reflection
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('extractAndSanitizeInput');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller);

        // Assert proper sanitization
        $this->assertEquals(123, $result['lessonId']);
        $this->assertEquals(456, $result['courseId']);
        
        // Content should be sanitized but preserve basic structure
        $this->assertStringContains('Safe content', $result['content']);
        $this->assertStringContains('More content', $result['content']);
        $this->assertStringNotContains('<script>', $result['content']);
        $this->assertStringNotContains('alert', $result['content']);
        
        // Options should be an array
        $this->assertIsArray($result['options']);
    }

    /**
     * Test parseQuizOptions with malicious JSON input
     * 
     * @covers MpccQuizAjaxController::parseQuizOptions
     */
    public function testParseQuizOptionsWithMaliciousJson(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('parseQuizOptions');
        $method->setAccessible(true);

        // Test malicious JSON string
        $maliciousJson = json_encode([
            'num_questions' => '10',
            'question_type' => 'multiple_choice<script>alert("xss")</script>',
            'difficulty' => 'easy',
            'custom_prompt' => '<p>Instructions</p><script>evil()</script>',
            'malicious_key' => '<img src="x" onerror="steal_data()">'
        ]);

        $parsed = $method->invoke($this->controller, $maliciousJson);

        // Should sanitize all values
        $this->assertEquals('10', $parsed['num_questions']);
        $this->assertEquals('multiple_choice', $parsed['question_type']);
        $this->assertStringNotContains('<script>', $parsed['question_type']);
        $this->assertEquals('easy', $parsed['difficulty']);
        $this->assertStringContains('Instructions', $parsed['custom_prompt']);
        $this->assertStringNotContains('<script>', $parsed['custom_prompt']);
        $this->assertStringNotContains('onerror', $parsed['malicious_key']);
    }

    /**
     * Test parseQuizOptions with malicious array input
     * 
     * @covers MpccQuizAjaxController::parseQuizOptions
     */
    public function testParseQuizOptionsWithMaliciousArray(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('parseQuizOptions');
        $method->setAccessible(true);

        $maliciousArray = [
            'num_questions' => '5<script>',
            'nested_attack' => [
                'value' => '<script>document.cookie</script>',
                'deep' => [
                    'evil' => 'javascript:alert("deep")'
                ]
            ]
        ];

        $parsed = $method->invoke($this->controller, $maliciousArray);

        $this->assertEquals('5', $parsed['num_questions']);
        $this->assertStringNotContains('<script>', $parsed['num_questions']);
        $this->assertStringNotContains('document.cookie', $parsed['nested_attack']['value']);
        $this->assertStringNotContains('javascript:', $parsed['nested_attack']['deep']['evil']);
    }

    /**
     * Test that sanitization handles different encoding attacks
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizationHandlesEncodingAttacks(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $encodedAttacks = [
            'url_encoded' => '%3Cscript%3Ealert%28%22xss%22%29%3C%2Fscript%3E',
            'html_entities' => '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            'unicode_attack' => "\u003cscript\u003ealert('unicode')\u003c/script\u003e",
            'null_byte_attack' => "safe_text\x00<script>alert('null')</script>",
            'nested' => [
                'encoded_value' => '%22%3E%3Cscript%3Ealert%28%22nested%22%29%3C%2Fscript%3E'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $encodedAttacks, 'text');

        // All should be sanitized to safe text
        foreach ($sanitized as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $nestedValue) {
                    $this->assertStringNotContains('script', $nestedValue);
                    $this->assertStringNotContains('alert', $nestedValue);
                }
            } else {
                $this->assertStringNotContains('script', $value);
                $this->assertStringNotContains('alert', $value);
            }
        }
    }

    /**
     * Test sanitization preserves legitimate data
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizationPreservesLegitimateData(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $legitimateData = [
            'course_title' => 'Introduction to Web Development',
            'lesson_content' => 'Learn about HTML, CSS, and JavaScript basics',
            'question_count' => '15',
            'difficulty_level' => 'intermediate',
            'tags' => ['web-dev', 'frontend', 'beginner-friendly'],
            'quiz_settings' => [
                'time_limit' => '30',
                'show_feedback' => 'true',
                'randomize_questions' => 'false'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $legitimateData, 'text');

        // Should preserve all legitimate content
        $this->assertEquals('Introduction to Web Development', $sanitized['course_title']);
        $this->assertEquals('Learn about HTML, CSS, and JavaScript basics', $sanitized['lesson_content']);
        $this->assertEquals('15', $sanitized['question_count']);
        $this->assertEquals('intermediate', $sanitized['difficulty_level']);
        $this->assertEquals(['web-dev', 'frontend', 'beginner-friendly'], $sanitized['tags']);
        $this->assertEquals('30', $sanitized['quiz_settings']['time_limit']);
        $this->assertEquals('true', $sanitized['quiz_settings']['show_feedback']);
    }

    /**
     * Test sanitization with edge cases and boundary conditions
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizationEdgeCases(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $edgeCases = [
            'very_long_string' => str_repeat('A', 10000) . '<script>alert("long")</script>',
            'deeply_nested' => [
                'level1' => [
                    'level2' => [
                        'level3' => [
                            'level4' => '<script>deep_attack()</script>safe_content'
                        ]
                    ]
                ]
            ],
            'unicode_content' => '这是中文内容<script>alert("unicode")</script>',
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'mixed_array' => [
                'string' => 'text<script>',
                'number' => '123',
                'boolean' => 'true'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $edgeCases, 'text');

        // Very long string should be sanitized
        $this->assertStringNotContains('<script>', $sanitized['very_long_string']);
        $this->assertStringContains(str_repeat('A', 10000), $sanitized['very_long_string']);
        
        // Deep nesting should work
        $this->assertEquals('safe_content', $sanitized['deeply_nested']['level1']['level2']['level3']['level4']);
        
        // Unicode should be preserved but scripts removed
        $this->assertStringContains('这是中文内容', $sanitized['unicode_content']);
        $this->assertStringNotContains('<script>', $sanitized['unicode_content']);
        
        // Special characters should be preserved
        $this->assertEquals('!@#$%^&*()_+-=[]{}|;:,.<>?', $sanitized['special_chars']);
    }

    /**
     * Test sanitization with null and undefined values
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizationWithNullAndUndefinedValues(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $nullData = [
            'null_value' => null,
            'false_value' => false,
            'zero_value' => 0,
            'empty_string' => '',
            'empty_array' => [],
            'nested' => [
                'null_nested' => null,
                'valid_value' => 'test'
            ]
        ];

        $sanitized = $method->invoke($this->controller, $nullData, 'text');

        $this->assertEquals('', $sanitized['null_value']);
        $this->assertEquals('', $sanitized['false_value']);
        $this->assertEquals('0', $sanitized['zero_value']);
        $this->assertEquals('', $sanitized['empty_string']);
        $this->assertEquals([], $sanitized['empty_array']);
        $this->assertEquals('', $sanitized['nested']['null_nested']);
        $this->assertEquals('test', $sanitized['nested']['valid_value']);
    }

    /**
     * Test that default sanitization type is 'text'
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testDefaultSanitizationTypeIsText(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $data = [
            'content' => '  Test Content  <script>alert("xss")</script>',
            'number' => '123'
        ];

        // Don't specify type - should default to 'text'
        $sanitized = $method->invoke($this->controller, $data);

        $this->assertEquals('Test Content', $sanitized['content']);
        $this->assertEquals('123', $sanitized['number']); // Should be string, not int
        $this->assertStringNotContains('<script>', $sanitized['content']);
    }

    /**
     * Test performance with large datasets
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     */
    public function testSanitizationPerformanceWithLargeDataset(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        // Create large dataset with nested arrays
        $largeData = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeData["item_$i"] = [
                'content' => "Content $i with <script>alert('$i')</script> malicious code",
                'number' => "$i",
                'nested' => [
                    'value' => "Nested value $i<script>nested_$i()</script>"
                ]
            ];
        }

        $startTime = microtime(true);
        $sanitized = $method->invoke($this->controller, $largeData, 'text');
        $endTime = microtime(true);

        // Should complete within reasonable time (less than 1 second)
        $this->assertLessThan(1.0, $endTime - $startTime);
        
        // Should sanitize all items
        $this->assertCount(1000, $sanitized);
        
        // Spot check some items
        $this->assertEquals('Content 0 with  malicious code', $sanitized['item_0']['content']);
        $this->assertEquals('Content 999 with  malicious code', $sanitized['item_999']['content']);
        $this->assertStringNotContains('<script>', $sanitized['item_500']['content']);
        $this->assertStringNotContains('alert', $sanitized['item_500']['nested']['value']);
    }

    /**
     * Test quiz-specific input sanitization scenarios
     * 
     * These tests simulate real quiz generation scenarios with potentially
     * dangerous input that could come from form fields or AJAX requests
     */
    public function testQuizSpecificSanitizationScenarios(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $sanitizeMethod = $reflection->getMethod('sanitizeArray');
        $sanitizeMethod->setAccessible(true);
        
        $parseMethod = $reflection->getMethod('parseQuizOptions');
        $parseMethod->setAccessible(true);

        // Scenario 1: Malicious quiz options
        $maliciousQuizOptions = json_encode([
            'num_questions' => '10<script>alert("questions")</script>',
            'question_type' => 'multiple_choice"onload="evil()"',
            'difficulty' => 'medium</script><script>document.location="evil.com"</script>',
            'custom_prompt' => 'Generate questions about <img src="x" onerror="steal_cookies()">topic'
        ]);

        $sanitizedOptions = $parseMethod->invoke($this->controller, $maliciousQuizOptions);

        $this->assertEquals('10', $sanitizedOptions['num_questions']);
        $this->assertEquals('multiple_choice', $sanitizedOptions['question_type']);
        $this->assertEquals('medium', $sanitizedOptions['difficulty']);
        $this->assertStringNotContains('onerror', $sanitizedOptions['custom_prompt']);
        $this->assertStringNotContains('<img', $sanitizedOptions['custom_prompt']);

        // Scenario 2: Malicious question validation data
        $maliciousQuestionData = [
            'questions' => [
                [
                    'question' => 'What is PHP<script>alert("php")</script>?',
                    'options' => [
                        'A<script>evil()</script>' => 'Programming Language',
                        'B' => 'Database<img src="x" onerror="attack()">',
                        'C' => 'Server',
                        'D' => 'Framework'
                    ],
                    'correct_answer' => 'A<script>evil()</script>',
                    'explanation' => 'PHP is a <script>steal()</script>programming language'
                ]
            ]
        ];

        $sanitizedQuestions = $sanitizeMethod->invoke($this->controller, $maliciousQuestionData, 'text');

        $question = $sanitizedQuestions['questions'][0];
        $this->assertEquals('What is PHP?', $question['question']);
        $this->assertStringNotContains('<script>', $question['explanation']);
        $this->assertStringNotContains('onerror', $question['options']['B']);
        $this->assertEquals('A', $question['correct_answer']); // Key should be sanitized
    }

    /**
     * Test SQL injection prevention in numeric inputs
     * 
     * Tests that numeric sanitization prevents SQL injection attempts
     */
    public function testSqlInjectionPreventionInNumericInputs(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $sqlInjectionAttempts = [
            'lesson_id' => "123'; DROP TABLE wp_posts; --",
            'course_id' => "456 OR 1=1",
            'question_count' => "10 UNION SELECT * FROM wp_users",
            'points' => "1'; UPDATE wp_posts SET post_title='hacked'; --"
        ];

        $sanitized = $method->invoke($this->controller, $sqlInjectionAttempts, 'int');

        // All should be converted to safe integers
        $this->assertEquals(123, $sanitized['lesson_id']);
        $this->assertEquals(456, $sanitized['course_id']);
        $this->assertEquals(10, $sanitized['question_count']);
        $this->assertEquals(1, $sanitized['points']);
        
        // Ensure no SQL keywords remain
        foreach ($sanitized as $value) {
            $this->assertIsInt($value);
            // Convert back to string to check for SQL keywords
            $stringValue = (string)$value;
            $this->assertStringNotContains('DROP', $stringValue);
            $this->assertStringNotContains('UNION', $stringValue);
            $this->assertStringNotContains('UPDATE', $stringValue);
        }
    }

    /**
     * Test that sanitization is type-aware and consistent
     * 
     * @covers MpccQuizAjaxController::sanitizeArray
     * @dataProvider sanitizationTypeProvider
     */
    public function testSanitizationIsTypeAware(string $type, array $input, array $expectedOutput): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('sanitizeArray');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, $input, $type);

        foreach ($expectedOutput as $key => $expectedValue) {
            if (is_bool($expectedValue)) {
                $this->assertIsBool($result[$key]);
                $this->assertEquals($expectedValue, $result[$key]);
            } elseif (is_int($expectedValue)) {
                $this->assertIsInt($result[$key]);
                $this->assertEquals($expectedValue, $result[$key]);
            } elseif (is_float($expectedValue)) {
                $this->assertIsFloat($result[$key]);
                $this->assertEquals($expectedValue, $result[$key]);
            } else {
                $this->assertEquals($expectedValue, $result[$key]);
            }
        }
    }

    /**
     * Data provider for type-aware sanitization tests
     * 
     * @return array Test cases with type, input, and expected output
     */
    public function sanitizationTypeProvider(): array
    {
        return [
            'text_type' => [
                'text',
                ['value' => '  Hello World  <script>'],
                ['value' => 'Hello World']
            ],
            'int_type' => [
                'int', 
                ['value' => '123.45<script>'],
                ['value' => 123]
            ],
            'float_type' => [
                'float',
                ['value' => '123.45<script>'],
                ['value' => 123.45]
            ],
            'boolean_type' => [
                'boolean',
                ['value' => 'true<script>'],
                ['value' => true]
            ],
            'email_type' => [
                'email',
                ['value' => 'test@example.com<script>'],
                ['value' => 'test@example.com']
            ],
            'url_type' => [
                'url',
                ['value' => 'https://example.com/<script>'],
                ['value' => 'https://example.com/']
            ]
        ];
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Ensure $_POST is cleaned
        $_POST = [];
    }
}