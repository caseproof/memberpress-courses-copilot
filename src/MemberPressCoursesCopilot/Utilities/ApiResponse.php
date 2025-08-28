<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Utilities;

use WP_Error;

/**
 * Standardized API Response Handler
 * 
 * Provides consistent response format for all AJAX endpoints
 * Following KISS principle with static methods for simplicity
 * 
 * @package MemberPressCoursesCopilot\Utilities
 * @since 1.0.0
 */
class ApiResponse
{
    /**
     * Standard error codes used throughout the plugin
     */
    const ERROR_INVALID_NONCE = 'mpcc_invalid_nonce';
    const ERROR_INSUFFICIENT_PERMISSIONS = 'mpcc_insufficient_permissions';
    const ERROR_MISSING_PARAMETER = 'mpcc_missing_parameter';
    const ERROR_INVALID_PARAMETER = 'mpcc_invalid_parameter';
    const ERROR_DATABASE_ERROR = 'mpcc_database_error';
    const ERROR_AI_SERVICE = 'mpcc_ai_service_error';
    const ERROR_COURSE_GENERATION = 'mpcc_course_generation_error';
    const ERROR_SESSION_NOT_FOUND = 'mpcc_session_not_found';
    const ERROR_CONTENT_GENERATION = 'mpcc_content_generation_error';
    const ERROR_GENERAL = 'mpcc_general_error';
    
    /**
     * Send successful JSON response
     *
     * @param mixed $data Data to return
     * @param string $message Optional success message
     * @param array $meta Optional metadata
     * @return void Terminates execution
     */
    public static function success($data = null, string $message = '', array $meta = []): void
    {
        $response = [
            'success' => true,
            'data' => $data
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
     * @param WP_Error $error WordPress error object
     * @param int $status_code HTTP status code (default 400)
     * @return void Terminates execution
     */
    public static function error(WP_Error $error, int $status_code = 400): void
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $error->get_error_code(),
                'message' => $error->get_error_message(),
                'data' => $error->get_error_data()
            ]
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
     * @param string $message Error message
     * @param string $code Error code
     * @param int $status_code HTTP status code
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
     * @param array $errors Array of validation errors
     * @param string $message Main error message
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
     * @param string $message Error message
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
     * @param string $message Error message
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
     * @param string $message Error message
     * @param string $code Specific error code
     * @return void Terminates execution
     */
    public static function notFound(string $message = 'Not found', string $code = self::ERROR_GENERAL): void
    {
        $error = new WP_Error($code, $message);
        self::error($error, 404);
    }
    
    /**
     * Check nonce and send error if invalid
     *
     * @param string $nonce Nonce value to verify
     * @param string $action Nonce action
     * @param string $error_message Custom error message
     * @return bool True if valid, sends error and terminates if invalid
     */
    public static function verifyNonce(string $nonce, string $action, string $error_message = 'Security verification failed'): bool
    {
        if (!wp_verify_nonce($nonce, $action)) {
            self::errorMessage($error_message, self::ERROR_INVALID_NONCE, 403);
            return false; // Never reached, but satisfies IDE
        }
        return true;
    }
    
    /**
     * Check user capability and send error if insufficient
     *
     * @param string $capability Capability to check
     * @param string $error_message Custom error message
     * @return bool True if capable, sends error and terminates if not
     */
    public static function verifyCapability(string $capability, string $error_message = 'Insufficient permissions'): bool
    {
        if (!current_user_can($capability)) {
            self::forbidden($error_message);
            return false; // Never reached, but satisfies IDE
        }
        return true;
    }
    
    /**
     * Validate required parameters and send error if missing
     *
     * @param array $params Parameters to check
     * @param array $required Required parameter names
     * @return bool True if all present, sends error and terminates if not
     */
    public static function validateRequired(array $params, array $required): bool
    {
        $missing = [];
        
        foreach ($required as $param) {
            if (!isset($params[$param]) || $params[$param] === '') {
                $missing[] = $param;
            }
        }
        
        if (!empty($missing)) {
            $error = new WP_Error(
                self::ERROR_MISSING_PARAMETER,
                sprintf('Missing required parameters: %s', implode(', ', $missing)),
                ['missing' => $missing]
            );
            self::error($error, 400);
            return false; // Never reached
        }
        
        return true;
    }
    
    /**
     * Create WP_Error from exception
     *
     * @param \Exception $exception Exception to convert
     * @param string $code Error code to use
     * @return WP_Error
     */
    public static function exceptionToError(\Exception $exception, string $code = self::ERROR_GENERAL): WP_Error
    {
        $logger = Logger::getInstance();
        
        // Log the full exception details
        $logger->error('Exception in API handler', [
            'code' => $code,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ]);
        
        // Return sanitized error for client
        return new WP_Error(
            $code,
            $exception->getMessage(),
            [
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine()
            ]
        );
    }
    
    /**
     * Format success response data consistently
     *
     * @param array $data Response data
     * @param array $defaults Default values to merge
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