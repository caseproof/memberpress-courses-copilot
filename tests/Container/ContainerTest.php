<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Container;

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Container\Container;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\DatabaseService;
use MemberPressCoursesCopilot\Services\ConversationManager;

/**
 * Container Test
 * 
 * Tests the dependency injection container functionality
 * 
 * @package MemberPressCoursesCopilot\Tests\Container
 * @since 1.0.0
 */
class ContainerTest extends TestCase
{
    private Container $container;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->container = Container::getInstance();
        $this->container->reset(); // Reset for clean state
    }
    
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->container->reset();
    }
    
    /**
     * Test singleton instance
     */
    public function testSingletonInstance(): void
    {
        $container1 = Container::getInstance();
        $container2 = Container::getInstance();
        
        $this->assertSame($container1, $container2);
    }
    
    /**
     * Test service registration and retrieval
     */
    public function testServiceRegistration(): void
    {
        // Register a mock service
        $mockService = $this->createMock(LLMService::class);
        
        $this->container->register(LLMService::class, function () use ($mockService) {
            return $mockService;
        });
        
        // Retrieve the service
        $retrieved = $this->container->get(LLMService::class);
        
        $this->assertSame($mockService, $retrieved);
    }
    
    /**
     * Test singleton behavior
     */
    public function testSingletonBehavior(): void
    {
        $callCount = 0;
        
        // Register as singleton
        $this->container->register('test_service', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        }, true);
        
        // Get service multiple times
        $service1 = $this->container->get('test_service');
        $service2 = $this->container->get('test_service');
        $service3 = $this->container->get('test_service');
        
        // Should only be created once
        $this->assertEquals(1, $callCount);
        $this->assertSame($service1, $service2);
        $this->assertSame($service2, $service3);
    }
    
    /**
     * Test non-singleton behavior
     */
    public function testNonSingletonBehavior(): void
    {
        $callCount = 0;
        
        // Register as non-singleton
        $this->container->register('test_service', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        }, false);
        
        // Get service multiple times
        $service1 = $this->container->get('test_service');
        $service2 = $this->container->get('test_service');
        $service3 = $this->container->get('test_service');
        
        // Should be created each time
        $this->assertEquals(3, $callCount);
        $this->assertNotSame($service1, $service2);
        $this->assertNotSame($service2, $service3);
    }
    
    /**
     * Test alias functionality
     */
    public function testAlias(): void
    {
        $mockService = $this->createMock(DatabaseService::class);
        
        $this->container->register(DatabaseService::class, function () use ($mockService) {
            return $mockService;
        });
        
        $this->container->alias('db', DatabaseService::class);
        
        // Should get same service via alias
        $serviceViaClass = $this->container->get(DatabaseService::class);
        $serviceViaAlias = $this->container->get('db');
        
        $this->assertSame($serviceViaClass, $serviceViaAlias);
    }
    
    /**
     * Test has() method
     */
    public function testHasMethod(): void
    {
        $this->container->register('test_service', function () {
            return new \stdClass();
        });
        
        $this->container->alias('test_alias', 'test_service');
        
        $this->assertTrue($this->container->has('test_service'));
        $this->assertTrue($this->container->has('test_alias'));
        $this->assertFalse($this->container->has('non_existent_service'));
    }
    
    /**
     * Test exception for non-existent service
     */
    public function testExceptionForNonExistentService(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Service 'non_existent' not found in container");
        
        $this->container->get('non_existent');
    }
    
    /**
     * Test automatic dependency injection
     */
    public function testAutomaticDependencyInjection(): void
    {
        // Register DatabaseService
        $mockDb = $this->createMock(DatabaseService::class);
        $this->container->register(DatabaseService::class, function () use ($mockDb) {
            return $mockDb;
        });
        
        // Register ConversationManager with class name (should auto-resolve dependencies)
        $this->container->register(ConversationManager::class, ConversationManager::class);
        
        // Get ConversationManager
        $conversationManager = $this->container->get(ConversationManager::class);
        
        $this->assertInstanceOf(ConversationManager::class, $conversationManager);
    }
    
    /**
     * Test mocking services for testing
     */
    public function testMockingServicesForTesting(): void
    {
        // Create mocks
        $mockLLM = $this->createMock(LLMService::class);
        $mockLLM->expects($this->once())
            ->method('generateContent')
            ->with('test prompt')
            ->willReturn('mocked response');
        
        // Register mock in container
        $this->container->register(LLMService::class, function () use ($mockLLM) {
            return $mockLLM;
        });
        
        // Use the mock
        $llmService = $this->container->get(LLMService::class);
        $response = $llmService->generateContent('test prompt');
        
        $this->assertEquals('mocked response', $response);
    }
}