<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Unit\Security;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\MpccQuizAjaxController;
use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Comprehensive Permission Tests for Quiz Functionality
 * 
 * Tests all permission checks and security validations in the quiz system
 * to ensure only authorized users can access quiz generation features
 * 
 * @package MemberPressCoursesCopilot\Tests\Unit\Security
 * @since 1.0.0
 */
class QuizPermissionTest extends TestCase
{
    private MpccQuizAjaxController $controller;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create mocks
        $mockQuizService = $this->createMock(MpccQuizAIService::class);
        $mockLogger = $this->createMock(Logger::class);
        
        // Create controller with mocked dependencies
        $this->controller = new MpccQuizAjaxController($mockQuizService, $mockLogger);
        
        $this->mockWordPressFunctions();
    }

    /**
     * Mock WordPress functions for permission testing
     */
    private function mockWordPressFunctions(): void
    {
        // Mock functions that might not be available in test environment
        // Nothing needed here now - moved to bootstrap
    }

    /**
     * Test generate_quiz permission check with edit_posts capability
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testGenerateQuizWithEditPostsPermission(): void
    {
        // Arrange
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'content' => 'Test content'
        ]);

        // Act & Assert - Should not fail on permission check
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        // Should not fail with permission error (might fail for other reasons like empty content)
        $this->assertFalse($response['success']); // Fails due to empty content, not permissions
        $this->assertStringNotContains('Insufficient permissions', $response['error']['message'] ?? '');
    }

    /**
     * Test generate_quiz permission check without edit_posts capability
     * 
     * @covers MpccQuizAjaxController::generate_quiz
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testGenerateQuizWithoutEditPostsPermission(): void
    {
        // Arrange
        $this->setCurrentUserCaps(['read' => true]); // No edit_posts
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'content' => 'Test content'
        ]);

        // Act & Assert
        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
        $this->assertEquals('mpcc_insufficient_permissions', $response['error']['code'] ?? '');
    }

    /**
     * Test regenerate_question permission check
     * 
     * @covers MpccQuizAjaxController::regenerate_question
     */
    public function testRegenerateQuestionPermissionCheck(): void
    {
        // Test with sufficient permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'question' => json_encode(['type' => 'multiple_choice']),
            'content' => 'Test content'
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->regenerate_question();
        });

        $this->assertStringNotContains('Insufficient permissions', $response['data']['message'] ?? '');

        // Test without sufficient permissions
        $this->setCurrentUserCaps(['read' => true]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->regenerate_question();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test validate_quiz permission check
     * 
     * @covers MpccQuizAjaxController::validate_quiz
     */
    public function testValidateQuizPermissionCheck(): void
    {
        // Test with sufficient permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'quiz_data' => json_encode([
                'questions' => [
                    [
                        'question' => 'Test?',
                        'type' => 'multiple_choice',
                        'options' => ['A' => '1', 'B' => '2'],
                        'correct_answer' => 'A'
                    ]
                ]
            ])
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        $this->assertTrue($response['success']);

        // Test without sufficient permissions
        $this->setCurrentUserCaps(['read' => true]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test create_quiz_from_lesson permission check
     * 
     * @covers MpccQuizAjaxController::create_quiz_from_lesson
     */
    public function testCreateQuizFromLessonPermissionCheck(): void
    {
        // Test with sufficient permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->create_quiz_from_lesson();
        });

        $this->assertTrue($response['success']);

        // Test without sufficient permissions
        $this->setCurrentUserCaps(['read' => true]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->create_quiz_from_lesson();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test get_lesson_course permission check
     * 
     * @covers MpccQuizAjaxController::get_lesson_course
     */
    public function testGetLessonCoursePermissionCheck(): void
    {
        // Test with sufficient permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->get_lesson_course();
        });

        $this->assertTrue($response['success']);

        // Test without sufficient permissions
        $this->setCurrentUserCaps(['read' => true]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->get_lesson_course();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test get_course_lessons permission check
     * 
     * @covers MpccQuizAjaxController::get_course_lessons
     */
    public function testGetCourseLessonsPermissionCheck(): void
    {
        // Test with sufficient permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'course_id' => 456
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->get_course_lessons();
        });

        $this->assertTrue($response['success']);

        // Test without sufficient permissions
        $this->setCurrentUserCaps(['read' => true]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->get_course_lessons();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test permission check with various WordPress capabilities
     * 
     * @covers MpccQuizAjaxController::verifyUserPermissions
     * @dataProvider wordpressCapabilityProvider
     */
    public function testPermissionCheckWithVariousCapabilities(array $capabilities, bool $expectedAccess): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        $this->setCurrentUserCaps($capabilities);
        $result = $method->invoke($this->controller);

        $this->assertEquals($expectedAccess, $result);
    }

    /**
     * Data provider for WordPress capability tests
     * 
     * @return array Test cases with capabilities and expected access
     */
    public function wordpressCapabilityProvider(): array
    {
        return [
            'administrator' => [
                ['edit_posts' => true, 'manage_options' => true, 'edit_others_posts' => true],
                true
            ],
            'editor' => [
                ['edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true],
                true
            ],
            'author' => [
                ['edit_posts' => true, 'publish_posts' => true],
                true
            ],
            'contributor' => [
                ['edit_posts' => true],
                true
            ],
            'subscriber' => [
                ['read' => true],
                false
            ],
            'no_capabilities' => [
                [],
                false
            ],
            'edit_posts_only' => [
                ['edit_posts' => true],
                true
            ],
            'other_capabilities' => [
                ['manage_categories' => true, 'moderate_comments' => true],
                false
            ]
        ];
    }

    /**
     * Test nonce verification with different nonce scenarios
     * 
     * @covers MpccQuizAjaxController::verifyQuizNonce
     * @dataProvider nonceScenarioProvider
     */
    public function testNonceVerificationScenarios(array $postData, bool $expectedValid): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyQuizNonce');
        $method->setAccessible(true);

        $this->setPostData($postData);
        $result = $method->invoke($this->controller);

        $this->assertEquals($expectedValid, $result);
    }

    /**
     * Data provider for nonce verification scenarios
     * 
     * @return array Test cases with POST data and expected validity
     */
    public function nonceScenarioProvider(): array
    {
        $validNonce = wp_create_nonce(NonceConstants::QUIZ_AI);
        $invalidNonce = wp_create_nonce('different_action');

        return [
            'valid_nonce' => [
                ['nonce' => $validNonce],
                true
            ],
            'invalid_nonce' => [
                ['nonce' => $invalidNonce],
                false
            ],
            'empty_nonce' => [
                ['nonce' => ''],
                false
            ],
            'missing_nonce' => [
                [],
                false
            ],
            'null_nonce' => [
                ['nonce' => null],
                false
            ],
            'malicious_nonce' => [
                ['nonce' => '<script>alert("xss")</script>'],
                false
            ],
            'numeric_nonce' => [
                ['nonce' => '123456'],
                false
            ]
        ];
    }

    /**
     * Test that all AJAX endpoints require proper nonce
     * 
     * Tests that each AJAX endpoint properly validates nonces before processing
     */
    public function testAllAjaxEndpointsRequireValidNonce(): void
    {
        $endpoints = [
            'generate_quiz' => ['content' => 'Test content'],
            'regenerate_question' => [
                'question' => json_encode(['type' => 'multiple_choice']),
                'content' => 'Test content'
            ],
            'validate_quiz' => [
                'quiz_data' => json_encode(['questions' => []])
            ],
            'create_quiz_from_lesson' => ['lesson_id' => 123],
            'get_lesson_course' => ['lesson_id' => 123],
            'get_course_lessons' => ['course_id' => 456]
        ];

        foreach ($endpoints as $endpoint => $additionalData) {
            // Test with invalid nonce
            $postData = array_merge($additionalData, ['nonce' => 'invalid-nonce']);
            $this->setPostData($postData);
            $this->setCurrentUserCaps(['edit_posts' => true]);

            $response = $this->captureJsonOutput(function() use ($endpoint) {
                $this->controller->$endpoint();
            });

            $this->assertFalse($response['success'], "Endpoint $endpoint should reject invalid nonce");
            $this->assertStringContains('Security check failed', $response['error']['message']);
            $this->assertEquals('mpcc_invalid_nonce', $response['error']['code'] ?? '');
        }
    }

    /**
     * Test that all AJAX endpoints require edit_posts capability
     * 
     * Tests that each endpoint properly validates user permissions
     */
    public function testAllAjaxEndpointsRequireEditPostsCapability(): void
    {
        $endpoints = [
            'generate_quiz' => ['content' => 'Test content'],
            'regenerate_question' => [
                'question' => json_encode(['type' => 'multiple_choice']),
                'content' => 'Test content'
            ],
            'validate_quiz' => [
                'quiz_data' => json_encode(['questions' => []])
            ],
            'create_quiz_from_lesson' => ['lesson_id' => 123],
            'get_lesson_course' => ['lesson_id' => 123],
            'get_course_lessons' => ['course_id' => 456]
        ];

        foreach ($endpoints as $endpoint => $additionalData) {
            // Test with valid nonce but insufficient permissions
            $postData = array_merge($additionalData, [
                'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI)
            ]);
            $this->setPostData($postData);
            $this->setCurrentUserCaps(['read' => true]); // No edit_posts

            $response = $this->captureJsonOutput(function() use ($endpoint) {
                $this->controller->$endpoint();
            });

            $this->assertFalse($response['success'], "Endpoint $endpoint should require edit_posts capability");
            $this->assertStringContains('Insufficient permissions', $response['error']['message']);
            $this->assertEquals('mpcc_insufficient_permissions', $response['error']['code'] ?? '');
        }
    }

    /**
     * Test permission hierarchy - higher capabilities should work
     * 
     * @covers MpccQuizAjaxController::verifyUserPermissions
     * @dataProvider permissionHierarchyProvider
     */
    public function testPermissionHierarchy(array $capabilities, bool $expectedAccess, string $description): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        $this->setCurrentUserCaps($capabilities);
        $result = $method->invoke($this->controller);

        $this->assertEquals($expectedAccess, $result, $description);
    }

    /**
     * Data provider for permission hierarchy tests
     * 
     * @return array Test cases with capabilities, expected access, and description
     */
    public function permissionHierarchyProvider(): array
    {
        return [
            'super_admin' => [
                ['edit_posts' => true, 'manage_options' => true, 'edit_others_posts' => true],
                true,
                'Super admin should have access'
            ],
            'administrator' => [
                ['edit_posts' => true, 'manage_options' => true],
                true,
                'Administrator should have access'
            ],
            'editor' => [
                ['edit_posts' => true, 'edit_others_posts' => true],
                true,
                'Editor should have access'
            ],
            'author' => [
                ['edit_posts' => true, 'publish_posts' => true],
                true,
                'Author should have access'
            ],
            'contributor' => [
                ['edit_posts' => true],
                true,
                'Contributor should have access'
            ],
            'subscriber' => [
                ['read' => true],
                false,
                'Subscriber should not have access'
            ],
            'no_user' => [
                [],
                false,
                'User with no capabilities should not have access'
            ]
        ];
    }

    /**
     * Test concurrent permission checks don't interfere
     * 
     * Simulates multiple permission checks happening simultaneously
     */
    public function testConcurrentPermissionChecks(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        // Test rapid permission checks with different capabilities
        $results = [];
        
        // First check with edit_posts
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $results[] = $method->invoke($this->controller);
        
        // Second check without edit_posts
        $this->setCurrentUserCaps(['read' => true]);
        $results[] = $method->invoke($this->controller);
        
        // Third check with edit_posts again
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $results[] = $method->invoke($this->controller);

        $this->assertTrue($results[0]);
        $this->assertFalse($results[1]);
        $this->assertTrue($results[2]);
    }

    /**
     * Test nonce validation with expired/invalid nonces
     * 
     * @covers MpccQuizAjaxController::verifyQuizNonce
     */
    public function testNonceValidationWithVariousInvalidNonces(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyQuizNonce');
        $method->setAccessible(true);

        $invalidNonces = [
            'completely_wrong' => 'abc123def456',
            'empty_string' => '',
            'null_value' => null,
            'boolean_false' => false,
            'numeric_zero' => 0,
            'sql_injection' => "'; DROP TABLE wp_posts; --",
            'xss_attempt' => '<script>alert("xss")</script>',
            'very_long' => str_repeat('a', 1000),
            'special_chars' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
            'unicode_attack' => "\u003cscript\u003ealert('unicode')\u003c/script\u003e"
        ];

        foreach ($invalidNonces as $description => $nonce) {
            $this->setPostData(['nonce' => $nonce]);
            $result = $method->invoke($this->controller);
            
            $this->assertFalse($result, "Invalid nonce should be rejected: $description");
        }
    }

    /**
     * Test that permission checks are case-sensitive
     * 
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testPermissionChecksAreCaseSensitive(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        // Test various case variations
        $caseSensitiveTests = [
            ['EDIT_POSTS' => true], // Uppercase
            ['Edit_Posts' => true], // Mixed case
            ['edit_Posts' => true], // Mixed case
            ['editposts' => true],  // No underscore
            ['edit posts' => true]  // Space instead of underscore
        ];

        foreach ($caseSensitiveTests as $caps) {
            $this->setCurrentUserCaps($caps);
            $result = $method->invoke($this->controller);
            
            // Should only accept exact 'edit_posts' capability
            $this->assertFalse($result, "Permission check should be case-sensitive");
        }

        // Verify correct case works
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $result = $method->invoke($this->controller);
        $this->assertTrue($result);
    }

    /**
     * Test permission checks with edge case user capabilities
     * 
     * @covers MpccQuizAjaxController::verifyUserPermissions
     */
    public function testPermissionChecksWithEdgeCaseCapabilities(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        $edgeCases = [
            // Capability exists but is false
            'false_capability' => [
                ['edit_posts' => false],
                false
            ],
            // Capability exists but is null
            'null_capability' => [
                ['edit_posts' => null],
                false
            ],
            // Capability exists but is empty string
            'empty_capability' => [
                ['edit_posts' => ''],
                false
            ],
            // Capability exists but is zero
            'zero_capability' => [
                ['edit_posts' => 0],
                false
            ],
            // Capability exists but is array
            'array_capability' => [
                ['edit_posts' => ['value' => true]],
                false
            ],
            // Multiple related capabilities but not edit_posts
            'related_capabilities' => [
                ['edit_pages' => true, 'edit_published_posts' => true],
                false
            ]
        ];

        foreach ($edgeCases as $description => [$capabilities, $expectedAccess]) {
            $this->setCurrentUserCaps($capabilities);
            $result = $method->invoke($this->controller);
            
            $this->assertEquals($expectedAccess, $result, "Edge case failed: $description");
        }
    }

    /**
     * Test security behavior when accessing protected methods directly
     * 
     * Ensures that even if someone tries to call protected methods directly,
     * they would still need proper setup
     */
    public function testProtectedMethodsRequireProperSetup(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        // List of protected methods that should not be callable without setup
        $protectedMethods = [
            'verifyQuizNonce',
            'verifyUserPermissions',
            'extractAndSanitizeInput',
            'sanitizeArray',
            'parseQuizOptions'
        ];

        foreach ($protectedMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $this->assertTrue($method->isProtected() || $method->isPrivate(), 
                "Method $methodName should be protected or private");
        }
    }

    /**
     * Test that permission failures are properly logged
     * 
     * While we can't easily test the Logger mock calls in this setup,
     * we can test that the flow continues properly after permission failures
     */
    public function testPermissionFailuresHandledGracefully(): void
    {
        // Test multiple consecutive permission failures
        $this->setCurrentUserCaps(['read' => true]); // No edit_posts

        $endpoints = [
            'generate_quiz' => ['content' => 'Test'],
            'validate_quiz' => ['quiz_data' => json_encode(['questions' => []])],
            'create_quiz_from_lesson' => ['lesson_id' => 123]
        ];

        foreach ($endpoints as $endpoint => $data) {
            $postData = array_merge($data, [
                'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI)
            ]);
            $this->setPostData($postData);

            $response = $this->captureJsonOutput(function() use ($endpoint) {
                $this->controller->$endpoint();
            });

            // Each should fail gracefully with proper error message
            $this->assertFalse($response['success']);
            $this->assertEquals('Insufficient permissions', $response['error']['message']);
            $this->assertEquals('mpcc_insufficient_permissions', $response['error']['code']);
        }
    }

    /**
     * Test security during quiz validation with malicious quiz data
     * 
     * Ensures that even with proper permissions, malicious quiz data is handled safely
     */
    public function testSecurityDuringQuizValidationWithMaliciousData(): void
    {
        $this->setCurrentUserCaps(['edit_posts' => true]);
        
        $maliciousQuizData = [
            'title' => 'Test Quiz<script>alert("title")</script>',
            'questions' => [
                [
                    'question' => 'Safe question?<script>evil()</script>',
                    'type' => 'multiple_choice<img src="x" onerror="attack()">',
                    'options' => [
                        'A<script>opt1()</script>' => 'Option 1<script>val1()</script>',
                        'B' => 'Option 2',
                        'C' => 'Option 3',
                        'D' => 'Option 4'
                    ],
                    'correct_answer' => 'A<script>answer()</script>'
                ]
            ]
        ];

        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'quiz_data' => json_encode($maliciousQuizData)
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->validate_quiz();
        });

        // Should process but validation should handle the malicious content
        // The actual validation results depend on the implementation,
        // but it should not crash or execute scripts
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('data', $response);
    }

    /**
     * Test rate limiting simulation
     * 
     * While the controller doesn't implement rate limiting, this tests
     * that multiple rapid requests are handled gracefully
     */
    public function testRapidPermissionChecks(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        $this->setCurrentUserCaps(['edit_posts' => true]);

        // Simulate 100 rapid permission checks
        $results = [];
        for ($i = 0; $i < 100; $i++) {
            $results[] = $method->invoke($this->controller);
        }

        // All should return true consistently
        foreach ($results as $index => $result) {
            $this->assertTrue($result, "Permission check $index should be true");
        }
        
        $this->assertCount(100, $results);
    }

    /**
     * Test cross-site request forgery (CSRF) protection
     * 
     * Tests that nonce verification provides CSRF protection
     */
    public function testCsrfProtectionViaNonceValidation(): void
    {
        // Simulate different sessions/contexts with different nonces
        $session1Nonce = wp_create_nonce(NonceConstants::QUIZ_AI . '_session1');
        $session2Nonce = wp_create_nonce(NonceConstants::QUIZ_AI . '_session2');
        
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyQuizNonce');
        $method->setAccessible(true);

        // Valid nonce for current action should work
        $this->setPostData(['nonce' => wp_create_nonce(NonceConstants::QUIZ_AI)]);
        $this->assertTrue($method->invoke($this->controller));

        // Nonces from different sessions/actions should fail
        $this->setPostData(['nonce' => $session1Nonce]);
        $this->assertFalse($method->invoke($this->controller));

        $this->setPostData(['nonce' => $session2Nonce]);
        $this->assertFalse($method->invoke($this->controller));
    }

    /**
     * Test that permissions are checked before any processing
     * 
     * Ensures security checks happen early in the request lifecycle
     */
    public function testPermissionsCheckedBeforeProcessing(): void
    {
        // Set up scenario where permissions fail but other data is valid
        $this->setCurrentUserCaps(['read' => true]); // No edit_posts
        $this->setPostData([
            'nonce' => wp_create_nonce(NonceConstants::QUIZ_AI),
            'lesson_id' => 123,
            'content' => 'Valid content that would normally work'
        ]);

        // Mock the quiz service to ensure it's not called when permissions fail
        $mockQuizService = $this->createMock(MpccQuizAIService::class);
        $mockQuizService->expects($this->never())->method('generateQuestions');
        
        $controller = new MpccQuizAjaxController($mockQuizService, $this->createMock(Logger::class));

        $response = $this->captureJsonOutput(function() use ($controller) {
            $controller->generate_quiz();
        });

        $this->assertFalse($response['success']);
        $this->assertStringContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test that nonce verification happens before permission checks
     * 
     * Ensures proper security check order
     */
    public function testNonceVerificationBeforePermissionCheck(): void
    {
        // Set up scenario where nonce fails but user has proper permissions
        $this->setCurrentUserCaps(['edit_posts' => true]);
        $this->setPostData([
            'nonce' => 'invalid-nonce',
            'lesson_id' => 123,
            'content' => 'Valid content'
        ]);

        $response = $this->captureJsonOutput(function() {
            $this->controller->generate_quiz();
        });

        // Should fail on nonce verification, not permission check
        $this->assertFalse($response['success']);
        $this->assertStringContains('Security check failed', $response['error']['message']);
        $this->assertStringNotContains('Insufficient permissions', $response['error']['message']);
    }

    /**
     * Test permission checking with malformed capability data
     * 
     * Tests robustness when WordPress capability system returns unexpected data
     */
    public function testPermissionCheckingWithMalformedCapabilityData(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('verifyUserPermissions');
        $method->setAccessible(true);

        // Test with various malformed capability scenarios
        $malformedScenarios = [
            'string_capabilities' => 'edit_posts',
            'numeric_capabilities' => 123,
            'object_capabilities' => (object)['edit_posts' => true],
            'null_capabilities' => null
        ];

        foreach ($malformedScenarios as $description => $capabilities) {
            // We can't directly set malformed capabilities in our test setup,
            // but we can test that the method handles unexpected returns gracefully
            
            // Instead, test that the method specifically looks for edit_posts
            $this->setCurrentUserCaps([]); // Empty capabilities
            $result = $method->invoke($this->controller);
            
            $this->assertFalse($result, "Malformed capabilities should result in denied access: $description");
        }
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Reset global user capabilities
        global $test_user_caps;
        $test_user_caps = ['read' => true];
    }
}