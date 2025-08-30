# MemberPress Courses Copilot - Quiz Integration Plan (REVISED v3)

## Overview
Simple AI-powered quiz generation that extends MemberPress Course Quizzes plugin following KISS, DRY, and YAGNI principles, matching existing UI patterns.

## Phase 1 Status: ✅ COMPLETED (08/29/2025)

### What Was Accomplished
- ✅ Multiple-choice question generation from lesson/course content
- ✅ Modal UI matching course/lesson AI pattern
- ✅ Direct integration with Gutenberg quiz editor
- ✅ Question preview before applying
- ✅ Secure AJAX implementation with proper nonce/capability checks
- ✅ Comprehensive error handling and fallback strategies
- ✅ Full documentation suite created
- ✅ Under 300 lines of core implementation code (as planned!)

### UX Enhancements Added
- ✅ Auto-detection of lesson context (multiple methods)
- ✅ Visual feedback for auto-detected lessons
- ✅ "Create Quiz" button on lesson editor
- ✅ Auto-opening modal when coming from lesson
- ✅ One-click workflow from lesson to AI quiz generation
- ✅ Dynamic monitoring for lesson selection changes

### Lessons Learned
1. **Block Creation Complexity**: MemberPress quiz blocks require proper store integration before creation
2. **Save Process**: Questions are saved when the quiz post is updated, not immediately
3. **ID Reservation**: The quiz plugin uses a sophisticated ID reservation system
4. **Error Recovery**: Multiple fallback strategies were needed for robustness
5. **User Feedback**: Clear messaging about save requirements is essential

## Core Principle: SIMPLICITY
- Start with multiple-choice questions only
- One service handles all quiz AI operations
- Reuse existing quiz database - no new tables needed
- Direct integration with existing quiz editor
- No complex patterns or analytics
- Add other question types only when users request them

## Implementation Approach (TDD)

### Phase 1: Write Tests First (Week 1)
```php
// tests/unit/Services/MpccQuizAIServiceTest.php
class MpccQuizAIServiceTest extends TestCase {
    public function test_generates_multiple_choice_questions() {
        $service = new MpccQuizAIService();
        $lesson_content = "The water cycle has three main stages...";
        $questions = $service->generateMultipleChoiceQuestions($lesson_content, 5);
        
        $this->assertCount(5, $questions);
        $this->assertEquals('multiple-choice', $questions[0]['type']);
        $this->assertArrayHasKey('text', $questions[0]);
        $this->assertArrayHasKey('options', $questions[0]);
        $this->assertCount(4, $questions[0]['options']);
        $this->assertArrayHasKey('answer', $questions[0]);
    }
    
    public function test_handles_api_errors_gracefully() {
        // Test error handling
    }
}
```

### Phase 2: Single Service Implementation (Week 2)
```php
// src/MemberPressCoursesCopilot/Services/MpccQuizAIService.php
class MpccQuizAIService implements IQuizAIService {
    private $llmService;
    
    public function __construct() {
        $this->llmService = new LLMService(); // Simple instantiation
    }
    
    // Start with ONLY multiple-choice
    public function generateMultipleChoiceQuestions($content, $count = 10) {
        $prompt = $this->buildMultipleChoicePrompt($content, $count);
        $response = $this->llmService->generateContent($prompt, 'quiz_generation');
        return $this->parseMultipleChoiceQuestions($response);
    }
    
    private function buildMultipleChoicePrompt($content, $count) {
        return "Generate {$count} multiple-choice questions from the following content. 
                Each question should have exactly 4 options (A, B, C, D) with one correct answer.
                Format as JSON array with structure: 
                {\"text\": \"question\", \"options\": [\"A\", \"B\", \"C\", \"D\"], \"answer\": \"A\"}
                
                Content: {$content}";
    }
    
    // Future method - implement ONLY when users request it
    public function generateQuestions($content, $count = 10, $options = []) {
        // For now, just redirect to multiple-choice
        return $this->generateMultipleChoiceQuestions($content, $count);
    }
}
```

### Phase 3: Simple AJAX Integration (Week 3)
```php
// src/MemberPressCoursesCopilot/Controllers/MpccQuizAjaxController.php
class MpccQuizAjaxController extends MpccBaseAjaxController {
    
    public function load_hooks() {
        add_action('wp_ajax_mpcc_generate_quiz', [$this, 'generate_quiz']);
    }
    
    public function generate_quiz() {
        check_ajax_referer('mpcc_quiz_nonce', 'nonce');
        
        if (!current_user_can('edit_courses')) {
            wp_send_json_error(__('Insufficient permissions', 'memberpress-courses-copilot'));
        }
        
        $lesson_id = intval($_POST['lesson_id']);
        $lesson = get_post($lesson_id);
        
        $service = new MpccQuizAIService();
        $questions = $service->generateMultipleChoiceQuestions($lesson->post_content);
        
        wp_send_json_success(['questions' => $questions]);
    }
}
```

### Phase 4: UI Integration Matching Course/Lesson Pattern (Week 4)

#### Key Discovery: Quiz Editor Uses Gutenberg Blocks
The quiz editor uses Gutenberg blocks for questions:
- `wp:memberpress-courses/multiple-choice-question`
- `wp:memberpress-courses/true-false-question`
- `wp:memberpress-courses/fill-blank-question`

#### UI Pattern: Modal Like Courses/Lessons
```javascript
// assets/js/quiz-ai-modal.js
// Match the existing "Generate with AI" button style and modal pattern
$('.editor-header__settings').prepend(
    '<button class="is-primary" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">' +
    'Generate with AI</button>'
);

// Modal interface matching course/lesson AI pattern
function openQuizAIModal() {
    // Full-screen modal with:
    // - Lesson selector
    // - Question count
    // - Quick action buttons
    // - Apply/Cancel options
}

// Insert questions as Gutenberg blocks
function applyQuestions(questions) {
    questions.forEach((question) => {
        const block = wp.blocks.createBlock('memberpress-courses/multiple-choice-question', {
            question: question.text,
            answers: question.options.map((opt, i) => ({
                answer: opt,
                isCorrect: i === question.answer_index
            }))
        });
        wp.data.dispatch('core/block-editor').insertBlocks([block]);
    });
}
```

## What We're NOT Building (YAGNI)

### ❌ DON'T Create:
- Separate generator classes for each question type
- Quiz template models with complex properties
- Pattern learning or embeddings
- Analytics services
- Quality scoring systems
- A/B testing infrastructure
- New database tables
- Complex UI modals with 20 options
- Support for all 9 question types initially

### ✅ DO Instead:
- Start with multiple-choice questions only
- One simple service method
- Direct integration with existing quiz editor
- Use existing quiz database structure
- One "Generate with AI" button
- Add other question types only when users request them

## Database Approach (KISS)

### NO New Tables
Use existing MemberPress quiz tables as-is:
- `wp_mpcs_questions` - Store generated questions
- `wp_mpcs_attempts` - Works normally
- `wp_mpcs_answers` - Works normally

### Simple Metadata
If we need to track AI generation, use post meta:
```php
update_post_meta($quiz_id, '_mpcc_ai_generated', true);
update_post_meta($quiz_id, '_mpcc_generation_date', current_time('mysql'));
```

## Following Caseproof Standards

### Script Enqueuing
```php
// Properly enqueue scripts with version and in_footer
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook !== 'post.php' || get_post_type() !== 'mpcs-quiz') {
        return;
    }
    
    wp_enqueue_script(
        'mpcc-quiz-ai',
        plugin_dir_url(__FILE__) . 'assets/js/quiz-ai-integration.js',
        ['jquery'],
        '1.0.0', // version
        true     // in_footer
    );
    
    wp_localize_script('mpcc-quiz-ai', 'mpcc_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('mpcc_quiz_nonce'),
        'strings' => [
            'generate_button' => __('Generate with AI', 'memberpress-courses-copilot'),
            'generating' => __('Generating questions...', 'memberpress-courses-copilot'),
            'error' => __('Error generating questions', 'memberpress-courses-copilot')
        ]
    ]);
});
```

### Proper Class Structure
```php
class MpccQuizAIService extends MpccBaseService {
    
    public function load_hooks() {
        // Centralized hook management
        add_filter('mpcs_quiz_editor_buttons', [$this, 'add_ai_button']);
    }
    
    public function add_ai_button($buttons) {
        $buttons[] = [
            'id' => 'mpcc-generate-quiz',
            'label' => __('Generate with AI', 'memberpress-courses-copilot'),
            'icon' => 'dashicons-admin-generic'
        ];
        return $buttons;
    }
}
```

### Proper File Organization
```
src/MemberPressCoursesCopilot/
├── Services/
│   └── MpccQuizAIService.php (ONE service for ALL quiz AI)
├── Controllers/
│   └── MpccQuizAjaxController.php (Simple AJAX endpoints)
└── Interfaces/
    └── IQuizAIService.php (Already exists)

assets/
├── js/
│   └── quiz-ai-integration.js (Minimal UI code)
└── css/
    └── quiz-ai.css (Simple button styling)
```

## Testing Strategy (TDD)

### 1. Unit Tests (Write First!)
```php
// Test the service in isolation
public function test_builds_correct_prompt() { }
public function test_parses_ai_response() { }
public function test_handles_api_errors() { }
```

### 2. Integration Tests
```php
// Test with real WordPress data
public function test_generates_quiz_from_real_lesson() { }
public function test_saves_questions_to_database() { }
```

### 3. Manual Testing Checklist
- [ ] Button appears in quiz editor
- [ ] Clicking button generates questions
- [ ] Generated questions display correctly
- [ ] Questions save properly
- [ ] Quiz works normally after generation

## Timeline (2 Weeks for MVP) ✅ COMPLETED IN 1 DAY!

### Actual Implementation Timeline (08/29/2025)
- **Morning**: Implemented MpccQuizAIService and AJAX controller
- **Afternoon**: Created modal UI and block integration
- **Evening**: Testing, bug fixes, and documentation

### Original Timeline (For Reference)
~~Week 1: Multiple-Choice MVP~~
- ~~Day 1-2: Write tests for multiple-choice generation~~ 
- ~~Day 3-4: Implement MpccQuizAIService (multiple-choice only)~~
- ~~Day 5: AJAX controller and basic UI button~~

~~Week 2: Polish & Ship~~
- ~~Day 1-2: Integration with quiz editor~~
- ~~Day 3: Error handling and loading states~~
- ~~Day 4: Testing with real content~~
- ~~Day 5: Documentation and deployment~~

**Result**: Compressed 2-week timeline into 1 day through focused implementation and reuse of existing patterns!

### Future Iterations (Only if requested)
- True/False questions (1-2 days)
- Short answer questions (1-2 days)
- Other question types (as needed)

## Success Metrics (SIMPLE)
- Multiple-choice quiz generation works in < 5 seconds
- Generated questions have correct format (4 options, 1 answer)
- Users can edit generated questions before saving
- No new database tables needed
- < 300 lines of new code for MVP

## Security Considerations
- Verify user capabilities using `current_user_can()`
- Sanitize all inputs using WordPress sanitization functions
- Escape all outputs per Caseproof standards (6.1):
  ```php
  // When displaying generated questions
  echo esc_html($question['text']);
  echo wp_kses_post($question['explanation']); // If allowing some HTML
  ```
- Use WordPress nonces with `check_ajax_referer()`
- When saving to database, use `$wpdb->prepare()` for all queries

## Remember: KISS, DRY, YAGNI
This plan starts with multiple-choice questions only, keeping the initial implementation under 300 lines of code. Other question types will be added only when users specifically request them, following the principle of building only what's needed.