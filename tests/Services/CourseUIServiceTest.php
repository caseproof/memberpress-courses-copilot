<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\CourseUIService;
use MemberPressCoursesCopilot\Services\EnhancedTemplateEngine;
use MemberPressCoursesCopilot\Utilities\Logger;
use Mockery;

/**
 * Unit tests for CourseUIService
 * 
 * Tests the UI rendering responsibilities and template integration
 * for the course creation interface components.
 */
class CourseUIServiceTest extends TestCase
{
    private CourseUIService $uiService;
    private $mockTemplateEngine;
    private $mockLogger;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock dependencies
        $this->mockLogger = Mockery::mock(Logger::class);
        $this->mockLogger->shouldReceive('debug')->andReturnNull();
        $this->mockLogger->shouldReceive('info')->andReturnNull();
        $this->mockLogger->shouldReceive('error')->andReturnNull();
        
        $this->mockTemplateEngine = Mockery::mock(EnhancedTemplateEngine::class);
        
        // Create UI service instance
        $this->uiService = new CourseUIService($this->mockTemplateEngine);
        
        // Inject mock logger
        $reflection = new \ReflectionClass($this->uiService);
        $property = $reflection->getProperty('logger');
        $property->setAccessible(true);
        $property->setValue($this->uiService, $this->mockLogger);
    }
    
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test UI service initialization
     */
    public function testInitialization(): void
    {
        // Expect global data to be set
        $this->mockTemplateEngine
            ->shouldReceive('setGlobalDataArray')
            ->once()
            ->with(Mockery::on(function($data) {
                return isset($data['plugin_version']) &&
                       isset($data['text_domain']) &&
                       isset($data['ajax_url']) &&
                       isset($data['nonce']);
            }));
        
        $this->uiService->init();
        
        // Test passes if no exceptions thrown and expectations met
        $this->assertTrue(true);
    }
    
    /**
     * Test rendering AI interface for course creation
     */
    public function testRenderAIInterfaceForCourseCreation(): void
    {
        $expectedTemplate = 'components/chat/creation-interface';
        $expectedData = [
            'context' => 'course_creation',
            'post_id' => 0,
            'welcome_message' => "Hi! I'm here to help you create an amazing course. What kind of course would you like to build?",
            'quick_actions' => [
                [
                    'text' => 'Programming Course',
                    'prompt' => 'I want to create a programming course',
                    'icon' => 'dashicons-editor-code'
                ],
                [
                    'text' => 'Business Course',
                    'prompt' => 'I want to create a business skills course',
                    'icon' => 'dashicons-chart-line'
                ],
                [
                    'text' => 'Creative Course',
                    'prompt' => 'I want to create a creative arts course',
                    'icon' => 'dashicons-art'
                ]
            ],
            'session_enabled' => true
        ];
        
        $this->mockTemplateEngine
            ->shouldReceive('render')
            ->once()
            ->with($expectedTemplate, Mockery::on(function($data) use ($expectedData) {
                return $data['context'] === $expectedData['context'] &&
                       $data['post_id'] === $expectedData['post_id'] &&
                       $data['welcome_message'] === $expectedData['welcome_message'] &&
                       count($data['quick_actions']) === 3;
            }))
            ->andReturn('<div>AI Interface HTML</div>');
        
        $result = $this->uiService->renderAIInterface('course_creation', 0);
        
        $this->assertEquals('<div>AI Interface HTML</div>', $result);
    }
    
    /**
     * Test rendering course preview
     */
    public function testRenderCoursePreview(): void
    {
        $courseData = [
            'title' => 'Test Programming Course',
            'description' => 'Learn programming basics',
            'sections' => [
                [
                    'title' => 'Introduction',
                    'lessons' => [
                        ['title' => 'Getting Started'],
                        ['title' => 'Basic Concepts']
                    ]
                ],
                [
                    'title' => 'Advanced Topics',
                    'lessons' => [
                        ['title' => 'Complex Algorithms']
                    ]
                ]
            ]
        ];
        
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('preview/course-structure', [
                'course' => $courseData,
                'show_actions' => true
            ])
            ->andReturn('<div class="course-preview">Preview HTML</div>');
        
        $result = $this->uiService->renderCoursePreview($courseData);
        
        $this->assertEquals('<div class="course-preview">Preview HTML</div>', $result);
    }
    
    /**
     * Test rendering modal wrapper
     */
    public function testRenderModal(): void
    {
        $options = [
            'id' => 'test-modal',
            'title' => 'Test Modal Title'
        ];
        
        $expectedOptions = array_merge([
            'id' => 'test-modal',
            'title' => 'Test Modal Title',
            'loading_text' => 'Loading AI Assistant...',
            'preview_title' => 'Course Preview',
            'show_close' => true,
            'dual_pane' => true
        ], $options);
        
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('modal/ai-creation-modal', Mockery::on(function($data) {
                return $data['id'] === 'test-modal' &&
                       $data['title'] === 'Test Modal Title' &&
                       $data['dual_pane'] === true;
            }))
            ->andReturn('<div class="modal">Modal HTML</div>');
        
        $result = $this->uiService->renderModal($options);
        
        $this->assertEquals('<div class="modal">Modal HTML</div>', $result);
    }
    
    /**
     * Test rendering error message
     */
    public function testRenderError(): void
    {
        $errorMessage = 'Test error occurred';
        $errorDetails = [
            'code' => 'test_error',
            'severity' => 'warning'
        ];
        
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('ui/error-message', [
                'message' => $errorMessage,
                'details' => $errorDetails,
                'dismissible' => true
            ])
            ->andReturn('<div class="error">Error HTML</div>');
        
        $result = $this->uiService->renderError($errorMessage, $errorDetails);
        
        $this->assertEquals('<div class="error">Error HTML</div>', $result);
    }
    
    /**
     * Test rendering success message
     */
    public function testRenderSuccess(): void
    {
        $successMessage = 'Course created successfully!';
        $actions = [
            ['label' => 'Edit Course', 'url' => '/edit-course'],
            ['label' => 'View Course', 'url' => '/view-course']
        ];
        
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('ui/success-message', [
                'message' => $successMessage,
                'actions' => $actions,
                'dismissible' => true
            ])
            ->andReturn('<div class="success">Success HTML</div>');
        
        $result = $this->uiService->renderSuccess($successMessage, $actions);
        
        $this->assertEquals('<div class="success">Success HTML</div>', $result);
    }
    
    /**
     * Test rendering loading indicator
     */
    public function testRenderLoadingIndicator(): void
    {
        // Test with custom message
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('ui/loading-indicator', [
                'message' => 'Processing course data...'
            ])
            ->andReturn('<div class="loading">Loading...</div>');
        
        $result = $this->uiService->renderLoadingIndicator('Processing course data...');
        
        $this->assertEquals('<div class="loading">Loading...</div>', $result);
        
        // Test with default message
        $this->mockTemplateEngine
            ->shouldReceive('renderComponent')
            ->once()
            ->with('ui/loading-indicator', [
                'message' => 'Loading...'
            ])
            ->andReturn('<div class="loading">Loading...</div>');
        
        $result = $this->uiService->renderLoadingIndicator();
        
        $this->assertEquals('<div class="loading">Loading...</div>', $result);
    }
    
    /**
     * Test welcome message generation for different contexts
     */
    public function testGetWelcomeMessage(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->uiService);
        $method = $reflection->getMethod('getWelcomeMessage');
        $method->setAccessible(true);
        
        // Test course creation context
        $message = $method->invoke($this->uiService, 'course_creation');
        $this->assertStringContainsString('help you create an amazing course', $message);
        
        // Test course editing context
        $message = $method->invoke($this->uiService, 'course_editing');
        $this->assertStringContainsString('help you improve this course', $message);
        
        // Test lesson creation context
        $message = $method->invoke($this->uiService, 'lesson_creation');
        $this->assertStringContainsString('create engaging lesson content', $message);
        
        // Test default context
        $message = $method->invoke($this->uiService, 'unknown_context');
        $this->assertStringContainsString('How can I assist you today?', $message);
    }
    
    /**
     * Test quick actions generation for different contexts
     */
    public function testGetQuickActions(): void
    {
        // Use reflection to test private method
        $reflection = new \ReflectionClass($this->uiService);
        $method = $reflection->getMethod('getQuickActions');
        $method->setAccessible(true);
        
        // Test course creation actions
        $actions = $method->invoke($this->uiService, 'course_creation');
        $this->assertCount(3, $actions);
        $this->assertEquals('Programming Course', $actions[0]['text']);
        $this->assertEquals('dashicons-editor-code', $actions[0]['icon']);
        
        // Test course editing actions
        $actions = $method->invoke($this->uiService, 'course_editing');
        $this->assertCount(3, $actions);
        $this->assertEquals('Add Lessons', $actions[0]['text']);
        $this->assertEquals('dashicons-plus-alt2', $actions[0]['icon']);
        
        // Test unknown context
        $actions = $method->invoke($this->uiService, 'unknown_context');
        $this->assertEmpty($actions);
    }
    
    /**
     * Test inline styles rendering
     */
    public function testRenderInlineStyles(): void
    {
        $this->mockTemplateEngine
            ->shouldReceive('render')
            ->once()
            ->with('admin/partials/inline-styles', [
                'primary_color' => '#667eea',
                'secondary_color' => '#764ba2',
                'gradient' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
            ])
            ->andReturn('<style>/* CSS */</style>');
        
        // Capture output
        ob_start();
        $this->uiService->renderInlineStyles();
        $output = ob_get_clean();
        
        $this->assertEquals('<style>/* CSS */</style>', $output);
    }
    
    /**
     * Test inline scripts rendering
     */
    public function testRenderInlineScripts(): void
    {
        $this->mockTemplateEngine
            ->shouldReceive('render')
            ->once()
            ->with('admin/partials/inline-scripts', Mockery::on(function($data) {
                return isset($data['ajax_url']) &&
                       isset($data['nonce']);
            }))
            ->andReturn('<script>/* JS */</script>');
        
        // Capture output
        ob_start();
        $this->uiService->renderInlineScripts();
        $output = ob_get_clean();
        
        $this->assertEquals('<script>/* JS */</script>', $output);
    }
}