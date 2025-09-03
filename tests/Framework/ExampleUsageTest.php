<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Framework;

use MemberPressCoursesCopilot\Tests\TestCase;

/**
 * Example test demonstrating the test isolation framework usage
 * 
 * This test shows how to use MockManager and TestDataFactory
 * to create isolated tests with proper mock management.
 * 
 * @package MemberPressCoursesCopilot\Tests\Framework
 */
class ExampleUsageTest extends TestCase
{
    /**
     * Test basic mock function usage
     */
    public function testBasicMockFunction(): void
    {
        // Mock a WordPress function for this test only
        $this->mockFunction('get_option', 'test_value');
        
        // The function will return our mocked value
        $this->assertEquals('test_value', get_option('any_option'));
        
        // Assert the function was called
        $this->assertFunctionCalled('get_option');
    }
    
    /**
     * Test mock function with callback
     */
    public function testMockFunctionWithCallback(): void
    {
        // Mock a function with a callback that processes arguments
        $this->mockFunction('sanitize_text_field', function($text) {
            return strtoupper($text);
        });
        
        // The function will use our callback
        $this->assertEquals('HELLO WORLD', sanitize_text_field('hello world'));
        
        // Assert it was called with specific arguments
        $this->assertFunctionCalledWith('sanitize_text_field', ['hello world']);
    }
    
    /**
     * Test multiple mocks at once
     */
    public function testMultipleMocks(): void
    {
        // Register multiple mocks
        $this->mockFunctions([
            'current_user_can' => true,
            'is_admin' => false,
            'get_current_user_id' => 42
        ]);
        
        // All mocks work independently
        $this->assertTrue(current_user_can('any_cap'));
        $this->assertFalse(is_admin());
        $this->assertEquals(42, get_current_user_id());
        
        // Assert call counts
        $this->assertFunctionCalled('current_user_can', 1);
        $this->assertFunctionCalled('is_admin', 1);
        $this->assertFunctionCalled('get_current_user_id', 1);
    }
    
    /**
     * Test using TestDataFactory
     */
    public function testDataFactory(): void
    {
        // Create a test course
        $course = TestDataFactory::createCourse([
            'post_title' => 'Advanced PHP Testing'
        ]);
        
        $this->assertEquals('Advanced PHP Testing', $course['post_title']);
        $this->assertEquals('mpcs-course', $course['post_type']);
        $this->assertArrayHasKey('meta', $course);
        
        // Create test lessons
        $lessons = TestDataFactory::createMany(
            [TestDataFactory::class, 'createLesson'],
            3,
            ['post_parent' => $course['ID']]
        );
        
        $this->assertCount(3, $lessons);
        foreach ($lessons as $lesson) {
            $this->assertEquals('mpcs-lesson', $lesson['post_type']);
            $this->assertEquals($course['ID'], $lesson['post_parent']);
        }
    }
    
    /**
     * Test mock isolation between tests
     */
    public function testMockIsolation(): void
    {
        // Mock get_option for this test
        $this->mockFunction('get_option', 'isolated_value');
        
        // It returns our mocked value
        $this->assertEquals('isolated_value', get_option('test'));
        
        // This mock won't affect other tests
    }
    
    /**
     * Test mock with call limit
     */
    public function testMockWithCallLimit(): void
    {
        // Allow function to be called only twice
        $this->mockFunction('wp_cache_get', 'cached_value', 2);
        
        // First two calls work
        $this->assertEquals('cached_value', wp_cache_get('key1'));
        $this->assertEquals('cached_value', wp_cache_get('key2'));
        
        // Third call would throw an exception
        try {
            wp_cache_get('key3');
            $this->fail('Expected exception for exceeding call limit');
        } catch (\RuntimeException $e) {
            $this->assertStringContains('exceeded call limit', $e->getMessage());
        }
    }
    
    /**
     * Test complex AJAX request scenario
     */
    public function testAjaxRequestWithMocks(): void
    {
        // Create test data
        $requestData = TestDataFactory::createAjaxRequest([
            'action' => 'copilot_chat',
            'data' => [
                'message' => 'How do I complete this lesson?',
                'lesson_id' => 123
            ]
        ]);
        
        // Mock WordPress functions needed for AJAX
        $this->mockFunctions([
            'current_user_can' => true,
            'check_ajax_referer' => true,
            'get_current_user_id' => 1
        ]);
        
        // Mock get_post to return our test lesson
        $this->mockFunction('get_post', TestDataFactory::createLesson([
            'ID' => 123,
            'post_title' => 'Introduction to Testing'
        ]));
        
        // Set up request
        $this->setPostData($requestData);
        
        // Assert our setup
        $this->assertTrue(current_user_can('read'));
        $post = get_post(123);
        $this->assertEquals('Introduction to Testing', $post['post_title']);
        
        // Verify all expected functions were called
        $this->assertFunctionCalled('current_user_can');
        $this->assertFunctionCalled('get_post');
    }
    
    /**
     * Test tearDown properly cleans up mocks
     */
    public function testTearDownCleansUp(): void
    {
        // This test verifies that mocks from testMockIsolation
        // don't affect this test
        
        // Without any mocks, get_option should return false (default behavior)
        $this->assertFalse(get_option('non_existent_option'));
        
        // This proves mocks are properly isolated between tests
    }
}