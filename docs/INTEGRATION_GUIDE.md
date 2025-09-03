# MemberPress Integration Guide

## Table of Contents

- [Overview](#overview)
- [MemberPress Core Integration](#memberpress-core-integration)
- [MemberPress Courses Integration](#memberpress-courses-integration)
- [WordPress Hooks and Filters](#wordpress-hooks-and-filters)
- [Custom Post Types Integration](#custom-post-types-integration)
- [User Capabilities Integration](#user-capabilities-integration)
- [Database Integration](#database-integration)
- [Admin Interface Integration](#admin-interface-integration)
- [Frontend Integration](#frontend-integration)
- [Extension Points](#extension-points)

## Overview

The MemberPress Courses Copilot plugin is deeply integrated with MemberPress and MemberPress Courses, extending their functionality with AI-powered course creation capabilities. This guide details all integration points and how to work with them.

### Integration Architecture

```
WordPress Core
├── MemberPress (Membership Management)
├── MemberPress Courses (Course Management)
└── Courses Copilot (AI Enhancement)
    ├── Extends MemberPress Courses UI
    ├── Uses MemberPress Capabilities
    └── Integrates with WordPress Admin
```

## MemberPress Core Integration

### Dependency Detection

The plugin checks for MemberPress availability:

```php
function memberpress_courses_copilot_is_memberpress_active(): bool {
    return defined('MEPR_PLUGIN_NAME') && class_exists('MeprCtrlFactory');
}
```

**Required MemberPress Elements:**
- `MEPR_PLUGIN_NAME` constant
- `MeprCtrlFactory` class
- Minimum version: 1.11.0

### Version Compatibility

```php
public function isMemberPressVersionSupported(): bool {
    if (!defined('MEPR_VERSION')) {
        return false;
    }
    
    return version_compare(MEPR_VERSION, self::MIN_MEMBERPRESS_VERSION, '>=');
}
```

### User Capability Integration

The plugin leverages MemberPress's role and capability system:

```php
// Check if user can edit courses
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions');
}

// Check if user can create courses
if (!current_user_can('publish_posts')) {
    wp_send_json_error('Cannot create courses');
}
```

## MemberPress Courses Integration

### Dependency Detection

```php
function memberpress_courses_copilot_is_courses_active(): bool {
    return (
        is_plugin_active('memberpress-courses/main.php') &&
        defined('memberpress\\courses\\VERSION') &&
        class_exists('memberpress\\courses\\models\\Course') &&
        class_exists('memberpress\\courses\\controllers\\App')
    );
}
```

**Required Courses Elements:**
- `memberpress\\courses\\VERSION` constant
- `memberpress\\courses\\models\\Course` class
- `memberpress\\courses\\controllers\\App` class
- Minimum version: 1.4.0

### Course Model Integration

The plugin works directly with MemberPress Courses models:

```php
// Create course using MemberPress Courses structure
use memberpress\courses\models\Course;
use memberpress\courses\models\Section;
use memberpress\courses\models\Lesson;

public function createCourseWithSections(array $courseData): int {
    // 1. Create course
    $courseId = wp_insert_post([
        'post_title' => $courseData['title'],
        'post_content' => $courseData['description'],
        'post_type' => 'mpcs-course',
        'post_status' => 'draft'
    ]);
    
    // 2. Create sections using MemberPress Courses
    foreach ($courseData['sections'] as $order => $sectionData) {
        $section = new Section();
        $section->course_id = $courseId;
        $section->title = $sectionData['title'];
        $section->order_index = $order;
        $section->store();
        
        // 3. Create lessons within sections
        foreach ($sectionData['lessons'] as $lessonOrder => $lessonData) {
            $lessonId = wp_insert_post([
                'post_title' => $lessonData['title'],
                'post_content' => $lessonData['content'],
                'post_type' => 'mpcs-lesson',
                'post_status' => 'publish'
            ]);
            
            // Link lesson to section
            update_post_meta($lessonId, '_mpcs_course_id', $courseId);
            update_post_meta($lessonId, '_mpcs_lesson_section_id', $section->id);
            update_post_meta($lessonId, '_mpcs_lesson_lesson_order', $lessonOrder);
        }
    }
    
    return $courseId;
}
```

### Quiz Integration

Integration with MemberPress Courses quiz system:

```php
public function createQuizFromLesson(int $lessonId): int {
    $lesson = get_post($lessonId);
    
    // 1. Create quiz post
    $quizId = wp_insert_post([
        'post_title' => sprintf('Quiz: %s', $lesson->post_title),
        'post_type' => 'mpcs-quiz',
        'post_status' => 'draft'
    ]);
    
    // 2. Link quiz to lesson and course structure
    $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
    $sectionId = get_post_meta($lessonId, '_mpcs_lesson_section_id', true);
    $lessonOrder = get_post_meta($lessonId, '_mpcs_lesson_lesson_order', true);
    
    // 3. Set quiz metadata for MemberPress Courses
    update_post_meta($quizId, '_mpcs_lesson_id', $lessonId);
    update_post_meta($quizId, '_mpcs_course_id', $courseId);
    update_post_meta($quizId, '_mpcs_lesson_section_id', $sectionId);
    update_post_meta($quizId, '_mpcs_lesson_lesson_order', intval($lessonOrder) + 1);
    
    return $quizId;
}
```

## WordPress Hooks and Filters

### Plugin Lifecycle Hooks

#### Activation Hook
```php
register_activation_hook(__FILE__, 'memberpress_courses_copilot_activate');

function memberpress_courses_copilot_activate(): void {
    // 1. Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        wp_die('PHP 8.0 or higher required');
    }
    
    // 2. Install database tables
    $database_service = $container->get(DatabaseService::class);
    $database_service->installTables();
    
    // 3. Seed default data
    $database_service->seedDefaultData();
    
    // 4. Flush rewrite rules
    flush_rewrite_rules();
}
```

#### Initialization Hook
```php
add_action('plugins_loaded', 'memberpress_courses_copilot_init', 20);

function memberpress_courses_copilot_init(): void {
    // Check dependencies before initialization
    if (!memberpress_courses_copilot_is_memberpress_active() || 
        !memberpress_courses_copilot_is_courses_active()) {
        add_action('admin_notices', 'memberpress_courses_copilot_missing_dependencies_notice');
        return;
    }
    
    Plugin::instance();
}
```

### WordPress Admin Hooks

#### Admin Menu Integration
```php
add_action('admin_menu', [$this, 'initializeAdmin']);

public function initializeAdmin(): void {
    // Add plugin menu pages
    $admin_menu = $this->container->get(AdminMenu::class);
    $admin_menu->init();
    
    // Add course editor page
    $course_editor = $this->container->get(CourseEditorPage::class);
    $course_editor->addMenuPage();
}
```

#### Metabox Integration
```php
add_action('add_meta_boxes', [$this, 'addAIMetabox']);

public function addAIMetabox(): void {
    $post_types = ['mpcs-course', 'mpcs-lesson'];
    
    foreach ($post_types as $post_type) {
        add_meta_box(
            'mpcc-ai-assistant',
            'AI Course Assistant',
            [$this, 'renderAIMetabox'],
            $post_type,
            'side',
            'high'
        );
    }
}
```

#### Asset Enqueuing
```php
add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

public function enqueueAdminScripts(string $hook_suffix): void {
    // Course editor page assets
    if ($hook_suffix === 'memberpress-courses_page_mpcc-course-editor') {
        wp_enqueue_script('mpcc-course-editor', 
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/course-editor-page.js',
            ['jquery', 'wp-api-fetch'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Localize script with AJAX data
        wp_localize_script('mpcc-course-editor', 'mpcc_editor', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::EDITOR_NONCE),
            'post_id' => get_the_ID(),
            'user_id' => get_current_user_id()
        ]);
    }
    
    // MemberPress Courses listing page
    if ($hook_suffix === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'mpcs-course') {
        wp_enqueue_script('mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/courses-integration.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
    }
}
```

### AJAX Hook Registration

#### Course-Related AJAX Actions
```php
// Course editor endpoints
add_action('wp_ajax_mpcc_chat_message', [$this, 'handleChatMessage']);
add_action('wp_ajax_mpcc_create_course', [$this, 'handleCreateCourse']);
add_action('wp_ajax_mpcc_load_session', [$this, 'handleLoadSession']);
add_action('wp_ajax_mpcc_save_conversation', [$this, 'handleSaveConversation']);

// Course integration endpoints
add_action('wp_ajax_mpcc_load_ai_interface', [$this, 'loadAIInterface']);
add_action('wp_ajax_mpcc_ai_chat', [$this, 'handleAIChat']);
add_action('wp_ajax_mpcc_create_course_with_ai', [$this, 'createCourseWithAI']);
```

#### Quiz-Related AJAX Actions
```php
// Quiz generation
add_action('wp_ajax_mpcc_generate_quiz', [$this, 'generate_quiz']);
add_action('wp_ajax_mpcc_create_quiz_from_lesson', [$this, 'create_quiz_from_lesson']);
add_action('wp_ajax_mpcc_regenerate_question', [$this, 'regenerate_question']);
add_action('wp_ajax_mpcc_validate_quiz', [$this, 'validate_quiz']);

// Quiz utilities
add_action('wp_ajax_mpcc_get_lesson_course', [$this, 'get_lesson_course']);
add_action('wp_ajax_mpcc_get_course_lessons', [$this, 'get_course_lessons']);
```

#### Session Management AJAX Actions
```php
// Session features
add_action('wp_ajax_mpcc_auto_save_session', [$this, 'handleAjaxAutoSave']);
add_action('wp_ajax_mpcc_extend_session', [$this, 'handleSessionExtension']);
add_action('wp_ajax_mpcc_get_sessions', [$this, 'handleGetSessions']);
add_action('wp_ajax_mpcc_delete_session', [$this, 'handleDeleteSession']);
```

### Custom WordPress Filters

#### Course Template Filters
```php
// Allow customization of course templates
$templates = apply_filters('mpcc_course_templates', $default_templates);

// Example usage in theme or plugin
add_filter('mpcc_course_templates', function($templates) {
    $templates['custom_programming'] = [
        'name' => 'Programming Course Template',
        'sections' => [
            ['title' => 'Fundamentals', 'lessons' => ['Introduction', 'Setup']],
            ['title' => 'Advanced Topics', 'lessons' => ['Algorithms', 'Data Structures']],
            ['title' => 'Projects', 'lessons' => ['Project 1', 'Final Project']]
        ]
    ];
    return $templates;
});
```

#### AI Prompt Filters
```php
// Allow customization of AI prompts
$prompt = apply_filters('mpcc_ai_prompt', $base_prompt, $context, $options);

// Example customization
add_filter('mpcc_ai_prompt', function($prompt, $context, $options) {
    if ($context === 'course_creation') {
        $prompt .= "\nPlease ensure the course follows our company's learning methodology.";
    }
    return $prompt;
}, 10, 3);
```

#### Validation Filters
```php
// Custom validation rules
$validation_rules = apply_filters('mpcc_course_validation_rules', $default_rules);

add_filter('mpcc_course_validation_rules', function($rules) {
    $rules['company_standards'] = function($courseData) {
        // Custom validation logic
        return ['valid' => true];
    };
    return $rules;
});
```

### Custom WordPress Actions

#### Course Generation Events
```php
// Fires when course generation starts
do_action('mpcc_course_generation_started', $courseData, $sessionId);

// Fires when course is successfully created
do_action('mpcc_course_generated', $courseId, $courseData, $sessionId);

// Fires when course generation fails
do_action('mpcc_course_generation_failed', $error, $courseData, $sessionId);

// Example listener
add_action('mpcc_course_generated', function($courseId, $courseData, $sessionId) {
    // Send notification email
    $user = wp_get_current_user();
    wp_mail(
        $user->user_email,
        'Course Created Successfully',
        "Your course '{$courseData['title']}' has been created."
    );
}, 10, 3);
```

#### Session Events
```php
// Session lifecycle events
do_action('mpcc_session_created', $session);
do_action('mpcc_session_loaded', $session);
do_action('mpcc_session_saved', $session);
do_action('mpcc_session_deleted', $sessionId);

// Auto-save events
do_action('mpcc_session_auto_saved', $sessionId, $data);
```

#### Quiz Generation Events
```php
// Quiz generation events
do_action('mpcc_quiz_generation_started', $lessonId, $options);
do_action('mpcc_quiz_generated', $quizId, $questions, $lessonId);
do_action('mpcc_quiz_creation_failed', $error, $lessonId);
```

## Custom Post Types Integration

### Working with MemberPress Courses Post Types

#### Course Post Type (`mpcs-course`)

```php
// Create course with proper metadata
public function createCourse(array $courseData): int {
    $courseId = wp_insert_post([
        'post_title' => $courseData['title'],
        'post_content' => $courseData['description'],
        'post_type' => 'mpcs-course',
        'post_status' => 'draft',
        'post_author' => get_current_user_id()
    ]);
    
    // Set course-specific metadata
    update_post_meta($courseId, '_mpcs_course_pricing', $courseData['pricing'] ?? []);
    update_post_meta($courseId, '_mpcs_course_settings', $courseData['settings'] ?? []);
    
    return $courseId;
}
```

#### Lesson Post Type (`mpcs-lesson`)

```php
// Create lesson with proper course association
public function createLesson(array $lessonData, int $courseId, string $sectionId): int {
    $lessonId = wp_insert_post([
        'post_title' => $lessonData['title'],
        'post_content' => $lessonData['content'],
        'post_type' => 'mpcs-lesson',
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);
    
    // Associate with course and section
    update_post_meta($lessonId, '_mpcs_course_id', $courseId);
    update_post_meta($lessonId, '_mpcs_lesson_section_id', $sectionId);
    update_post_meta($lessonId, '_mpcs_lesson_lesson_order', $lessonData['order']);
    
    return $lessonId;
}
```

#### Quiz Post Type (`mpcs-quiz`)

```php
// Create quiz with proper lesson association
public function createQuizForLesson(int $lessonId): int {
    $lesson = get_post($lessonId);
    
    $quizId = wp_insert_post([
        'post_title' => sprintf('Quiz: %s', $lesson->post_title),
        'post_type' => 'mpcs-quiz',
        'post_status' => 'draft',
        'post_author' => get_current_user_id()
    ]);
    
    // Critical: Set proper associations for quiz to appear in course
    $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
    $sectionId = get_post_meta($lessonId, '_mpcs_lesson_section_id', true);
    $lessonOrder = get_post_meta($lessonId, '_mpcs_lesson_lesson_order', true);
    
    update_post_meta($quizId, '_mpcs_lesson_id', $lessonId);
    update_post_meta($quizId, '_mpcs_course_id', $courseId);
    update_post_meta($quizId, '_mpcs_lesson_section_id', $sectionId);
    update_post_meta($quizId, '_mpcs_lesson_lesson_order', intval($lessonOrder) + 1);
    
    return $quizId;
}
```

### Meta Field Integration

#### Required Meta Fields for Course Structure

```php
// Course meta fields
'_mpcs_course_pricing'     // Course pricing configuration
'_mpcs_course_settings'    // Course settings
'_mpcs_course_curriculum'  // Course curriculum data

// Lesson meta fields
'_mpcs_course_id'          // Parent course ID
'_mpcs_lesson_section_id'  // Section identifier
'_mpcs_lesson_lesson_order' // Order within section
'_mpcs_lesson_type'        // Lesson type (lesson, quiz)

// Quiz meta fields
'_mpcs_lesson_id'          // Associated lesson ID
'_mpcs_course_id'          // Parent course ID
'_mpcs_lesson_section_id'  // Section identifier
'_mpcs_lesson_lesson_order' // Order within section
```

## Admin Interface Integration

### Menu Integration

#### Plugin Menu Registration
```php
// Register under MemberPress Courses menu
add_submenu_page(
    'edit.php?post_type=mpcs-course',  // Parent menu
    'AI Course Editor',                 // Page title
    'AI Course Editor',                 // Menu title
    'edit_posts',                      // Capability
    'mpcc-course-editor',              // Menu slug
    [$this, 'renderCoursePage']        // Callback
);
```

#### Settings Page Integration
```php
// Add settings page
add_options_page(
    'MemberPress Courses Copilot Settings',
    'Courses Copilot',
    'manage_options',
    'memberpress-courses-copilot',
    [$this, 'renderSettingsPage']
);
```

### Course Listing Integration

#### "Create with AI" Button Integration

**File:** `Services/CourseIntegrationService.php`

```php
public function init(): void {
    // Add button to courses listing page
    add_action('admin_footer', [$this, 'addCreateWithAIButton']);
    
    // Enqueue assets on courses page
    add_action('admin_enqueue_scripts', [$this, 'enqueueIntegrationAssets']);
}

public function addCreateWithAIButton(): void {
    global $pagenow, $typenow;
    
    // Only on courses listing page
    if ($pagenow === 'edit.php' && $typenow === 'mpcs-course') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Add "Create with AI" button next to "Add New"
            $('.page-title-action').after(
                '<a href="#" class="page-title-action mpcc-create-with-ai">' +
                'Create with AI</a>'
            );
            
            // Handle button click
            $('.mpcc-create-with-ai').on('click', function(e) {
                e.preventDefault();
                openAIModal();
            });
        });
        </script>
        <?php
    }
}
```

### Post Editor Integration

#### AI Assistant Metabox

```php
public function renderAIMetabox($post): void {
    // Include nonce for security
    NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_nonce');
    
    // Render AI chat interface
    ?>
    <div id="mpcc-ai-assistant">
        <div class="mpcc-chat-container">
            <div class="mpcc-chat-messages" id="mpcc-chat-messages"></div>
            <div class="mpcc-chat-input">
                <textarea 
                    id="mpcc-chat-input" 
                    placeholder="Ask the AI assistant for help with this <?= $post->post_type === 'mpcs-course' ? 'course' : 'lesson' ?>..."
                    rows="3"
                ></textarea>
                <button type="button" id="mpcc-send-message" class="button button-primary">
                    Send
                </button>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Initialize AI assistant
        new MPCCAIAssistant({
            postId: <?= $post->ID ?>,
            postType: '<?= $post->post_type ?>',
            nonce: '<?= NonceConstants::create(NonceConstants::AI_ASSISTANT) ?>'
        });
    });
    </script>
    <?php
}
```

## Database Integration

### Custom Table Integration with WordPress

#### Table Creation with WordPress Standards

```php
public function installTables(): bool {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Conversations table
    $conversations_table = $wpdb->prefix . 'mpcc_conversations';
    $sql = "CREATE TABLE $conversations_table (
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
        KEY updated_at (updated_at),
        CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users}(ID) ON DELETE CASCADE
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    return $this->verifyTableExists($conversations_table);
}
```

#### Database Queries with WordPress Methods

```php
// Use WordPress database abstraction
public function getUserSessions(int $userId, int $limit = 10): array {
    global $wpdb;
    
    $table = $wpdb->prefix . 'mpcc_conversations';
    
    $sql = $wpdb->prepare(
        "SELECT session_id, title, created_at, updated_at,
                JSON_LENGTH(messages) as message_count
         FROM $table 
         WHERE user_id = %d 
         ORDER BY updated_at DESC 
         LIMIT %d",
        $userId,
        $limit
    );
    
    $results = $wpdb->get_results($sql, ARRAY_A);
    
    if ($wpdb->last_error) {
        $this->logger->error('Database query error', [
            'error' => $wpdb->last_error,
            'query' => $sql
        ]);
        return [];
    }
    
    return $results;
}
```

## Frontend Integration

### JavaScript Integration Patterns

#### WordPress Admin JavaScript Integration

```javascript
// Integration with WordPress admin
(function($) {
    'use strict';
    
    // Wait for WordPress admin to be ready
    $(document).ready(function() {
        // Initialize on specific admin pages
        if (window.pagenow === 'mpcs-course' || window.pagenow === 'mpcs-lesson') {
            initializeAIAssistant();
        }
        
        if (window.pagenow === 'edit-mpcs-course') {
            initializeCoursesIntegration();
        }
    });
    
    function initializeAIAssistant() {
        // AI assistant for post editors
        const assistant = new MPCCAIAssistant({
            postId: window.mpcc_ai_assistant?.post_id || 0,
            postType: window.mpcc_ai_assistant?.post_type || 'mpcs-course',
            nonce: window.mpcc_ai_assistant?.nonce
        });
        
        assistant.init();
    }
    
    function initializeCoursesIntegration() {
        // "Create with AI" button integration
        const integration = new MPCCCoursesIntegration({
            nonce: window.mpcc_courses_integration?.nonce
        });
        
        integration.init();
    }
    
})(jQuery);
```

#### WordPress Block Editor Integration

```javascript
// Integration with Gutenberg block editor
const { registerPlugin } = wp.plugins;
const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;

const MPCCAIPlugin = () => {
    return (
        <>
            <PluginSidebarMoreMenuItem target="mpcc-ai-sidebar">
                AI Assistant
            </PluginSidebarMoreMenuItem>
            <PluginSidebar name="mpcc-ai-sidebar" title="AI Course Assistant">
                <div id="mpcc-ai-assistant-gutenberg">
                    {/* AI chat interface */}
                </div>
            </PluginSidebar>
        </>
    );
};

registerPlugin('memberpress-courses-copilot', {
    render: MPCCAIPlugin
});
```

## Extension Points

### Adding Custom AI Providers

#### 1. Implement ILLMService Interface

```php
class CustomAIProvider implements ILLMService {
    public function sendMessage(string $message, array $conversationHistory = []): array {
        // Custom AI provider implementation
        $response = $this->callCustomAPI($message, $conversationHistory);
        
        return [
            'message' => $response['content'],
            'usage' => $response['usage'],
            'model' => 'custom-model-v1'
        ];
    }
    
    public function generateCourse(array $courseData): array {
        // Custom course generation logic
        return $this->generateWithCustomPrompts($courseData);
    }
}
```

#### 2. Register Custom Provider

```php
// In plugin or theme
add_action('mpcc_register_ai_providers', function($container) {
    $container->bind(ILLMService::class, CustomAIProvider::class);
});
```

### Custom Course Templates

```php
// Add custom templates
add_filter('mpcc_course_templates', function($templates) {
    $templates['business_course'] = [
        'name' => 'Business Course Template',
        'description' => 'Template for business and entrepreneurship courses',
        'sections' => [
            [
                'title' => 'Business Fundamentals',
                'lessons' => [
                    'Introduction to Business',
                    'Market Research',
                    'Business Planning'
                ]
            ],
            [
                'title' => 'Operations',
                'lessons' => [
                    'Operations Management',
                    'Supply Chain',
                    'Quality Control'
                ]
            ],
            [
                'title' => 'Growth Strategies',
                'lessons' => [
                    'Marketing Strategies',
                    'Sales Techniques',
                    'Scaling Your Business'
                ]
            ]
        ],
        'metadata' => [
            'difficulty' => 'intermediate',
            'duration' => '8 weeks',
            'prerequisites' => ['Basic business knowledge']
        ]
    ];
    
    return $templates;
});
```

### Custom Validation Rules

```php
// Add industry-specific validation
add_filter('mpcc_course_validation_rules', function($rules) {
    $rules['healthcare_compliance'] = function($courseData) {
        // Check for required healthcare compliance elements
        $required_sections = ['Safety Protocols', 'Regulatory Compliance'];
        $missing = [];
        
        foreach ($required_sections as $required) {
            $found = false;
            foreach ($courseData['sections'] as $section) {
                if (stripos($section['title'], $required) !== false) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $missing[] = $required;
            }
        }
        
        if (!empty($missing)) {
            return [
                'valid' => false,
                'errors' => ["Missing required sections: " . implode(', ', $missing)]
            ];
        }
        
        return ['valid' => true];
    };
    
    return $rules;
});
```

### Custom Question Types for Quizzes

```php
// Add custom question types
add_filter('mpcc_supported_question_types', function($types) {
    $types['scenario_based'] = [
        'name' => 'Scenario-Based Question',
        'description' => 'Questions based on real-world scenarios',
        'ai_supported' => true,
        'validation_rules' => [
            'scenario' => 'required',
            'question' => 'required',
            'options' => 'required|array|min:2',
            'correct_answer' => 'required'
        ]
    ];
    
    return $types;
});

// Handle custom question generation
add_filter('mpcc_quiz_prompt', function($prompt, $questionType, $content) {
    if ($questionType === 'scenario_based') {
        $prompt .= "\nGenerate scenario-based questions that present real-world situations ";
        $prompt .= "relevant to the content. Each question should include a brief scenario ";
        $prompt .= "followed by multiple choice options.";
    }
    
    return $prompt;
}, 10, 3);
```

### Integration with Other Plugins

#### Learning Management System Integration

```php
// Hook into LMS events
add_action('learndash_course_completed', function($data) {
    // Suggest follow-up courses via AI
    $suggestions = getSuggestedCourses($data['course'], $data['user']);
    // ... implementation
});

// Integration with WooCommerce
add_action('woocommerce_order_status_completed', function($orderId) {
    $order = wc_get_order($orderId);
    // Check if order contains courses
    foreach ($order->get_items() as $item) {
        if ($item->get_product()->get_type() === 'course') {
            // Trigger course access via AI recommendations
        }
    }
});
```

#### Analytics Integration

```php
// Google Analytics integration
add_action('mpcc_course_generated', function($courseId, $courseData) {
    // Track course generation
    if (function_exists('gtag')) {
        gtag('event', 'course_generated', [
            'course_id' => $courseId,
            'sections' => count($courseData['sections']),
            'lessons' => array_sum(array_map(function($s) { 
                return count($s['lessons']); 
            }, $courseData['sections']))
        ]);
    }
});
```

### Theming and Customization

#### Custom CSS Integration

```php
// Allow theme customization
add_action('admin_head', function() {
    if (get_current_screen()->id === 'memberpress-courses_page_mpcc-course-editor') {
        ?>
        <style>
        .mpcc-chat-container {
            /* Theme can override default styles */
            border-color: <?= get_theme_mod('primary_color', '#0073aa') ?>;
        }
        </style>
        <?php
    }
});
```

#### Template Override System

```php
// Allow theme to override templates
public function getTemplatePath(string $template): string {
    // Check theme directory first
    $theme_template = get_stylesheet_directory() . '/memberpress-courses-copilot/' . $template;
    if (file_exists($theme_template)) {
        return $theme_template;
    }
    
    // Fallback to plugin template
    return MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/' . $template;
}
```

This integration guide provides comprehensive information about how the MemberPress Courses Copilot plugin integrates with WordPress, MemberPress, and other systems, along with extension points for customization.