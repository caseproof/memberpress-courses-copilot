# MemberPress Courses Copilot API Documentation

## Overview

The MemberPress Courses Copilot plugin provides AJAX endpoints for AI-powered course creation and management. All endpoints require proper authentication and nonce verification.

## Authentication

All AJAX endpoints require:
1. Valid WordPress user session
2. Appropriate user capabilities (typically `edit_posts` or `publish_posts`)
3. Valid nonce verification

## AJAX Endpoints

### Course Editor Page Endpoints

These endpoints are used by the standalone AI Course Editor page.

#### `mpcc_chat_message`
Send a message to the AI and receive a response.

**Request:**
```javascript
{
  action: 'mpcc_chat_message',
  nonce: mpcc_editor.nonce,
  message: 'User message text',
  session_id: 'session-uuid',
  conversation_history: [...], // Array of previous messages
  course_structure: {...}      // Current course structure
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    message: 'AI response text',
    course_structure: {...} // Updated course structure if applicable
  }
}
```

#### `mpcc_load_session`
Load a saved conversation session.

**Request:**
```javascript
{
  action: 'mpcc_load_session',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    session_id: 'session-uuid',
    title: 'Course: My Course Title',
    conversation_history: [...],
    conversation_state: {...},
    course_structure: {...},
    last_updated: '2025-08-28 10:30:00',
    created_at: '2025-08-28 09:00:00'
  }
}
```

#### `mpcc_save_conversation`
Save the current conversation state.

**Request:**
```javascript
{
  action: 'mpcc_save_conversation',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  conversation_history: [...],
  conversation_state: {...}
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    saved: true
  }
}
```

#### `mpcc_create_course`
Create a WordPress course from the AI-generated structure.

**Request:**
```javascript
{
  action: 'mpcc_create_course',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  course_data: {
    title: 'Course Title',
    description: 'Course description',
    sections: [...]
  }
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    course_id: 123,
    edit_url: 'https://site.com/wp-admin/post.php?post=123&action=edit'
  }
}
```

#### `mpcc_get_sessions`
Get a list of user's conversation sessions.

**Request:**
```javascript
{
  action: 'mpcc_get_sessions',
  nonce: mpcc_editor.nonce
}
```

**Response:**
```javascript
{
  success: true,
  data: [
    {
      id: 'session-uuid',
      title: 'Course: My Course',
      last_updated: '2025-08-28 10:30:00',
      created_at: '2025-08-28 09:00:00',
      message_count: 15
    }
  ]
}
```

#### `mpcc_update_session_title`
Update the title of a conversation session.

**Request:**
```javascript
{
  action: 'mpcc_update_session_title',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  title: 'New Session Title'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    updated: true,
    title: 'New Session Title'
  }
}
```

#### `mpcc_delete_session`
Delete a conversation session.

**Request:**
```javascript
{
  action: 'mpcc_delete_session',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    deleted: true
  }
}
```

#### `mpcc_duplicate_course`
Duplicate a course structure into a new draft session.

**Request:**
```javascript
{
  action: 'mpcc_duplicate_course',
  nonce: mpcc_editor.nonce,
  session_id: 'original-session-uuid',
  course_data: {...}
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    new_session_id: 'new-session-uuid',
    course_title: 'Course Title (Draft Copy)'
  }
}
```

### Lesson Content Management

#### `mpcc_save_lesson_content`
Save draft content for a lesson.

**Request:**
```javascript
{
  action: 'mpcc_save_lesson_content',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  section_id: 'section-id',
  lesson_id: 'lesson-id',
  content: 'Lesson content text',
  order_index: 0
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    saved: true,
    saved_at: '2025-08-28T10:30:00+00:00'
  }
}
```

#### `mpcc_load_lesson_content`
Load draft content for a lesson.

**Request:**
```javascript
{
  action: 'mpcc_load_lesson_content',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  section_id: 'section-id',
  lesson_id: 'lesson-id'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    draft: {
      content: 'Lesson content text',
      order_index: 0,
      updated_at: '2025-08-28 10:30:00'
    }
  }
}
```

#### `mpcc_generate_lesson_content`
Generate AI content for a lesson.

**Request:**
```javascript
{
  action: 'mpcc_generate_lesson_content',
  nonce: mpcc_editor.nonce,
  session_id: 'session-uuid',
  lesson_title: 'Introduction to PHP',
  section_title: 'Getting Started',
  course_title: 'PHP Programming',
  course_context: {...}
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    content: 'Generated lesson content...',
    generated_at: '2025-08-28T10:30:00+00:00'
  }
}
```

### Course Integration Endpoints

These endpoints are used by the MemberPress Courses listing page integration.

#### `mpcc_load_ai_interface`
Load the AI chat interface HTML.

**Request:**
```javascript
{
  action: 'mpcc_load_ai_interface',
  nonce: mpcc_courses_integration.nonce,
  context: 'course_creation',
  post_id: 0
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    html: '<div>AI interface HTML...</div>',
    context: 'course_creation',
    post_id: 0
  }
}
```

#### `mpcc_ai_chat`
Handle AI chat messages in the modal interface.

**Request:**
```javascript
{
  action: 'mpcc_ai_chat',
  nonce: mpcc_courses_integration.nonce,
  message: 'User message',
  context: 'course_creation',
  conversation_history: [...],
  conversation_state: {...},
  session_id: 'session-uuid'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    message: 'AI response',
    course_data: {...}, // If course structure is generated
    ready_to_create: true,
    actions: [
      {action: 'create_course', label: 'Create Course', type: 'primary'}
    ]
  }
}
```

#### `mpcc_create_course_with_ai`
Create a course from the AI modal interface.

**Request:**
```javascript
{
  action: 'mpcc_create_course_with_ai',
  nonce: mpcc_courses_integration.nonce,
  course_data: {...},
  session_id: 'session-uuid'
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    message: 'Course created successfully!',
    course_id: 123,
    edit_url: 'https://site.com/wp-admin/post.php?post=123&action=edit',
    preview_url: 'https://site.com/courses/my-course/'
  }
}
```

### Course Edit Page AI Assistant

#### `mpcc_course_chat_message`
Handle AI chat on the course edit page.

**Request:**
```javascript
{
  action: 'mpcc_course_chat_message',
  nonce: mpcc_ai_assistant.nonce,
  message: 'How can I improve this course?',
  course_id: 123,
  course_data: {...},
  conversation_history: [...]
}
```

**Response:**
```javascript
{
  success: true,
  data: {
    message: 'AI suggestions for improvement...',
    course_updates: {...} // Optional course structure updates
  }
}
```

## Error Responses

All endpoints return consistent error responses:

```javascript
{
  success: false,
  data: 'Error message'
}
```

Common error codes:
- `403` - Security check failed (invalid nonce)
- `403` - Insufficient permissions
- `400` - Missing required parameters
- `404` - Resource not found
- `500` - Server error

## Security

### Nonce Constants

The plugin uses these nonce actions:
- `mpcc_editor_nonce` - Course editor page
- `mpcc_courses_integration` - Courses listing integration
- `mpcc_ai_interface` - AI interface loading
- `mpcc_ai_assistant` - Course edit page assistant

### Capabilities Required

- `edit_posts` - View and use AI features
- `publish_posts` - Create courses
- `edit_post` - Edit specific course

## JavaScript Integration

### Course Editor Page
```javascript
// Send chat message
jQuery.post(ajaxurl, {
  action: 'mpcc_chat_message',
  nonce: mpcc_editor.nonce,
  message: 'Create a PHP course',
  session_id: currentSessionId,
  conversation_history: conversationHistory,
  course_structure: courseStructure
}, function(response) {
  if (response.success) {
    // Handle AI response
    displayMessage(response.data.message);
    if (response.data.course_structure) {
      updateCoursePreview(response.data.course_structure);
    }
  }
});
```

### Courses Listing Integration
```javascript
// Initialize AI modal
jQuery.post(ajaxurl, {
  action: 'mpcc_load_ai_interface',
  nonce: mpcc_courses_integration.nonce,
  context: 'course_creation'
}, function(response) {
  if (response.success) {
    jQuery('#mpcc-ai-modal-content').html(response.data.html);
  }
});
```

## Rate Limiting

The plugin implements rate limiting on AI requests:
- Maximum 60 requests per minute per user
- Maximum message length: 5000 characters
- Maximum conversation history: 50 messages

## Debugging

Enable debug logging by adding to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('MPCC_DEBUG', true);
```

Logs are written to:
- WordPress debug log: `/wp-content/debug.log`
- Plugin log: `/wp-content/plugins/memberpress-courses-copilot/logs/debug.log`