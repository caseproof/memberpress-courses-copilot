# MemberPress Courses Copilot - Architecture Documentation

## Overview

The MemberPress Courses Copilot plugin follows a service-oriented architecture with dependency injection, clear separation of concerns, and interface-based design for testability.

## Directory Structure

```
src/MemberPressCoursesCopilot/
├── Admin/              # Admin UI components
├── Commands/           # WP-CLI commands
├── Container/          # Dependency injection container
├── Controllers/        # AJAX request handlers
├── Database/           # Database table management
├── Interfaces/         # Service interfaces
├── Models/             # Data models
├── Security/           # Security utilities
├── Services/           # Core business logic
└── Utilities/          # Helper functions
```

## Core Components

### Plugin Bootstrap

**File:** `Plugin.php`

The main plugin class that:
- Initializes the dependency injection container
- Registers all services
- Hooks into WordPress lifecycle events
- Manages plugin activation/deactivation

```php
$plugin = Plugin::instance();
$plugin->init();
```

### Dependency Injection Container

**Files:** `Container/Container.php`, `Container/ServiceProvider.php`

A PSR-11 compatible container that manages service dependencies:

```php
$container = new Container();
$container->register(ILLMService::class, LLMService::class);
$service = $container->get(ILLMService::class);
```

### Service Interfaces

All major services implement interfaces for loose coupling and testability:

#### ILLMService
**File:** `Interfaces/ILLMService.php`

Handles communication with AI providers through the authentication gateway.

```php
interface ILLMService {
    public function generateContent(
        string $prompt,
        string $context = 'general',
        array $options = []
    ): array;
    
    public function validateApiKey(string $apiKey): bool;
    public function getModelCapabilities(string $model): array;
}
```

#### IConversationManager
**File:** `Interfaces/IConversationManager.php`

Manages conversation sessions and persistence.

```php
interface IConversationManager {
    public function createSession(array $data): ConversationSession;
    public function loadSession(string $sessionId): ?ConversationSession;
    public function saveSession(ConversationSession $session): bool;
    public function deleteSession(string $sessionId): bool;
    public function getUserSessions(int $userId, int $limit = 10): array;
}
```

#### ICourseGenerator
**File:** `Interfaces/ICourseGenerator.php`

Creates WordPress course entities from AI-generated structures.

```php
interface ICourseGenerator {
    public function generateCourse(array $courseData): array;
    public function validateCourseData(array $courseData): array;
    public function generateSection(array $sectionData, int $courseId): int;
    public function generateLesson(array $lessonData, int $sectionId): int;
}
```

#### IDatabaseService
**File:** `Interfaces/IDatabaseService.php`

Provides database operations abstraction.

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

## Key Services

### LLMService
**File:** `Services/LLMService.php`

Communicates with the authentication gateway for AI operations:
- Manages API requests to the auth gateway
- Handles response parsing and error handling
- Implements retry logic and timeouts
- Routes requests to appropriate AI models

### ConversationManager
**File:** `Services/ConversationManager.php`

Handles all conversation-related operations:
- Creates and manages conversation sessions
- Persists conversations to database
- Manages conversation state and history
- Handles session lifecycle (create, load, save, delete)

### CourseGeneratorService
**File:** `Services/CourseGeneratorService.php`

Transforms AI-generated course structures into WordPress entities:
- Creates course custom post types
- Generates section hierarchies
- Creates individual lessons with content
- Manages course metadata and relationships

### LessonDraftService
**File:** `Services/LessonDraftService.php`

Manages lesson content drafts before course creation:
- Saves lesson content during preview editing
- Maps drafts to course structure during creation
- Handles draft cleanup after course creation

### CourseAjaxService & SimpleAjaxController
**Files:** `Services/CourseAjaxService.php`, `Controllers/SimpleAjaxController.php`

Handle AJAX requests from the frontend:
- Process chat messages
- Manage conversation persistence
- Handle course creation requests
- Coordinate between services

## Data Models

### ConversationSession
**File:** `Models/ConversationSession.php`

Represents a conversation session with:
- Session metadata (ID, user, timestamps)
- Message history
- Conversation state and context
- Progress tracking

### CourseTemplate
**File:** `Models/CourseTemplate.php`

Defines course structure templates:
- Template metadata
- Default sections and lessons
- Learning objectives patterns
- Assessment suggestions

### GeneratedCourse
**File:** `Models/GeneratedCourse.php`

Represents a generated course:
- Course metadata
- Section and lesson hierarchy
- Content and assessments
- Quality metrics

## Security

### Nonce Management
**File:** `Security/NonceConstants.php`

Centralizes nonce actions and verification:

```php
class NonceConstants {
    const EDITOR_NONCE = 'mpcc_editor_nonce';
    const COURSES_INTEGRATION = 'mpcc_courses_integration';
    
    public static function verify($nonce, $action, $allowEmpty = false): bool;
    public static function create($action): string;
}
```

### Capability Checks

All endpoints verify user capabilities:
- `edit_posts` - Basic AI access
- `publish_posts` - Course creation
- `manage_options` - Settings management

## Database Schema

### mpcc_conversations
Stores conversation sessions:
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

### mpcc_lesson_drafts
Stores lesson content drafts:
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

## Frontend Architecture

### JavaScript Modules

- `ai-chat-interface.js` - Chat UI component
- `course-editor-page.js` - Main editor page controller
- `course-preview-editor.js` - Preview and lesson editing
- `session-manager.js` - Session state management
- `shared-utilities.js` - Common utility functions

### State Management

The frontend maintains state for:
- Current session ID
- Conversation history
- Course structure
- UI state (loading, errors, etc.)

### Communication Flow

1. User sends message → AJAX to controller
2. Controller validates request → Calls service
3. Service processes → Returns response
4. Controller formats response → Returns to frontend
5. Frontend updates UI → Saves state

## Testing

### Unit Tests
Test individual services and models in isolation using PHPUnit.

### Integration Tests
Test service interactions and WordPress integration.

### Frontend Tests
Manual test scenarios and automated browser tests.

## Extension Points

### Adding New AI Providers

Implement the `ILLMService` interface:

```php
class CustomLLMService implements ILLMService {
    public function generateContent($prompt, $context, $options): array {
        // Custom implementation
    }
}
```

### Custom Course Templates

Add templates via filter:

```php
add_filter('mpcc_course_templates', function($templates) {
    $templates['custom'] = [
        'name' => 'Custom Template',
        'sections' => [...]
    ];
    return $templates;
});
```

### Validation Rules

Add custom validation:

```php
add_filter('mpcc_course_validation_rules', function($rules) {
    $rules['custom_rule'] = function($courseData) {
        // Validation logic
    };
    return $rules;
});
```

## Performance Considerations

- Database queries are optimized with proper indexes
- AI responses are cached where appropriate
- Large conversations are paginated
- Assets are minified and concatenated
- Lazy loading for heavy components

## Error Handling

The plugin uses a consistent error handling approach:
- All exceptions are caught and logged
- User-friendly error messages are displayed
- Detailed errors are logged for debugging
- Graceful fallbacks for service failures

## Deployment

1. Build assets: `npm run build`
2. Run tests: `composer test`
3. Check coding standards: `composer cs-check`
4. Create distribution package
5. Deploy to WordPress installation