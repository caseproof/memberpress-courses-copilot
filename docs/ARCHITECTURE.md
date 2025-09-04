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

### Core Services

#### LLMService
**File:** `Services/LLMService.php`  
**Interface:** `ILLMService`  
**Singleton:** Yes

Handles all communication with AI providers through authentication gateway.

**Responsibilities:**
- API requests to external authentication gateway
- Response parsing and error handling
- Retry logic and timeout management
- AI model routing and configuration
- Token management and validation

**Key Methods:**
```php
public function sendMessage(string $message, array $conversationHistory = []): array
public function generateCourse(array $courseData): array
public function getModelCapabilities(): array
public function validateConnection(): bool
```

**Dependencies:**
- Authentication Gateway (external service)
- Logger utility for request/response logging

#### ConversationManager
**File:** `Services/ConversationManager.php`  
**Interface:** `IConversationManager`  
**Singleton:** Yes

Manages conversation sessions and state persistence.

**Responsibilities:**
- Session creation and lifecycle management
- Conversation history persistence
- State management across requests
- Session cleanup and optimization
- Multi-user session isolation

**Key Methods:**
```php
public function createSession(array $data): ConversationSession
public function loadSession(string $sessionId): ?ConversationSession
public function saveSession(ConversationSession $session): bool
public function deleteSession(string $sessionId): bool
public function getUserSessions(int $userId, int $limit = 10): array
public function cleanupExpiredSessions(): int
```

**Dependencies:**
- DatabaseService for persistence
- Logger for operation tracking

#### CourseGeneratorService
**File:** `Services/CourseGeneratorService.php`  
**Interface:** `ICourseGenerator`  
**Singleton:** Yes

Transforms AI-generated course structures into WordPress entities.

**Responsibilities:**
- WordPress course post creation
- Section hierarchy generation
- Lesson content creation and ordering
- Course metadata management
- MemberPress Courses integration
- Draft to published workflow

**Key Methods:**
```php
public function generateCourse(array $courseData): array
public function validateCourseData(array $courseData): array
public function generateSection(array $sectionData, int $courseId): int
public function generateLesson(array $lessonData, int $sectionId): int
public function updateCourseStructure(int $courseId, array $structure): bool
```

**Dependencies:**
- Logger for operation tracking
- WordPress post/meta APIs

#### MpccQuizAIService
**File:** `Services/MpccQuizAIService.php`  
**Interface:** `IQuizAIService`  
**Singleton:** Yes

Specialized AI service for quiz generation and management. This is a streamlined implementation focused on the core quiz generation functionality.

**Responsibilities:**
- Quiz question generation from content
- Multiple question type support
- Content analysis and validation
- Question quality assessment
- Quiz structure optimization

**Key Methods:**
```php
public function generateQuestions(string $content, array $options = []): array
public function generateMultipleChoiceQuestions(string $content, int $count = 5): array
public function generateTrueFalseQuestions(string $content, int $count = 5): array
public function generateTextAnswerQuestions(string $content, int $count = 5): array
public function generateMultipleSelectQuestions(string $content, int $count = 5): array
public function getSupportedQuestionTypes(): array
```

**Dependencies:**
- LLMService for AI communication
- Logger for generation tracking

### Support Services

#### DatabaseService
**File:** `Services/DatabaseService.php`  
**Interface:** `IDatabaseService`  
**Singleton:** Yes

Provides database operations abstraction and custom table management.

**Responsibilities:**
- Custom table creation and management
- CRUD operations with proper sanitization
- Database schema versioning
- Migration handling
- Query optimization

#### LessonDraftService
**File:** `Services/LessonDraftService.php`  
**Singleton:** Yes

Manages lesson content drafts during course creation workflow.

**Responsibilities:**
- Draft content storage and retrieval
- Session-based draft management
- Draft to lesson conversion
- Cleanup of unused drafts

#### AssetManager
**File:** `Services/AssetManager.php`  
**Singleton:** Yes

Handles asset loading and management across the plugin.

**Responsibilities:**
- Script and stylesheet registration
- Conditional asset loading based on page context
- Asset dependency management
- Localization of JavaScript variables

### Integration Services

#### CourseIntegrationService
**File:** `Services/CourseIntegrationService.php`  
**Singleton:** Yes

Integrates AI functionality into MemberPress Courses listing pages.

**Responsibilities:**
- "Create with AI" button injection
- Modal interface management
- Course listing page modifications
- Context-aware AI integration

#### EditorAIIntegrationService
**File:** `Services/EditorAIIntegrationService.php`  
**Singleton:** Yes

Provides AI assistance within WordPress post editors.

**Responsibilities:**
- Metabox registration for courses and lessons
- Editor-specific AI chat interface
- Content improvement suggestions
- Context-aware assistance

#### CourseUIService
**File:** `Services/CourseUIService.php`  
**Singleton:** Yes

Manages UI components and frontend integration.

**Responsibilities:**
- UI component rendering
- Template loading and processing
- Frontend state management
- User interface consistency

### Utility Services

#### ContentGenerationService
**File:** `Services/ContentGenerationService.php`  
**Singleton:** Yes

Handles various content generation tasks beyond courses.

#### EnhancedTemplateEngine
**File:** `Services/EnhancedTemplateEngine.php`  
**Singleton:** Yes

Provides template management for course creation.

#### SessionFeaturesService
**File:** `Services/SessionFeaturesService.php`  
**Singleton:** Yes

Manages advanced session features like auto-save and extension.

### Controllers

#### SimpleAjaxController
**File:** `Controllers/SimpleAjaxController.php`  
**Handles:** Course Editor Page endpoints

**Registered Actions:**
- `mpcc_chat_message` - Main chat interface
- `mpcc_load_session` - Session loading
- `mpcc_create_course` - Course creation
- `mpcc_get_sessions` - Session listing
- `mpcc_save_conversation` - Session persistence
- `mpcc_save_lesson_content` - Lesson draft management
- `mpcc_generate_lesson_content` - AI lesson generation

#### MpccQuizAjaxController
**File:** `Controllers/MpccQuizAjaxController.php`  
**Handles:** Quiz-related endpoints

**Registered Actions:**
- `mpcc_generate_quiz` - Quiz question generation
- `mpcc_create_quiz_from_lesson` - Quiz creation from lesson
- `mpcc_regenerate_question` - Single question regeneration
- `mpcc_validate_quiz` - Quiz validation
- `mpcc_get_lesson_course` - Lesson course lookup
- `mpcc_get_course_lessons` - Course lesson listing

#### CourseAjaxService
**File:** `Services/CourseAjaxService.php`  
**Handles:** Course integration endpoints

**Registered Actions:**
- `mpcc_load_ai_interface` - AI modal loading
- `mpcc_create_course_with_ai` - Modal course creation
- `mpcc_ai_chat` - Modal chat interface
- `mpcc_course_chat_message` - Course edit chat

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