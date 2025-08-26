# Dependency Injection Guide

## Overview

The MemberPress Courses Copilot plugin uses a simple dependency injection (DI) container to manage service dependencies. This approach follows the KISS principle while providing the benefits of dependency injection:

- Easy testing with mock objects
- Clear dependency management
- Singleton pattern support
- Service aliases for convenience

## Usage

### Accessing Services

There are three ways to access services:

1. **Via the Plugin instance (recommended)**:
```php
$container = Plugin::instance()->getContainer();
$llmService = $container->get(LLMService::class);
```

2. **Via helper functions**:
```php
// Get a service
$llmService = mpcc_get_service(LLMService::class);

// Get the container itself
$container = mpcc_container();

// Check if service exists
if (mpcc_has_service('custom_service')) {
    $service = mpcc_get_service('custom_service');
}
```

3. **Via dependency injection in constructors**:
```php
class MyController {
    private LLMService $llmService;
    
    public function __construct(LLMService $llmService) {
        $this->llmService = $llmService;
    }
}
```

### Service Registration

All core services are registered in `ServiceProvider::register()`. Services are registered as singletons by default:

```php
// Simple registration
$container->register(MyService::class, MyService::class, true);

// Registration with factory
$container->register(MyService::class, function (Container $container) {
    $dependency = $container->get(DependencyService::class);
    return new MyService($dependency);
}, true);

// Non-singleton registration
$container->register(MyService::class, MyService::class, false);
```

### Available Services

Core services available via the container:

- `Logger::class` - Logging service (alias: 'logger')
- `DatabaseService::class` - Database operations (alias: 'database')
- `LLMService::class` - AI/LLM integration (alias: 'llm')
- `SessionService::class` - Session management (alias: 'session')
- `ConversationManager::class` - Conversation management (alias: 'conversation')
- `ContentGenerationService::class` - Content generation (alias: 'content')
- `CourseGeneratorService::class` - Course generation (alias: 'course_generator')
- `TemplateEngine::class` - Template rendering (alias: 'template')

### Testing with Mocks

The DI container makes it easy to mock services for testing:

```php
class MyTest extends TestCase {
    protected function setUp(): void {
        $container = Container::getInstance();
        $container->reset(); // Clear services
        
        // Register mock
        $mockLLM = $this->createMock(LLMService::class);
        $mockLLM->method('generateContent')
            ->willReturn('mocked response');
            
        $container->register(LLMService::class, function () use ($mockLLM) {
            return $mockLLM;
        });
    }
}
```

### Backward Compatibility

For backward compatibility, services can be instantiated directly:

```php
public function __construct(
    ?LLMService $llmService = null,
    ?DatabaseService $databaseService = null
) {
    // Use injected or get from container
    $container = function_exists('mpcc_container') ? mpcc_container() : null;
    
    $this->llmService = $llmService ?? 
        ($container ? $container->get(LLMService::class) : new LLMService());
}
```

## Best Practices

1. **Always use type hints** for dependencies in constructors
2. **Register services as singletons** unless you specifically need new instances
3. **Use aliases** for commonly accessed services
4. **Mock services in tests** rather than using real implementations
5. **Avoid service locator pattern** - inject dependencies instead

## Examples

### Creating a New Service

```php
namespace MemberPressCoursesCopilot\Services;

class MyNewService extends BaseService {
    private DatabaseService $database;
    private Logger $logger;
    
    public function __construct(
        DatabaseService $database,
        Logger $logger
    ) {
        parent::__construct();
        $this->database = $database;
        $this->logger = $logger;
    }
    
    public function init(): void {
        // Initialize hooks, filters, etc.
    }
}
```

### Registering the Service

In `ServiceProvider::registerCoreServices()`:

```php
$container->register(MyNewService::class, function (Container $container) {
    return new MyNewService(
        $container->get(DatabaseService::class),
        $container->get(Logger::class)
    );
}, true);
```

### Using in a Controller

```php
class MyController {
    private MyNewService $myService;
    
    public function __construct() {
        $this->myService = mpcc_get_service(MyNewService::class);
    }
}
```

## Migration Guide

To migrate existing code to use DI:

1. **Identify direct instantiations**:
   ```php
   // Before
   $service = new LLMService();
   
   // After
   $service = mpcc_get_service(LLMService::class);
   ```

2. **Update constructors**:
   ```php
   // Before
   public function __construct() {
       $this->llmService = new LLMService();
   }
   
   // After
   public function __construct(?LLMService $llmService = null) {
       $this->llmService = $llmService ?? mpcc_get_service(LLMService::class);
   }
   ```

3. **Register custom services**:
   - Add registration to `ServiceProvider`
   - Update instantiation to use container

## Troubleshooting

**Service not found error**:
- Check that the service is registered in `ServiceProvider`
- Verify the class name/namespace is correct
- Ensure autoloader is updated (`composer dump-autoload`)

**Circular dependency**:
- Review service dependencies
- Consider using setter injection for circular dependencies
- Refactor to eliminate circular dependencies

**Performance concerns**:
- Services are singletons by default
- Container adds minimal overhead
- Use non-singleton registration sparingly