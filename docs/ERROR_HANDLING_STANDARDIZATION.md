# Standardized Error Handling System

## Overview
This document describes the standardized error handling system implemented for the MemberPress Courses Copilot plugin. The system provides consistent error responses across all AJAX endpoints and services.

## Key Components

### 1. ApiResponse Utility Class
**Location**: `src/MemberPressCoursesCopilot/Utilities/ApiResponse.php`

A centralized utility class that provides static methods for consistent API responses:

#### Success Methods:
- `ApiResponse::success($data, $message = '', $meta = [])` - Send successful response
- `ApiResponse::formatSuccessData($data, $defaults = [])` - Format success data consistently

#### Error Methods:
- `ApiResponse::error(WP_Error $error, $status_code = 400)` - Send error from WP_Error
- `ApiResponse::errorMessage($message, $code, $status_code = 400)` - Send simple error
- `ApiResponse::validationError($errors, $message)` - Send validation errors
- `ApiResponse::unauthorized($message)` - Send 401 unauthorized
- `ApiResponse::forbidden($message)` - Send 403 forbidden  
- `ApiResponse::notFound($message, $code)` - Send 404 not found

#### Helper Methods:
- `ApiResponse::verifyNonce($nonce, $action, $error_message)` - Check nonce and auto-respond on failure
- `ApiResponse::verifyCapability($capability, $error_message)` - Check capability and auto-respond
- `ApiResponse::validateRequired($params, $required)` - Validate required parameters
- `ApiResponse::exceptionToError($exception, $code)` - Convert exception to WP_Error with logging

### 2. Standard Error Codes
Defined as constants in ApiResponse:
- `ERROR_INVALID_NONCE` - Invalid security nonce
- `ERROR_INSUFFICIENT_PERMISSIONS` - User lacks required permissions
- `ERROR_MISSING_PARAMETER` - Required parameter missing
- `ERROR_INVALID_PARAMETER` - Parameter validation failed
- `ERROR_DATABASE_ERROR` - Database operation failed
- `ERROR_AI_SERVICE` - AI service error
- `ERROR_COURSE_GENERATION` - Course generation failed
- `ERROR_SESSION_NOT_FOUND` - Session not found
- `ERROR_CONTENT_GENERATION` - Content generation failed
- `ERROR_GENERAL` - General error

## Implementation Examples

### Before (Inconsistent):
```php
if (!wp_verify_nonce($_POST['nonce'], 'action')) {
    wp_die('Security check failed');
}

if (empty($param)) {
    throw new \Exception('Parameter required');
}

wp_send_json_error('Something went wrong');
```

### After (Standardized):
```php
if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
    ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
    return;
}

if (empty($param)) {
    ApiResponse::errorMessage('Parameter required', ApiResponse::ERROR_MISSING_PARAMETER);
    return;
}

$error = new WP_Error(ApiResponse::ERROR_GENERAL, 'Something went wrong');
ApiResponse::error($error);
```

## Updated Files

### Controllers:
1. **SimpleAjaxController.php**
   - All methods now use ApiResponse for consistent responses
   - Proper error codes for different failure scenarios
   - Exception handling with logging via `exceptionToError()`

### Services:
2. **CourseAjaxService.php**
   - Updated all AJAX handlers to use ApiResponse
   - Consistent security checks with proper error codes
   - LLM service errors wrapped in WP_Error objects

3. **CourseGeneratorService.php**
   - Added imports for ApiResponse and WP_Error
   - Ready for error standardization in service methods

4. **LLMService.php**
   - Maintains backward compatibility with array-based errors
   - Calling code converts array errors to WP_Error as needed

## Benefits

1. **Consistency**: All endpoints return errors in the same format
2. **Debugging**: Error codes make it easier to identify issues
3. **Security**: Proper HTTP status codes for auth failures
4. **Logging**: Automatic exception logging with sanitized client responses
5. **WordPress Standards**: Uses WP_Error throughout
6. **Type Safety**: Clear return types and error handling

## Response Formats

### Success Response:
```json
{
    "success": true,
    "data": {
        "field": "value",
        "timestamp": "2025-08-28T10:30:00+00:00"
    }
}
```

### Error Response:
```json
{
    "success": false,
    "data": {
        "error": {
            "code": "mpcc_invalid_parameter",
            "message": "Course title is required",
            "data": {
                "validation_errors": ["title"]
            }
        }
    }
}
```

## Best Practices

1. **Always use specific error codes** - Don't default to ERROR_GENERAL
2. **Include helpful error messages** - Be specific about what went wrong
3. **Log before responding** - Use logger for detailed error info
4. **Sanitize error data** - Don't expose sensitive information
5. **Use proper HTTP status codes** - 400 for client errors, 500 for server errors
6. **Handle exceptions gracefully** - Use try-catch with exceptionToError()

## Future Improvements

1. Add rate limiting error responses
2. Implement error recovery suggestions in responses  
3. Add error tracking/metrics
4. Create frontend error handler component
5. Add unit tests for all error scenarios

## Migration Guide

To update existing code:

1. Add `use` statements:
   ```php
   use MemberPressCoursesCopilot\Utilities\ApiResponse;
   use WP_Error;
   ```

2. Replace `wp_send_json_success()` with `ApiResponse::success()`
3. Replace `wp_send_json_error()` with `ApiResponse::error()` or specific methods
4. Replace `throw new \Exception()` in AJAX handlers with proper ApiResponse calls
5. Add proper error codes from the constants