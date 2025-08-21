<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Services;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\EnhancedTemplateEngine;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Utilities\Logger;
use Mockery;

/**
 * Unit tests for EnhancedTemplateEngine
 * 
 * Tests the template rendering, caching, JavaScript template management,
 * and theme override functionality.
 */
class EnhancedTemplateEngineTest extends TestCase
{
    private EnhancedTemplateEngine $templateEngine;
    private $mockLogger;
    private $mockLLMService;
    private string $testTemplateDir;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test template directory
        $this->testTemplateDir = sys_get_temp_dir() . '/mpcc_test_templates_' . uniqid();
        mkdir($this->testTemplateDir . '/templates', 0777, true);
        mkdir($this->testTemplateDir . '/templates/components', 0777, true);
        mkdir($this->testTemplateDir . '/templates/admin', 0777, true);
        mkdir($this->testTemplateDir . '/templates/js-templates', 0777, true);
        
        // Mock dependencies
        $this->mockLogger = Mockery::mock(Logger::class);
        $this->mockLogger->shouldReceive('debug')->andReturnNull();
        $this->mockLogger->shouldReceive('info')->andReturnNull();
        $this->mockLogger->shouldReceive('error')->andReturnNull();
        
        $this->mockLLMService = Mockery::mock(LLMService::class);
        
        // Create template engine instance
        $this->templateEngine = new EnhancedTemplateEngine($this->mockLLMService);
        
        // Inject mock logger and test directory
        $reflection = new \ReflectionClass($this->templateEngine);
        
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $loggerProperty->setValue($this->templateEngine, $this->mockLogger);
        
        $templateDirProperty = $reflection->getProperty('templateDir');
        $templateDirProperty->setAccessible(true);
        $templateDirProperty->setValue($this->templateEngine, $this->testTemplateDir . '/templates/');
    }
    
    protected function tearDown(): void
    {
        // Clean up test directory
        $this->removeDirectory($this->testTemplateDir);
        
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Recursively remove directory
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . "/" . $object)) {
                    $this->removeDirectory($dir . "/" . $object);
                } else {
                    unlink($dir . "/" . $object);
                }
            }
        }
        rmdir($dir);
    }
    
    /**
     * Test template rendering
     */
    public function testRenderTemplate(): void
    {
        // Create test template
        $templateContent = '<?php echo $title; ?> - <?php echo $content; ?>';
        file_put_contents($this->testTemplateDir . '/templates/test-template.php', $templateContent);
        
        $result = $this->templateEngine->render('test-template', [
            'title' => 'Test Title',
            'content' => 'Test Content'
        ]);
        
        $this->assertEquals('Test Title - Test Content', $result);
    }
    
    /**
     * Test component rendering
     */
    public function testRenderComponent(): void
    {
        // Create test component
        $componentContent = '<div class="component"><?php echo $message; ?></div>';
        file_put_contents($this->testTemplateDir . '/templates/components/test-component.php', $componentContent);
        
        $result = $this->templateEngine->renderComponent('test-component', [
            'message' => 'Hello Component'
        ]);
        
        $this->assertEquals('<div class="component">Hello Component</div>', $result);
    }
    
    /**
     * Test template not found error
     */
    public function testRenderNonExistentTemplate(): void
    {
        $result = $this->templateEngine->render('non-existent-template');
        
        $this->assertStringContainsString('Template Error:', $result);
        $this->assertStringContainsString('Template not found: non-existent-template', $result);
    }
    
    /**
     * Test global data setting
     */
    public function testGlobalData(): void
    {
        // Set global data
        $this->templateEngine->setGlobalData('site_name', 'Test Site');
        $this->templateEngine->setGlobalDataArray([
            'version' => '1.0.0',
            'author' => 'Test Author'
        ]);
        
        // Create template that uses global data
        $templateContent = '<?php echo $site_name; ?> v<?php echo $version; ?> by <?php echo $author; ?>';
        file_put_contents($this->testTemplateDir . '/templates/global-test.php', $templateContent);
        
        $result = $this->templateEngine->render('global-test');
        
        $this->assertEquals('Test Site v1.0.0 by Test Author', $result);
    }
    
    /**
     * Test template caching
     */
    public function testTemplateCache(): void
    {
        $cacheKey = 'test_cache_key';
        $content = '<div>Cached Content</div>';
        
        // Cache template
        $this->templateEngine->cacheTemplate($cacheKey, $content, 60);
        
        // Retrieve cached template
        $cachedContent = $this->templateEngine->getCachedTemplate($cacheKey);
        
        $this->assertEquals($content, $cachedContent);
        
        // Clear cache
        $this->templateEngine->clearCache($cacheKey);
        
        // Verify cache is cleared
        $clearedContent = $this->templateEngine->getCachedTemplate($cacheKey);
        $this->assertFalse($clearedContent);
    }
    
    /**
     * Test JavaScript template enqueuing
     */
    public function testEnqueueJsTemplate(): void
    {
        // Create JS template
        $jsTemplateContent = '<div class="js-template">{{message}}</div>';
        file_put_contents($this->testTemplateDir . '/templates/js-templates/test-js-template.html', $jsTemplateContent);
        
        $this->templateEngine->enqueueJsTemplate('test-js-tpl', 'test-js-template');
        
        // Test rendering JS templates
        ob_start();
        $this->templateEngine->renderJsTemplates();
        $output = ob_get_clean();
        
        $this->assertStringContainsString('<!-- MemberPress Courses Copilot JS Templates -->', $output);
        $this->assertStringContainsString('id="test-js-tpl"', $output);
        $this->assertStringContainsString('<div class="js-template">{{message}}</div>', $output);
    }
    
    /**
     * Test template performance tracking
     */
    public function testPerformanceTracking(): void
    {
        // Create simple template
        $templateContent = '<?php echo "Hello"; ?>';
        file_put_contents($this->testTemplateDir . '/templates/perf-test.php', $templateContent);
        
        // Render multiple times
        for ($i = 0; $i < 5; $i++) {
            $this->templateEngine->render('perf-test');
        }
        
        $metrics = $this->templateEngine->getPerformanceMetrics();
        
        $this->assertArrayHasKey('perf-test', $metrics);
        $this->assertEquals(5, $metrics['perf-test']['count']);
        $this->assertArrayHasKey('avg_time', $metrics['perf-test']);
        $this->assertArrayHasKey('min_time', $metrics['perf-test']);
        $this->assertArrayHasKey('max_time', $metrics['perf-test']);
    }
    
    /**
     * Test partial template inclusion
     */
    public function testPartialInclusion(): void
    {
        // Create partial
        mkdir($this->testTemplateDir . '/templates/partials', 0777, true);
        $partialContent = '<p>Partial Content: <?php echo $data; ?></p>';
        file_put_contents($this->testTemplateDir . '/templates/partials/test-partial.php', $partialContent);
        
        // Capture output
        ob_start();
        $this->templateEngine->partial('test-partial', ['data' => 'Test Data']);
        $output = ob_get_clean();
        
        $this->assertEquals('<p>Partial Content: Test Data</p>', $output);
    }
    
    /**
     * Test template with PHP error handling
     */
    public function testTemplateWithPHPError(): void
    {
        // Create template with PHP error
        $templateContent = '<?php echo $undefined_variable; ?>';
        file_put_contents($this->testTemplateDir . '/templates/error-template.php', $templateContent);
        
        // Suppress PHP notices for this test
        $errorLevel = error_reporting();
        error_reporting($errorLevel & ~E_NOTICE);
        
        $result = $this->templateEngine->render('error-template');
        
        // Restore error reporting
        error_reporting($errorLevel);
        
        // Should still render (with empty variable)
        $this->assertEquals('', $result);
    }
    
    /**
     * Test intelligent template selection
     */
    public function testSelectOptimalTemplate(): void
    {
        $courseDescription = 'I want to create a technical programming course for beginners';
        $userPreferences = ['technical', 'skill_based'];
        $context = [
            'target_audience' => 'beginners',
            'learning_objectives' => ['understand basics', 'write simple programs']
        ];
        
        // Mock AI recommendations
        $this->mockLLMService
            ->shouldReceive('generateResponse')
            ->once()
            ->andReturn(json_encode([
                [
                    'type' => 'technical',
                    'confidence' => 0.9,
                    'reasoning' => 'Strong technical keywords detected'
                ]
            ]));
        
        $result = $this->templateEngine->selectOptimalTemplate($courseDescription, $userPreferences, $context);
        
        $this->assertArrayHasKey('primary_recommendation', $result);
        $this->assertArrayHasKey('alternative_recommendations', $result);
        $this->assertArrayHasKey('confidence_score', $result);
        $this->assertArrayHasKey('reasoning', $result);
    }
    
    /**
     * Test localize script functionality
     */
    public function testLocalizeScript(): void
    {
        // Mock WordPress function
        if (!function_exists('wp_localize_script')) {
            function wp_localize_script($handle, $objectName, $data) {
                global $test_localized_data;
                $test_localized_data = [
                    'handle' => $handle,
                    'objectName' => $objectName,
                    'data' => $data
                ];
            }
        }
        
        $this->templateEngine->localizeScript('test-script', 'testData', [
            'message' => 'Test <script>alert("XSS")</script>',
            'url' => 'https://example.com'
        ]);
        
        global $test_localized_data;
        $this->assertEquals('test-script', $test_localized_data['handle']);
        $this->assertEquals('testData', $test_localized_data['objectName']);
        $this->assertStringNotContainsString('<script>', $test_localized_data['data']['message']);
    }
    
    /**
     * Test directory creation
     */
    public function testCreateTemplateDirectories(): void
    {
        $result = $this->templateEngine->createTemplateDirectories();
        
        $this->assertTrue($result);
        $this->assertDirectoryExists($this->testTemplateDir . '/templates/admin/partials');
        $this->assertDirectoryExists($this->testTemplateDir . '/templates/components/modal');
        $this->assertDirectoryExists($this->testTemplateDir . '/templates/components/chat');
        $this->assertDirectoryExists($this->testTemplateDir . '/templates/js-templates');
    }
}