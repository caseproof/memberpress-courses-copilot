<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Models\ConversationSession;

/**
 * ConversationManager Test
 * 
 * Tests conversation management with real database operations
 * Following CLAUDE.md principles - no mocks, real tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Services
 * @since 1.0.0
 */
class ConversationManagerTest extends TestCase
{
    private ConversationManager $conversationManager;
    private DatabaseService $databaseService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize services
        $this->databaseService = new DatabaseService();
        $this->conversationManager = new ConversationManager($this->databaseService);
        
        // Create test tables
        $this->databaseService->installTables();
    }
    
    /**
     * Test creating a new session
     */
    public function testCreateSession(): void
    {
        $userId = 123;
        $context = 'course_creation';
        $metadata = ['course_type' => 'programming'];
        
        $session = $this->conversationManager->createSession($userId, $context, $metadata);
        
        $this->assertInstanceOf(ConversationSession::class, $session);
        $this->assertNotEmpty($session->getSessionId());
        $this->assertEquals($userId, $session->getUserId());
        $this->assertEquals($context, $session->getContext());
        $this->assertEquals($metadata, $session->getMetadata());
        $this->assertEmpty($session->getMessages());
    }
    
    /**
     * Test loading an existing session
     */
    public function testLoadSession(): void
    {
        // Create a session first
        $session = $this->conversationManager->createSession(456, 'test_context');
        $sessionId = $session->getSessionId();
        
        // Add a message
        $session->addMessage('user', 'Hello');
        $this->conversationManager->saveSession($session);
        
        // Load the session
        $loadedSession = $this->conversationManager->loadSession($sessionId);
        
        $this->assertInstanceOf(ConversationSession::class, $loadedSession);
        $this->assertEquals($sessionId, $loadedSession->getSessionId());
        $this->assertEquals(456, $loadedSession->getUserId());
        $this->assertEquals('test_context', $loadedSession->getContext());
        
        // Check messages
        $messages = $loadedSession->getMessages();
        $this->assertCount(1, $messages);
        $this->assertEquals('user', $messages[0]['role']);
        $this->assertEquals('Hello', $messages[0]['content']);
    }
    
    /**
     * Test saving session with messages
     */
    public function testSaveSessionWithMessages(): void
    {
        $session = $this->conversationManager->createSession(789, 'chat');
        
        // Add multiple messages
        $session->addMessage('user', 'What is PHP?');
        $session->addMessage('assistant', 'PHP is a server-side scripting language.');
        $session->addMessage('user', 'Can you show me an example?');
        
        // Save session
        $result = $this->conversationManager->saveSession($session);
        $this->assertTrue($result);
        
        // Reload and verify
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $messages = $loaded->getMessages();
        
        $this->assertCount(3, $messages);
        $this->assertEquals('What is PHP?', $messages[0]['content']);
        $this->assertEquals('PHP is a server-side scripting language.', $messages[1]['content']);
        $this->assertEquals('Can you show me an example?', $messages[2]['content']);
    }
    
    /**
     * Test getting user sessions
     */
    public function testGetUserSessions(): void
    {
        $userId = 999;
        
        // Create multiple sessions
        $session1 = $this->conversationManager->createSession($userId, 'context1');
        $session1->addMessage('user', 'First session');
        $this->conversationManager->saveSession($session1);
        
        $session2 = $this->conversationManager->createSession($userId, 'context2');
        $session2->addMessage('user', 'Second session');
        $this->conversationManager->saveSession($session2);
        
        $session3 = $this->conversationManager->createSession($userId, 'context3');
        $session3->addMessage('user', 'Third session');
        $this->conversationManager->saveSession($session3);
        
        // Get user sessions
        $sessions = $this->conversationManager->getUserSessions($userId);
        
        $this->assertIsArray($sessions);
        $this->assertCount(3, $sessions);
        
        // Sessions should be ordered by most recent first
        $sessionIds = array_column($sessions, 'session_id');
        $this->assertEquals($session3->getSessionId(), $sessionIds[0]);
        $this->assertEquals($session2->getSessionId(), $sessionIds[1]);
        $this->assertEquals($session1->getSessionId(), $sessionIds[2]);
    }
    
    /**
     * Test session with metadata
     */
    public function testSessionMetadata(): void
    {
        $metadata = [
            'course_id' => 123,
            'course_title' => 'Introduction to Programming',
            'target_audience' => 'beginners',
            'duration' => '4 weeks'
        ];
        
        $session = $this->conversationManager->createSession(111, 'course_creation', $metadata);
        $this->conversationManager->saveSession($session);
        
        // Load and verify metadata
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $loadedMetadata = $loaded->getMetadata();
        
        $this->assertEquals($metadata['course_id'], $loadedMetadata['course_id']);
        $this->assertEquals($metadata['course_title'], $loadedMetadata['course_title']);
        $this->assertEquals($metadata['target_audience'], $loadedMetadata['target_audience']);
        $this->assertEquals($metadata['duration'], $loadedMetadata['duration']);
    }
    
    /**
     * Test updating session metadata
     */
    public function testUpdateSessionMetadata(): void
    {
        $session = $this->conversationManager->createSession(222, 'test');
        $session->setMetadata('key1', 'value1');
        $this->conversationManager->saveSession($session);
        
        // Update metadata
        $session->setMetadata('key2', 'value2');
        $session->setMetadata('key1', 'updated_value');
        $this->conversationManager->saveSession($session);
        
        // Load and verify
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $metadata = $loaded->getMetadata();
        
        $this->assertEquals('updated_value', $metadata['key1']);
        $this->assertEquals('value2', $metadata['key2']);
    }
    
    /**
     * Test session context filtering
     */
    public function testGetSessionsByContext(): void
    {
        $userId = 333;
        
        // Create sessions with different contexts
        $courseSession1 = $this->conversationManager->createSession($userId, 'course_creation');
        $this->conversationManager->saveSession($courseSession1);
        
        $courseSession2 = $this->conversationManager->createSession($userId, 'course_creation');
        $this->conversationManager->saveSession($courseSession2);
        
        $chatSession = $this->conversationManager->createSession($userId, 'general_chat');
        $this->conversationManager->saveSession($chatSession);
        
        // Get only course creation sessions
        $courseSessions = $this->conversationManager->getUserSessions($userId, 'course_creation');
        
        $this->assertCount(2, $courseSessions);
        foreach ($courseSessions as $session) {
            $this->assertEquals('course_creation', $session['context']);
        }
    }
    
    /**
     * Test session message limit
     */
    public function testSessionMessageLimit(): void
    {
        $session = $this->conversationManager->createSession(444, 'test');
        
        // Add many messages
        for ($i = 1; $i <= 100; $i++) {
            $session->addMessage('user', "Message $i");
            $session->addMessage('assistant', "Response $i");
        }
        
        // Save should handle large conversation
        $result = $this->conversationManager->saveSession($session);
        $this->assertTrue($result);
        
        // Load and verify all messages are preserved
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $messages = $loaded->getMessages();
        
        $this->assertCount(200, $messages); // 100 user + 100 assistant
    }
    
    /**
     * Test concurrent session access
     */
    public function testConcurrentSessionAccess(): void
    {
        $session = $this->conversationManager->createSession(555, 'test');
        $sessionId = $session->getSessionId();
        
        // Simulate concurrent updates
        $session1 = $this->conversationManager->loadSession($sessionId);
        $session2 = $this->conversationManager->loadSession($sessionId);
        
        $session1->addMessage('user', 'Update from session 1');
        $session2->addMessage('user', 'Update from session 2');
        
        $this->conversationManager->saveSession($session1);
        $this->conversationManager->saveSession($session2);
        
        // Load final state
        $final = $this->conversationManager->loadSession($sessionId);
        $messages = $final->getMessages();
        
        // Last save wins - should have session2's message
        $this->assertCount(1, $messages);
        $this->assertEquals('Update from session 2', $messages[0]['content']);
    }
    
    /**
     * Test session expiration/cleanup
     */
    public function testSessionCleanup(): void
    {
        // Create old sessions (would need to modify timestamps in real implementation)
        $oldSession = $this->conversationManager->createSession(666, 'old_session');
        $this->conversationManager->saveSession($oldSession);
        
        $newSession = $this->conversationManager->createSession(666, 'new_session');
        $this->conversationManager->saveSession($newSession);
        
        // Both should exist
        $sessions = $this->conversationManager->getUserSessions(666);
        $this->assertGreaterThanOrEqual(2, count($sessions));
    }
    
    /**
     * Test error handling for invalid session
     */
    public function testLoadInvalidSession(): void
    {
        $result = $this->conversationManager->loadSession('invalid_session_id');
        $this->assertNull($result);
    }
    
    /**
     * Test session with special characters
     */
    public function testSessionWithSpecialCharacters(): void
    {
        $session = $this->conversationManager->createSession(777, 'test');
        
        // Add messages with special characters
        $specialContent = 'Test with "quotes", \'apostrophes\', <tags>, & ampersands, émojis 🎉';
        $session->addMessage('user', $specialContent);
        
        $jsonContent = '{"key": "value", "nested": {"array": [1, 2, 3]}}';
        $session->addMessage('assistant', $jsonContent);
        
        $this->conversationManager->saveSession($session);
        
        // Load and verify
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $messages = $loaded->getMessages();
        
        $this->assertEquals($specialContent, $messages[0]['content']);
        $this->assertEquals($jsonContent, $messages[1]['content']);
    }
    
    /**
     * Test session title generation
     */
    public function testSessionTitleGeneration(): void
    {
        $session = $this->conversationManager->createSession(888, 'course_creation');
        
        // Add initial message that should influence title
        $session->addMessage('user', 'I want to create a course about Python programming for beginners');
        
        // Set a title based on content
        $session->setMetadata('title', 'Python Programming Course');
        $this->conversationManager->saveSession($session);
        
        // Verify title is saved
        $loaded = $this->conversationManager->loadSession($session->getSessionId());
        $metadata = $loaded->getMetadata();
        
        $this->assertEquals('Python Programming Course', $metadata['title']);
    }
}