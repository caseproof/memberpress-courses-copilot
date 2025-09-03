# MemberPress Courses Copilot - Developer Guide

## Table of Contents

- [Overview](#overview)
- [Plugin Architecture](#plugin-architecture)
- [Service Layer](#service-layer)
- [Data Flow](#data-flow)
- [Integration Points](#integration-points)
- [Security Model](#security-model)
- [Database Schema](#database-schema)
- [Frontend Architecture](#frontend-architecture)
- [Development Workflow](#development-workflow)
- [Testing Strategy](#testing-strategy)
- [Debugging Guide](#debugging-guide)
- [Extension Points](#extension-points)

## Overview

The MemberPress Courses Copilot is an AI-powered WordPress plugin that assists users in creating courses for MemberPress Courses. It follows modern PHP development practices with strict typing, dependency injection, and service-oriented architecture.

### Key Features

- **AI-Powered Course Generation**: Creates complete courses with sections and lessons
- **Interactive Chat Interface**: Real-time conversation with AI assistant
- **Quiz Generation**: AI-generated quizzes from lesson content
- **Course Preview & Editing**: Edit generated content before publishing
- **Session Management**: Persistent conversation history
- **WordPress Integration**: Seamless integration with MemberPress Courses

### Technology Stack

- **PHP 8.0+**: Modern PHP with strict typing
- **WordPress 6.0+**: Latest WordPress features and hooks
- **JavaScript ES6+**: Modern frontend development
- **Dependency Injection**: PSR-11 compatible container
- **PHPUnit**: Comprehensive testing framework

## Plugin Architecture

### High-Level Architecture

```
WordPress Plugin
├── Bootstrap Layer (Plugin.php)
├── Dependency Injection Container
├── Service Layer (Business Logic)
├── Controller Layer (AJAX Handlers)
├── Admin Layer (WordPress Admin UI)
├── Database Layer (Custom Tables)
└── Frontend Layer (JavaScript/CSS)
```

### Directory Structure

```
src/MemberPressCoursesCopilot/
├── Admin/                  # WordPress admin UI components
│   ├── AdminMenu.php      # Plugin menu registration
│   ├── CourseEditorPage.php # Standalone course editor
│   └── SettingsPage.php   # Plugin settings interface
├── Commands/              # WP-CLI commands
│   └── DatabaseCommand.php # Database management CLI
├── Container/             # Dependency injection system
│   ├── Container.php      # PSR-11 compatible DI container
│   └── ServiceProvider.php # Service registration
├── Controllers/           # AJAX request handlers
│   ├── MpccQuizAjaxController.php # Quiz-related endpoints
│   └── SimpleAjaxController.php   # Course editor endpoints
├── Database/              # Database table management
│   └── LessonDraftTable.php # Lesson draft storage
├── Interfaces/            # Service contracts
│   ├── IConversationManager.php
│   ├── ICourseGenerator.php
│   ├── IDatabaseService.php
│   ├── ILLMService.php
│   └── IQuizAIService.php
├── Models/                # Data models
│   ├── ConversationSession.php
│   ├── CourseLesson.php
│   ├── CourseSection.php
│   ├── CourseTemplate.php
│   ├── GeneratedCourse.php
│   └── QualityReport.php
├── Security/              # Security utilities
│   └── NonceConstants.php # Centralized nonce management
├── Services/              # Core business logic
│   ├── AssetManager.php   # Asset loading and management
│   ├── BaseService.php    # Base service class
│   ├── ContentGenerationService.php
│   ├── ConversationFlowHandler.php
│   ├── ConversationManager.php
│   ├── CourseAjaxService.php
│   ├── CourseGeneratorService.php
│   ├── CourseIntegrationService.php
│   ├── CourseUIService.php
│   ├── DatabaseService.php
│   ├── EditorAIIntegrationService.php
│   ├── EnhancedTemplateEngine.php
│   ├── LLMService.php
│   ├── LessonDraftService.php
│   ├── MpccQuizAIService.php
│   └── SessionFeaturesService.php
└── Utilities/             # Helper classes
    ├── ApiResponse.php    # Standardized API responses
    ├── Helper.php         # General utility functions
    └── Logger.php         # Logging system
```

## Service Layer

The plugin uses a service-oriented architecture with clear separation of concerns. All major functionality is encapsulated in services that implement well-defined interfaces.

### Core Services

#### LLMService (`Services/LLMService.php`)
Handles all communication with AI providers through an authentication gateway.

**Responsibilities:**
- API communication with external AI services
- Request/response formatting
- Error handling and retry logic
- Authentication token management

**Key Methods:**
```php
public function sendMessage(string $message, array $conversationHistory = []): array
public function generateCourse(array $courseData): array
```

#### ConversationManager (`Services/ConversationManager.php`)
Manages conversation sessions and state persistence.

**Responsibilities:**
- Creating and managing conversation sessions
- Persisting conversation history to database
- Loading and retrieving session data
- Session lifecycle management

**Key Methods:**
```php
public function createSession(array $data): ConversationSession
public function loadSession(string $sessionId): ?ConversationSession
public function saveSession(ConversationSession $session): bool
```

#### CourseGeneratorService (`Services/CourseGeneratorService.php`)
Transforms AI-generated course structures into WordPress entities.

**Responsibilities:**
- Creating WordPress course posts
- Generating course sections and lessons
- Managing course metadata and relationships
- Handling course publishing workflow

#### MpccQuizAIService (`Services/MpccQuizAIService.php`)
Specialized service for AI-powered quiz generation.

**Responsibilities:**
- Generating quiz questions from content
- Supporting multiple question types
- Content validation and optimization
- Quiz structure formatting

### Service Dependencies

```
Plugin.php
├── Container/ServiceProvider.php
├── Controllers/*
│   ├── SimpleAjaxController
│   │   ├── ConversationManager
│   │   ├── CourseGeneratorService
│   │   └── LessonDraftService
│   └── MpccQuizAjaxController
│       └── MpccQuizAIService
│           └── LLMService
└── Services/*
    ├── ConversationManager → DatabaseService
    ├── CourseGeneratorService → Logger
    ├── ContentGenerationService → LLMService + Logger
    └── LessonDraftService → DatabaseService
```

## Data Flow

### Course Creation Workflow

```
1. User Input → Frontend Interface
   ├── Course Editor Page (standalone)
   └── Courses Integration (modal)

2. Frontend → AJAX Controller
   ├── SimpleAjaxController (course editor)
   └── CourseAjaxService (integration)

3. Controller → Service Layer
   ├── ConversationManager (session management)
   ├── LLMService (AI processing)
   └── CourseGeneratorService (WordPress creation)

4. Service → External APIs
   ├── Authentication Gateway
   └── AI Provider (OpenAI, Claude, etc.)

5. Response → Database
   ├── Conversation storage
   ├── Lesson drafts
   └── Course metadata

6. Database → Frontend Update
   ├── Updated UI state
   ├── Course preview
   └── Success notifications
```

### Quiz Generation Workflow

```
1. User Request → Quiz Interface
   ├── Lesson edit page
   ├── Course edit page
   └── Quiz editor

2. Frontend → MpccQuizAjaxController
   ├── Content extraction
   ├── Parameter validation
   └── Security checks

3. Controller → MpccQuizAIService
   ├── Content processing
   ├── AI prompt engineering
   └── Response formatting

4. Service → LLMService
   ├── Gateway authentication
   ├── AI API request
   └── Response parsing

5. Generated Questions → Frontend
   ├── Question validation
   ├── UI updates
   └── Quiz creation
```

## Integration Points

### MemberPress Integration

The plugin deeply integrates with MemberPress and MemberPress Courses:

#### Required Dependencies
- **MemberPress Core**: Base membership functionality
- **MemberPress Courses**: Course management system

#### Dependency Checking
```php
function memberpress_courses_copilot_is_memberpress_active(): bool {
    return defined('MEPR_PLUGIN_NAME') && class_exists('MeprCtrlFactory');
}

function memberpress_courses_copilot_is_courses_active(): bool {
    return (
        defined('memberpress\\courses\\VERSION') &&
        class_exists('memberpress\\courses\\models\\Course') &&
        class_exists('memberpress\\courses\\controllers\\App')
    );
}
```

#### WordPress Hooks Used

**Plugin Lifecycle:**
- `plugins_loaded` - Plugin initialization
- `init` - Component registration
- `admin_menu` - Admin interface setup
- `admin_enqueue_scripts` - Asset loading

**Course Integration:**
- `add_meta_boxes` - AI assistant metabox
- `save_post` - Course data synchronization
- `admin_notices` - User feedback

**AJAX Hooks:**
- All AJAX actions use `wp_ajax_*` hooks
- Security verification on every endpoint
- Capability checks before processing

### Custom Post Types Integration

The plugin works with MemberPress Courses post types:

- **mpcs-course**: Main course entity
- **mpcs-lesson**: Individual lessons within courses
- **mpcs-section**: Course sections (via metadata)
- **mpcs-quiz**: Generated quizzes

## Security Model

### Multi-Layer Security

1. **WordPress Nonces**: CSRF protection for all requests
2. **Capability Checks**: WordPress role-based permissions
3. **Input Sanitization**: All user inputs are sanitized
4. **Output Escaping**: All outputs are escaped
5. **API Gateway**: External API keys stored securely off-site

### Nonce Management

Centralized in `NonceConstants.php`:

```php
public const EDITOR_NONCE = 'mpcc_editor_nonce';
public const COURSES_INTEGRATION = 'mpcc_courses_integration';
public const AI_INTERFACE = 'mpcc_ai_interface';
public const QUIZ_AI = 'mpcc_quiz_ai_nonce';
```

### Capability Requirements

| Action | Required Capability | Purpose |
|--------|-------------------|---------|
| View AI Interface | `edit_posts` | Basic access to AI features |
| Create Courses | `publish_posts` | Create and publish courses |
| Manage Settings | `manage_options` | Configure plugin settings |
| Edit Specific Course | `edit_post` | Edit individual courses |

### Input Validation

All user inputs go through validation:

```php
private function extractAndSanitizeInput(): array {
    return [
        'lessonId' => absint($_POST['lesson_id'] ?? 0),
        'content' => sanitize_textarea_field($_POST['content'] ?? ''),
        'options' => $this->sanitizeArray($_POST['options'] ?? [])
    ];
}
```

## Database Schema

### Custom Tables

#### mpcc_conversations
Stores conversation sessions and chat history.

```sql
CREATE TABLE {prefix}_mpcc_conversations (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(36) NOT NULL,
    user_id bigint(20) unsigned NOT NULL,
    title varchar(255),
    messages longtext,
    context longtext,
    current_state varchar(50),
    metadata longtext,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY session_id (session_id),
    KEY user_id (user_id),
    KEY updated_at (updated_at)
);
```

**Key Fields:**
- `session_id`: UUID for session identification
- `messages`: JSON-encoded conversation history
- `context`: Course structure and state data
- `current_state`: Conversation flow state
- `metadata`: Additional session metadata

#### mpcc_lesson_drafts
Stores lesson content drafts during course creation.

```sql
CREATE TABLE {prefix}_mpcc_lesson_drafts (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(36) NOT NULL,
    section_id varchar(50) NOT NULL,
    lesson_id varchar(50) NOT NULL,
    content longtext,
    order_index int(11) DEFAULT 0,
    created_at datetime NOT NULL,
    updated_at datetime NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY unique_lesson (session_id, section_id, lesson_id),
    KEY session_id (session_id)
);
```

**Key Fields:**
- `session_id`: Links to conversation session
- `section_id`: Course section identifier
- `lesson_id`: Lesson identifier within section
- `content`: Draft lesson content
- `order_index`: Lesson ordering within section

### WordPress Integration

The plugin also uses WordPress's built-in systems:

- **wp_posts**: Course, lesson, and quiz posts
- **wp_postmeta**: Course metadata and relationships
- **wp_options**: Plugin settings and configuration
- **wp_usermeta**: User-specific settings

## Frontend Architecture

### JavaScript Modules

#### Core Modules

**ai-chat-interface.js**
- Main chat UI component
- Message handling and display
- Real-time conversation updates
- State management for chat sessions

**course-editor-page.js**
- Standalone course editor controller
- Course structure management
- Preview functionality
- Session persistence

**course-preview-editor.js**
- Lesson content editing interface
- Draft management
- Content generation requests
- Preview updates

**session-manager.js**
- Session state persistence
- Auto-save functionality
- Session restoration
- History management

#### Utility Modules

**shared-utilities.js**
- Common utility functions
- API request helpers
- DOM manipulation utilities
- Validation functions

**toast.js**
- User notification system
- Success/error messaging
- Non-blocking alerts

**debug.js**
- Development debugging tools
- Console logging utilities
- Performance monitoring

### State Management

The frontend maintains state across multiple dimensions:

#### Session State
```javascript
{
    sessionId: 'uuid-string',
    conversationHistory: [...],
    currentCourseStructure: {...},
    lastSavedAt: timestamp,
    isDirty: boolean
}
```

#### UI State
```javascript
{
    isLoading: boolean,
    currentView: 'chat|preview|settings',
    expandedSections: [...],
    activeLesson: 'section-id:lesson-id',
    modalOpen: boolean
}
```

#### Communication State
```javascript
{
    wsConnected: boolean,
    lastPing: timestamp,
    requestQueue: [...],
    retryCount: number
}
```

## Development Workflow

### Setting Up Development Environment

1. **Prerequisites**
   ```bash
   # PHP 8.0+ with required extensions
   php -v
   
   # Composer for dependency management
   composer --version
   
   # Node.js for asset building
   node --version
   npm --version
   ```

2. **Install Dependencies**
   ```bash
   # PHP dependencies
   composer install
   
   # JavaScript dependencies
   npm install
   ```

3. **Build Assets**
   ```bash
   # Development build with watch
   npm run dev
   
   # Production build
   npm run build
   ```

4. **Run Tests**
   ```bash
   # Unit tests
   ./vendor/bin/phpunit
   
   # Integration tests
   ./vendor/bin/phpunit --group integration
   
   # All tests
   composer test
   ```

### Code Standards

The plugin follows WordPress Coding Standards with modern PHP practices:

```bash
# Check coding standards
composer cs-check

# Fix coding standards
composer cs-fix
```

### Adding New Features

1. **Define Interface** (if needed)
   ```php
   interface INewService {
       public function doSomething(string $input): array;
   }
   ```

2. **Implement Service**
   ```php
   class NewService implements INewService {
       public function doSomething(string $input): array {
           // Implementation
       }
   }
   ```

3. **Register in Container**
   ```php
   // In ServiceProvider.php
   $container->register(NewService::class, NewService::class, true);
   $container->bind(INewService::class, NewService::class);
   ```

4. **Add Controller Methods** (if AJAX endpoint needed)
   ```php
   public function handleNewAction(): void {
       // Security checks
       // Input validation
       // Service calls
       // Response formatting
   }
   ```

5. **Write Tests**
   ```php
   class NewServiceTest extends TestCase {
       public function testDoSomething(): void {
           // Test implementation
       }
   }
   ```

## Testing Strategy

### Test Types

#### Unit Tests (`tests/Services/`)
- Test individual service methods in isolation
- Mock external dependencies
- Fast execution for CI/CD

#### Integration Tests (`tests/Integration/`)
- Test service interactions
- WordPress database integration
- AJAX endpoint testing

#### End-to-End Tests (`tests/e2e/`)
- Browser automation with Playwright
- Full user workflow testing
- JavaScript functionality validation

### Test Structure

```php
class ServiceTest extends TestCase {
    private Service $service;
    
    protected function setUp(): void {
        parent::setUp();
        $this->service = new Service();
    }
    
    public function testMethod(): void {
        $result = $this->service->method('input');
        $this->assertArrayHasKey('expected', $result);
    }
}
```

## Debugging Guide

### Debug Configuration

Enable debugging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('MPCC_DEBUG', true);
```

### Log Locations

- **WordPress Debug Log**: `/wp-content/debug.log`
- **Plugin Log**: `/wp-content/uploads/memberpress-courses-copilot/logs/copilot.log`

### Common Debug Scenarios

#### AJAX Request Issues
1. Check browser Network tab for request/response
2. Verify nonce values in requests
3. Check PHP error logs for server-side errors
4. Validate request parameters

#### Service Communication Problems
1. Enable service-level logging
2. Check authentication gateway responses
3. Verify API endpoint availability
4. Review service method calls

#### Database Issues
1. Check table existence and structure
2. Verify foreign key relationships
3. Review query performance
4. Check data integrity

### Debug Tools

#### Logger Usage
```php
$logger = Logger::getInstance();
$logger->info('Debug message', ['context' => 'data']);
$logger->error('Error occurred', ['exception' => $e]);
```

#### API Response Debugging
```php
// In controller methods
ApiResponse::success($data, 'Debug message', ['debug' => $debugData]);
```

## Extension Points

### Adding Custom AI Providers

Implement the `ILLMService` interface:

```php
class CustomLLMService implements ILLMService {
    public function sendMessage(string $message, array $conversationHistory = []): array {
        // Custom implementation
        return ['message' => 'Custom response'];
    }
    
    public function generateCourse(array $courseData): array {
        // Custom course generation
        return ['course' => $generatedCourse];
    }
}
```

Register in ServiceProvider:
```php
$container->bind(ILLMService::class, CustomLLMService::class);
```

### Custom Course Templates

Add templates via WordPress filters:

```php
add_filter('mpcc_course_templates', function($templates) {
    $templates['custom'] = [
        'name' => 'Custom Template',
        'description' => 'Custom course template',
        'sections' => [
            ['title' => 'Introduction', 'lessons' => ['Overview', 'Getting Started']],
            ['title' => 'Advanced Topics', 'lessons' => ['Deep Dive', 'Case Studies']]
        ]
    ];
    return $templates;
});
```

### Custom Validation Rules

Add validation for course data:

```php
add_filter('mpcc_course_validation_rules', function($rules) {
    $rules['custom_rule'] = function($courseData) {
        if (empty($courseData['custom_field'])) {
            return ['valid' => false, 'message' => 'Custom field required'];
        }
        return ['valid' => true];
    };
    return $rules;
});
```

### Custom Question Types

Extend quiz generation with new question types:

```php
add_filter('mpcc_supported_question_types', function($types) {
    $types['drag_drop'] = [
        'name' => 'Drag & Drop',
        'description' => 'Drag and drop matching questions',
        'ai_supported' => false
    ];
    return $types;
});
```

### Frontend Customization

Add custom JavaScript modules:

```php
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'memberpress-courses_page_mpcc-course-editor') {
        wp_enqueue_script(
            'custom-mpcc-extension',
            plugins_url('custom-extension.js', __FILE__),
            ['mpcc-course-editor'],
            '1.0.0',
            true
        );
    }
});
```

## Performance Considerations

### Database Optimization

- **Indexed Queries**: All foreign keys and frequently queried fields are indexed
- **Query Optimization**: Use WordPress query optimization techniques
- **Caching**: Implement object caching for expensive operations
- **Pagination**: Large datasets are paginated

### Frontend Performance

- **Asset Minification**: Production assets are minified and compressed
- **Lazy Loading**: Heavy components load on demand
- **Request Debouncing**: User inputs are debounced to reduce API calls
- **Caching**: API responses cached where appropriate

### AI Service Optimization

- **Request Batching**: Multiple AI requests can be batched
- **Response Caching**: Common responses cached temporarily
- **Timeout Management**: Proper timeout handling for external APIs
- **Retry Logic**: Automatic retry for transient failures

## Error Handling

### Standardized Error Responses

All errors follow consistent format through `ApiResponse` utility:

```php
// Simple error
ApiResponse::errorMessage('Error occurred', ApiResponse::ERROR_GENERAL, 400);

// Complex error with context
$error = new WP_Error('custom_code', 'Detailed message', ['context' => 'data']);
ApiResponse::error($error, 422);
```

### Error Categories

| Code | Category | Description |
|------|----------|-------------|
| `mpcc_invalid_nonce` | Security | Nonce verification failed |
| `mpcc_insufficient_permissions` | Authorization | User lacks required capabilities |
| `mpcc_missing_parameter` | Validation | Required parameter missing |
| `mpcc_invalid_parameter` | Validation | Parameter value invalid |
| `mpcc_database_error` | Database | Database operation failed |
| `mpcc_ai_service_error` | External | AI service unavailable/error |
| `mpcc_general_error` | Generic | Unspecified error |

### Frontend Error Handling

```javascript
// Standardized error handling
function handleApiError(response) {
    if (!response.success) {
        const error = response.error || response.data;
        
        switch(error.code) {
            case 'mpcc_invalid_nonce':
                // Refresh page or re-authenticate
                location.reload();
                break;
            case 'mpcc_insufficient_permissions':
                showToast('You do not have permission to perform this action', 'error');
                break;
            default:
                showToast(error.message || 'An error occurred', 'error');
        }
    }
}
```

## Best Practices

### Service Design

1. **Single Responsibility**: Each service has one clear purpose
2. **Interface Segregation**: Interfaces are focused and minimal
3. **Dependency Injection**: All dependencies injected via constructor
4. **Immutable Data**: Prefer immutable data structures where possible

### Controller Design

1. **Thin Controllers**: Controllers only handle HTTP concerns
2. **Security First**: Always verify nonce and capabilities first
3. **Input Validation**: Validate and sanitize all inputs
4. **Service Delegation**: Delegate business logic to services

### Frontend Design

1. **Progressive Enhancement**: Works with JavaScript disabled
2. **Error Boundaries**: Graceful error handling
3. **State Management**: Clear state management patterns
4. **Performance**: Optimize for user experience

### Testing Practices

1. **Test Coverage**: Aim for 80%+ code coverage
2. **Test Isolation**: Tests should not depend on each other
3. **Mock External Dependencies**: Mock WordPress functions and external APIs
4. **Real-World Scenarios**: Test with realistic data and edge cases

This guide provides the foundation for understanding and extending the MemberPress Courses Copilot plugin. For specific implementation details, refer to the individual service documentation and code comments.