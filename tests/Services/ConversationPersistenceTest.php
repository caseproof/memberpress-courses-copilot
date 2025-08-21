<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\ConversationSession;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Utilities\Logger;
use Mockery;

/**
 * Unit tests for conversation persistence functionality
 * 
 * Tests the ConversationSession and ConversationManager services
 * to ensure proper session handling and data persistence.
 */
class ConversationPersistenceTest extends TestCase
{
    private ConversationSession $conversationSession;
    private ConversationManager $conversationManager;
    private $mockLogger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the logger
        $this->mockLogger = Mockery::mock(Logger::class);
        $this->mockLogger->shouldReceive('debug')->andReturnNull();
        $this->mockLogger->shouldReceive('info')->andReturnNull();
        $this->mockLogger->shouldReceive('error')->andReturnNull();
        
        // Create instances with mocked logger
        $this->conversationSession = new ConversationSession();
        $this->conversationManager = new ConversationManager();
        
        // Inject mock logger using reflection
        $this->injectMockLogger($this->conversationSession);
        $this->injectMockLogger($this->conversationManager);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Inject mock logger into service using reflection
     */
    private function injectMockLogger($service): void
    {
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($service, $this->mockLogger);
    }
    
    /**
     * Test creating a new conversation session
     */
    public function testCreateNewSession(): void
    {
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation',
            'title' => 'Test Course Creation'
        ]);
        
        $this->assertNotEmpty($sessionId);
        $this->assertIsString($sessionId);
        $this->assertEquals(32, strlen($sessionId)); // MD5 hash length
    }
    
    /**
     * Test saving conversation data
     */
    public function testSaveConversation(): void
    {
        // Create session first
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        $conversationData = [
            'conversation_history' => [
                ['role' => 'user', 'content' => 'I want to create a programming course'],
                ['role' => 'assistant', 'content' => 'Great! Let me help you create a programming course.']
            ],
            'conversation_state' => [
                'current_step' => 'gathering_details',
                'collected_data' => [
                    'course_type' => 'programming',
                    'target_audience' => 'beginners'
                ]
            ]
        ];
        
        $result = $this->conversationSession->saveSession($sessionId, $conversationData);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test loading conversation data
     */
    public function testLoadConversation(): void
    {
        // Create and save session first
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        $originalData = [
            'conversation_history' => [
                ['role' => 'user', 'content' => 'Test message'],
                ['role' => 'assistant', 'content' => 'Test response']
            ],
            'conversation_state' => [
                'current_step' => 'testing',
                'collected_data' => ['test' => 'data']
            ]
        ];
        
        $this->conversationSession->saveSession($sessionId, $originalData);
        
        // Load the session
        $loadedData = $this->conversationSession->loadSession($sessionId);
        
        $this->assertNotNull($loadedData);
        $this->assertArrayHasKey('conversation_history', $loadedData);
        $this->assertArrayHasKey('conversation_state', $loadedData);
        $this->assertEquals($originalData['conversation_history'], $loadedData['conversation_history']);
        $this->assertEquals($originalData['conversation_state'], $loadedData['conversation_state']);
    }
    
    /**
     * Test session validation
     */
    public function testSessionValidation(): void
    {
        $validSessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        $this->assertTrue($this->conversationSession->isValidSession($validSessionId));
        $this->assertFalse($this->conversationSession->isValidSession('invalid-session-id'));
    }
    
    /**
     * Test session expiration
     */
    public function testSessionExpiration(): void
    {
        // This test would require mocking time functions
        // For now, we'll test the basic structure
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        $sessionData = $this->conversationSession->getSessionMetadata($sessionId);
        
        $this->assertArrayHasKey('created_at', $sessionData);
        $this->assertArrayHasKey('updated_at', $sessionData);
        $this->assertArrayHasKey('expires_at', $sessionData);
    }
    
    /**
     * Test conversation manager user sessions listing
     */
    public function testListUserSessions(): void
    {
        $userId = 1;
        
        // Create multiple sessions
        $sessionIds = [];
        for ($i = 0; $i < 3; $i++) {
            $sessionIds[] = $this->conversationSession->createSession([
                'user_id' => $userId,
                'context' => 'course_creation',
                'title' => "Test Course $i"
            ]);
        }
        
        // List user sessions
        $sessions = $this->conversationManager->getUserSessions($userId);
        
        $this->assertIsArray($sessions);
        $this->assertGreaterThanOrEqual(3, count($sessions));
        
        // Verify session structure
        foreach ($sessions as $session) {
            $this->assertArrayHasKey('session_id', $session);
            $this->assertArrayHasKey('title', $session);
            $this->assertArrayHasKey('created_at', $session);
            $this->assertArrayHasKey('updated_at', $session);
        }
    }
    
    /**
     * Test conversation progress calculation
     */
    public function testCalculateProgress(): void
    {
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        // Save conversation with progress data
        $this->conversationSession->saveSession($sessionId, [
            'conversation_state' => [
                'current_step' => 'course_structure',
                'collected_data' => [
                    'course_title' => 'Test Course',
                    'course_description' => 'Test Description',
                    'target_audience' => 'Beginners',
                    'course_sections' => [
                        ['title' => 'Section 1'],
                        ['title' => 'Section 2']
                    ]
                ]
            ]
        ]);
        
        $progress = $this->conversationManager->calculateProgress($sessionId);
        
        $this->assertIsNumeric($progress);
        $this->assertGreaterThan(0, $progress);
        $this->assertLessThanOrEqual(100, $progress);
    }
    
    /**
     * Test conversation cleanup for expired sessions
     */
    public function testCleanupExpiredSessions(): void
    {
        // Create a session that would be expired
        $expiredSessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation',
            'expires_at' => time() - 3600 // Expired 1 hour ago
        ]);
        
        // Run cleanup
        $cleanedCount = $this->conversationManager->cleanupExpiredSessions();
        
        $this->assertIsInt($cleanedCount);
        $this->assertGreaterThanOrEqual(0, $cleanedCount);
        
        // Verify expired session is no longer valid
        $this->assertFalse($this->conversationSession->isValidSession($expiredSessionId));
    }
    
    /**
     * Test concurrent session handling
     */
    public function testConcurrentSessions(): void
    {
        $userId = 1;
        
        // Create multiple concurrent sessions
        $session1 = $this->conversationSession->createSession([
            'user_id' => $userId,
            'context' => 'course_creation',
            'title' => 'Course 1'
        ]);
        
        $session2 = $this->conversationSession->createSession([
            'user_id' => $userId,
            'context' => 'course_editing',
            'title' => 'Course 2'
        ]);
        
        // Save different data to each session
        $this->conversationSession->saveSession($session1, [
            'conversation_history' => [['role' => 'user', 'content' => 'Session 1 message']]
        ]);
        
        $this->conversationSession->saveSession($session2, [
            'conversation_history' => [['role' => 'user', 'content' => 'Session 2 message']]
        ]);
        
        // Load both sessions and verify data integrity
        $data1 = $this->conversationSession->loadSession($session1);
        $data2 = $this->conversationSession->loadSession($session2);
        
        $this->assertNotEquals($data1['conversation_history'][0]['content'], 
                              $data2['conversation_history'][0]['content']);
        $this->assertEquals('Session 1 message', $data1['conversation_history'][0]['content']);
        $this->assertEquals('Session 2 message', $data2['conversation_history'][0]['content']);
    }
    
    /**
     * Test session data sanitization
     */
    public function testDataSanitization(): void
    {
        $sessionId = $this->conversationSession->createSession([
            'user_id' => 1,
            'context' => 'course_creation'
        ]);
        
        // Try to save potentially malicious data
        $maliciousData = [
            'conversation_history' => [
                [
                    'role' => 'user', 
                    'content' => '<script>alert("XSS")</script>Test message'
                ]
            ],
            'conversation_state' => [
                'collected_data' => [
                    'title' => '<img src=x onerror=alert("XSS")>',
                    'sql_injection' => "'; DROP TABLE sessions; --"
                ]
            ]
        ];
        
        $this->conversationSession->saveSession($sessionId, $maliciousData);
        $loadedData = $this->conversationSession->loadSession($sessionId);
        
        // Verify data is properly sanitized
        $this->assertStringNotContainsString('<script>', $loadedData['conversation_history'][0]['content']);
        $this->assertStringNotContainsString('onerror=', $loadedData['conversation_state']['collected_data']['title']);
    }
}