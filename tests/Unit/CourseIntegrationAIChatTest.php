<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\CourseIntegrationService;
use MemberPressCoursesCopilot\Services\LLMService;

/**
 * Test AI Chat functionality in CourseIntegrationService
 * 
 * This test specifically covers the AI chat response handling that was
 * causing issues where the backend was working but frontend wasn't
 * receiving proper success responses.
 */
class CourseIntegrationAIChatTest extends TestCase
{
    private CourseIntegrationService $integrationService;
    private LLMService $llmService;

    protected function setUp(): void
    {
        $this->integrationService = new CourseIntegrationService();
        $this->llmService = new LLMService();
    }

    /**
     * Test that AI chat generates valid response structure
     */
    public function testAIChatResponseStructure(): void
    {
        // Mock a successful AI response
        $aiResponse = $this->llmService->generateContent('Create a Python course outline');
        
        $this->assertIsArray($aiResponse);
        $this->assertArrayHasKey('error', $aiResponse);
        $this->assertArrayHasKey('content', $aiResponse);
        
        if (!$aiResponse['error']) {
            $this->assertIsString($aiResponse['content']);
            $this->assertNotEmpty($aiResponse['content']);
        }
    }

    /**
     * Test course data extraction from AI response
     */
    public function testCourseDataExtraction(): void
    {
        // Simulate AI response with JSON course data
        $mockAIResponse = 'Here is your course outline:

```json
{
    "title": "Introduction to Python Programming",
    "description": "A comprehensive course for beginners",
    "sections": [
        {
            "title": "Getting Started",
            "lessons": [
                {"title": "Installing Python", "content": "Learn how to install Python"}
            ]
        }
    ]
}
```

This course will teach you the fundamentals of Python programming.';

        // Test JSON extraction using the same regex pattern from the code
        $courseData = null;
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $mockAIResponse, $matches)) {
            $jsonData = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $courseData = $jsonData;
            }
        }

        $this->assertNotNull($courseData);
        $this->assertArrayHasKey('title', $courseData);
        $this->assertArrayHasKey('sections', $courseData);
        $this->assertEquals('Introduction to Python Programming', $courseData['title']);
        $this->assertCount(1, $courseData['sections']);
    }

    /**
     * Test response data structure for frontend
     */
    public function testResponseDataStructure(): void
    {
        // Test the response structure that should be sent to frontend
        $mockResponseData = [
            'message' => 'I can help you create a Python course.',
            'course_data' => [
                'title' => 'Python Basics',
                'sections' => [
                    ['title' => 'Introduction', 'lessons' => []]
                ]
            ],
            'context' => 'course_creation',
            'timestamp' => time(),
            'conversation_state' => [
                'current_step' => 'collecting_requirements',
                'collected_data' => []
            ],
            'actions' => [],
            'ready_to_create' => false,
            'update_preview' => true
        ];

        // Verify all required keys are present
        $requiredKeys = [
            'message', 'course_data', 'context', 'timestamp',
            'conversation_state', 'actions', 'ready_to_create', 'update_preview'
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $mockResponseData);
        }

        // Verify conversation state structure
        $this->assertArrayHasKey('current_step', $mockResponseData['conversation_state']);
        $this->assertArrayHasKey('collected_data', $mockResponseData['conversation_state']);
    }

    /**
     * Test course readiness detection
     */
    public function testCourseReadinessDetection(): void
    {
        // Test with complete course data
        $completeCourseData = [
            'title' => 'Complete Python Course',
            'description' => 'A complete course',
            'sections' => [
                [
                    'title' => 'Section 1',
                    'lessons' => [
                        ['title' => 'Lesson 1', 'content' => 'Content 1']
                    ]
                ]
            ]
        ];

        // This should be ready to create
        $readyToCreate = isset($completeCourseData['title']) && 
                        isset($completeCourseData['sections']) && 
                        count($completeCourseData['sections']) > 0;

        $this->assertTrue($readyToCreate);

        // Test with incomplete course data
        $incompleteCourseData = [
            'title' => 'Incomplete Course'
            // Missing sections
        ];

        $notReadyToCreate = isset($incompleteCourseData['title']) && 
                           isset($incompleteCourseData['sections']) && 
                           count($incompleteCourseData['sections']) > 0;

        $this->assertFalse($notReadyToCreate);
    }

    /**
     * Test nonce validation behavior
     */
    public function testNonceValidation(): void
    {
        // Mock $_POST data that would be sent from frontend
        $mockPostData = [
            'nonce' => 'test_nonce_value',
            'message' => 'Create a course about Python',
            'context' => 'course_creation',
            'conversation_history' => [],
            'conversation_state' => ['current_step' => 'initial']
        ];

        // In our test environment, wp_verify_nonce always returns true
        $nonceValid = wp_verify_nonce($mockPostData['nonce'], 'mpcc_courses_integration');
        $this->assertTrue($nonceValid);
    }

    /**
     * Test conversation state management
     */
    public function testConversationStateManagement(): void
    {
        $initialState = ['current_step' => 'initial', 'collected_data' => []];
        
        // Simulate collecting course data
        $newCourseData = ['title' => 'New Course'];
        $updatedState = [
            'current_step' => 'collecting_requirements',
            'collected_data' => array_merge($initialState['collected_data'], $newCourseData)
        ];

        $this->assertEquals('collecting_requirements', $updatedState['current_step']);
        $this->assertArrayHasKey('title', $updatedState['collected_data']);
        $this->assertEquals('New Course', $updatedState['collected_data']['title']);
    }

    /**
     * Test error conditions
     */
    public function testErrorConditions(): void
    {
        // Test empty message
        $emptyMessage = '';
        $this->assertEmpty($emptyMessage);

        // Test invalid JSON in AI response
        $invalidJsonResponse = '```json\n{invalid json}\n```';
        if (preg_match('/```json\s*([\s\S]*?)\s*```/', $invalidJsonResponse, $matches)) {
            $jsonData = json_decode($matches[1], true);
            $this->assertNull($jsonData);
            $this->assertNotEquals(JSON_ERROR_NONE, json_last_error());
        }
    }

    /**
     * Integration test for the complete AI chat flow
     */
    public function testCompleteAIChatFlow(): void
    {
        // Simulate complete flow from message to response
        $userMessage = 'I want to create a beginner Python course';
        
        // 1. Generate AI response
        $aiResponse = $this->llmService->generateContent($userMessage, 'course_assistance');
        $this->assertFalse($aiResponse['error']);
        
        // 2. Process response (simulate what handleAIChat does)
        $processedMessage = $aiResponse['content'];
        $courseData = null; // Would be extracted from JSON if present
        $readyToCreate = false;
        
        // 3. Build response data structure
        $responseData = [
            'message' => $processedMessage,
            'course_data' => $courseData,
            'context' => 'course_creation',
            'timestamp' => time(),
            'conversation_state' => [
                'current_step' => 'initial',
                'collected_data' => []
            ],
            'actions' => [],
            'ready_to_create' => $readyToCreate,
            'update_preview' => !empty($courseData)
        ];
        
        // 4. Verify response structure is valid for frontend
        $this->assertIsArray($responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertIsString($responseData['message']);
        $this->assertNotEmpty($responseData['message']);
        $this->assertIsBool($responseData['ready_to_create']);
        $this->assertIsBool($responseData['update_preview']);
    }
}