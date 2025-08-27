# MemberPress Courses Copilot Style Guide

## Table of Contents
1. [Core Principles](#core-principles)
2. [CSS/Design Standards](#cssdesign-standards)
3. [JavaScript Standards](#javascript-standards)
4. [PHP Standards](#php-standards)
5. [UI Component Library](#ui-component-library)
6. [Security Standards](#security-standards)
7. [Development Workflow](#development-workflow)

## Core Principles

### KISS (Keep It Simple, Stupid)
- One service for one purpose (e.g., single LLMService for all AI operations)
- Direct implementations without unnecessary abstractions
- Simple instantiation over complex dependency injection

### DRY (Don't Repeat Yourself)
- Single source of truth for configurations
- Reusable components and utilities
- Shared system prompts and templates

### YAGNI (You Aren't Gonna Need It)
- No settings UI unless absolutely necessary
- Hardcode what works reliably
- Avoid premature optimization

### Clean Code Practices
- **NO LEGACY CODE**: Delete old code immediately when replacing
- **NO FALLBACKS**: Use real data or fail properly
- **NO MOCK DATA**: Never use placeholder content
- Clear, descriptive naming
- Single responsibility per function/class

## CSS/Design Standards

### Naming Conventions
```css
/* Block-Element-Modifier with prefix */
.mpcc-block {}
.mpcc-block-element {}
.mpcc-block--modifier {}

/* State classes */
.active, .disabled, .loading, .show, .hide
```

### Color Palette
```css
:root {
    /* Primary Colors */
    --mpcc-primary: #0073aa;        /* WordPress Blue */
    --mpcc-primary-dark: #005a87;
    --mpcc-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    
    /* Status Colors */
    --mpcc-success: #46b450;
    --mpcc-warning: #ffb900;
    --mpcc-error: #dc3232;
    --mpcc-info: #00a0d2;
    
    /* Neutrals */
    --mpcc-bg-primary: #ffffff;
    --mpcc-bg-secondary: #f9f9f9;
    --mpcc-text-primary: #2c3338;
    --mpcc-text-secondary: #50575e;
    --mpcc-border-primary: #dcdcde;
}
```

### Typography
```css
/* Font Stack */
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;

/* Font Sizes */
--mpcc-font-size-xs: 0.75rem;   /* 12px */
--mpcc-font-size-sm: 0.875rem;  /* 14px */
--mpcc-font-size-base: 1rem;    /* 16px */
--mpcc-font-size-lg: 1.125rem;  /* 18px */
--mpcc-font-size-xl: 1.25rem;   /* 20px */
--mpcc-font-size-2xl: 1.5rem;   /* 24px */

/* Font Weights */
font-weight: 400;  /* Regular */
font-weight: 500;  /* Medium */
font-weight: 600;  /* Semibold */
font-weight: 700;  /* Bold */
```

### Spacing System
```css
/* Spacing Scale */
--mpcc-spacing-xs: 0.25rem;   /* 4px */
--mpcc-spacing-sm: 0.5rem;    /* 8px */
--mpcc-spacing-md: 1rem;      /* 16px */
--mpcc-spacing-lg: 1.5rem;    /* 24px */
--mpcc-spacing-xl: 2rem;      /* 32px */
--mpcc-spacing-2xl: 3rem;     /* 48px */
```

### Component Styling
```css
/* Buttons */
.button-primary {
    background: var(--mpcc-gradient);
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    transition: all 0.2s ease;
}

.button-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Cards */
.mpcc-card {
    background: var(--mpcc-bg-primary);
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    transition: all 0.2s ease;
}

.mpcc-card:hover {
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
}
```

### Animations
```css
/* Standard transitions */
transition: all 0.2s ease;
transition: opacity 0.2s ease;

/* Fade in animation */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loading spinner */
@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
```

### Responsive Breakpoints
```scss
$mobile: 480px;
$tablet: 768px;
$desktop: 1200px;
$wp-admin-bar: 782px;

@media (max-width: $tablet) {
    /* Mobile styles */
}
```

## JavaScript Standards

### Code Organization
```javascript
// IIFE wrapper for jQuery compatibility
(function($) {
    'use strict';
    
    // Module/Object pattern
    const ModuleName = {
        init: function() {
            this.bindEvents();
            this.initializeComponents();
        },
        
        bindEvents: function() {
            // Event delegation for dynamic content
            $(document).on('click', '.mpcc-element', this.handleClick.bind(this));
        }
    };
    
    // Initialize on document ready
    $(document).ready(() => ModuleName.init());
    
})(jQuery);
```

### Naming Conventions
```javascript
// Variables and functions: camelCase
const userName = 'John';
function calculateTotal() {}

// Classes and constructors: PascalCase
class CourseEditor {}
const MyComponent = {};

// Constants: UPPERCASE (rarely used)
const MAX_RETRIES = 3;

// jQuery elements: $ prefix
const $button = $('#mpcc-button');
```

### Event Handling
```javascript
// Namespaced events
$(document).on('click.mpcc-module', '.selector', handler);

// Custom events
$(document).trigger('mpcc:course-updated', data);

// Event delegation for dynamic content
$(document).on('click', '.mpcc-dynamic-button', function(e) {
    e.preventDefault();
    // Handle event
});
```

### AJAX Pattern
```javascript
$.ajax({
    url: mpccSettings.ajaxUrl,
    type: 'POST',
    data: {
        action: 'mpcc_action_name',
        nonce: mpccSettings.nonce,
        // Other data
    },
    success: (response) => {
        if (response.success) {
            // Handle success
            mpccToast.success(response.data.message);
        } else {
            mpccToast.error(response.data.message || 'Operation failed');
        }
    },
    error: (xhr, status, error) => {
        console.error('AJAX Error:', error);
        mpccToast.error('An error occurred. Please try again.');
    }
});
```

### Error Handling
```javascript
// Defensive programming
if (!$element.length) {
    console.warn('Element not found');
    return;
}

// Try-catch for critical operations
try {
    const data = JSON.parse(jsonString);
} catch (error) {
    console.error('JSON Parse Error:', error);
    mpccToast.error('Invalid data format');
}
```

## PHP Standards

### File Structure
```php
<?php
/**
 * Class description
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

defined('ABSPATH') || exit;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Service class description
 */
class ExampleService extends BaseService
{
    // Implementation
}
```

### Naming Conventions
```php
// Classes: PascalCase
class CourseGeneratorService {}

// Methods: camelCase
public function generateCourse(): array {}

// Properties: camelCase
private string $sessionId;

// Constants: UPPERCASE
const API_VERSION = '1.0.0';

// Database fields: snake_case
$wpdb->prefix . 'mpcc_conversations';
```

### Security Practices
```php
// Nonce verification
if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE)) {
    wp_send_json_error('Security check failed');
}

// Capability checks
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions');
}

// Data sanitization
$title = sanitize_text_field($_POST['title'] ?? '');
$content = wp_kses_post($_POST['content'] ?? '');

// SQL queries with prepare
$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}mpcc_sessions WHERE user_id = %d",
    get_current_user_id()
);
```

### Service Architecture
```php
class ExampleService extends BaseService
{
    private LLMService $llmService;
    
    public function __construct(?LLMService $llmService = null)
    {
        parent::__construct();
        
        // Graceful dependency resolution
        $container = function_exists('mpcc_container') ? mpcc_container() : null;
        $this->llmService = $llmService ?? 
            ($container ? $container->get(LLMService::class) : new LLMService());
    }
}
```

### WordPress Integration
```php
// Action hooks
add_action('wp_ajax_mpcc_action', [$this, 'handleAction']);
add_action('wp_ajax_nopriv_mpcc_action', [$this, 'handleActionNoPriv']);

// Filter hooks
add_filter('mpcc_course_data', [$this, 'filterCourseData'], 10, 2);

// Custom hooks
do_action('mpcc_course_created', $courseId, $courseData);
```

## UI Component Library

### Layout Components
```html
<!-- Main container -->
<div class="mpcc-container">
    <!-- Header -->
    <div class="mpcc-header">
        <h1 class="mpcc-header-title">Title</h1>
        <div class="mpcc-header-actions">
            <!-- Action buttons -->
        </div>
    </div>
    
    <!-- Content area -->
    <div class="mpcc-content">
        <!-- Main content -->
    </div>
</div>
```

### Button Components
```html
<!-- Primary button -->
<button type="button" class="button button-primary mpcc-gradient-button">
    <span class="dashicons dashicons-yes"></span>
    Primary Action
</button>

<!-- Icon button -->
<button type="button" class="button-link mpcc-icon-button">
    <span class="dashicons dashicons-edit"></span>
</button>
```

### Card Component
```html
<div class="mpcc-card">
    <div class="mpcc-card-header">
        <h3 class="mpcc-card-title">Card Title</h3>
    </div>
    <div class="mpcc-card-body">
        <!-- Content -->
    </div>
    <div class="mpcc-card-footer">
        <!-- Actions -->
    </div>
</div>
```

### Form Components
```html
<!-- Text input with label -->
<div class="mpcc-form-group">
    <label for="field-id" class="mpcc-label">Field Label</label>
    <input type="text" id="field-id" class="mpcc-input" />
</div>

<!-- Textarea -->
<div class="mpcc-form-group">
    <label for="textarea-id" class="mpcc-label">Description</label>
    <textarea id="textarea-id" class="mpcc-textarea" rows="4"></textarea>
</div>
```

### Chat Components
```html
<!-- Chat message -->
<div class="mpcc-chat-message mpcc-message-user">
    <div class="mpcc-message-content">
        User message content
    </div>
</div>

<div class="mpcc-chat-message mpcc-message-assistant">
    <div class="mpcc-message-content">
        Assistant response
    </div>
</div>
```

## Security Standards

### Input Validation
- Always validate and sanitize user input
- Use WordPress sanitization functions
- Never trust data from $_GET, $_POST, or $_REQUEST

### Output Escaping
```php
echo esc_html($text);
echo esc_attr($attribute);
echo esc_url($url);
echo wp_kses_post($html);
```

### Nonce Usage
```php
// Creating nonce
wp_nonce_field('action_name', 'nonce_name');

// Verifying nonce
if (!wp_verify_nonce($_POST['nonce_name'], 'action_name')) {
    die('Security check failed');
}
```

### SQL Security
```php
// Always use prepare for queries
$results = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}table WHERE column = %s",
        $value
    )
);
```

## Development Workflow

### Git Commit Messages
```bash
# Format: type(scope): subject
feat(ai-chat): Add message retry functionality
fix(course-editor): Resolve section delete issue
docs(readme): Update installation instructions
style(css): Format button styles consistently
refactor(services): Simplify LLM service logic
```

### Pre-commit Checks
1. Run PHPCS: `composer run cs-check`
2. Fix issues: `composer run cs-fix`
3. Test functionality manually
4. Verify no console errors

### Code Review Checklist
- [ ] Follows naming conventions
- [ ] Proper error handling
- [ ] Security measures in place
- [ ] No hardcoded values
- [ ] Comments for complex logic
- [ ] Follows KISS/DRY/YAGNI principles
- [ ] No legacy code left behind
- [ ] Tested on multiple browsers

### Testing Standards
```php
// PHPUnit test example
class ExampleServiceTest extends TestCase
{
    public function testMethodName(): void
    {
        // Arrange
        $service = new ExampleService();
        
        // Act
        $result = $service->methodName();
        
        // Assert
        $this->assertEquals('expected', $result);
    }
}
```

## Best Practices Summary

1. **Keep it simple** - Don't over-engineer solutions
2. **Be consistent** - Follow established patterns
3. **Security first** - Always validate and escape
4. **Clean as you go** - Remove unused code immediately
5. **Document intent** - Comment why, not what
6. **Test thoroughly** - Manual and automated testing
7. **Progressive enhancement** - Features should degrade gracefully
8. **Accessibility matters** - ARIA labels, keyboard navigation
9. **Performance conscious** - Cache when appropriate
10. **User experience** - Clear feedback and error messages

---

This style guide is a living document. Update it as patterns evolve and new decisions are made.