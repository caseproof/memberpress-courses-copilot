<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use MemberPressCoursesCopilot\Tests\TestCase;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Database\LessonDraftTable;

/**
 * LessonDraftService Test
 * 
 * Tests lesson draft functionality with real database operations
 * Following CLAUDE.md principles - no mocks, real tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Services
 * @since 1.0.0
 */
class LessonDraftServiceTest extends TestCase
{
    private LessonDraftService $lessonDraftService;
    private DatabaseService $databaseService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Initialize services
        $this->databaseService = new DatabaseService();
        $this->databaseService->installTables();
        
        $this->lessonDraftService = new LessonDraftService($this->databaseService);
    }
    
    /**
     * Test saving a new lesson draft
     */
    public function testSaveNewDraft(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_1';
        $content = 'This is the lesson content about PHP basics.';
        $orderIndex = 1;
        
        $result = $this->lessonDraftService->saveDraft(
            $sessionId,
            $sectionId,
            $lessonId,
            $content,
            $orderIndex
        );
        
        $this->assertTrue($result);
        
        // Verify the draft was saved
        $saved = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        
        $this->assertNotNull($saved);
        $this->assertEquals($sessionId, $saved->session_id);
        $this->assertEquals($sectionId, $saved->section_id);
        $this->assertEquals($lessonId, $saved->lesson_id);
        $this->assertEquals($content, $saved->content);
        $this->assertEquals($orderIndex, $saved->order_index);
    }
    
    /**
     * Test updating an existing draft
     */
    public function testUpdateExistingDraft(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_1';
        
        // Save initial draft
        $originalContent = 'Original content';
        $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, $originalContent);
        
        // Update the draft
        $updatedContent = 'Updated content with more details';
        $result = $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, $updatedContent, 2);
        
        $this->assertTrue($result);
        
        // Verify update
        $saved = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        
        $this->assertEquals($updatedContent, $saved->content);
        $this->assertEquals(2, $saved->order_index);
    }
    
    /**
     * Test getting drafts by session
     */
    public function testGetDraftsBySession(): void
    {
        $sessionId = 'test_session_' . uniqid();
        
        // Save multiple drafts for the session
        $drafts = [
            ['section_1', 'lesson_1', 'Content for lesson 1', 1],
            ['section_1', 'lesson_2', 'Content for lesson 2', 2],
            ['section_2', 'lesson_3', 'Content for lesson 3', 1],
            ['section_2', 'lesson_4', 'Content for lesson 4', 2],
        ];
        
        foreach ($drafts as $draft) {
            $this->lessonDraftService->saveDraft(
                $sessionId,
                $draft[0],
                $draft[1],
                $draft[2],
                $draft[3]
            );
        }
        
        // Get all drafts for the session
        $sessionDrafts = $this->lessonDraftService->getDraftsBySession($sessionId);
        
        $this->assertIsArray($sessionDrafts);
        $this->assertCount(4, $sessionDrafts);
        
        // Verify order
        foreach ($sessionDrafts as $index => $draft) {
            $this->assertEquals($drafts[$index][2], $draft->content);
        }
    }
    
    /**
     * Test getting drafts by section
     */
    public function testGetDraftsBySection(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_test';
        
        // Save drafts for the section
        for ($i = 1; $i <= 3; $i++) {
            $this->lessonDraftService->saveDraft(
                $sessionId,
                $sectionId,
                "lesson_$i",
                "Content for lesson $i",
                $i
            );
        }
        
        // Save draft for different section
        $this->lessonDraftService->saveDraft(
            $sessionId,
            'other_section',
            'other_lesson',
            'Other content',
            1
        );
        
        // Get section drafts
        $sectionDrafts = $this->lessonDraftService->getDraftsBySection($sessionId, $sectionId);
        
        $this->assertCount(3, $sectionDrafts);
        
        // Verify all drafts are from correct section
        foreach ($sectionDrafts as $draft) {
            $this->assertEquals($sectionId, $draft->section_id);
        }
        
        // Verify they're ordered correctly
        $this->assertEquals('lesson_1', $sectionDrafts[0]->lesson_id);
        $this->assertEquals('lesson_2', $sectionDrafts[1]->lesson_id);
        $this->assertEquals('lesson_3', $sectionDrafts[2]->lesson_id);
    }
    
    /**
     * Test deleting a draft
     */
    public function testDeleteDraft(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_to_delete';
        
        // Save a draft
        $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, 'Content to delete');
        
        // Verify it exists
        $draft = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        $this->assertNotNull($draft);
        
        // Delete it
        $result = $this->lessonDraftService->deleteDraft($sessionId, $sectionId, $lessonId);
        $this->assertTrue($result);
        
        // Verify it's gone
        $deleted = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        $this->assertNull($deleted);
    }
    
    /**
     * Test deleting all drafts for a session
     */
    public function testDeleteSessionDrafts(): void
    {
        $sessionId = 'test_session_' . uniqid();
        
        // Save multiple drafts
        for ($i = 1; $i <= 5; $i++) {
            $this->lessonDraftService->saveDraft(
                $sessionId,
                "section_$i",
                "lesson_$i",
                "Content $i"
            );
        }
        
        // Verify they exist
        $drafts = $this->lessonDraftService->getDraftsBySession($sessionId);
        $this->assertCount(5, $drafts);
        
        // Delete all session drafts
        $result = $this->lessonDraftService->deleteSessionDrafts($sessionId);
        $this->assertTrue($result);
        
        // Verify they're gone
        $deletedDrafts = $this->lessonDraftService->getDraftsBySession($sessionId);
        $this->assertCount(0, $deletedDrafts);
    }
    
    /**
     * Test handling of special characters in content
     */
    public function testSpecialCharactersInContent(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_special';
        
        $specialContent = <<<'CONTENT'
This content has "quotes", 'apostrophes', <tags>, & ampersands.
It also has newlines
and special characters: émojis 🎉, código en español.
Even some JSON: {"key": "value", "array": [1, 2, 3]}
CONTENT;
        
        $result = $this->lessonDraftService->saveDraft(
            $sessionId,
            $sectionId,
            $lessonId,
            $specialContent
        );
        
        $this->assertTrue($result);
        
        // Retrieve and verify
        $saved = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        
        $this->assertEquals($specialContent, $saved->content);
        $this->assertStringContainsString('émojis 🎉', $saved->content);
        $this->assertStringContainsString('{"key": "value"', $saved->content);
    }
    
    /**
     * Test draft ordering
     */
    public function testDraftOrdering(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        
        // Save drafts in random order
        $lessons = [
            ['lesson_3', 'Content 3', 3],
            ['lesson_1', 'Content 1', 1],
            ['lesson_4', 'Content 4', 4],
            ['lesson_2', 'Content 2', 2],
        ];
        
        foreach ($lessons as $lesson) {
            $this->lessonDraftService->saveDraft(
                $sessionId,
                $sectionId,
                $lesson[0],
                $lesson[1],
                $lesson[2]
            );
        }
        
        // Get drafts - should be ordered by order_index
        $drafts = $this->lessonDraftService->getDraftsBySection($sessionId, $sectionId);
        
        $this->assertCount(4, $drafts);
        $this->assertEquals('lesson_1', $drafts[0]->lesson_id);
        $this->assertEquals('lesson_2', $drafts[1]->lesson_id);
        $this->assertEquals('lesson_3', $drafts[2]->lesson_id);
        $this->assertEquals('lesson_4', $drafts[3]->lesson_id);
    }
    
    /**
     * Test error handling for invalid data
     */
    public function testErrorHandling(): void
    {
        // Test with empty session ID
        $result = $this->lessonDraftService->saveDraft('', 'section', 'lesson', 'content');
        $this->assertFalse($result);
        
        // Test getting non-existent draft
        $draft = $this->lessonDraftService->getDraft('non_existent', 'section', 'lesson');
        $this->assertNull($draft);
        
        // Test deleting non-existent draft
        $deleteResult = $this->lessonDraftService->deleteDraft('non_existent', 'section', 'lesson');
        $this->assertTrue($deleteResult); // Should return true even if nothing to delete
    }
    
    /**
     * Test concurrent draft updates
     */
    public function testConcurrentDraftUpdates(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_1';
        
        // Save initial draft
        $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, 'Initial content');
        
        // Simulate concurrent updates
        $update1 = $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, 'Update 1');
        $update2 = $this->lessonDraftService->saveDraft($sessionId, $sectionId, $lessonId, 'Update 2');
        
        $this->assertTrue($update1);
        $this->assertTrue($update2);
        
        // Last update wins
        $final = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        $this->assertEquals('Update 2', $final->content);
    }
    
    /**
     * Test large content handling
     */
    public function testLargeContentHandling(): void
    {
        $sessionId = 'test_session_' . uniqid();
        $sectionId = 'section_1';
        $lessonId = 'lesson_large';
        
        // Generate large content (10KB)
        $largeContent = str_repeat('This is a long lesson content. ', 350);
        
        $result = $this->lessonDraftService->saveDraft(
            $sessionId,
            $sectionId,
            $lessonId,
            $largeContent
        );
        
        $this->assertTrue($result);
        
        // Verify it's saved correctly
        $saved = $this->lessonDraftService->getDraft($sessionId, $sectionId, $lessonId);
        $this->assertEquals(strlen($largeContent), strlen($saved->content));
    }
}