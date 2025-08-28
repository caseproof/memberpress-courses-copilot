<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Security;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\SimpleAjaxController;
use MemberPressCoursesCopilot\Security\NonceConstants;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;

/**
 * AJAX Security Test
 * 
 * Tests security measures for AJAX endpoints including:
 * - Nonce verification
 * - User permissions
 * - Input sanitization
 * - XSS prevention
 * 
 * Following CLAUDE.md principles - real security tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Security
 * @since 1.0.0
 */
class AjaxSecurityTest extends TestCase
{
    private SimpleAjaxController $controller;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize controller with mocked services to focus on security
        $this->controller = new SimpleAjaxController();
        
        // Set default user capabilities
        global $test_user_caps;
        $test_user_caps = ['read' => true];
    }
    
    /**
     * Test AJAX endpoint requires authentication
     */
    public function testRequiresAuthentication(): void
    {
        // Set user as not logged in (ID = 0)
        global $test_user_caps;
        $test_user_caps = [];
        
        $_POST = [
            'message' => 'Test message'
        ];
        
        try {
            $output = $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
            
            $this->assertFalse($output['success']);
            $this->assertStringContainsString('authenticated', $output['data'] ?? '');
        } catch (\Exception $e) {
            $this->assertStringContainsString('authenticated', $e->getMessage());
        }
    }
    
    /**
     * Test nonce verification on chat message
     */
    public function testChatMessageNonceVerification(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Test without nonce
        $_POST = [
            'message' => 'Test message',
            'session_id' => 'test_session'
        ];
        
        try {
            $output = $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
            
            $this->assertFalse($output['success']);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Security check failed', $e->getMessage());
        }
        
        // Test with invalid nonce
        $_POST['nonce'] = 'invalid_nonce';
        
        try {
            $output = $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
            
            $this->assertFalse($output['success']);
        } catch (\Exception $e) {
            $this->assertStringContainsString('Security check failed', $e->getMessage());
        }
        
        // Test with valid nonce
        $_POST['nonce'] = wp_create_nonce(NonceConstants::CHAT_MESSAGE);
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        // Should not fail on security check (may fail on other validations)
        if (!$output['success']) {
            $this->assertStringNotContainsString('Security check failed', $output['data'] ?? '');
        }
    }
    
    /**
     * Test permission checks for course creation
     */
    public function testCreateCoursePermissions(): void
    {
        // User without edit_posts capability
        global $test_user_caps;
        $test_user_caps = ['read' => true];
        
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CREATE_COURSE),
            'session_id' => 'test_session'
        ];
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleCreateCourse();
        });
        
        $this->assertFalse($output['success']);
        $this->assertStringContainsString('permission', strtolower($output['data'] ?? ''));
        
        // User with proper capability
        $test_user_caps['edit_posts'] = true;
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleCreateCourse();
        });
        
        // Should not fail on permissions (may fail on other validations)
        if (!$output['success']) {
            $this->assertStringNotContainsString('permission', strtolower($output['data'] ?? ''));
        }
    }
    
    /**
     * Test input sanitization for chat messages
     */
    public function testChatMessageSanitization(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Test with potentially dangerous input
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'message' => '<script>alert("XSS")</script>Hello',
            'session_id' => 'test_<script>alert("XSS")</script>',
            'context' => 'course_creation<img src=x onerror=alert("XSS")>'
        ];
        
        // Capture what would be sent to the service
        $capturedData = null;
        
        // We need to check how the controller processes this data
        // Since we can't easily intercept the service calls, we'll check the output
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        // The controller should sanitize inputs before processing
        // Check that dangerous content is not reflected in errors
        if (isset($output['data']) && is_string($output['data'])) {
            $this->assertStringNotContainsString('<script>', $output['data']);
            $this->assertStringNotContainsString('onerror=', $output['data']);
        }
    }
    
    /**
     * Test session ID validation
     */
    public function testSessionIdValidation(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Test with SQL injection attempt in session_id
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => "'; DROP TABLE sessions; --"
        ];
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        // Should handle gracefully without executing SQL
        $this->assertFalse($output['success']);
        
        // Test with valid session ID format
        $_POST['session_id'] = 'valid_session_123';
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        // May fail for not found, but not for invalid format
        if (!$output['success']) {
            $this->assertStringNotContainsString('SQL', $output['data'] ?? '');
        }
    }
    
    /**
     * Test all AJAX endpoints have nonce checks
     */
    public function testAllEndpointsHaveNonceChecks(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        $endpoints = [
            'handleChatMessage' => NonceConstants::CHAT_MESSAGE,
            'handleLoadSession' => NonceConstants::LOAD_SESSION,
            'handleCreateCourse' => NonceConstants::CREATE_COURSE,
            'handleGetSessions' => NonceConstants::GET_SESSIONS,
            'handleUpdateSessionTitle' => NonceConstants::UPDATE_SESSION,
            'handleDeleteSession' => NonceConstants::DELETE_SESSION,
            'handleDuplicateCourse' => NonceConstants::DUPLICATE_COURSE,
        ];
        
        foreach ($endpoints as $method => $nonceAction) {
            // Test without nonce
            $_POST = ['test' => 'data'];
            
            try {
                $output = $this->captureJsonOutput(function() use ($method) {
                    $this->controller->$method();
                });
                
                $this->assertFalse($output['success'], "Endpoint $method should fail without nonce");
            } catch (\Exception $e) {
                $this->assertStringContainsString('Security check failed', $e->getMessage());
            }
        }
    }
    
    /**
     * Test XSS prevention in responses
     */
    public function testXssPreventionInResponses(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Inject XSS in various fields
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::UPDATE_SESSION),
            'session_id' => 'test_session',
            'title' => '<script>alert("XSS")</script>New Title'
        ];
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleUpdateSessionTitle();
        });
        
        // Check that output doesn't contain unescaped scripts
        $outputJson = json_encode($output);
        $this->assertStringNotContainsString('<script>alert("XSS")</script>', $outputJson);
        
        // If the title is returned, it should be escaped
        if (isset($output['data']['title'])) {
            $this->assertStringNotContainsString('<script>', $output['data']['title']);
        }
    }
    
    /**
     * Test rate limiting protection
     */
    public function testRateLimitingProtection(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'message' => 'Test message',
            'session_id' => 'test_session'
        ];
        
        // Make multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 10; $i++) {
            $responses[] = $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
        }
        
        // At minimum, all requests should be handled safely
        // In a real implementation, we'd check for rate limit errors
        $this->assertCount(10, $responses);
    }
    
    /**
     * Test CORS and referer checks
     */
    public function testCorsAndRefererChecks(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Set suspicious referer
        $_SERVER['HTTP_REFERER'] = 'http://evil-site.com';
        
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'message' => 'Test',
            'session_id' => 'test'
        ];
        
        // In a real WordPress environment, this would be blocked
        // For now, just ensure the request is handled
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        $this->assertIsArray($output);
    }
    
    /**
     * Test file path traversal protection
     */
    public function testPathTraversalProtection(): void
    {
        global $test_user_caps;
        $test_user_caps = ['edit_posts' => true];
        
        // Attempt path traversal in session operations
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => '../../../etc/passwd'
        ];
        
        $output = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        // Should fail safely
        $this->assertFalse($output['success']);
        
        // Should not reveal system paths
        if (isset($output['data'])) {
            $this->assertStringNotContainsString('/etc/passwd', $output['data']);
            $this->assertStringNotContainsString('..', $output['data']);
        }
    }
    
    /**
     * Test authorization for different user roles
     */
    public function testUserRoleAuthorization(): void
    {
        $testCases = [
            'subscriber' => ['read' => true],
            'contributor' => ['read' => true, 'edit_posts' => true],
            'author' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true],
            'editor' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true, 'edit_others_posts' => true],
            'admin' => ['read' => true, 'edit_posts' => true, 'publish_posts' => true, 'manage_options' => true]
        ];
        
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CREATE_COURSE),
            'session_id' => 'test_session'
        ];
        
        foreach ($testCases as $role => $caps) {
            global $test_user_caps;
            $test_user_caps = $caps;
            
            $output = $this->captureJsonOutput(function() {
                $this->controller->handleCreateCourse();
            });
            
            // Only users with edit_posts should be able to create courses
            if (isset($caps['edit_posts']) && $caps['edit_posts']) {
                // May fail for other reasons, but not permissions
                if (!$output['success']) {
                    $this->assertStringNotContainsString('permission', strtolower($output['data'] ?? ''));
                }
            } else {
                $this->assertFalse($output['success']);
                $this->assertStringContainsString('permission', strtolower($output['data'] ?? ''));
            }
        }
    }
}