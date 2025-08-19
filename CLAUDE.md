# MemberPress Courses Copilot Development Guidelines

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
│   │   ├── LLMService.php
│   │   ├── CourseGeneratorService.php
│   │   ├── CourseContentRouter.php
│   │   └── ProxyConfigService.php
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

### Proxy Configuration
- **Proxy URL**: `https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com`
- **Master Key**: Use existing LiteLLM master key from MemberPress Copilot
- **Endpoint**: All providers use `/chat/completions` (OpenAI-compatible)
- **Authentication**: Master key in Authorization header

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

### API Client Pattern
```php
class LLMService {
    private $proxyUrl = 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com';
    
    public function sendRequest(array $messages, string $contentType = 'content'): array {
        $provider = $this->getProviderForContentType($contentType);
        $model = $this->getModelForProvider($provider);
        
        $response = wp_remote_post($this->proxyUrl . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getMasterKey(),
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $this->getTemperatureForContentType($contentType)
            ])
        ]);
        
        return $this->handleResponse($response, $provider);
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

## Common Patterns & Examples

### Service Registration (DI Container)
```php
class Plugin {
    private Container $container;
    
    public function initServices(): void {
        $this->container->register(LLMService::class);
        $this->container->register(CourseGeneratorService::class);
        $this->container->register(AdminController::class);
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

Remember: This plugin leverages existing LiteLLM proxy infrastructure to provide AI-powered course creation while maintaining WordPress standards and MemberPress integration patterns. Always prioritize user experience, educational quality, and system reliability.