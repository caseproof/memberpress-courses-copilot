# Nonce Standardization Documentation

## Current Nonce Usage Analysis

After analyzing the codebase, I found the following unique nonce names currently in use:

### 1. **mpcc_cleanup_sessions**
- Used in: memberpress-courses-copilot.php (line 134)
- Purpose: Admin action for cleaning up empty sessions

### 2. **mpcc_courses_integration** 
- Used in: Multiple locations
  - ai-chat-interface.php template
  - CourseAjaxService.php (multiple methods)
  - CourseAssetService.php
  - CourseUIService.php
  - SimpleAjaxController.php
  - EnhancedTemplateEngine.php
  - AjaxController.php
- Purpose: General AJAX operations for course integration

### 3. **mpcc_ai_interface**
- Used in: 
  - CourseAjaxService.php (handleAIResponse method)
  - CourseIntegrationService.php
  - SimpleAjaxController.php
- Purpose: AI-specific AJAX operations

### 4. **mpcc_editor_nonce**
- Used in:
  - CourseEditorPage.php
  - SimpleAjaxController.php (all editor methods)
  - AjaxController.php
  - CourseAjaxService.php (as fallback)
- Purpose: Course editor page operations

### 5. **mpcc_ajax_nonce**
- Used in: AjaxController.php (as fallback option)
- Purpose: Generic AJAX fallback

### 6. **mpcc_nonce**
- Used in: AjaxController.php (as fallback option)
- Purpose: Generic fallback

### 7. **mpcc_export**
- Used in: AjaxController.php and RestApiController.php
- Purpose: Export functionality

### 8. **mpcc_generate_course**
- Used in: CLAUDE.md documentation
- Purpose: Course generation (mentioned in docs only)

### 9. **mpcc_quality_feedback**
- Used in: QualityFeedbackService.php
- Purpose: Quality feedback operations

### 10. **mpcc_apply_improvement**
- Used in: QualityFeedbackService.php
- Purpose: Applying improvements

### 11. **mpcc_auto_save_nonce**
- Used in: SessionFeaturesService.php
- Purpose: Auto-save functionality

### 12. **mpcc_extend_session_nonce**
- Used in: SessionFeaturesService.php
- Purpose: Session extension

### 13. **mpcc_quality_gates**
- Used in: QualityGatesService.php
- Purpose: Quality gates validation

### 14. **mpcc_request_review**
- Used in: QualityGatesService.php
- Purpose: Review requests

### 15. **mpcc_certify_quality**
- Used in: QualityGatesService.php
- Purpose: Quality certification

### 16. **wp_rest**
- Used in: RestApiController.php
- Purpose: WordPress REST API standard

### 17. **mp_ai_copilot_nonce**
- Used in: Documentation only
- Purpose: Legacy/documentation reference

## Current Implementation

The nonces have been standardized using the `NonceConstants` class located at `src/MemberPressCoursesCopilot/Security/NonceConstants.php`. 

The current standard nonces are:

### Core Nonces (via NonceConstants class)

```php
class NonceConstants {
    const ADMIN_ACTION = 'mpcc_admin_action';
    const EDITOR_NONCE = 'mpcc_editor_nonce';
    const COURSES_INTEGRATION = 'mpcc_courses_integration';
    const AI_INTERFACE = 'mpcc_ai_interface';
    const AI_ASSISTANT = 'mpcc_ai_assistant';
}
```

### Usage Examples

```php
// Creating a nonce
$nonce = NonceConstants::create(NonceConstants::EDITOR_NONCE);

// Verifying a nonce
if (NonceConstants::verify($_POST['nonce'], NonceConstants::EDITOR_NONCE)) {
    // Process request
}
```

### JavaScript Usage

```javascript
// Nonces are localized to JavaScript
jQuery.post(ajaxurl, {
    action: 'mpcc_chat_message',
    nonce: mpcc_editor.nonce,
    message: 'User message'
});
```

## Benefits of Current Implementation

1. **Type Safety**: Constants prevent typos in nonce names
2. **Centralized Management**: All nonce definitions in one place
3. **Consistent Verification**: Standard verify() method with proper error handling
4. **Backward Compatibility**: Supports multiple nonce checks during migration
5. **Security**: Maintains WordPress nonce security standards

## Migration Status

Most of the codebase has been migrated to use the NonceConstants class. Any remaining direct nonce strings should be updated to use the constants for consistency.

## Quiz AI Integration Security

### Overview
The Quiz AI integration uses nonce security to protect AJAX endpoints for quiz generation.

### Implementation

#### Nonce Definition
In `NonceConstants.php`:
```php
// Quiz AI operations
public const QUIZ_AI = 'mpcc_quiz_ai_nonce';
```

#### JavaScript Localization
In `AssetManager.php`:
```php
$quizAILocalization = [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => NonceConstants::create(NonceConstants::QUIZ_AI),
    'strings' => [/* ... */]
];
wp_localize_script('mpcc-quiz-ai-integration-copilot', 'mpcc_ajax', $quizAILocalization);
```

#### AJAX Request
```javascript
$.ajax({
    url: mpcc_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'mpcc_generate_quiz',
        lesson_id: lessonId,
        nonce: mpcc_ajax.nonce,
        options: { num_questions: 10 }
    }
});
```

#### Server-Side Verification
In `MpccQuizAjaxController.php`:
```php
public function generate_quiz(): void {
    // Verify nonce
    if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
        ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
        return;
    }
    
    // Check capabilities
    if (!current_user_can('edit_courses')) {
        ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_UNAUTHORIZED, 403);
        return;
    }
    
    // Process request...
}
```

### Troubleshooting 403 Errors

1. **Check nonce consistency**: Ensure the nonce action matches between creation and verification
2. **Verify user capabilities**: User must have 'edit_courses' capability
3. **Check nonce expiration**: WordPress nonces expire after 24 hours
4. **Enable debug logging**: Check `/wp-content/debug.log` for security messages

### Security Best Practices for Quiz AI

1. Always verify nonces before processing requests
2. Check user capabilities after nonce verification
3. Sanitize all input data (lesson_id, options, etc.)
4. Use ApiResponse for consistent error handling
5. Log security events for audit trails