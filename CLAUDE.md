# MemberPress Courses Copilot Development Guidelines

## Core Development Principles

### KISS (Keep It Simple, Stupid)
- **One service for AI**: Just `LLMService.php` with hardcoded credentials
- **No user configuration**: Everything works out of the box
- **Simple interfaces**: Methods do one thing well
- **Avoid abstraction layers**: Direct API calls, no complex wrappers

### DRY (Don't Repeat Yourself)
- **Single source of truth**: Proxy credentials in ONE place (LLMService constants)
- **Reusable components**: One AI service used everywhere
- **Shared prompts**: System prompts defined once and reused
- **No duplicate functionality**: One way to do each task

### YAGNI (You Aren't Gonna Need It)
- **No settings UI**: Users don't need to configure AI
- **No provider switching**: Hardcoded to use what works
- **No complex DI**: Simple instantiation where needed
- **No unused features**: Only implement what's actually used

## CRITICAL: NO FAKE/MOCK DATA EVER
- **NEVER USE FALLBACKS** - If an API fails, FAIL PROPERLY with proper error logging
- **NEVER CREATE MOCK DATA** - No fake responses, fake content, fake anything
- **NEVER USE PLACEHOLDER CONTENT** - No "Lorem ipsum", no "Sample content"  
- **NO SIMULATION CODE** - Every function must work with real data or throw exceptions
- **FAIL FAST AND LOUD** - When something breaks, let it break visibly with proper error messages
- **LOG EVERYTHING** - Use error_log() extensively to debug real issues, not mask them
- **NO GRACEFUL DEGRADATION** - If the AI service is down, the feature doesn't work. Period.
- **EXCEPTION THROWING** - Throw exceptions when things fail, don't return fake success
- **REAL OR NOTHING** - Either it works with real data/APIs or it doesn't work at all

## Security: Auth Gateway Implementation
API keys are now protected via an authentication gateway. See `/docs/AUTH_GATEWAY_IMPLEMENTATION.md` for setup details. The plugin uses license keys only - no API keys in code.

## Architecture Principles

### Simplicity First
```php
// GOOD: Simple, direct, works
$llm = new LLMService();
$response = $llm->generateContent($prompt);

// BAD: Over-engineered, complex
$container->get('llm.factory')->create('anthropic')->withConfig($config)->generate($prompt);
```

### Minimal Dependencies
- Services should work standalone when possible
- Avoid service chains and circular dependencies
- Use WordPress APIs directly, don't wrap them

### Clear Boundaries
- **LLMService**: All AI communication (ONE service only!)
- **CourseIntegrationService**: UI integration with MemberPress Courses
- **ContentGenerationService**: Course content creation logic
- Each service has a clear, single responsibility

## Project Overview
This plugin is an AI-powered conversational course creation assistant that reduces course development time from 6-10 hours to 10-30 minutes using LiteLLM proxy infrastructure and intelligent content generation.

## Build & Development Commands
- Install dependencies: `composer install` (PSR-4 autoloading required)
- Code standards check: `composer run cs-check`
- Auto-fix code standards: `composer run cs-fix`
- Testing: Follow procedures in `tests/test-procedures.md`
- Debug output: `error_log('MPCC: debug info here');`

## Code Organization & Architecture Standards (PSR-4)

### Namespace & Structure
- **Primary Namespace**: `MemberPressCoursesCopilot`
- **Text Domain**: `memberpress-courses-copilot`
- **Plugin Prefix**: `mpcc_`
- **Database Tables**: Prefix with `mpcc_`

### Directory Structure (PSR-4 Compliant)
```
memberpress-courses-copilot/
├── memberpress-courses-copilot.php (Main plugin file)
├── src/MemberPressCoursesCopilot/
│   ├── Plugin.php (Main plugin class)
│   ├── Controllers/
│   │   ├── AdminController.php
│   │   ├── AjaxController.php
│   │   └── CourseGenerationController.php
│   ├── Services/
│   │   ├── LLMService.php (Single, simple AI service with hardcoded credentials)
│   │   ├── CourseGeneratorService.php
│   │   └── CourseIntegrationService.php
│   ├── Models/
│   │   ├── CourseTemplate.php
│   │   └── GeneratedCourse.php
│   ├── Admin/
│   │   ├── AdminMenu.php
│   │   └── SettingsPage.php
│   └── Utilities/
│       ├── Logger.php
│       └── Validator.php
├── composer.json (PSR-4 autoloading + Caseproof-WP standard)
├── .editorconfig
└── assets/
    ├── css/
    ├── js/
    └── images/
```

### Class Organization Standards
- **Single Class Per File**: Each PHP file must contain only one class (PHPCS enforced)
- **Class Naming**: PascalCase with descriptive names
- **File Naming**: Must match class names exactly for PSR-4 autoloading
- **Namespacing**: Use full namespace declarations, avoid prefixed class names

## Coding Standards (Caseproof-WP Standard)

### PHP Standards
- **Standard**: Follow Caseproof-WP Standard (https://github.com/caseproof/coding-standards-php)
- **PHPCS Integration**: Mandatory for all code
- **Methods**: Use camelCase for methods, snake_case for WordPress hooks
- **Constants**: UPPERCASE with underscores
- **Properties**: camelCase for class properties

### Security Best Practices (Selective ABSPATH)
- **ABSPATH Checks**: Only use `defined('ABSPATH') || exit;` where actually needed:
  - Main plugin files that execute code
  - Files with code outside class/function definitions
- **Don't Use ABSPATH**: In class-only files with no executable code outside classes
- **Security**: Always verify nonces, sanitize inputs, escape outputs
- **Permissions**: Use WordPress capability checks before actions

### WordPress Integration
- **Hooks Prefix**: Use `mpcc_` prefix for actions and filters
- **Dependencies**: Verify MemberPress Core and MemberPress Courses are active
- **Error Handling**: Use WP_Error objects, proper try/catch for API calls
- **Translations**: Use `memberpress-courses-copilot` text domain

## LiteLLM Proxy Integration Guidelines

### KISS Implementation
```php
// This is ALL you need - no configuration, no settings, no complexity
class LLMService {
    private const PROXY_URL = 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com';
    private const MASTER_KEY = 'sk-litellm-EkFY6Wgp9MaDGjbrkCQx4qmbSH4wa0XrEVJmklFcYgw=';
    
    public function generateContent($prompt) {
        // Direct API call - simple and works
        return wp_remote_post(self::PROXY_URL . '/chat/completions', [...]);
    }
}
```

### Why This Follows Our Principles
- **KISS**: One service, hardcoded values, direct API calls
- **DRY**: Constants defined once, used everywhere in the service
- **YAGNI**: No proxy managers, no config services, no settings UI

### Provider Routing Strategy
```php
// Content-aware provider selection
private $courseContentTypeProviders = [
    'course_outline' => 'anthropic',        // Course structure creation
    'lesson_content' => 'anthropic',        // Lesson writing
    'learning_objectives' => 'anthropic',   // Educational content
    'quiz_generation' => 'openai',          // Structured data
    'assessment_scoring' => 'openai',       // Objective evaluation
    'validation_checks' => 'openai',        // Quality assurance
    'help_documentation' => 'docsbot'       // User support
];
```

### Fallback Chain Implementation
```php
private $providerFallbacks = [
    'anthropic' => ['openai'],
    'openai' => ['anthropic'],
    'docsbot' => ['anthropic', 'openai']
];
```

### DON'T DO THIS (Overcomplicated)
```php
// BAD: Too many abstractions, configuration, complexity
class ProxyConfigService { /* Don't create this */ }
class TokenUsageService { /* Don't create this */ }
class CourseContentRouter { /* Don't create this */ }
class ErrorHandlingService { /* Don't create this */ }

// BAD: Dependency injection nightmare
public function __construct(
    ProxyConfigService $proxy,
    TokenUsageService $tokens,
    CourseContentRouter $router,
    ErrorHandlingService $errors,
    Logger $logger,
    // ... 10 more dependencies
) {
    // This is too complex!
}
```

### DO THIS INSTEAD (Simple and Clean)
```php
// GOOD: Everything in one simple service
class LLMService {
    public function generateContent($prompt, $type = 'general') {
        $response = wp_remote_post(self::PROXY_URL . '/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . self::MASTER_KEY],
            'body' => json_encode([
                'model' => $this->getModel($type),
                'messages' => [['role' => 'user', 'content' => $prompt]]
            ])
        ]);
        return $this->parseResponse($response);
    }
}
```

## Course-Specific Development Patterns

### Course Generation Workflow
1. **Template Selection**: User chooses course type (Technical, Business, Creative, Academic)
2. **Requirements Gathering**: AI-driven conversation to collect specifications
3. **Structure Generation**: Create course hierarchy (Course → Sections → Lessons)
4. **Content Creation**: Generate learning objectives and lesson outlines
5. **Quality Validation**: Automated pedagogical and accessibility checks
6. **WordPress Integration**: Create MemberPress Courses entities

### Data Models
```php
class CourseTemplate {
    private string $templateType;
    private array $defaultStructure;
    private array $suggestedQuestions;
    private array $qualityChecks;
}

class GeneratedCourse {
    private string $title;
    private string $description;
    private array $learningObjectives;
    private array $sections;
    private array $metadata;
}
```

### Integration with MemberPress Courses
```php
// Hook into existing course creation
add_action('mpcc_course_generated', [$this, 'createMemberPressCoursesEntities']);

public function createMemberPressCoursesEntities(GeneratedCourse $course): void {
    // Use existing mpcs-course custom post type
    $courseId = wp_insert_post([
        'post_type' => 'mpcs-course',
        'post_title' => $course->getTitle(),
        'post_content' => $course->getDescription(),
        'post_status' => 'draft'
    ]);
    
    // Create sections and lessons using MemberPress Courses API
    foreach ($course->getSections() as $section) {
        $this->createCourseSection($courseId, $section);
    }
}
```

## Quality Assurance & Validation

### Automated Checks
- **Pedagogical Validation**: Learning progression, Bloom's taxonomy distribution
- **Content Quality**: Reading level, length, objective alignment
- **Accessibility**: WCAG compliance, inclusive design
- **Technical Validation**: WordPress standards, security checks

### Testing Strategy
- **Unit Tests**: Core logic and AI service integration
- **Integration Tests**: MemberPress Courses compatibility
- **User Acceptance**: Course generation workflow testing
- **Performance Tests**: LiteLLM proxy response times

## Development Workflow

### Phase Implementation
Refer to `/docs/IMPLEMENTATION_PLAN.md` for complete 16-week development roadmap:
- **Phase 1**: Foundation & Standards (Weeks 1-3)
- **Phase 2**: AI Collaboration Engine (Weeks 4-6)
- **Phase 3**: Course Generation System (Weeks 7-9)
- **Phase 4**: Advanced Features & QA (Weeks 10-12)
- **Phase 5**: UX & Mobile Optimization (Weeks 13-14)

### Git Workflow
- **Branches**: Feature branches from `main`
- **Commits**: Conventional commit messages
- **Code Review**: All changes require review
- **Standards**: PHPCS must pass before merge
- **GitHub CLI**: ALWAYS use `gh` commands for GitHub operations:
  - Create PRs: `gh pr create`
  - Push code: `gh repo sync` or create with `gh repo create --source . --push`
  - View issues: `gh issue view <number>`
  - NEVER use `git push` directly - use GitHub CLI commands

## Performance Guidelines

### Optimization Strategies
- **Caching**: Use WordPress transients for AI responses
- **Database**: Optimize queries, use proper indexes
- **Assets**: Minify CSS/JS, use Node.js build process
- **API Calls**: Batch requests when possible, implement retry logic

### Monitoring
- **Cost Tracking**: Monitor LiteLLM proxy usage
- **Performance**: Log response times and success rates
- **Errors**: Comprehensive error logging and reporting
- **User Analytics**: Track course generation success metrics

## Security Considerations

### API Security
- **No Direct Keys**: All API keys managed at LiteLLM proxy level
- **Rate Limiting**: Implemented via proxy, not plugin
- **Input Sanitization**: All user inputs properly validated
- **Output Escaping**: All AI-generated content escaped for display

### WordPress Security
- **Capabilities**: Use `edit_courses` capability for course generation
- **Nonces**: Required for all AJAX operations
- **Data Validation**: Sanitize all inputs, validate AI responses
- **User Permissions**: Respect WordPress role-based access

## Documentation Standards

### Code Documentation
- **PHPDoc**: All classes, methods, and properties
- **Inline Comments**: Complex logic explanation
- **README**: Installation and configuration instructions
- **CHANGELOG**: Version history and breaking changes

### User Documentation
- **Help Integration**: DocsBot integration for user support
- **Admin Interface**: Contextual help and tooltips
- **Error Messages**: User-friendly error explanations
- **Success Feedback**: Clear confirmation of actions

## Deployment & Maintenance

### Environment Configuration
- **Development**: Local WordPress with MemberPress setup
- **Staging**: Test environment with LiteLLM proxy access
- **Production**: WordPress.org plugin standards compliance

### Update Strategy
- **Backward Compatibility**: Maintain API compatibility
- **Database Migrations**: Handle schema changes gracefully
- **Settings Migration**: Preserve user configurations
- **Feature Flags**: Enable gradual feature rollout

## What NOT to Build (YAGNI)

### ❌ Don't Create These
- **Settings pages for AI configuration** - Hardcode it
- **Multiple AI service classes** - One LLMService is enough
- **Dependency injection containers** - Use `new LLMService()`
- **Configuration managers** - Use constants
- **Provider switchers** - Hardcode what works
- **Token tracking services** - Not needed for MVP
- **Complex error handlers** - Use try/catch and WP_Error
- **Abstract base classes** - Keep it concrete and simple
- **Service locators** - Just instantiate what you need

### ✅ Do This Instead
```php
// Simple instantiation wherever needed
$llm = new LLMService();
$result = $llm->generateContent($prompt);

// Direct error handling
if ($result['error']) {
    wp_send_json_error($result['message']);
}
```

## Common Patterns & Examples

### Service Registration (SIMPLE)
```php
// DON'T do complex DI containers
// DO this simple initialization
class Plugin {
    public function initializeComponents(): void {
        // Simple, direct, works
        global $mpcc_llm_service;
        $mpcc_llm_service = new LLMService();
        
        // Initialize other services as needed
        $integration = new CourseIntegrationService();
        $integration->init();
    }
}
```

### AJAX Handler Pattern
```php
class AjaxController {
    public function handleCourseGeneration(): void {
        check_ajax_referer('mpcc_generate_course', 'nonce');
        
        if (!current_user_can('edit_courses')) {
            wp_die(__('Insufficient permissions', 'memberpress-courses-copilot'));
        }
        
        $requirements = $this->sanitizeRequirements($_POST['requirements']);
        $course = $this->courseGenerator->generateCourse($requirements);
        
        wp_send_json_success($course->toArray());
    }
}
```

## Remember These Principles

### 🎯 KISS - Keep It Simple
- One LLMService with hardcoded credentials
- Direct WordPress API usage
- No unnecessary abstractions

### 🔄 DRY - Don't Repeat Yourself  
- Single source of truth for AI configuration
- Reusable prompt templates
- Shared validation logic

### ⚡ YAGNI - You Aren't Gonna Need It
- No settings UI (users don't need it)
- No provider switching (hardcode what works)
- No complex patterns (simple instantiation)

### The Golden Rule
**If you're creating more than one file to solve a problem, you're probably overcomplicating it.**

This plugin leverages existing LiteLLM proxy infrastructure to provide AI-powered course creation while maintaining WordPress standards and MemberPress integration patterns. Always prioritize simplicity, user experience, and getting things done over architectural perfection.