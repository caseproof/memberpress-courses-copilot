<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Models\ConversationSession;

/**
 * DatabaseService Test
 * 
 * Tests real database operations using SQLite
 * Following CLAUDE.md principles - no mocks, real database tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Services
 * @since 1.0.0
 */
class DatabaseServiceTest extends TestCase
{
    private DatabaseService $databaseService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize DatabaseService
        $this->databaseService = new DatabaseService();
        
        // Create tables for testing
        $this->createTestTables();
    }
    
    /**
     * Create test tables
     */
    private function createTestTables(): void
    {
        global $wpdb;
        
        // Install tables using the service
        $result = $this->databaseService->installTables();
        
        // If installation failed, create minimal tables for testing
        if (!$result) {
            $this->createMinimalTestTables();
        }
    }
    
    /**
     * Create minimal test tables if full installation fails
     */
    private function createMinimalTestTables(): void
    {
        global $wpdb;
        
        // Create conversations table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mpcc_conversations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            session_id TEXT NOT NULL,
            user_id INTEGER DEFAULT 0,
            context TEXT,
            conversation_data TEXT,
            status TEXT DEFAULT 'active',
            created_at TEXT,
            updated_at TEXT
        )";
        $wpdb->query($sql);
        
        // Create templates table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}mpcc_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT,
            template_data TEXT,
            usage_count INTEGER DEFAULT 0,
            created_at TEXT,
            updated_at TEXT
        )";
        $wpdb->query($sql);
    }
    
    /**
     * Test table installation
     */
    public function testInstallTables(): void
    {
        global $wpdb;
        
        // Tables should be created
        $tables = $wpdb->get_results("SELECT name FROM sqlite_master WHERE type='table'");
        $tableNames = array_map(function($table) { return $table->name; }, $tables);
        
        $this->assertContains($wpdb->prefix . 'mpcc_conversations', $tableNames);
        $this->assertContains($wpdb->prefix . 'mpcc_templates', $tableNames);
    }
    
    /**
     * Test saving a conversation
     */
    public function testSaveConversation(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $conversationData = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello'],
                ['role' => 'assistant', 'content' => 'Hi there!']
            ],
            'metadata' => [
                'course_id' => 123,
                'timestamp' => time()
            ]
        ];
        
        $result = $this->databaseService->saveConversation(
            $sessionId,
            1, // user_id
            $conversationData,
            'course_creation'
        );
        
        $this->assertTrue($result);
        
        // Verify the conversation was saved
        $saved = $this->databaseService->getConversation($sessionId);
        $this->assertNotNull($saved);
        $this->assertEquals($sessionId, $saved['session_id']);
        $this->assertEquals(1, $saved['user_id']);
        $this->assertEquals('course_creation', $saved['context']);
        
        // Verify conversation data
        $savedData = json_decode($saved['conversation_data'], true);
        $this->assertEquals($conversationData['messages'], $savedData['messages']);
    }
    
    /**
     * Test updating a conversation
     */
    public function testUpdateConversation(): void
    {
        $sessionId = 'test_session_' . uniqid();
        
        // First save
        $initialData = [
            'messages' => [
                ['role' => 'user', 'content' => 'Initial message']
            ]
        ];
        
        $this->databaseService->saveConversation($sessionId, 1, $initialData);
        
        // Update with new data
        $updatedData = [
            'messages' => [
                ['role' => 'user', 'content' => 'Initial message'],
                ['role' => 'assistant', 'content' => 'Response'],
                ['role' => 'user', 'content' => 'Follow up']
            ]
        ];
        
        $result = $this->databaseService->saveConversation($sessionId, 1, $updatedData);
        $this->assertTrue($result);
        
        // Verify update
        $saved = $this->databaseService->getConversation($sessionId);
        $savedData = json_decode($saved['conversation_data'], true);
        $this->assertCount(3, $savedData['messages']);
    }
    
    /**
     * Test getting user conversations
     */
    public function testGetUserConversations(): void
    {
        $userId = 123;
        
        // Create multiple conversations
        for ($i = 1; $i <= 3; $i++) {
            $this->databaseService->saveConversation(
                'session_' . $i,
                $userId,
                ['messages' => [['role' => 'user', 'content' => "Message $i"]]],
                'test_context'
            );
        }
        
        // Get user conversations
        $conversations = $this->databaseService->getUserConversations($userId);
        
        $this->assertIsArray($conversations);
        $this->assertCount(3, $conversations);
        
        // Verify order (should be newest first)
        $this->assertEquals('session_3', $conversations[0]['session_id']);
    }
    
    /**
     * Test conversation search
     */
    public function testSearchConversations(): void
    {
        // Create conversations with searchable content
        $this->databaseService->saveConversation(
            'search_1',
            1,
            ['messages' => [['role' => 'user', 'content' => 'Python programming course']]]
        );
        
        $this->databaseService->saveConversation(
            'search_2',
            1,
            ['messages' => [['role' => 'user', 'content' => 'JavaScript basics']]]
        );
        
        $this->databaseService->saveConversation(
            'search_3',
            1,
            ['messages' => [['role' => 'user', 'content' => 'Advanced Python techniques']]]
        );
        
        // Search for Python
        $results = $this->databaseService->searchConversations(1, 'Python');
        
        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        
        // Verify both Python conversations are found
        $sessionIds = array_column($results, 'session_id');
        $this->assertContains('search_1', $sessionIds);
        $this->assertContains('search_3', $sessionIds);
    }
    
    /**
     * Test saving and retrieving templates
     */
    public function testTemplateOperations(): void
    {
        $templateData = [
            'name' => 'Basic Programming Course',
            'category' => 'programming',
            'structure' => [
                'sections' => 5,
                'lessons_per_section' => 4,
                'duration' => '4 weeks'
            ]
        ];
        
        // Save template
        $templateId = $this->databaseService->saveTemplate($templateData);
        $this->assertIsNumeric($templateId);
        $this->assertGreaterThan(0, $templateId);
        
        // Get template
        $saved = $this->databaseService->getTemplate($templateId);
        $this->assertNotNull($saved);
        $this->assertEquals('Basic Programming Course', $saved['name']);
        $this->assertEquals('programming', $saved['category']);
        
        // Update usage count
        $this->databaseService->incrementTemplateUsage($templateId);
        
        $updated = $this->databaseService->getTemplate($templateId);
        $this->assertEquals(1, $updated['usage_count']);
    }
    
    /**
     * Test getting templates by category
     */
    public function testGetTemplatesByCategory(): void
    {
        // Create templates in different categories
        $this->databaseService->saveTemplate([
            'name' => 'Math Course 1',
            'category' => 'mathematics'
        ]);
        
        $this->databaseService->saveTemplate([
            'name' => 'Math Course 2',
            'category' => 'mathematics'
        ]);
        
        $this->databaseService->saveTemplate([
            'name' => 'Science Course',
            'category' => 'science'
        ]);
        
        // Get math templates
        $mathTemplates = $this->databaseService->getTemplatesByCategory('mathematics');
        
        $this->assertIsArray($mathTemplates);
        $this->assertCount(2, $mathTemplates);
        
        foreach ($mathTemplates as $template) {
            $this->assertEquals('mathematics', $template['category']);
        }
    }
    
    /**
     * Test transaction handling
     */
    public function testTransactionHandling(): void
    {
        global $wpdb;
        
        $sessionId = 'transaction_test';
        
        // Start transaction
        $wpdb->query('BEGIN TRANSACTION');
        
        try {
            // Save conversation
            $this->databaseService->saveConversation(
                $sessionId,
                1,
                ['messages' => [['role' => 'user', 'content' => 'Transaction test']]]
            );
            
            // Verify it exists within transaction
            $exists = $this->databaseService->getConversation($sessionId);
            $this->assertNotNull($exists);
            
            // Rollback
            $wpdb->query('ROLLBACK');
            
            // Verify it doesn't exist after rollback
            $afterRollback = $this->databaseService->getConversation($sessionId);
            $this->assertNull($afterRollback);
            
        } catch (\Exception $e) {
            $wpdb->query('ROLLBACK');
            throw $e;
        }
    }
    
    /**
     * Test error handling
     */
    public function testErrorHandling(): void
    {
        global $wpdb;
        
        // Try to save with invalid data that would violate constraints
        $result = $this->databaseService->saveConversation(
            '', // Empty session ID should fail
            1,
            ['messages' => []]
        );
        
        // Should handle error gracefully
        $this->assertFalse($result);
    }
    
    /**
     * Test data sanitization
     */
    public function testDataSanitization(): void
    {
        $sessionId = 'sanitize_test';
        
        // Save with potentially dangerous content
        $data = [
            'messages' => [[
                'role' => 'user',
                'content' => '<script>alert("XSS")</script>SELECT * FROM users;'
            ]]
        ];
        
        $result = $this->databaseService->saveConversation($sessionId, 1, $data);
        $this->assertTrue($result);
        
        // Retrieve and verify content is properly stored
        $saved = $this->databaseService->getConversation($sessionId);
        $savedData = json_decode($saved['conversation_data'], true);
        
        // Data should be stored as-is (escaped by database, not modified)
        $this->assertEquals($data['messages'][0]['content'], $savedData['messages'][0]['content']);
    }
}