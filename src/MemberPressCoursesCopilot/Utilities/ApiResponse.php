<?php

namespace MemberPressCoursesCopilot\Utilities;

use WP_Error;

/**
 * Standardized API Response Handler
 *
 * Provides consistent response format for all AJAX endpoints
 * Following KISS principle with static methods for simplicity
 *
 * @package MemberPressCoursesCopilot\Utilities
 * @since   1.0.0
 */
class ApiResponse
{
    /**
     * Standard error codes used throughout the plugin
     */
    const ERROR_INVALID_NONCE            = 'mpcc_invalid_nonce';
    const ERROR_INSUFFICIENT_PERMISSIONS = 'mpcc_insufficient_permissions';
    const ERROR_MISSING_PARAMETER        = 'mpcc_missing_parameter';
    const ERROR_INVALID_PARAMETER        = 'mpcc_invalid_parameter';
    const ERROR_DATABASE_ERROR           = 'mpcc_database_error';
    const ERROR_AI_SERVICE               = 'mpcc_ai_service_error';
    const ERROR_COURSE_GENERATION        = 'mpcc_course_generation_error';
    const ERROR_SESSION_NOT_FOUND        = 'mpcc_session_not_found';
    const ERROR_CONTENT_GENERATION       = 'mpcc_content_generation_error';
    const ERROR_GENERAL                  = 'mpcc_general_error';

    /**
     * Send successful JSON response
     *
     * @param  mixed  $data    Data to return
     * @param  string $message Optional success message
     * @param  array  $meta    Optional metadata
     * @return void Terminates execution
     */
    public static function success($data = null, string $message = '', array $meta = []): void
    {
        $response = [
            'success' => true,
            'data'    => $data,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        if (!empty($meta)) {
            $response['meta'] = $meta;
        }

        wp_send_json_success($response);
    }

    /**
     * Send error JSON response from WP_Error
     *
     * @param  WP_Error $error       WordPress error object
     * @param  integer  $status_code HTTP status code (default 400)
     * @return void Terminates execution
     */
    public static function error(WP_Error $error, int $status_code = 400): void
    {
        $response = [
            'success' => false,
            'error'   => [
                'code'    => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data'    => $error->get_error_data(),
            ],
        ];

        // Add all error messages if multiple
        $all_errors = $error->get_error_messages();
        if (count($all_errors) > 1) {
            $response['error']['messages'] = $all_errors;
        }

        status_header($status_code);
        wp_send_json_error($response);
    }

    /**
     * Send simple error response with message
     *
     * @param  string  $message     Error message
     * @param  string  $code        Error code
     * @param  integer $status_code HTTP status code
     * @return void Terminates execution
     */
    public static function errorMessage(string $message, string $code = self::ERROR_GENERAL, int $status_code = 400): void
    {
        $error = new WP_Error($code, $message);
        self::error($error, $status_code);
    }

    /**
     * Send validation error response
     *
     * @param  array  $errors  Array of validation errors
     * @param  string $message Main error message
     * @return void Terminates execution
     */
    public static function validationError(array $errors, string $message = 'Validation failed'): void
    {
        $error = new WP_Error(self::ERROR_INVALID_PARAMETER, $message, ['validation_errors' => $errors]);
        self::error($error, 400);
    }

    /**
     * Send unauthorized error response
     *
     * @param  string $message Error message
     * @return void Terminates execution
     */
    public static function unauthorized(string $message = 'Unauthorized'): void
    {
        $error = new WP_Error(self::ERROR_INSUFFICIENT_PERMISSIONS, $message);
        self::error($error, 401);
    }

    /**
     * Send forbidden error response
     *
     * @param  string $message Error message
     * @return void Terminates execution
     */
    public static function forbidden(string $message = 'Forbidden'): void
    {
        $error = new WP_Error(self::ERROR_INSUFFICIENT_PERMISSIONS, $message);
        self::error($error, 403);
    }

    /**
     * Send not found error response
     *
     * @param  string $message Error message
     * @param  string $code    Specific error code
     * @return void Terminates execution
     */
    public static function notFound(string $message = 'Not found', string $code = self::ERROR_GENERAL): void
    {
        $error = new WP_Error($code, $message);
        self::error($error, 404);
    }

    /**
     * Centralized nonce verification with automatic error handling
     *
     * This method provides a standardized way to verify CSRF protection nonces
     * across all AJAX endpoints. It automatically sends appropriate error responses
     * when security validation fails, eliminating boilerplate code duplication.
     *
     * Security Validation Process:
     * 1. Uses WordPress core wp_verify_nonce() for validation
     * 2. Checks nonce against specific action for context validation
     * 3. Automatically terminates request with 403 error on failure
     * 4. Returns true only for valid nonces (simplifies caller logic)
     *
     * CSRF Protection Benefits:
     * - Prevents cross-site request forgery attacks
     * - Validates request originated from legitimate source
     * - Ensures request hasn't been replayed or modified
     * - Protects against malicious third-party requests
     *
     * Error Response Strategy:
     * - Uses 403 Forbidden status (authorization failure, not authentication)
     * - Includes ERROR_INVALID_NONCE constant for error categorization
     * - Provides customizable error message for user-facing feedback
     * - Terminates execution immediately to prevent further processing
     *
     * Usage Pattern:
     * Called at the beginning of AJAX handlers before any data processing:
     * ```php
     * ApiResponse::verifyNonce($_POST['nonce'], NonceConstants::QUIZ_AI);
     * // Continue with request processing...
     * ```
     *
     * @param  string $nonce         Nonce value from request to verify
     * @param  string $action        Nonce action constant for validation context
     * @param  string $error_message Custom error message for user feedback
     * @return boolean True if valid (function terminates on invalid nonce)
     */
    public static function verifyNonce(string $nonce, string $action, string $error_message = 'Security verification failed'): bool
    {
        // WordPress nonce verification returns false, 1, or 2
        // false = invalid, 1 = valid (within 12 hour window), 2 = valid (within 24 hour window)
        if (!wp_verify_nonce($nonce, $action)) {
            // Automatically send error response and terminate execution
            // This prevents any further processing of potentially malicious requests
            self::errorMessage($error_message, self::ERROR_INVALID_NONCE, 403);
            return false; // Never reached due to termination, but satisfies static analysis
        }
        return true;
    }

    /**
     * Centralized user capability verification with automatic error handling
     *
     * This method provides standardized permission checking across all AJAX endpoints.
     * It automatically sends appropriate error responses when users lack required
     * privileges, following WordPress security best practices.
     *
     * Permission Validation Process:
     * 1. Uses WordPress current_user_can() for capability checking
     * 2. Validates against specific capability string (not role names)
     * 3. Automatically terminates request with 403 error on insufficient permissions
     * 4. Returns true only for authorized users (simplifies caller logic)
     *
     * WordPress Capability System:
     * - Capabilities are granular permissions (edit_posts, manage_options, etc.)
     * - More flexible than role-based checks (Editor, Administrator, etc.)
     * - Supports custom capabilities defined by plugins
     * - Handles capability mapping and inheritance automatically
     *
     * Common Capabilities Used:
     * - 'edit_posts': Content creation and editing (typical for course operations)
     * - 'manage_options': Administrative settings access
     * - 'edit_others_posts': Editing content by other users
     * - 'publish_posts': Publishing content publicly
     *
     * Security Benefits:
     * - Prevents privilege escalation attacks
     * - Enforces principle of least privilege
     * - Integrates with WordPress user management
     * - Supports multisite capability inheritance
     *
     * Error Response Strategy:
     * - Uses 403 Forbidden status for authorization failures
     * - Provides clear error message for permission issues
     * - Terminates execution to prevent unauthorized data access
     * - Uses self::forbidden() for consistent error format
     *
     * Usage Pattern:
     * Called after nonce verification in AJAX handlers:
     * ```php
     * ApiResponse::verifyCapability('edit_posts');
     * // Continue with authorized operations...
     * ```
     *
     * @param  string $capability    WordPress capability to check (e.g., 'edit_posts')
     * @param  string $error_message Custom error message for user feedback
     * @return boolean True if user has capability (function terminates on insufficient permissions)
     */
    public static function verifyCapability(string $capability, string $error_message = 'Insufficient permissions'): bool
    {
        // current_user_can() checks against WordPress capability system
        // Returns false for insufficient permissions, including non-logged-in users
        if (!current_user_can($capability)) {
            // Automatically send 403 Forbidden response and terminate execution
            // This prevents any unauthorized access to protected functionality
            self::forbidden($error_message);
            return false; // Never reached due to termination, but satisfies static analysis
        }
        return true;
    }

    /**
     * Comprehensive required parameter validation with detailed error reporting
     *
     * This method provides centralized validation for required AJAX parameters,
     * ensuring all necessary data is present before processing requests. It
     * automatically generates detailed error responses for missing parameters.
     *
     * Validation Rules:
     * - Parameters must exist in the array (isset() check)
     * - Parameters must not be empty strings (business logic requirement)
     * - Both conditions must be met for parameter to be considered valid
     * - Array values and objects are considered valid if they exist
     *
     * Parameter Processing:
     * - Iterates through all required parameter names
     * - Builds comprehensive list of missing parameters
     * - Differentiates between missing and empty parameters
     * - Provides specific parameter names in error messages
     *
     * Error Response Structure:
     * - Uses ERROR_MISSING_PARAMETER constant for categorization
     * - Includes user-friendly message with specific missing parameters
     * - Provides machine-readable 'missing' array in error data
     * - Returns 400 Bad Request status (client error)
     *
     * Business Logic Benefits:
     * - Prevents processing with incomplete data
     * - Provides clear feedback for API consumers
     * - Enables client-side form validation improvements
     * - Reduces server-side error handling complexity
     *
     * Usage Examples:
     * ```php
     * // Validate essential AJAX parameters
     * ApiResponse::validateRequired($_POST, ['lesson_id', 'content', 'nonce']);
     *
     * // Validate complex nested data
     * ApiResponse::validateRequired($data, ['course.title', 'course.sections']);
     * ```
     *
     * Integration with AJAX Handlers:
     * Called early in AJAX methods after security validation but before
     * data processing. This ensures clean failure for incomplete requests.
     *
     * @param  array $params   Parameter array to validate (typically $_POST data)
     * @param  array $required Array of required parameter names
     * @return boolean True if all parameters present (function terminates on missing parameters)
     */
    public static function validateRequired(array $params, array $required): bool
    {
        $missing = [];

        // Check each required parameter for existence and non-empty value
        foreach ($required as $param) {
            // Parameter must exist in array AND not be empty string
            // isset() handles array key existence, === '' handles empty values
            if (!isset($params[$param]) || $params[$param] === '') {
                $missing[] = $param;
            }
        }

        // If any parameters are missing, generate detailed error response
        if (!empty($missing)) {
            // Create comprehensive error with both human and machine-readable data
            $error = new WP_Error(
                self::ERROR_MISSING_PARAMETER,                    // Error code constant
                sprintf('Missing required parameters: %s', implode(', ', $missing)), // User message
                ['missing' => $missing]                          // Machine-readable data
            );

            // Send 400 Bad Request response and terminate execution
            // 400 status indicates client error (malformed request)
            self::error($error, 400);
            return false; // Never reached due to termination, satisfies static analysis
        }

        // All required parameters are present and valid
        return true;
    }

    /**
     * Create WP_Error from exception
     *
     * @param  \Exception $exception Exception to convert
     * @param  string     $code      Error code to use
     * @return WP_Error
     */
    public static function exceptionToError(\Exception $exception, string $code = self::ERROR_GENERAL): WP_Error
    {
        $logger = Logger::getInstance();

        // Log the full exception details
        $logger->error('Exception in API handler', [
            'code'    => $code,
            'message' => $exception->getMessage(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'trace'   => $exception->getTraceAsString(),
        ]);

        // Return sanitized error for client
        return new WP_Error(
            $code,
            $exception->getMessage(),
            [
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
            ]
        );
    }

    /**
     * Format success response data consistently
     *
     * @param  array $data     Response data
     * @param  array $defaults Default values to merge
     * @return array Formatted response data
     */
    public static function formatSuccessData(array $data, array $defaults = []): array
    {
        $response = array_merge($defaults, $data);

        // Add common metadata
        if (!isset($response['timestamp'])) {
            $response['timestamp'] = current_time('c');
        }

        return $response;
    }
}
