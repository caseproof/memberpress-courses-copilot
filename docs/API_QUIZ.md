# MemberPress Courses Copilot - Quiz API Documentation

## Overview

This document details the AJAX API endpoints for the Quiz AI integration feature.

**Note:** For complete API documentation including all endpoints, detailed examples, and comprehensive error handling, see [API_REFERENCE.md](API_REFERENCE.md). This document focuses specifically on quiz-related endpoints.

## Endpoints

### Generate Quiz Questions

Generates AI-powered quiz questions from lesson or course content.

**Action:** `mpcc_generate_quiz`  
**Method:** POST (AJAX)  
**Capability Required:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce` (via `NonceConstants::QUIZ_AI`)

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `mpcc_generate_quiz` |
| `nonce` | string | Yes | Security nonce |
| `lesson_id` | int | No* | ID of lesson to generate from |
| `course_id` | int | No* | ID of course to generate from |
| `content` | string | No* | Direct content to generate from |
| `options` | array/JSON | No | Generation options |

*At least one of `lesson_id`, `course_id`, or `content` is required.

#### Options Object

```javascript
{
    num_questions: 10,        // Number of questions (1-20)
    difficulty: "medium",     // Difficulty level: easy|medium|hard
    custom_prompt: "",        // Additional instructions
    question_type: "multiple_choice"  // Type of questions (future)
}
```

#### Success Response

```json
{
    "success": true,
    "data": {
        "questions": [
            {
                "type": "multiple_choice",
                "question": "What is the capital of France?",
                "options": {
                    "A": "London",
                    "B": "Paris",
                    "C": "Berlin",
                    "D": "Madrid"
                },
                "correct_answer": "B",
                "explanation": "Paris is the capital city of France."
            }
        ],
        "total": 10,
        "type": "multiple-choice"
    }
}
```

#### Error Responses

##### Invalid Nonce (403)
```json
{
    "success": false,
    "data": {
        "message": "Security check failed",
        "code": "mpcc_invalid_nonce"
    }
}
```

##### Insufficient Permissions (403)
```json
{
    "success": false,
    "data": {
        "message": "Insufficient permissions",
        "code": "mpcc_insufficient_permissions"
    }
}
```

##### Missing Parameters (400)
```json
{
    "success": false,
    "data": {
        "message": "Content, lesson ID, or course ID is required",
        "code": "mpcc_missing_parameter"
    }
}
```

##### No Content Available (400)
```json
{
    "success": false,
    "data": {
        "message": "No content available to generate quiz from",
        "code": "mpcc_missing_parameter"
    }
}
```

##### Generation Failed (500)
```json
{
    "success": false,
    "data": {
        "message": "Failed to generate quiz questions",
        "code": "mpcc_general_error"
    }
}
```

### Create Quiz from Lesson

Creates a new quiz associated with a specific lesson.

**Action:** `mpcc_create_quiz_from_lesson`  
**Method:** POST (AJAX)  
**Capability Required:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `mpcc_create_quiz_from_lesson` |
| `nonce` | string | Yes | Security nonce |
| `lesson_id` | int | Yes | ID of the lesson to create quiz for |

#### Success Response

```json
{
    "success": true,
    "data": {
        "quiz_id": 1970,
        "edit_url": "http://localhost/wp-admin/post.php?post=1970&action=edit&lesson_id=1941&auto_open=true",
        "message": "Quiz created successfully!"
    }
}
```

The `edit_url` includes:
- `lesson_id`: Pre-selects the lesson in the AI generator
- `auto_open=true`: Automatically opens the AI Quiz Generator modal

### Regenerate Question (Future)

Regenerates a single question with new content.

**Action:** `mpcc_regenerate_question`  
**Method:** POST (AJAX)  
**Capability Required:** `edit_courses`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `mpcc_regenerate_question` |
| `nonce` | string | Yes | Security nonce |
| `question` | JSON | Yes | Current question data |
| `content` | string | Yes | Content to regenerate from |
| `options` | JSON | No | Regeneration options |

### Validate Quiz (Future)

Validates quiz structure and content quality.

**Action:** `mpcc_validate_quiz`  
**Method:** POST (AJAX)  
**Capability Required:** `edit_courses`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | Yes | Must be `mpcc_validate_quiz` |
| `nonce` | string | Yes | Security nonce |
| `quiz_data` | JSON | Yes | Quiz data to validate |

## JavaScript Integration

### Basic Usage

```javascript
jQuery.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_generate_quiz',
        lesson_id: 123,
        nonce: mpcc_ajax.nonce,
        options: {
            num_questions: 10,
            difficulty: 'medium'
        }
    },
    success: function(response) {
        if (response.success) {
            const questions = response.data.questions;
            // Process questions
        } else {
            console.error('Error:', response.data.message);
        }
    },
    error: function(xhr, status, error) {
        console.error('Request failed:', error);
    }
});
```

### Using wp.apiFetch

```javascript
wp.apiFetch({
    path: '/mpcc/v1/quiz/generate',
    method: 'POST',
    data: {
        lesson_id: 123,
        options: {
            num_questions: 10
        }
    }
}).then(response => {
    console.log('Questions:', response.questions);
}).catch(error => {
    console.error('Error:', error.message);
});
```

## Security Implementation

### Nonce Verification

All endpoints require valid nonce verification:

```php
use MemberPressCoursesCopilot\Security\NonceConstants;

// Manual verification
$nonce = $_POST['nonce'] ?? '';
if (!wp_verify_nonce($nonce, NonceConstants::QUIZ_AI)) {
    wp_send_json_error('Security check failed', 403);
}
```

### Capability Checks

Users must have appropriate permissions:

```php
if (!current_user_can('edit_posts')) {
    wp_send_json_error('Insufficient permissions', 403);
}
```

### Input Sanitization

All inputs are sanitized before processing:

```php
$lessonId = absint($_POST['lesson_id'] ?? 0);
$content = sanitize_textarea_field($_POST['content'] ?? '');
$options = $this->sanitizeArray($_POST['options'] ?? []);
```

## Error Handling

### Error Codes

| Code | Description |
|------|-------------|
| `mpcc_invalid_nonce` | Security nonce verification failed |
| `mpcc_insufficient_permissions` | User lacks required capabilities |
| `mpcc_missing_parameter` | Required parameter is missing |
| `mpcc_invalid_parameter` | Parameter value is invalid |
| `mpcc_general_error` | General server error |
| `mpcc_api_error` | External API error |

### Error Response Format

```php
use MemberPressCoursesCopilot\Utilities\ApiResponse;

// Send error response
ApiResponse::errorMessage(
    'Error message here',
    ApiResponse::ERROR_INVALID_PARAMETER,
    400
);
```

## Rate Limiting

Currently no rate limiting is implemented. Future considerations:
- Limit requests per user per minute
- Implement cooldown between generations
- Track usage for analytics

## Debugging

### Enable Debug Mode

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Debug Logs

```bash
tail -f wp-content/debug.log | grep "MPCC Quiz"
```

### Common Debug Points

1. Nonce verification
2. Content retrieval
3. AI service calls
4. Response formatting

## Examples

### Create Quiz from Lesson

```javascript
// Create a new quiz from lesson edit page
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_create_quiz_from_lesson',
        lesson_id: 1941,
        nonce: mpcc_ajax.nonce
    },
    success: function(response) {
        if (response.success) {
            // Redirect to quiz editor with AI modal auto-opening
            window.location.href = response.data.edit_url;
        }
    }
});
```

### Generate from Lesson

```javascript
// Generate 5 easy questions from lesson 456
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_generate_quiz',
        lesson_id: 456,
        nonce: mpcc_ajax.nonce,
        options: {
            num_questions: 5,
            difficulty: 'easy'
        }
    },
    success: function(response) {
        if (response.success) {
            response.data.questions.forEach(q => {
                console.log('Question:', q.question);
                console.log('Answer:', q.correct_answer);
            });
        }
    }
});
```

### Generate from Custom Content

```javascript
// Generate questions from provided content
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_generate_quiz',
        content: 'The water cycle has three main stages...',
        nonce: mpcc_ajax.nonce,
        options: {
            num_questions: 10,
            custom_prompt: 'Focus on the evaporation process'
        }
    },
    success: function(response) {
        // Handle response
    }
});
```

### Error Handling Example

```javascript
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_generate_quiz',
        lesson_id: 999999, // Non-existent
        nonce: mpcc_ajax.nonce
    },
    success: function(response) {
        if (!response.success) {
            switch(response.data.code) {
                case 'mpcc_missing_parameter':
                    alert('No content found for this lesson');
                    break;
                case 'mpcc_invalid_nonce':
                    location.reload(); // Session expired
                    break;
                default:
                    alert('Error: ' + response.data.message);
            }
        }
    },
    error: function(xhr, status, error) {
        alert('Network error. Please try again.');
    }
});
```

## Integration with Block Editor

### Creating Question Blocks

```javascript
// After receiving questions from API
response.data.questions.forEach(question => {
    // Create block
    const block = wp.blocks.createBlock(
        'memberpress-courses/multiple-choice-question',
        { questionId: 0 }
    );
    
    // Add to editor
    wp.data.dispatch('core/block-editor').insertBlocks([block]);
    
    // Update question data in store
    wp.data.dispatch('memberpress/course/question').addPlaceholder(
        block.clientId,
        {
            question: question.question,
            type: 'multiple-choice',
            options: Object.entries(question.options).map(([k, v]) => ({
                value: v,
                isCorrect: k === question.correct_answer
            })),
            feedback: question.explanation
        }
    );
});
```

## Future Enhancements

### Planned Features

1. **Additional Question Types**
   - True/False questions
   - Short answer questions
   - Fill in the blank

2. **Bulk Operations**
   - Generate entire quiz from course
   - Import/export questions
   - Question bank management

3. **Advanced Options**
   - Topic filtering
   - Bloom's taxonomy levels
   - Custom scoring rules

4. **Analytics**
   - Track generation success rates
   - Monitor question quality
   - Usage statistics

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 08/29/2025 | Initial release with multiple-choice support |