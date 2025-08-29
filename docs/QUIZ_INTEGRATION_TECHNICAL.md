# MemberPress Courses Copilot - Quiz Integration Technical Documentation

## Overview

The Quiz Integration feature enables AI-powered generation of quiz questions directly within the MemberPress Course Quizzes editor. This document details the technical architecture, implementation approach, and integration points.

## Architecture

### System Components

```
┌─────────────────────────────────────────────────────────────┐
│                     Quiz Editor (Gutenberg)                  │
├─────────────────────────────────────────────────────────────┤
│                  Quiz AI Modal (Frontend)                    │
├─────────────────────────────────────────────────────────────┤
│                    AJAX Controller Layer                     │
├─────────────────────────────────────────────────────────────┤
│                    Quiz AI Service Layer                     │
├─────────────────────────────────────────────────────────────┤
│                      LLM Service (AI)                        │
├─────────────────────────────────────────────────────────────┤
│              MemberPress Quiz Plugin Database                │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

1. **User Interaction**: User clicks "Generate with AI" button in quiz editor
2. **Modal Display**: JavaScript modal opens with lesson selector and options
3. **Content Retrieval**: System fetches lesson content from WordPress database
4. **AI Generation**: Content sent to LLM service for question generation
5. **Response Processing**: AI response parsed into structured question format
6. **Block Creation**: Questions converted to Gutenberg blocks
7. **Store Integration**: Question data added to `memberpress/course/question` store
8. **Save Process**: Questions persisted when quiz post is saved

## Integration with MemberPress Course Quizzes

### Key Integration Points

1. **Block Types**: Uses existing MemberPress quiz block types
   - `memberpress-courses/multiple-choice-question`
   - Future: `memberpress-courses/true-false-question`
   - Future: `memberpress-courses/short-answer-question`

2. **Data Store**: Integrates with `memberpress/course/question` Redux-like store
   - `addPlaceholder()`: Adds temporary question data
   - `getNextQuestionId()`: Reserves database question IDs
   - `saveQuestions()`: Persists questions on post save

3. **Database Tables**: Uses existing quiz plugin tables
   - `wp_mpcs_questions`: Stores question data
   - No new tables required (following KISS principle)

## Implementation Details

### Service Layer

#### MpccQuizAIService
```php
class MpccQuizAIService implements IQuizAIService {
    private ILLMService $llmService;
    
    public function generateMultipleChoiceQuestions(string $content, int $count): array
    {
        // 1. Build AI prompt with specific format requirements
        // 2. Send to LLM service
        // 3. Parse JSON response
        // 4. Return structured question array
    }
}
```

Key features:
- Dependency injection of LLM service
- Structured prompt engineering for consistent output
- JSON parsing with error handling
- Support for multiple question types (extensible)

### AJAX Controller

#### MpccQuizAjaxController
```php
class MpccQuizAjaxController {
    public function generate_quiz(): void
    {
        // 1. Security verification (nonce + capabilities)
        // 2. Input sanitization
        // 3. Content retrieval (lesson/course)
        // 4. AI generation via service
        // 5. Response formatting
    }
}
```

Security measures:
- Nonce verification using `NonceConstants::QUIZ_AI`
- Capability check: `current_user_can('edit_posts')`
- Comprehensive input sanitization
- Error handling with ApiResponse class

### Frontend Integration

#### Quiz AI Modal (quiz-ai-modal.js)
```javascript
class MPCCQuizAIModal {
    detectLessonContext() {
        // 1. Check URL parameters
        // 2. Check referrer
        // 3. Check form fields
        // 4. Check post meta
        // 5. Check existing quiz meta
        // 6. Return detected lesson ID
    }
    
    async applyQuestions() {
        // 1. Get current quiz ID
        // 2. Process each generated question
        // 3. Add placeholder to store
        // 4. Reserve question ID from API
        // 5. Create Gutenberg block
        // 6. Insert blocks into editor
    }
}
```

Key implementation details:
- Multi-method lesson detection with fallbacks
- Auto-opening modal when coming from lesson
- Real-time monitoring for lesson changes
- Async/await pattern for API calls
- Fallback handling for ID reservation failures
- Visual feedback (save button highlighting)
- Error recovery strategies

## Block Creation Process

### Step-by-Step Flow

1. **Generate Unique Client ID**
   ```javascript
   const clientId = wp.blocks.createBlock('memberpress-courses/multiple-choice-question', {}).clientId;
   ```

2. **Add Placeholder to Store**
   ```javascript
   dispatch.addPlaceholder(clientId, {
       question: "What is...",
       type: 'multiple-choice',
       options: [{value: "Option A", isCorrect: true}, ...],
       feedback: "Explanation..."
   });
   ```

3. **Reserve Database ID** (Optional)
   ```javascript
   const result = await dispatch.getNextQuestionId(quizId, clientId);
   const questionId = result.id;
   ```

4. **Create Block with ID**
   ```javascript
   const block = wp.blocks.createBlock('memberpress-courses/multiple-choice-question', {
       questionId: questionId || 0  // 0 triggers placeholder creation
   });
   ```

5. **Insert into Editor**
   ```javascript
   wp.data.dispatch('core/block-editor').insertBlocks([block]);
   ```

## API Endpoints

### AJAX Endpoint: `mpcc_generate_quiz`

**Request:**
```javascript
{
    action: 'mpcc_generate_quiz',
    lesson_id: 123,
    nonce: 'abc123...',
    options: {
        num_questions: 10,
        difficulty: 'medium',
        custom_prompt: ''
    }
}
```

**Response:**
```javascript
{
    success: true,
    data: {
        questions: [
            {
                type: 'multiple_choice',
                question: 'What is the capital of France?',
                options: {
                    A: 'London',
                    B: 'Paris',
                    C: 'Berlin',
                    D: 'Madrid'
                },
                correct_answer: 'B',
                explanation: 'Paris is the capital city of France.'
            }
        ],
        total: 10,
        type: 'multiple-choice'
    }
}
```

## UI/UX Enhancements

### Auto-Detection System

The quiz generator implements intelligent lesson context detection:

1. **Detection Methods** (in priority order):
   - URL parameters (`lesson_id`, `lesson`, `from_lesson`)
   - Document referrer (when navigating from lesson)
   - Form fields (`_mpcs_lesson_id`, etc.)
   - Post meta fields
   - Existing quiz associations

2. **Visual Feedback**:
   - Green indicator box with checkmark icon
   - Contextual message: "Auto-detected from [source]"
   - Subtle highlight animation on form field
   - Real-time updates when lesson selection changes

3. **Auto-Opening Behavior**:
   - Modal opens automatically when `auto_open=true` in URL
   - Shows loading notification before opening
   - Removes parameter after opening to prevent re-triggers
   - 1-second delay ensures all elements are loaded

4. **Lesson-to-Quiz Workflow**:
   - "Create Quiz" button added to lesson editor
   - Creates quiz with pre-filled title: "Quiz: [Lesson Title]"
   - Navigates to quiz editor with lesson context
   - Modal opens automatically for immediate question generation

## Error Handling

### Error Recovery Strategies

1. **AI Generation Failure**
   - Fallback to manual question creation
   - Clear error messaging to user
   - Retry option available

2. **Block Creation Failure**
   - Create blocks with questionId: 0
   - Let WordPress save process handle ID assignment
   - User notification about save requirement

3. **Content Retrieval Failure**
   - Use lesson title and excerpt as fallback
   - Retrieve parent course content if available
   - Clear error message if no content found

## Security Implementation

### Security Layers

1. **Authentication**: WordPress user must be logged in
2. **Authorization**: `edit_posts` capability required
3. **Nonce Verification**: CSRF protection via `NonceConstants::QUIZ_AI`
4. **Input Sanitization**: All inputs sanitized before processing
5. **Output Escaping**: AI responses sanitized before display

### Code Example
```php
// Security checks in controller
if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI)) {
    ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
    return;
}

if (!current_user_can('edit_posts')) {
    ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
    return;
}
```

## Performance Considerations

### Optimization Strategies

1. **Debounced API Calls**: Prevent multiple simultaneous requests
2. **Loading States**: Visual feedback during generation
3. **Batch Block Creation**: Insert all blocks at once
4. **Client-side Validation**: Reduce server requests

### Caching

Currently no caching implemented (following YAGNI principle). Future considerations:
- Cache generated questions per lesson
- Store common question patterns
- Implement smart regeneration

## Extensibility

### Adding New Question Types

1. **Update AI Service**
   ```php
   public function generateTrueFalseQuestions(string $content, int $count): array {
       // Implement generation logic
   }
   ```

2. **Update Frontend Modal**
   ```javascript
   // Add new question type option
   const questionTypes = ['multiple-choice', 'true-false', 'short-answer'];
   ```

3. **Update Block Creation**
   ```javascript
   const blockType = `memberpress-courses/${questionType}-question`;
   const block = wp.blocks.createBlock(blockType, attributes);
   ```

## Testing Approach

### Unit Tests
- Service layer generation logic
- JSON parsing edge cases
- Security validation

### Integration Tests
- AJAX endpoint functionality
- Block creation process
- Store integration

### Manual Testing
- Button appears in quiz editor
- Modal opens and functions
- Questions generate correctly
- Blocks insert properly
- Save process works

## Known Limitations

1. **Question Types**: Currently only multiple-choice (MVP approach)
2. **Bulk Operations**: No bulk question management yet
3. **Question Editing**: Limited in-modal editing capabilities
4. **Import/Export**: No question bank functionality

## Future Enhancements

### Phase 2: Additional Question Types
- True/False questions
- Short answer questions
- Fill in the blank
- Matching questions

### Phase 3: Advanced Features
- Question difficulty settings
- Topic-based generation
- Question bank management
- Analytics integration

### Phase 4: Enterprise Features
- Custom question templates
- Bulk import/export
- API access for third-party tools
- Advanced scoring options

## Debugging Guide

### Common Issues

1. **"Generate with AI" Button Missing**
   - Check: Quiz editor page detection
   - Check: Script enqueuing
   - Check: DOM ready timing

2. **Questions Not Appearing**
   - Check: Block creation console errors
   - Check: Store integration
   - Check: Save process

3. **Empty Blocks Created**
   - Check: Question ID reservation
   - Check: Placeholder data
   - Check: Block attributes

### Debug Logging

Enable debug logging:
```php
error_log('MPCC Quiz: Debug message here');
```

Check logs at:
- WordPress debug.log
- Browser console
- Network tab for AJAX calls

## Code Standards

Following Caseproof WordPress Coding Standards:
- Proper nonce verification
- Capability checks
- Input sanitization
- Output escaping
- Error handling
- Documentation

## Dependencies

### Required Plugins
- MemberPress Core
- MemberPress Courses
- MemberPress Course Quizzes

### WordPress Version
- Minimum: WordPress 5.8 (Gutenberg maturity)
- Recommended: WordPress 6.0+

### PHP Version
- Minimum: PHP 7.4
- Recommended: PHP 8.0+