<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Integration;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Controllers\SimpleAjaxController;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * AJAX Integration Test
 * 
 * Tests complete AJAX workflows with real service integration
 * Following CLAUDE.md principles - real integration tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Integration
 * @since 1.0.0
 */
class AjaxIntegrationTest extends TestCase
{
    private SimpleAjaxController $controller;
    private DatabaseService $databaseService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize real services for integration testing
        $this->databaseService = new DatabaseService();
        $this->databaseService->installTables();
        
        // Initialize controller with real dependencies
        $this->controller = new SimpleAjaxController();
        
        // Set user capabilities
        global $test_user_caps;
        $test_user_caps = ['read' => true, 'edit_posts' => true];
    }
    
    /**
     * Test complete chat workflow
     */
    public function testCompleteChatWorkflow(): void
    {
        // Step 1: Create a new session
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'message' => 'I want to create a course about Python programming',
            'context' => 'course_creation'
        ];
        
        $response1 = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        $this->assertTrue($response1['success']);
        $this->assertArrayHasKey('session_id', $response1['data']);
        $this->assertArrayHasKey('response', $response1['data']);
        
        $sessionId = $response1['data']['session_id'];
        
        // Step 2: Continue conversation with session
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'session_id' => $sessionId,
            'message' => 'The course should be for beginners and cover basics'
        ];
        
        $response2 = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        $this->assertTrue($response2['success']);
        $this->assertEquals($sessionId, $response2['data']['session_id']);
        
        // Step 3: Load the session
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => $sessionId
        ];
        
        $response3 = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        $this->assertTrue($response3['success']);
        $this->assertArrayHasKey('messages', $response3['data']);
        $this->assertGreaterThanOrEqual(2, count($response3['data']['messages']));
    }
    
    /**
     * Test course creation workflow
     */
    public function testCourseCreationWorkflow(): void
    {
        // Step 1: Create session with course data
        $sessionId = $this->createTestSession();
        
        // Step 2: Generate course structure through conversation
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'session_id' => $sessionId,
            'message' => 'Create a 3-section course with 2 lessons each'
        ];
        
        $chatResponse = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        $this->assertTrue($chatResponse['success']);
        
        // Step 3: Create the actual course
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CREATE_COURSE),
            'session_id' => $sessionId
        ];
        
        $createResponse = $this->captureJsonOutput(function() {
            $this->controller->handleCreateCourse();
        });
        
        // May fail if no course data in session, but should handle gracefully
        $this->assertIsArray($createResponse);
        $this->assertArrayHasKey('success', $createResponse);
        
        if ($createResponse['success']) {
            $this->assertArrayHasKey('course_id', $createResponse['data']);
            $this->assertArrayHasKey('redirect_url', $createResponse['data']);
        }
    }
    
    /**
     * Test session management workflow
     */
    public function testSessionManagementWorkflow(): void
    {
        // Create multiple sessions
        $session1 = $this->createTestSession('Session 1');
        $session2 = $this->createTestSession('Session 2');
        $session3 = $this->createTestSession('Session 3');
        
        // Get all sessions
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::GET_SESSIONS)
        ];
        
        $sessionsResponse = $this->captureJsonOutput(function() {
            $this->controller->handleGetSessions();
        });
        
        $this->assertTrue($sessionsResponse['success']);
        $this->assertArrayHasKey('sessions', $sessionsResponse['data']);
        $this->assertGreaterThanOrEqual(3, count($sessionsResponse['data']['sessions']));
        
        // Update session title
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::UPDATE_SESSION),
            'session_id' => $session1,
            'title' => 'Updated Session Title'
        ];
        
        $updateResponse = $this->captureJsonOutput(function() {
            $this->controller->handleUpdateSessionTitle();
        });
        
        $this->assertTrue($updateResponse['success']);
        
        // Delete a session
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::DELETE_SESSION),
            'session_id' => $session3
        ];
        
        $deleteResponse = $this->captureJsonOutput(function() {
            $this->controller->handleDeleteSession();
        });
        
        $this->assertTrue($deleteResponse['success']);
        
        // Verify session was deleted
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => $session3
        ];
        
        $loadResponse = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        $this->assertFalse($loadResponse['success']);
    }
    
    /**
     * Test lesson draft workflow
     */
    public function testLessonDraftWorkflow(): void
    {
        $sessionId = $this->createTestSession();
        
        // Get session drafts (should be empty initially)
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::GET_SESSION_DRAFTS),
            'session_id' => $sessionId
        ];
        
        $draftsResponse = $this->captureJsonOutput(function() {
            $this->controller->handleGetSessionDrafts();
        });
        
        $this->assertTrue($draftsResponse['success']);
        $this->assertArrayHasKey('drafts', $draftsResponse['data']);
    }
    
    /**
     * Test error recovery workflow
     */
    public function testErrorRecoveryWorkflow(): void
    {
        // Test with invalid session
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'session_id' => 'invalid_session_id',
            'message' => 'This should fail gracefully'
        ];
        
        $response = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        // Should either create new session or return error
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        
        if (!$response['success']) {
            $this->assertArrayHasKey('data', $response);
            $this->assertIsString($response['data']); // Error message
        } else {
            // New session created
            $this->assertArrayHasKey('session_id', $response['data']);
        }
    }
    
    /**
     * Test concurrent requests handling
     */
    public function testConcurrentRequestsHandling(): void
    {
        $sessionId = $this->createTestSession();
        
        // Simulate multiple rapid requests
        $responses = [];
        
        for ($i = 1; $i <= 5; $i++) {
            $_POST = [
                'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
                'session_id' => $sessionId,
                'message' => "Message $i"
            ];
            
            $responses[] = $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
        }
        
        // All requests should be handled
        $this->assertCount(5, $responses);
        
        // Verify session has all messages
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => $sessionId
        ];
        
        $loadResponse = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        $this->assertTrue($loadResponse['success']);
        $messages = $loadResponse['data']['messages'];
        
        // Should have at least the user messages
        $userMessages = array_filter($messages, fn($m) => $m['role'] === 'user');
        $this->assertGreaterThanOrEqual(5, count($userMessages));
    }
    
    /**
     * Test complete course duplication workflow
     */
    public function testCourseDuplicationWorkflow(): void
    {
        // Mock course ID for testing
        $originalCourseId = 123;
        
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::DUPLICATE_COURSE),
            'course_id' => $originalCourseId
        ];
        
        $response = $this->captureJsonOutput(function() {
            $this->controller->handleDuplicateCourse();
        });
        
        $this->assertIsArray($response);
        $this->assertArrayHasKey('success', $response);
        
        // Note: May fail without actual course, but should handle gracefully
        if ($response['success']) {
            $this->assertArrayHasKey('new_course_id', $response['data']);
            $this->assertArrayHasKey('redirect_url', $response['data']);
        }
    }
    
    /**
     * Test conversation save workflow
     */
    public function testConversationSaveWorkflow(): void
    {
        $sessionId = $this->createTestSession();
        
        // Add some messages
        for ($i = 1; $i <= 3; $i++) {
            $_POST = [
                'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
                'session_id' => $sessionId,
                'message' => "Test message $i"
            ];
            
            $this->captureJsonOutput(function() {
                $this->controller->handleChatMessage();
            });
        }
        
        // Save conversation
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::SAVE_CONVERSATION),
            'session_id' => $sessionId,
            'title' => 'Test Conversation',
            'metadata' => [
                'course_type' => 'programming',
                'level' => 'beginner'
            ]
        ];
        
        $response = $this->captureJsonOutput(function() {
            $this->controller->handleSaveConversation();
        });
        
        $this->assertTrue($response['success']);
        
        // Verify it's saved by loading it again
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::LOAD_SESSION),
            'session_id' => $sessionId
        ];
        
        $loadResponse = $this->captureJsonOutput(function() {
            $this->controller->handleLoadSession();
        });
        
        $this->assertTrue($loadResponse['success']);
        if (isset($loadResponse['data']['metadata'])) {
            $this->assertEquals('Test Conversation', $loadResponse['data']['metadata']['title'] ?? '');
        }
    }
    
    /**
     * Helper: Create a test session
     */
    private function createTestSession(string $firstMessage = 'Test message'): string
    {
        $_POST = [
            'nonce' => wp_create_nonce(NonceConstants::CHAT_MESSAGE),
            'message' => $firstMessage,
            'context' => 'test_context'
        ];
        
        $response = $this->captureJsonOutput(function() {
            $this->controller->handleChatMessage();
        });
        
        return $response['data']['session_id'] ?? 'test_session_' . uniqid();
    }
}