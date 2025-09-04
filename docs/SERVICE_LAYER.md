# Service Layer Documentation

## Table of Contents

- [Overview](#overview)
- [Dependency Injection Container](#dependency-injection-container)
- [Service Interfaces](#service-interfaces)
- [Service Implementations](#service-implementations)
- [Service Registration](#service-registration)
- [Service Lifecycle](#service-lifecycle)
- [Testing Services](#testing-services)
- [Creating New Services](#creating-new-services)

## Overview

The MemberPress Courses Copilot plugin uses a service-oriented architecture with dependency injection to ensure loose coupling, testability, and maintainability. All business logic is encapsulated in services that implement well-defined interfaces.

### Design Principles

1. **Single Responsibility**: Each service has one clear purpose
2. **Interface Segregation**: Interfaces are focused and minimal
3. **Dependency Inversion**: Services depend on abstractions, not concretions
4. **Open/Closed**: Services are open for extension, closed for modification
5. **Immutable State**: Services maintain immutable state where possible

### Service Types

- **Core Services**: Essential business logic (LLM, Conversation, Course Generation)
- **Support Services**: Infrastructure and utilities (Database, Asset Management)
- **Integration Services**: WordPress and third-party integrations
- **Utility Services**: Shared functionality and helpers

## Dependency Injection Container

### Container Implementation

**File:** `Container/Container.php`

The plugin uses a PSR-11 compatible dependency injection container:

```php
class Container implements ContainerInterface {
    public function get(string $id): object
    public function has(string $id): bool
    public function register(string $id, $concrete, bool $singleton = false): void
    public function bind(string $abstract, string $concrete): void
    public function alias(string $alias, string $abstract): void
}
```

### Service Registration

**File:** `Container/ServiceProvider.php`

All services are registered in the ServiceProvider:

```php
class ServiceProvider {
    public static function register(Container $container): void {
        self::registerUtilities($container);
        self::registerCoreServices($container);
        self::registerAdminServices($container);
        self::registerControllers($container);
        self::registerAliases($container);
        self::registerInterfaceBindings($container);
    }
}
```

### Container Usage

```php
// Get container instance
$container = Container::getInstance();

// Register services
ServiceProvider::register($container);

// Retrieve services
$llmService = $container->get(LLMService::class);
$conversationManager = $container->get(IConversationManager::class);
```

## Service Interfaces

### Core Interfaces

#### ILLMService

**File:** `Interfaces/ILLMService.php`

Defines contract for AI language model communication.

```php
interface ILLMService {
    public function sendMessage(string $message, array $conversationHistory = []): array;
    public function generateCourse(array $courseData): array;
}
```

**Purpose:** Abstracts AI provider implementation, enabling multiple AI backends.

#### IConversationManager

**File:** `Interfaces/IConversationManager.php`

Defines contract for conversation session management.

```php
interface IConversationManager {
    public function createSession(array $data): ConversationSession;
    public function loadSession(string $sessionId): ?ConversationSession;
    public function saveSession(ConversationSession $session): bool;
    public function deleteSession(string $sessionId): bool;
    public function getUserSessions(int $userId, int $limit = 10): array;
}
```

**Purpose:** Standardizes conversation persistence and retrieval operations.

#### ICourseGenerator

**File:** `Interfaces/ICourseGenerator.php`

Defines contract for WordPress course creation.

```php
interface ICourseGenerator {
    public function generateCourse(array $courseData): array;
    public function validateCourseData(array $courseData): array;
    public function generateSection(array $sectionData, int $courseId): int;
    public function generateLesson(array $lessonData, int $sectionId): int;
}
```

**Purpose:** Abstracts course creation logic for different course systems.

#### IDatabaseService

**File:** `Interfaces/IDatabaseService.php`

Defines contract for database operations.

```php
interface IDatabaseService {
    public function createTables(): bool;
    public function dropTables(): bool;
    public function insert(string $table, array $data): int;
    public function update(string $table, array $data, array $where): int;
    public function delete(string $table, array $where): int;
    public function get(string $table, array $where = [], array $options = []): array;
}
```

**Purpose:** Provides database abstraction for testing and potential migrations.

#### IQuizAIService

**File:** `Interfaces/IQuizAIService.php`

Defines contract for AI-powered quiz generation.

```php
interface IQuizAIService {
    public function generateQuestions(string $content, array $options = []): array;
    public function generateMultipleChoiceQuestions(string $content, array $options = []): array;
    public function generateTrueFalseQuestions(string $content, array $options = []): array;
    public function generateTextAnswerQuestions(string $content, array $options = []): array;
    public function generateMultipleSelectQuestions(string $content, array $options = []): array;
    public function getSupportedQuestionTypes(): array;
}
```

**Purpose:** Standardizes quiz generation across different content types.

## Service Implementations

### Core Service Details

#### LLMService Implementation

**File:** `Services/LLMService.php`

```php
class LLMService implements ILLMService {
    private const GATEWAY_URL = 'https://auth-gateway.example.com';
    private const TIMEOUT = 30;
    private const MAX_RETRIES = 3;
    
    public function sendMessage(string $message, array $conversationHistory = []): array {
        $payload = $this->preparePayload($message, $conversationHistory);
        $response = $this->makeRequest('/chat', $payload);
        return $this->parseResponse($response);
    }
    
    private function makeRequest(string $endpoint, array $data): array {
        // HTTP client implementation with retry logic
    }
    
    private function preparePayload(string $message, array $history): array {
        // Format data for gateway API
    }
    
    private function parseResponse(array $response): array {
        // Parse and validate gateway response
    }
}
```

**Key Features:**
- Retry logic with exponential backoff
- Request/response logging
- Error handling and recovery
- Authentication token management

#### ConversationManager Implementation

**File:** `Services/ConversationManager.php`

```php
class ConversationManager implements IConversationManager {
    private IDatabaseService $databaseService;
    private Logger $logger;
    
    public function __construct(IDatabaseService $databaseService, Logger $logger = null) {
        $this->databaseService = $databaseService;
        $this->logger = $logger ?? Logger::getInstance();
    }
    
    public function createSession(array $data): ConversationSession {
        $session = new ConversationSession([
            'session_id' => $this->generateSessionId(),
            'user_id' => get_current_user_id(),
            'title' => $data['title'] ?? 'New Conversation',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $this->saveSession($session);
        return $session;
    }
    
    public function saveSession(ConversationSession $session): bool {
        $data = $session->toArray();
        
        if ($session->exists()) {
            return $this->databaseService->update(
                'mpcc_conversations',
                $data,
                ['session_id' => $session->getSessionId()]
            ) > 0;
        } else {
            return $this->databaseService->insert(
                'mpcc_conversations',
                $data
            ) > 0;
        }
    }
}
```

**Key Features:**
- Session lifecycle management
- Automatic cleanup of expired sessions
- Optimized database queries
- Session state validation

#### CourseGeneratorService Implementation

**File:** `Services/CourseGeneratorService.php`

```php
class CourseGeneratorService implements ICourseGenerator {
    private Logger $logger;
    
    public function generateCourse(array $courseData): array {
        // Validate course data structure
        $validation = $this->validateCourseData($courseData);
        if (!$validation['valid']) {
            throw new InvalidArgumentException(implode(', ', $validation['errors']));
        }
        
        // Create course post
        $courseId = $this->createCoursePost($courseData);
        
        // Generate sections and lessons
        $sectionsCreated = 0;
        $lessonsCreated = 0;
        
        foreach ($courseData['sections'] as $sectionData) {
            $sectionId = $this->generateSection($sectionData, $courseId);
            $sectionsCreated++;
            
            foreach ($sectionData['lessons'] as $lessonData) {
                $this->generateLesson($lessonData, $sectionId);
                $lessonsCreated++;
            }
        }
        
        return [
            'course_id' => $courseId,
            'sections_created' => $sectionsCreated,
            'lessons_created' => $lessonsCreated,
            'edit_url' => get_edit_post_link($courseId),
            'preview_url' => get_permalink($courseId)
        ];
    }
}
```

**Key Features:**
- Data validation before creation
- Transactional course creation
- Error rollback on failure
- Comprehensive logging

#### MpccQuizAIService Implementation

**File:** `Services/MpccQuizAIService.php`

```php
class MpccQuizAIService implements IQuizAIService {
    private ILLMService $llmService;
    private Logger $logger;
    
    public function generateQuestions(string $content, array $options = []): array {
        // Validate content
        $validation = $this->validateContent($content);
        if (!$validation['valid']) {
            return [
                'error' => true,
                'message' => $validation['message'],
                'suggestion' => $validation['suggestion']
            ];
        }
        
        // Prepare prompt for AI
        $prompt = $this->buildQuizPrompt($content, $options);
        
        // Generate questions via LLM
        $response = $this->llmService->sendMessage($prompt);
        
        // Parse and validate questions
        $questions = $this->parseQuestions($response);
        
        return [
            'questions' => $questions,
            'total' => count($questions),
            'type' => $options['type'] ?? 'multiple_choice'
        ];
    }
    
    private function validateContent(string $content): array {
        if (strlen($content) < 200) {
            return [
                'valid' => false,
                'message' => 'Content is too short to generate meaningful questions',
                'suggestion' => 'Please provide at least 200 characters of content for quiz generation.'
            ];
        }
        
        return ['valid' => true];
    }
}
```

**Key Features:**
- Content validation before generation
- Multiple question type support
- Quality assessment
- Intelligent error messages with suggestions

## Service Registration

### Registration Process

Services are registered in `ServiceProvider::register()` method with dependency resolution:

```php
private static function registerCoreServices(Container $container): void {
    // LLM Service (singleton)
    $container->register(LLMService::class, function (Container $container) {
        return new LLMService();
    }, true);
    
    // Conversation Manager with dependencies
    $container->register(ConversationManager::class, function (Container $container) {
        $databaseService = $container->get(DatabaseService::class);
        $logger = $container->get(Logger::class);
        return new ConversationManager($databaseService, $logger);
    }, true);
    
    // Course Generator Service
    $container->register(CourseGeneratorService::class, function (Container $container) {
        $logger = $container->get(Logger::class);
        return new CourseGeneratorService($logger);
    }, true);
}
```

### Interface Bindings

Interfaces are bound to their implementations:

```php
private static function registerInterfaceBindings(Container $container): void {
    $container->bind(IDatabaseService::class, DatabaseService::class);
    $container->bind(ILLMService::class, LLMService::class);
    $container->bind(IConversationManager::class, ConversationManager::class);
    $container->bind(ICourseGenerator::class, CourseGeneratorService::class);
    $container->bind(IQuizAIService::class, MpccQuizAIService::class);
}
```

### Service Aliases

Convenient aliases for common services:

```php
private static function registerAliases(Container $container): void {
    $container->alias('logger', Logger::class);
    $container->alias('database', DatabaseService::class);
    $container->alias('llm', LLMService::class);
    $container->alias('conversation', ConversationManager::class);
    $container->alias('course_generator', CourseGeneratorService::class);
}
```

## Service Lifecycle

### Singleton Management

Most services are registered as singletons to ensure:
- Consistent state across requests
- Reduced memory usage
- Improved performance
- Shared configuration

### Initialization Order

Services are initialized in dependency order:

1. **Utilities** (Logger, Helper)
2. **Core Services** (Database, LLM)
3. **Business Services** (Conversation, Course Generation)
4. **Integration Services** (Course Integration, Editor Integration)
5. **Controllers** (AJAX handlers)
6. **Admin Services** (UI components)

### Dependency Resolution

The container automatically resolves dependencies:

```php
// ConversationManager depends on DatabaseService
$container->register(ConversationManager::class, function (Container $container) {
    $databaseService = $container->get(DatabaseService::class); // Auto-resolved
    return new ConversationManager($databaseService);
}, true);
```

## Testing Services

### Unit Testing with Mocks

Services are designed for easy unit testing with dependency injection:

```php
class ConversationManagerTest extends TestCase {
    private ConversationManager $conversationManager;
    private IDatabaseService $mockDatabase;
    
    protected function setUp(): void {
        $this->mockDatabase = $this->createMock(IDatabaseService::class);
        $this->conversationManager = new ConversationManager($this->mockDatabase);
    }
    
    public function testCreateSession(): void {
        $this->mockDatabase
            ->expects($this->once())
            ->method('insert')
            ->with('mpcc_conversations', $this->anything())
            ->willReturn(1);
        
        $session = $this->conversationManager->createSession(['title' => 'Test']);
        
        $this->assertInstanceOf(ConversationSession::class, $session);
        $this->assertEquals('Test', $session->getTitle());
    }
}
```

### Integration Testing

Integration tests use the full service stack:

```php
class CourseGenerationIntegrationTest extends WP_UnitTestCase {
    private Container $container;
    private ICourseGenerator $courseGenerator;
    
    protected function setUp(): void {
        parent::setUp();
        
        $this->container = Container::getInstance();
        ServiceProvider::register($this->container);
        $this->courseGenerator = $this->container->get(ICourseGenerator::class);
    }
    
    public function testGenerateCompleteCourse(): void {
        $courseData = $this->getValidCourseData();
        $result = $this->courseGenerator->generateCourse($courseData);
        
        $this->assertArrayHasKey('course_id', $result);
        $this->assertGreaterThan(0, $result['course_id']);
        
        // Verify course was created in WordPress
        $course = get_post($result['course_id']);
        $this->assertEquals('mpcs-course', $course->post_type);
    }
}
```

## Creating New Services

### Step 1: Define Interface

Create interface in `Interfaces/` directory:

```php
<?php

namespace MemberPressCoursesCopilot\Interfaces;

interface INewService {
    public function performAction(string $input): array;
    public function validateInput(string $input): bool;
}
```

### Step 2: Implement Service

Create service in `Services/` directory:

```php
<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Interfaces\INewService;
use MemberPressCoursesCopilot\Utilities\Logger;

class NewService implements INewService {
    private Logger $logger;
    
    public function __construct(Logger $logger = null) {
        $this->logger = $logger ?? Logger::getInstance();
    }
    
    public function performAction(string $input): array {
        if (!$this->validateInput($input)) {
            throw new InvalidArgumentException('Invalid input provided');
        }
        
        $this->logger->info('Performing action', ['input' => $input]);
        
        // Implementation logic here
        
        return ['result' => 'success'];
    }
    
    public function validateInput(string $input): bool {
        return !empty($input) && strlen($input) >= 3;
    }
}
```

### Step 3: Register in Container

Add to `ServiceProvider.php`:

```php
private static function registerCoreServices(Container $container): void {
    // ... existing registrations
    
    // New Service (singleton)
    $container->register(NewService::class, function (Container $container) {
        $logger = $container->get(Logger::class);
        return new NewService($logger);
    }, true);
}

private static function registerInterfaceBindings(Container $container): void {
    // ... existing bindings
    
    $container->bind(INewService::class, NewService::class);
}
```

### Step 4: Write Tests

Create test file in `tests/Services/`:

```php
<?php

use PHPUnit\Framework\TestCase;
use MemberPressCoursesCopilot\Services\NewService;
use MemberPressCoursesCopilot\Utilities\Logger;

class NewServiceTest extends TestCase {
    private NewService $service;
    private Logger $mockLogger;
    
    protected function setUp(): void {
        $this->mockLogger = $this->createMock(Logger::class);
        $this->service = new NewService($this->mockLogger);
    }
    
    public function testPerformActionSuccess(): void {
        $result = $this->service->performAction('valid input');
        
        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('success', $result['result']);
    }
    
    public function testPerformActionInvalidInput(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->service->performAction('x'); // Too short
    }
    
    public function testValidateInput(): void {
        $this->assertTrue($this->service->validateInput('valid'));
        $this->assertFalse($this->service->validateInput('x'));
        $this->assertFalse($this->service->validateInput(''));
    }
}
```

### Step 5: Use in Controllers

Inject service in controller:

```php
class NewController {
    private INewService $newService;
    
    public function __construct(INewService $newService) {
        $this->newService = $newService;
    }
    
    public function handleNewAction(): void {
        try {
            $input = sanitize_text_field($_POST['input'] ?? '');
            $result = $this->newService->performAction($input);
            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
```

## Service Communication Patterns

### Service-to-Service Communication

Services communicate through their interfaces:

```php
class CourseAjaxService {
    private IConversationManager $conversationManager;
    private ICourseGenerator $courseGenerator;
    private ILLMService $llmService;
    
    public function handleCreateCourse(): void {
        // Load conversation
        $session = $this->conversationManager->loadSession($sessionId);
        
        // Generate course
        $result = $this->courseGenerator->generateCourse($courseData);
        
        // Update conversation with results
        $session->addResult($result);
        $this->conversationManager->saveSession($session);
    }
}
```

### Event-Driven Communication

Services can communicate through WordPress hooks:

```php
class CourseGeneratorService {
    public function generateCourse(array $courseData): array {
        $result = $this->createCourse($courseData);
        
        // Notify other services of course creation
        do_action('mpcc_course_generated', $result, $courseData);
        
        return $result;
    }
}

class AnalyticsService {
    public function __construct() {
        add_action('mpcc_course_generated', [$this, 'trackCourseGeneration'], 10, 2);
    }
    
    public function trackCourseGeneration(array $result, array $courseData): void {
        // Track course generation metrics
    }
}
```

## Performance Considerations

### Memory Management

- **Singleton Services**: Reduce memory usage through singletons
- **Lazy Loading**: Services instantiated only when needed
- **Resource Cleanup**: Proper cleanup of resources and connections

### Caching Strategies

```php
class LLMService {
    private array $responseCache = [];
    
    public function sendMessage(string $message, array $history = []): array {
        $cacheKey = $this->getCacheKey($message, $history);
        
        if (isset($this->responseCache[$cacheKey])) {
            return $this->responseCache[$cacheKey];
        }
        
        $response = $this->makeRequest($message, $history);
        $this->responseCache[$cacheKey] = $response;
        
        return $response;
    }
}
```

### Database Optimization

- **Query Optimization**: Use indexed fields for queries
- **Batch Operations**: Group multiple operations
- **Connection Reuse**: Share database connections

## Error Handling in Services

### Exception Hierarchy

```php
namespace MemberPressCoursesCopilot\Exceptions;

class ServiceException extends Exception {}
class DatabaseException extends ServiceException {}
class AIServiceException extends ServiceException {}
class ValidationException extends ServiceException {}
```

### Service Error Handling

```php
class ConversationManager {
    public function saveSession(ConversationSession $session): bool {
        try {
            $this->validateSession($session);
            return $this->persistSession($session);
        } catch (ValidationException $e) {
            $this->logger->warning('Session validation failed', [
                'session_id' => $session->getSessionId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        } catch (DatabaseException $e) {
            $this->logger->error('Database error saving session', [
                'session_id' => $session->getSessionId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
```

## Service Configuration

### Configuration Management

Services can be configured through WordPress options:

```php
class LLMService {
    private function getConfig(): array {
        return get_option('mpcc_llm_config', [
            'timeout' => 30,
            'max_retries' => 3,
            'default_model' => 'gpt-4',
            'temperature' => 0.7
        ]);
    }
    
    public function updateConfig(array $config): bool {
        $existing = $this->getConfig();
        $updated = array_merge($existing, $config);
        return update_option('mpcc_llm_config', $updated);
    }
}
```

### Environment-Specific Configuration

```php
class DatabaseService {
    private function getTablePrefix(): string {
        global $wpdb;
        
        // Use different prefix for testing
        if (defined('WP_TESTS_DOMAIN')) {
            return $wpdb->prefix . 'test_';
        }
        
        return $wpdb->prefix;
    }
}
```

## Service Documentation Standards

### PHPDoc Standards

All services should include comprehensive PHPDoc comments:

```php
/**
 * Conversation Manager Service
 * 
 * Handles conversation session management including creation, persistence,
 * and retrieval of conversation data. Implements session lifecycle management
 * with automatic cleanup of expired sessions.
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 * 
 * @implements IConversationManager
 */
class ConversationManager implements IConversationManager {
    /**
     * Create a new conversation session
     * 
     * Creates a new session with the provided data and persists it to the database.
     * The session ID is automatically generated as a UUID.
     * 
     * @param array $data Session initialization data
     * @return ConversationSession The created session object
     * 
     * @throws DatabaseException If session creation fails
     * @throws ValidationException If data validation fails
     * 
     * @since 1.0.0
     */
    public function createSession(array $data): ConversationSession {
        // Implementation
    }
}
```

### Method Documentation

```php
/**
 * Generate quiz questions from content
 * 
 * Analyzes the provided content and generates quiz questions based on the
 * specified options. Supports multiple question types and difficulty levels.
 * 
 * @param string $content The content to generate questions from
 * @param array $options {
 *     Optional generation parameters
 *     
 *     @type string $type Question type (multiple_choice, true_false, text_answer)
 *     @type int    $count Number of questions to generate (1-20)
 *     @type string $difficulty Difficulty level (easy, medium, hard)
 *     @type string $custom_prompt Additional instructions for AI
 * }
 * 
 * @return array {
 *     @type array  $questions Generated questions array
 *     @type int    $total Total number of questions
 *     @type string $type Question type used
 *     @type string $suggestion Optional improvement suggestion
 * }
 * 
 * @throws ValidationException If content is invalid
 * @throws AIServiceException If AI generation fails
 * 
 * @since 1.0.0
 */
public function generateQuestions(string $content, array $options = []): array
```

This service layer documentation provides a comprehensive guide to understanding, extending, and maintaining the service architecture of the MemberPress Courses Copilot plugin.