# MemberPress Courses Copilot - Quiz Implementation Guide

## Overview

This guide provides step-by-step instructions for extending the quiz integration feature, particularly for adding new question types and enhancing functionality while maintaining the KISS, DRY, and YAGNI principles.

## Adding a New Question Type

### Example: Implementing True/False Questions

#### Step 1: Update the AI Service

Edit `/src/MemberPressCoursesCopilot/Services/MpccQuizAIService.php`:

```php
/**
 * Generate true/false questions from content
 * 
 * @param string $content Content to generate questions from
 * @param int $count Number of questions to generate
 * @return array Generated questions
 */
public function generateTrueFalseQuestions(string $content, int $count = 5): array
{
    $this->logger->info('Generating true/false questions', [
        'content_length' => strlen($content),
        'question_count' => $count
    ]);

    $prompt = $this->buildTrueFalsePrompt($content, $count);
    $response = $this->llmService->generateContent($prompt, 'quiz_generation');
    
    if ($response['error']) {
        $this->logger->error('Failed to generate questions', ['error' => $response['message']]);
        return [];
    }

    return $this->parseTrueFalseQuestions($response['content']);
}

private function buildTrueFalsePrompt(string $content, int $count): string
{
    return "Generate {$count} true/false questions based on the following content. 
    
For each question, provide:
1. A statement that is either true or false
2. The correct answer (true or false)
3. Brief explanation

Return ONLY a JSON array with this structure:
[
    {
        \"question\": \"Statement here\",
        \"correct_answer\": \"true\",
        \"explanation\": \"Explanation here\"
    }
]

Content: {$content}";
}
```

#### Step 2: Update the AJAX Controller

Edit `/src/MemberPressCoursesCopilot/Controllers/MpccQuizAjaxController.php`:

```php
// In generate_quiz() method, add support for question type
$questionType = sanitize_text_field($_POST['question_type'] ?? 'multiple_choice');

switch ($questionType) {
    case 'true_false':
        $questions = $this->quizAIService->generateTrueFalseQuestions($content, $count);
        break;
    case 'multiple_choice':
    default:
        $questions = $this->quizAIService->generateMultipleChoiceQuestions($content, $count);
        break;
}
```

#### Step 3: Update the Frontend Modal

Edit `/assets/js/quiz-ai-modal.js`:

```javascript
// Add question type selector to modal HTML
const modalHtml = `
    <!-- Add after lesson selector -->
    <div class="mpcc-form-section">
        <label>Question Type:</label>
        <select id="mpcc-modal-question-type" class="components-select-control__input">
            <option value="multiple_choice">Multiple Choice</option>
            <option value="true_false">True/False</option>
        </select>
    </div>
`;

// Update generateQuestions method
generateQuestions(difficulty = 'medium') {
    const questionType = $('#mpcc-modal-question-type').val();
    
    $.ajax({
        url: mpcc_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'mpcc_generate_quiz',
            lesson_id: lessonId,
            question_type: questionType,
            nonce: mpcc_ajax.nonce,
            options: {
                num_questions: questionCount,
                difficulty: difficulty
            }
        },
        // ... rest of ajax call
    });
}
```

#### Step 4: Update Block Creation Logic

```javascript
// In applyQuestions() method
const blockType = questionData.type === 'true_false' 
    ? 'memberpress-courses/true-false-question'
    : 'memberpress-courses/multiple-choice-question';

const block = wp.blocks.createBlock(blockType, {
    questionId: questionId || 0
});

// Format data appropriately for true/false
if (questionData.type === 'true_false') {
    questionData.options = [
        { value: 'True', isCorrect: question.correct_answer === 'true' },
        { value: 'False', isCorrect: question.correct_answer === 'false' }
    ];
}
```

## Code Organization

### File Structure
```
memberpress-courses-copilot/
├── src/MemberPressCoursesCopilot/
│   ├── Services/
│   │   └── MpccQuizAIService.php (All quiz AI logic)
│   ├── Controllers/
│   │   └── MpccQuizAjaxController.php (AJAX handlers)
│   └── Interfaces/
│       └── IQuizAIService.php (Service interface)
├── assets/
│   ├── js/
│   │   └── quiz-ai-modal.js (Frontend UI)
│   └── css/
│       └── quiz-ai-modal.css (Styling)
└── docs/
    └── (Documentation files)
```

### Naming Conventions

- **PHP Classes**: PascalCase with `Mpcc` prefix
- **Methods**: camelCase for class methods
- **AJAX Actions**: snake_case with `mpcc_` prefix
- **JavaScript Classes**: PascalCase
- **CSS Classes**: BEM notation with `mpcc-` prefix

## Service Layer Patterns

### Dependency Injection

Always use constructor injection:
```php
class MpccQuizAIService {
    private ILLMService $llmService;
    
    public function __construct(ILLMService $llmService) {
        $this->llmService = $llmService;
    }
}
```

### Error Handling

Use consistent error handling:
```php
try {
    $result = $this->someOperation();
} catch (\Exception $e) {
    $this->logger->error('Operation failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    return ApiResponse::error(
        ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL)
    );
}
```

## Frontend Implementation Patterns

### Modal Management

Follow the established pattern:
```javascript
class MPCCQuizAIModal {
    constructor() {
        this.modalOpen = false;
        this.generatedQuestions = [];
        this.init();
    }
    
    openModal() {
        if (this.modalOpen) return;
        // Modal opening logic
    }
    
    closeModal() {
        $('#mpcc-quiz-ai-modal').remove();
        this.modalOpen = false;
    }
}
```

### API Communication

Use jQuery AJAX with proper error handling:
```javascript
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_action_name',
        nonce: mpcc_ajax.nonce,
        // other data
    },
    success: (response) => {
        if (response.success) {
            // Handle success
        } else {
            this.showNotice(response.data?.message || 'Error', 'error');
        }
    },
    error: (xhr, status, error) => {
        this.showNotice(`Error: ${error}`, 'error');
    }
});
```

## Testing New Features

### Unit Testing

Create test file at `/tests/Unit/Services/MpccQuizAIServiceTest.php`:

```php
class MpccQuizAIServiceTest extends TestCase {
    public function test_generates_true_false_questions() {
        $llmService = $this->createMock(ILLMService::class);
        $llmService->expects($this->once())
            ->method('generateContent')
            ->willReturn([
                'error' => false,
                'content' => '[{"question": "Test?", "correct_answer": "true", "explanation": "Test"}]'
            ]);
            
        $service = new MpccQuizAIService($llmService);
        $questions = $service->generateTrueFalseQuestions('Test content', 1);
        
        $this->assertCount(1, $questions);
        $this->assertEquals('true_false', $questions[0]['type']);
    }
}
```

### Manual Testing Checklist

- [ ] New question type appears in modal dropdown
- [ ] Generation produces correct format
- [ ] Blocks created with proper type
- [ ] Questions display correctly in editor
- [ ] Save process persists questions
- [ ] Questions work in frontend quiz

## Security Considerations

### Input Validation

Always validate and sanitize:
```php
$questionType = in_array($_POST['question_type'], ['multiple_choice', 'true_false']) 
    ? $_POST['question_type'] 
    : 'multiple_choice';
```

### Capability Checks

Verify user permissions:
```php
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions', 403);
}
```

## Performance Guidelines

### Optimization Tips

1. **Limit Question Generation**: Cap at reasonable numbers (e.g., max 20)
2. **Implement Loading States**: Show progress during generation
3. **Batch Operations**: Insert all blocks at once
4. **Debounce User Actions**: Prevent rapid-fire requests

### Example Debouncing
```javascript
let generateTimeout;
$('#generate-button').on('click', () => {
    clearTimeout(generateTimeout);
    generateTimeout = setTimeout(() => {
        this.generateQuestions();
    }, 300);
});
```

## Common Pitfalls to Avoid

### 1. Over-Engineering
❌ Don't create separate services for each question type
✅ Keep all quiz AI logic in one service

### 2. Ignoring WordPress Patterns
❌ Don't bypass WordPress security functions
✅ Use wp_verify_nonce(), current_user_can(), etc.

### 3. Complex State Management
❌ Don't implement Redux for simple state
✅ Use class properties and jQuery data

### 4. Premature Optimization
❌ Don't add caching before it's needed
✅ Focus on working implementation first

## Debugging Techniques

### Enable Debug Logging
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('MPCC_DEBUG', true);
```

### Console Logging Strategy
```javascript
// Conditional logging
if (window.MPCC_DEBUG) {
    console.log('MPCC Quiz:', 'Debug message', data);
}
```

### Network Inspection
1. Open browser DevTools
2. Go to Network tab
3. Filter by XHR requests
4. Inspect AJAX payloads and responses

## Best Practices

### 1. Follow KISS Principle
- Start with simplest implementation
- Add complexity only when needed
- Prefer clarity over cleverness

### 2. Maintain DRY Code
- Reuse existing patterns
- Extract common functionality
- Avoid copy-paste programming

### 3. Apply YAGNI Philosophy
- Don't add features "just in case"
- Implement only what's requested
- Keep MVP mindset

### 4. Document as You Code
- Add PHPDoc blocks
- Include inline comments for complex logic
- Update documentation with changes

## Integration Checklist

When adding new features, ensure:

- [ ] Service methods follow existing patterns
- [ ] AJAX endpoints use security checks
- [ ] Frontend follows modal UI pattern
- [ ] CSS matches existing styles
- [ ] Tests cover new functionality
- [ ] Documentation is updated
- [ ] Error handling is comprehensive
- [ ] Logging helps debugging

## Future Enhancement Guidelines

### Planning New Features

1. **Start with User Story**: What does the user need?
2. **Design API First**: Define data structures
3. **Implement Backend**: Service layer first
4. **Add Frontend**: UI follows functionality
5. **Test Thoroughly**: Unit and integration tests
6. **Document Changes**: Update all relevant docs

### Example: Adding Fill-in-the-Blank

1. **User Story**: "As a teacher, I want to create fill-in-the-blank questions"
2. **API Design**: 
   ```json
   {
       "type": "fill_blank",
       "question": "The capital of France is ____.",
       "blanks": ["Paris"],
       "explanation": "Paris is the capital city of France."
   }
   ```
3. **Implementation Steps**:
   - Add generation method to service
   - Update controller for new type
   - Add UI option in modal
   - Create block mapping
   - Test end-to-end

## Maintenance Guidelines

### Regular Tasks

1. **Review Error Logs**: Check for patterns
2. **Update Dependencies**: Keep WordPress/plugins current
3. **Performance Monitoring**: Track generation times
4. **User Feedback**: Gather feature requests
5. **Code Cleanup**: Remove unused code

### Version Management

Follow semantic versioning:
- **Major**: Breaking changes
- **Minor**: New features
- **Patch**: Bug fixes

Update version in:
- Plugin header
- composer.json
- package.json
- Documentation

## Support Resources

### Internal Documentation
- `/docs/ARCHITECTURE.md` - System overview
- `/docs/API.md` - Endpoint reference
- `/docs/TROUBLESHOOTING.md` - Common issues

### External Resources
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Gutenberg Block Development](https://developer.wordpress.org/block-editor/)
- [MemberPress Developer Docs](https://docs.memberpress.com/)