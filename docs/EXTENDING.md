# Extending MemberPress Courses Copilot

This guide covers how to extend and customize the MemberPress Courses Copilot plugin.

## Hooks and Filters

### Actions

#### `mpcc_before_course_creation`
Fired before a course is created from AI-generated structure.

```php
do_action('mpcc_before_course_creation', $courseData, $sessionId);
```

#### `mpcc_after_course_creation`
Fired after a course is successfully created.

```php
do_action('mpcc_after_course_creation', $courseId, $courseData, $sessionId);
```

#### `mpcc_conversation_started`
Fired when a new AI conversation is initiated.

```php
do_action('mpcc_conversation_started', $sessionId, $userId);
```

### Filters

#### `mpcc_ai_prompt`
Modify the prompt sent to the AI service.

```php
add_filter('mpcc_ai_prompt', function($prompt, $contentType, $context) {
    if ($contentType === 'course_structure') {
        $prompt .= "\nInclude industry-specific examples.";
    }
    return $prompt;
}, 10, 3);
```

#### `mpcc_course_validation_rules`
Add custom validation rules for course data.

```php
add_filter('mpcc_course_validation_rules', function($rules) {
    $rules['minimum_lessons'] = function($courseData) {
        $lessonCount = 0;
        foreach ($courseData['sections'] ?? [] as $section) {
            $lessonCount += count($section['lessons'] ?? []);
        }
        return $lessonCount >= 5 ? true : 'Course must have at least 5 lessons';
    };
    return $rules;
});
```

#### `mpcc_lesson_content_template`
Customize the default lesson content template.

```php
add_filter('mpcc_lesson_content_template', function($template, $lessonData) {
    return $template . "\n\n<!-- Custom footer content -->";
}, 10, 2);
```

## Custom Service Implementation

### Creating a Custom LLM Service

```php
use MemberPressCoursesCopilot\Interfaces\ILLMService;

class CustomLLMService implements ILLMService {
    public function generateContent(string $prompt, string $contentType = 'general', array $options = []): array {
        // Your custom implementation
        // Could use a different AI provider, local model, etc.
        return [
            'success' => true,
            'content' => 'Generated content',
            'error' => false
        ];
    }
    
    public function validateApiKey(string $apiKey): bool {
        // Custom validation logic
        return true;
    }
    
    public function getModelCapabilities(string $model): array {
        return ['max_tokens' => 4000];
    }
}

// Register your service
add_action('mpcc_register_services', function($container) {
    $container->register(ILLMService::class, CustomLLMService::class);
});
```

### Adding Custom Content Types

```php
// Register a new content type for AI generation
add_filter('mpcc_content_types', function($types) {
    $types['quiz'] = [
        'name' => 'Quiz Questions',
        'model' => 'gpt-4',
        'temperature' => 0.3,
        'system_prompt' => 'Generate quiz questions...'
    ];
    return $types;
});
```

## JavaScript Extensions

### Adding Custom Chat Commands

```javascript
// Add a custom command handler
jQuery(document).on('mpcc:chat:init', function(e, chatInterface) {
    chatInterface.addCommand('/quiz', function(args) {
        // Handle quiz generation command
        chatInterface.sendMessage('Generate a quiz for ' + args.join(' '));
    });
});
```

### Customizing the Course Preview

```javascript
// Modify course preview rendering
jQuery(document).on('mpcc:preview:render', function(e, courseData, container) {
    // Add custom elements to the preview
    const customSection = jQuery('<div class="custom-preview-section">');
    customSection.html('Estimated completion time: ' + calculateTime(courseData));
    container.prepend(customSection);
});
```

## Database Extensions

### Adding Custom Tables

```php
class CustomExtensionTable {
    public static function create() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'mpcc_custom_data';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(36) NOT NULL,
            custom_field varchar(255),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY session_id (session_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Hook into plugin activation
register_activation_hook(__FILE__, [CustomExtensionTable::class, 'create']);
```

## UI Customization

### Adding Custom Settings

```php
add_filter('mpcc_settings_fields', function($fields) {
    $fields['custom_section'] = [
        'title' => 'Custom Settings',
        'fields' => [
            'custom_option' => [
                'label' => 'Custom Option',
                'type' => 'text',
                'default' => '',
                'description' => 'Description of custom option'
            ]
        ]
    ];
    return $fields;
});
```

### Custom Admin Pages

```php
add_action('admin_menu', function() {
    add_submenu_page(
        'memberpress-courses',
        'Custom Extension',
        'Custom Extension',
        'manage_options',
        'mpcc-custom',
        'render_custom_page'
    );
});

function render_custom_page() {
    // Render your custom admin page
    ?>
    <div class="wrap">
        <h1>Custom Extension</h1>
        <!-- Your custom UI here -->
    </div>
    <?php
}
```

## Best Practices

### 1. Use Proper Namespacing
Prefix all custom functions, classes, and hooks with a unique identifier.

### 2. Check for Plugin Existence
Always verify the plugin is active before extending:

```php
if (!class_exists('MemberPressCoursesCopilot\Plugin')) {
    return;
}
```

### 3. Use Dependency Injection
When possible, use the plugin's container for dependencies:

```php
$container = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
$llmService = $container->get(ILLMService::class);
```

### 4. Follow WordPress Coding Standards
Maintain consistency with the plugin's coding style.

### 5. Document Your Extensions
Add PHPDoc blocks and inline comments for maintainability.

## Example: Complete Extension

Here's a complete example of adding quiz generation functionality:

```php
<?php
/**
 * MemberPress Courses Copilot - Quiz Extension
 */

namespace MyCourseExtensions;

class QuizExtension {
    public function init() {
        // Add quiz generation to lesson options
        add_action('mpcc_lesson_actions', [$this, 'addQuizButton']);
        
        // Handle quiz generation AJAX
        add_action('wp_ajax_mpcc_generate_quiz', [$this, 'handleQuizGeneration']);
        
        // Add quiz to course structure
        add_filter('mpcc_course_structure', [$this, 'addQuizSupport']);
    }
    
    public function addQuizButton($lessonId) {
        ?>
        <button class="mpcc-generate-quiz" data-lesson-id="<?php echo esc_attr($lessonId); ?>">
            Generate Quiz
        </button>
        <?php
    }
    
    public function handleQuizGeneration() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'mpcc_quiz_nonce')) {
            wp_die('Security check failed');
        }
        
        $lessonId = sanitize_text_field($_POST['lesson_id']);
        $lessonContent = get_post_field('post_content', $lessonId);
        
        // Use the LLM service to generate quiz
        $container = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
        $llmService = $container->get(\MemberPressCoursesCopilot\Interfaces\ILLMService::class);
        
        $prompt = "Generate 5 multiple choice questions based on this lesson content:\n\n" . $lessonContent;
        
        $response = $llmService->generateContent($prompt, 'quiz_generation');
        
        if ($response['success']) {
            // Save quiz as lesson meta
            update_post_meta($lessonId, '_mpcc_quiz_questions', $response['content']);
            
            wp_send_json_success([
                'quiz' => $response['content']
            ]);
        } else {
            wp_send_json_error('Failed to generate quiz');
        }
    }
    
    public function addQuizSupport($structure) {
        // Add quiz capability to course structure
        foreach ($structure['sections'] as &$section) {
            foreach ($section['lessons'] as &$lesson) {
                $lesson['supports_quiz'] = true;
            }
        }
        return $structure;
    }
}

// Initialize the extension
add_action('plugins_loaded', function() {
    if (class_exists('MemberPressCoursesCopilot\Plugin')) {
        $extension = new QuizExtension();
        $extension->init();
    }
});
```

## Resources

- [WordPress Plugin API](https://codex.wordpress.org/Plugin_API)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [MemberPress Developer Docs](https://docs.memberpress.com/)