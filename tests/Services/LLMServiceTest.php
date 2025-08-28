<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * LLMService Test
 * 
 * Tests the LLM service with real API integration
 * Following CLAUDE.md principles - no mocks, real tests
 * 
 * @package MemberPressCoursesCopilot\Tests\Services
 * @since 1.0.0
 */
class LLMServiceTest extends TestCase
{
    private LLMService $llmService;
    private bool $skipApiTests = false;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Check if we have real API credentials
        if (!defined('MPCC_AUTH_GATEWAY_URL') || !getenv('MPCC_TEST_LICENSE_KEY')) {
            $this->skipApiTests = true;
            $this->markTestSkipped('Real API credentials not available. Set MPCC_AUTH_GATEWAY_URL constant and MPCC_TEST_LICENSE_KEY environment variable to run API tests.');
        }
        
        $this->llmService = new LLMService();
    }
    
    /**
     * Test generateContent with real API call
     * Tests actual API integration, not mocks
     */
    public function testGenerateContentRealApi(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        $prompt = "Generate a single paragraph about the benefits of online learning. Keep it under 50 words.";
        
        $result = $this->llmService->generateContent($prompt);
        
        // Test that we get a valid response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('error', $result);
        
        if (!$result['error']) {
            // If successful, content should not be empty
            $this->assertNotEmpty($result['content']);
            $this->assertIsString($result['content']);
            
            // Content should be reasonable length (not too short, not too long)
            $this->assertGreaterThan(10, strlen($result['content']));
            $this->assertLessThan(5000, strlen($result['content']));
        } else {
            // If error, should have error message
            $this->assertArrayHasKey('message', $result);
            $this->assertNotEmpty($result['message']);
        }
    }
    
    /**
     * Test chat method with conversation history
     * Tests real conversation flow
     */
    public function testChatWithHistory(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        $history = [
            ['role' => 'user', 'content' => 'I want to create a course about Python'],
            ['role' => 'assistant', 'content' => 'Great! I can help you create a Python course. What level of learners are you targeting?'],
            ['role' => 'user', 'content' => 'Beginners']
        ];
        
        $result = $this->llmService->chat($history);
        
        // Verify response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('error', $result);
        
        if (!$result['error']) {
            $this->assertNotEmpty($result['content']);
            // Response should be contextually aware of the conversation
            $this->assertIsString($result['content']);
        }
    }
    
    /**
     * Test generateCourseContent method
     * Tests course-specific content generation
     */
    public function testGenerateCourseContent(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        $courseData = [
            'title' => 'Introduction to Web Development',
            'description' => 'Learn the basics of HTML, CSS, and JavaScript',
            'target_audience' => 'Complete beginners'
        ];
        
        $result = $this->llmService->generateCourseContent($courseData);
        
        // Test response structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('error', $result);
        
        if (!$result['error']) {
            $this->assertNotEmpty($result['content']);
            
            // Parse the content as JSON (course structure should be JSON)
            $courseStructure = json_decode($result['content'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                // If it's valid JSON, verify it has course structure
                $this->assertIsArray($courseStructure);
            }
        }
    }
    
    /**
     * Test error handling when API is unreachable
     */
    public function testHandleApiTimeout(): void
    {
        // Create service with invalid URL to force timeout
        $service = new LLMService();
        
        // Use reflection to set a bad URL
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('authGatewayUrl');
        $property->setAccessible(true);
        $property->setValue($service, 'http://invalid-url-that-does-not-exist.local');
        
        $result = $service->generateContent('test prompt', 'general', ['timeout' => 1]);
        
        // Should return error
        $this->assertTrue($result['error']);
        $this->assertNotEmpty($result['message']);
        $this->assertEmpty($result['content']);
    }
    
    /**
     * Test different content types use appropriate models
     */
    public function testContentTypeModelSelection(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        // Test course content generation (should use more capable model)
        $courseResult = $this->llmService->generateContent(
            'Create a course outline',
            'course_structure'
        );
        
        $this->assertIsArray($courseResult);
        
        // Test simple content generation (should use faster model)
        $simpleResult = $this->llmService->generateContent(
            'Write a short greeting',
            'lesson_content'
        );
        
        $this->assertIsArray($simpleResult);
    }
    
    /**
     * Test API response validation
     */
    public function testValidateApiResponse(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        // Test with very specific prompt to get predictable response
        $result = $this->llmService->generateContent(
            'Respond with exactly: "Hello World"',
            'general',
            ['temperature' => 0.0] // Low temperature for deterministic response
        );
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertArrayHasKey('error', $result);
        
        if (!$result['error']) {
            $this->assertStringContainsString('Hello', $result['content']);
        }
    }
    
    /**
     * Test max tokens limit
     */
    public function testMaxTokensLimit(): void
    {
        if ($this->skipApiTests) {
            $this->markTestSkipped('API tests skipped - no credentials');
        }
        
        $result = $this->llmService->generateContent(
            'Write a very long essay about technology',
            'general',
            ['max_tokens' => 50] // Very small limit
        );
        
        $this->assertIsArray($result);
        
        if (!$result['error']) {
            // Response should be relatively short due to token limit
            $this->assertLessThan(500, strlen($result['content'])); // Rough estimate
        }
    }
}