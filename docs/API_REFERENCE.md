# MemberPress Courses Copilot - Complete API Reference

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Error Handling](#error-handling)
- [Course Editor Endpoints](#course-editor-endpoints)
- [Course Integration Endpoints](#course-integration-endpoints)
- [Quiz Generation Endpoints](#quiz-generation-endpoints)
- [Lesson Content Endpoints](#lesson-content-endpoints)
- [Session Management Endpoints](#session-management-endpoints)
- [Editor AI Integration Endpoints](#editor-ai-integration-endpoints)
- [Template Management Endpoints](#template-management-endpoints)
- [JavaScript Integration Examples](#javascript-integration-examples)
- [Rate Limiting](#rate-limiting)
- [Testing Endpoints](#testing-endpoints)

## Overview

The MemberPress Courses Copilot plugin provides a comprehensive REST-like API through WordPress AJAX endpoints. All endpoints follow consistent patterns for security, validation, and response formatting.

### Base Configuration

All AJAX requests go through WordPress's AJAX handler:

```javascript
const ajaxUrl = mpcc_ajax.ajax_url; // WordPress admin-ajax.php
```

### Global Request Format

```javascript
{
    action: 'endpoint_action_name',
    nonce: 'security_nonce_value',
    // ... endpoint-specific parameters
}
```

### Global Response Format

#### Success Response
```javascript
{
    success: true,
    data: {
        // endpoint-specific data
    },
    message: 'Optional success message',
    meta: {
        // Optional metadata like timestamps
    }
}
```

#### Error Response
```javascript
{
    success: false,
    error: {
        code: 'error_code',
        message: 'Human-readable error message',
        data: {
            // Additional error context
        }
    }
}
```

## Authentication

### Security Requirements

All endpoints require:

1. **Valid WordPress Session**: User must be logged in
2. **Nonce Verification**: CSRF protection via WordPress nonces
3. **Capability Checks**: Appropriate user permissions
4. **Input Sanitization**: All inputs are sanitized and validated

### Nonce Types

| Nonce Action | Used By | Purpose |
|--------------|---------|---------|
| `mpcc_editor_nonce` | Course Editor Page | Standalone editor operations |
| `mpcc_courses_integration` | Courses Listing | Modal integration |
| `mpcc_ai_interface` | AI Interface | Chat interface loading |
| `mpcc_quiz_ai_nonce` | Quiz Generation | Quiz-related operations |
| `mpcc_ai_assistant` | Course Edit | Course edit page assistant |

### Capability Requirements

| Capability | Required For | WordPress Default |
|------------|-------------|------------------|
| `edit_posts` | Basic AI access, quiz generation | Editor and above |
| `publish_posts` | Course creation | Author and above |
| `edit_post` | Edit specific course | Post owner or Editor+ |
| `manage_options` | Plugin settings | Administrator |

## Error Handling

### Standard Error Codes

| Code | HTTP Status | Description |
|------|-------------|-------------|
| `mpcc_invalid_nonce` | 403 | Nonce verification failed |
| `mpcc_insufficient_permissions` | 403 | User lacks required capabilities |
| `mpcc_missing_parameter` | 400 | Required parameter missing |
| `mpcc_invalid_parameter` | 400 | Parameter value invalid |
| `mpcc_database_error` | 500 | Database operation failed |
| `mpcc_ai_service_error` | 503 | AI service unavailable |
| `mpcc_session_not_found` | 404 | Session does not exist |
| `mpcc_general_error` | 500 | Unspecified server error |

### Error Response Examples

#### Nonce Failure
```javascript
{
    success: false,
    error: {
        code: 'mpcc_invalid_nonce',
        message: 'Security check failed',
        data: null
    }
}
```

#### Permission Denied
```javascript
{
    success: false,
    error: {
        code: 'mpcc_insufficient_permissions',
        message: 'You do not have permission to create courses',
        data: {
            required_capability: 'publish_posts',
            user_capabilities: ['edit_posts']
        }
    }
}
```

#### Validation Error
```javascript
{
    success: false,
    error: {
        code: 'mpcc_invalid_parameter',
        message: 'Invalid lesson content',
        data: {
            validation_errors: [
                'Content is too short (minimum 50 characters)',
                'Content contains invalid HTML tags'
            ]
        }
    }
}
```

## Course Editor Endpoints

These endpoints power the standalone AI Course Editor page (`/wp-admin/admin.php?page=mpcc-course-editor`).

### Send Chat Message

Generate AI responses for course creation conversations.

**Action:** `mpcc_chat_message`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | User's chat message |
| `session_id` | string | No | Session UUID (auto-generated if empty) |
| `conversation_history` | array | No | Previous conversation messages |
| `course_structure` | object | No | Current course structure |

#### Example Request
```javascript
{
    action: 'mpcc_chat_message',
    nonce: mpcc_editor.nonce,
    message: 'Create a beginner PHP programming course',
    session_id: 'uuid-session-id',
    conversation_history: [
        {role: 'user', content: 'Hello'},
        {role: 'assistant', content: 'Hi! How can I help you create a course?'}
    ],
    course_structure: {
        title: 'PHP Programming',
        sections: [...]
    }
}
```

#### Success Response
```javascript
{
    success: true,
    data: {
        message: 'I\'ll help you create a PHP programming course...',
        session_id: 'uuid-session-id',
        course_structure: {
            title: 'PHP Programming Fundamentals',
            description: 'Learn PHP from basics to advanced concepts',
            sections: [
                {
                    id: 'section-1',
                    title: 'Getting Started',
                    lessons: [
                        {id: 'lesson-1', title: 'Introduction to PHP'},
                        {id: 'lesson-2', title: 'Setting up Environment'}
                    ]
                }
            ]
        },
        conversation_state: {
            stage: 'course_structure',
            ready_to_create: true
        }
    }
}
```

### Load Conversation Session

Retrieve a saved conversation session with all its data.

**Action:** `mpcc_load_session`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID to load |

#### Success Response
```javascript
{
    success: true,
    data: {
        session_id: 'uuid-session-id',
        title: 'Course: PHP Programming Fundamentals',
        conversation_history: [...],
        conversation_state: {
            stage: 'course_structure',
            ready_to_create: true
        },
        course_structure: {...},
        created_at: '2025-09-03T10:00:00+00:00',
        updated_at: '2025-09-03T11:30:00+00:00'
    }
}
```

### Save Conversation

Persist conversation state and history.

**Action:** `mpcc_save_conversation`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID |
| `conversation_history` | array | Yes | Complete conversation history |
| `conversation_state` | object | No | Current conversation state |
| `course_structure` | object | No | Course structure data |

#### Success Response
```javascript
{
    success: true,
    data: {
        saved: true,
        session_id: 'uuid-session-id',
        saved_at: '2025-09-03T12:00:00+00:00'
    }
}
```

### Create Course

Create a WordPress course from AI-generated structure.

**Action:** `mpcc_create_course`  
**Method:** POST  
**Capability:** `publish_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID |
| `course_data` | object | Yes | Complete course structure |

#### Course Data Structure
```javascript
{
    title: 'Course Title',
    description: 'Course description',
    sections: [
        {
            id: 'section-1',
            title: 'Section Title',
            lessons: [
                {
                    id: 'lesson-1',
                    title: 'Lesson Title',
                    content: 'Lesson content...',
                    order: 1
                }
            ],
            order: 1
        }
    ],
    metadata: {
        difficulty: 'beginner',
        duration: '4 weeks',
        prerequisites: []
    }
}
```

#### Success Response
```javascript
{
    success: true,
    data: {
        course_id: 123,
        edit_url: 'https://site.com/wp-admin/post.php?post=123&action=edit',
        preview_url: 'https://site.com/courses/course-slug/',
        message: 'Course created successfully with 3 sections and 12 lessons',
        sections_created: 3,
        lessons_created: 12
    }
}
```

### Get User Sessions

Retrieve list of user's conversation sessions.

**Action:** `mpcc_get_sessions`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `limit` | int | No | Maximum sessions to return (default: 10) |
| `offset` | int | No | Pagination offset (default: 0) |

#### Success Response
```javascript
{
    success: true,
    data: {
        sessions: [
            {
                session_id: 'uuid-1',
                title: 'Course: PHP Programming',
                created_at: '2025-09-03T10:00:00+00:00',
                updated_at: '2025-09-03T11:30:00+00:00',
                message_count: 15,
                has_course_structure: true
            }
        ],
        total: 25,
        has_more: true
    }
}
```

### Update Session Title

Update the title of a conversation session.

**Action:** `mpcc_update_session_title`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

### Delete Session

Delete a conversation session and all associated data.

**Action:** `mpcc_delete_session`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

### Duplicate Course

Create a copy of a course structure in a new session.

**Action:** `mpcc_duplicate_course`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

## Course Integration Endpoints

These endpoints are used by the MemberPress Courses listing page integration modal.

### Load AI Interface

Load the AI chat interface HTML for modal display.

**Action:** `mpcc_load_ai_interface`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_courses_integration`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `context` | string | Yes | Interface context ('course_creation') |
| `post_id` | int | No | Associated post ID (0 for new) |

#### Success Response
```javascript
{
    success: true,
    data: {
        html: '<div class="mpcc-ai-chat">...</div>',
        context: 'course_creation',
        post_id: 0,
        nonce: 'fresh-nonce-value'
    }
}
```

### AI Chat (Modal)

Handle AI chat within the modal interface.

**Action:** `mpcc_ai_chat`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_courses_integration`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | User's chat message |
| `context` | string | Yes | Chat context |
| `conversation_history` | array | No | Previous messages |
| `conversation_state` | object | No | Current conversation state |
| `session_id` | string | No | Session identifier |

#### Success Response
```javascript
{
    success: true,
    data: {
        message: 'AI response message',
        course_data: {
            // Generated course structure
        },
        ready_to_create: true,
        actions: [
            {
                action: 'create_course',
                label: 'Create Course',
                type: 'primary',
                enabled: true
            }
        ],
        session_id: 'uuid-session-id'
    }
}
```

### Create Course with AI

Create a course directly from the modal interface.

**Action:** `mpcc_create_course_with_ai`  
**Method:** POST  
**Capability:** `publish_posts`  
**Nonce:** `mpcc_courses_integration`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `course_data` | object | Yes | Complete course structure |
| `session_id` | string | No | Associated session ID |

#### Success Response
```javascript
{
    success: true,
    data: {
        message: 'Course created successfully!',
        course_id: 123,
        edit_url: 'https://site.com/wp-admin/post.php?post=123&action=edit',
        preview_url: 'https://site.com/courses/course-slug/',
        redirect_url: 'https://site.com/wp-admin/edit.php?post_type=mpcs-course'
    }
}
```

## Quiz Generation Endpoints

### Generate Quiz Questions

Generate AI-powered quiz questions from lesson, course, or custom content.

**Action:** `mpcc_generate_quiz`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lesson_id` | int | No* | Lesson ID to generate from |
| `course_id` | int | No* | Course ID to generate from |
| `content` | string | No* | Direct content to use |
| `options` | object | No | Generation options |

*At least one of `lesson_id`, `course_id`, or `content` is required.

#### Options Object
```javascript
{
    num_questions: 10,        // Number of questions (1-20)
    difficulty: "medium",     // Difficulty: easy|medium|hard
    question_type: "multiple_choice", // Question type
    custom_prompt: "Focus on practical applications"
}
```

#### Success Response
```javascript
{
    success: true,
    data: {
        questions: [
            {
                type: 'multiple_choice',
                question: 'What is the primary purpose of PHP?',
                options: {
                    'A': 'Client-side scripting',
                    'B': 'Server-side scripting',
                    'C': 'Database management',
                    'D': 'Web design'
                },
                correct_answer: 'B',
                explanation: 'PHP is primarily used for server-side scripting to create dynamic web pages.',
                points: 1,
                difficulty: 'medium'
            }
        ],
        total: 10,
        type: 'multiple_choice',
        suggestion: 'Consider adding more practical examples to your content for better quiz generation.'
    }
}
```

#### Error Response Example
```javascript
{
    success: false,
    error: {
        code: 'mpcc_invalid_parameter',
        message: 'Content is too short to generate meaningful questions',
        data: {
            suggestion: 'Please provide at least 200 characters of content for quiz generation.',
            content_length: 45,
            minimum_required: 200
        }
    }
}
```

### Create Quiz from Lesson

Create a new quiz post associated with a specific lesson.

**Action:** `mpcc_create_quiz_from_lesson`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lesson_id` | int | Yes | ID of lesson to create quiz for |
| `course_id` | int | No | Course ID (auto-detected if not provided) |

#### Success Response
```javascript
{
    success: true,
    data: {
        quiz_id: 1970,
        edit_url: 'http://site.com/wp-admin/post.php?post=1970&action=edit&lesson_id=1941&auto_open=true',
        message: 'Quiz created successfully!',
        course_id: 456,
        lesson_id: 1941
    }
}
```

**Note:** The `edit_url` includes:
- `lesson_id`: Pre-selects the lesson in AI generator
- `auto_open=true`: Automatically opens AI Quiz Generator modal

### Get Lesson Course

Retrieve course information for a specific lesson.

**Action:** `mpcc_get_lesson_course`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lesson_id` | int | Yes | Lesson ID to query |

#### Success Response
```javascript
{
    success: true,
    data: {
        lesson_id: 1941,
        course_id: 456,
        course_title: 'PHP Programming Fundamentals'
    }
}
```

### Get Course Lessons

Retrieve all lessons for a specific course.

**Action:** `mpcc_get_course_lessons`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Success Response
```javascript
{
    success: true,
    data: {
        course_id: 456,
        course_title: 'PHP Programming Fundamentals',
        lessons: [
            {
                id: 1941,
                title: 'Introduction to PHP',
                section_id: 'section-1'
            },
            {
                id: 1942,
                title: 'PHP Syntax Basics',
                section_id: 'section-1'
            }
        ],
        lesson_count: 12
    }
}
```

### Regenerate Question

Generate alternative versions of an existing question.

**Action:** `mpcc_regenerate_question`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `question` | object | Yes | Current question data |
| `content` | string | Yes | Source content |
| `options` | object | No | Regeneration options |

### Validate Quiz

Validate quiz structure and content quality.

**Action:** `mpcc_validate_quiz`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_quiz_ai_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `quiz_data` | object | Yes | Complete quiz data to validate |

#### Success Response
```javascript
{
    success: true,
    data: {
        valid: true,
        errors: [],
        warnings: [
            'Question 3: Consider adding more detailed explanation'
        ],
        summary: {
            total_questions: 10,
            total_points: 10,
            question_types: {
                'multiple_choice': 8,
                'true_false': 2
            }
        }
    }
}
```

## Lesson Content Endpoints

### Save Lesson Content

Save draft content for a lesson during course creation.

**Action:** `mpcc_save_lesson_content`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID |
| `section_id` | string | Yes | Section identifier |
| `lesson_id` | string | Yes | Lesson identifier |
| `content` | string | Yes | Lesson content |
| `order_index` | int | No | Lesson order (default: 0) |

#### Success Response
```javascript
{
    success: true,
    data: {
        saved: true,
        session_id: 'uuid-session-id',
        section_id: 'section-1',
        lesson_id: 'lesson-1',
        saved_at: '2025-09-03T12:00:00+00:00'
    }
}
```

### Load Lesson Content

Load saved draft content for a lesson.

**Action:** `mpcc_load_lesson_content`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID |
| `section_id` | string | No | Section ID (if omitted, loads all drafts) |
| `lesson_id` | string | No | Lesson ID |

#### Success Response (Single Lesson)
```javascript
{
    success: true,
    data: {
        draft: {
            content: 'Lesson content...',
            order_index: 1,
            created_at: '2025-09-03T10:00:00+00:00',
            updated_at: '2025-09-03T12:00:00+00:00'
        }
    }
}
```

#### Success Response (All Drafts)
```javascript
{
    success: true,
    data: {
        drafts: {
            'section-1': {
                'lesson-1': {
                    content: 'Content...',
                    order_index: 1
                },
                'lesson-2': {
                    content: 'Content...',
                    order_index: 2
                }
            }
        },
        total_drafts: 12
    }
}
```

### Generate Lesson Content

Generate AI content for a specific lesson.

**Action:** `mpcc_generate_lesson_content`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `session_id` | string | Yes | Session UUID |
| `lesson_title` | string | Yes | Lesson title |
| `section_title` | string | No | Parent section title |
| `course_title` | string | No | Course title for context |
| `course_context` | object | No | Additional course context |

#### Success Response
```javascript
{
    success: true,
    data: {
        content: 'Generated lesson content with examples and explanations...',
        word_count: 850,
        generated_at: '2025-09-03T12:00:00+00:00',
        suggestions: [
            'Consider adding code examples',
            'Include practical exercises'
        ]
    }
}
```

## Session Management Endpoints

### Auto-Save Session

Automatically save session state (called periodically by frontend).

**Action:** `mpcc_auto_save_session`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_auto_save_nonce`

### Extend Session

Extend session timeout for active users.

**Action:** `mpcc_extend_session`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_extend_session_nonce`

## Editor AI Integration Endpoints

These endpoints integrate AI assistance directly into WordPress post editors.

### Editor AI Chat

AI chat functionality within post editors (courses, lessons).

**Action:** `mpcc_editor_ai_chat`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_ai_assistant`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `message` | string | Yes | User message |
| `post_id` | int | No | Current post ID |
| `post_type` | string | No | Post type context |
| `conversation_history` | array | No | Previous messages |

#### Success Response
```javascript
{
    success: true,
    data: {
        message: 'AI response with suggestions...',
        suggestions: [
            {
                type: 'content_improvement',
                description: 'Add more examples',
                action: 'insert_content',
                content: 'Example content...'
            }
        ],
        post_updates: {
            // Optional post content updates
        }
    }
}
```

### Update Post Content

Update post content with AI suggestions.

**Action:** `mpcc_update_post_content`  
**Method:** POST  
**Capability:** `edit_post`  
**Nonce:** `mpcc_ai_assistant`

## Template Management Endpoints

### Get Template

Retrieve course templates for quick course creation.

**Action:** `mpcc_get_template`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_editor_nonce`

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `template_id` | string | Yes | Template identifier |
| `context` | string | No | Template context |

#### Success Response
```javascript
{
    success: true,
    data: {
        template: {
            id: 'programming-course',
            name: 'Programming Course',
            description: 'Template for programming courses',
            sections: [
                {
                    title: 'Introduction',
                    lessons: ['Overview', 'Setup']
                },
                {
                    title: 'Fundamentals',
                    lessons: ['Basics', 'Syntax', 'Examples']
                }
            ],
            metadata: {
                difficulty: 'beginner',
                duration: '4 weeks'
            }
        }
    }
}
```

## JavaScript Integration Examples

### Basic AJAX Request Pattern

```javascript
function makeAjaxRequest(action, data, callback) {
    jQuery.ajax({
        url: mpcc_ajax.ajax_url,
        type: 'POST',
        data: {
            action: action,
            nonce: mpcc_ajax.nonce,
            ...data
        },
        success: function(response) {
            if (response.success) {
                callback(null, response.data);
            } else {
                callback(response.error || response.data, null);
            }
        },
        error: function(xhr, status, error) {
            callback({
                code: 'network_error',
                message: 'Network request failed: ' + error
            }, null);
        }
    });
}
```

### Sending Chat Message

```javascript
function sendChatMessage(message, sessionId, callback) {
    makeAjaxRequest('mpcc_chat_message', {
        message: message,
        session_id: sessionId,
        conversation_history: getCurrentHistory(),
        course_structure: getCurrentCourseStructure()
    }, callback);
}

// Usage
sendChatMessage('Create a Python course', 'uuid-123', function(error, data) {
    if (error) {
        console.error('Chat error:', error);
        return;
    }
    
    updateChatInterface(data.message);
    if (data.course_structure) {
        updateCoursePreview(data.course_structure);
    }
});
```

### Creating Course

```javascript
function createCourse(courseData, sessionId, callback) {
    makeAjaxRequest('mpcc_create_course', {
        course_data: courseData,
        session_id: sessionId
    }, function(error, data) {
        if (error) {
            callback(error, null);
            return;
        }
        
        // Show success message
        showToast('Course created successfully!', 'success');
        
        // Redirect to course editor
        if (data.edit_url) {
            window.location.href = data.edit_url;
        }
        
        callback(null, data);
    });
}
```

### Generating Quiz Questions

```javascript
function generateQuiz(lessonId, options, callback) {
    makeAjaxRequest('mpcc_generate_quiz', {
        lesson_id: lessonId,
        options: {
            num_questions: options.count || 10,
            difficulty: options.difficulty || 'medium',
            question_type: options.type || 'multiple_choice'
        }
    }, function(error, data) {
        if (error) {
            handleQuizError(error);
            return;
        }
        
        // Process generated questions
        data.questions.forEach(function(question, index) {
            addQuestionToEditor(question, index);
        });
        
        callback(null, data);
    });
}

function handleQuizError(error) {
    switch(error.code) {
        case 'mpcc_invalid_parameter':
            if (error.data && error.data.suggestion) {
                showToast(error.data.suggestion, 'warning');
            } else {
                showToast(error.message, 'error');
            }
            break;
        case 'mpcc_missing_parameter':
            showToast('Please select content to generate quiz from', 'error');
            break;
        default:
            showToast('Failed to generate quiz. Please try again.', 'error');
    }
}
```

### Session Management

```javascript
// Auto-save session periodically
setInterval(function() {
    if (sessionIsDirty()) {
        saveSession(getCurrentSessionId(), function(error, data) {
            if (!error) {
                markSessionClean();
                console.log('Session auto-saved');
            }
        });
    }
}, 30000); // Every 30 seconds

function saveSession(sessionId, callback) {
    makeAjaxRequest('mpcc_save_conversation', {
        session_id: sessionId,
        conversation_history: getCurrentHistory(),
        conversation_state: getCurrentState(),
        course_structure: getCurrentCourseStructure()
    }, callback);
}

function loadSession(sessionId, callback) {
    makeAjaxRequest('mpcc_load_session', {
        session_id: sessionId
    }, function(error, data) {
        if (error) {
            callback(error, null);
            return;
        }
        
        // Restore session state
        setConversationHistory(data.conversation_history);
        setCourseStructure(data.course_structure);
        setConversationState(data.conversation_state);
        
        callback(null, data);
    });
}
```

### Error Handling with Retry Logic

```javascript
function makeAjaxRequestWithRetry(action, data, callback, maxRetries = 3) {
    let retryCount = 0;
    
    function attemptRequest() {
        makeAjaxRequest(action, data, function(error, response) {
            if (error && retryCount < maxRetries) {
                // Check if error is retryable
                if (isRetryableError(error)) {
                    retryCount++;
                    setTimeout(attemptRequest, Math.pow(2, retryCount) * 1000); // Exponential backoff
                    return;
                }
            }
            
            callback(error, response);
        });
    }
    
    attemptRequest();
}

function isRetryableError(error) {
    const retryableCodes = [
        'network_error',
        'mpcc_ai_service_error',
        'mpcc_general_error'
    ];
    return retryableCodes.includes(error.code);
}
```

## Rate Limiting

### Current Implementation

The plugin implements basic rate limiting:

- **Request Frequency**: Maximum 60 requests per minute per user
- **Content Length**: Maximum 5000 characters per message
- **History Length**: Maximum 50 messages per conversation
- **Session Duration**: 24 hours maximum per session

### Future Enhancements

Planned rate limiting improvements:

- **Token Bucket Algorithm**: More sophisticated rate limiting
- **User Tier Support**: Different limits for different user roles
- **API Usage Tracking**: Monitor and optimize API usage
- **Graceful Degradation**: Fallback behavior when limits exceeded

## Testing Endpoints

### Ping Endpoint

Test basic connectivity and authentication.

**Action:** `mpcc_ping`  
**Method:** POST  
**Capability:** `edit_posts`  
**Nonce:** `mpcc_courses_integration`

#### Success Response
```javascript
{
    success: true,
    data: {
        pong: true,
        timestamp: '2025-09-03T12:00:00+00:00',
        user_id: 1,
        capabilities: ['edit_posts', 'publish_posts']
    }
}
```

## Best Practices

### Frontend Integration

1. **Always check response.success** before processing data
2. **Handle all error codes** appropriately
3. **Implement retry logic** for transient failures
4. **Show user feedback** for all operations
5. **Validate inputs** before sending requests

### Error Handling

1. **Use specific error codes** for different error types
2. **Provide actionable error messages** to users
3. **Log detailed errors** for debugging
4. **Implement graceful fallbacks** where possible

### Security

1. **Never skip nonce verification** in AJAX handlers
2. **Always check user capabilities** before processing
3. **Sanitize all inputs** and escape all outputs
4. **Use prepared statements** for database queries

### Performance

1. **Implement caching** for expensive operations
2. **Use pagination** for large datasets
3. **Debounce user inputs** to reduce API calls
4. **Optimize database queries** with proper indexes

This API reference provides comprehensive documentation for all available endpoints in the MemberPress Courses Copilot plugin. For implementation examples and advanced usage patterns, refer to the source code and test files.