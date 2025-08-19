<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Error Handling Service
 * 
 * Centralized error handling, user feedback, and recovery mechanisms
 * for the MemberPress Courses Copilot plugin.
 */
class ErrorHandlingService
{
    private Logger $logger;
    private array $errorCodes;
    private array $errorStats = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->initializeErrorCodes();
    }

    /**
     * Handle AI service errors with appropriate recovery
     */
    public function handleAIServiceError(\Exception $error, string $context, array $metadata = []): array
    {
        $errorCode = $this->categorizeError($error);
        $this->logError($error, $context, $errorCode, $metadata);
        $this->updateErrorStats($errorCode);
        
        return [
            'success' => false,
            'error_code' => $errorCode,
            'user_message' => $this->getUserMessage($errorCode),
            'technical_message' => $error->getMessage(),
            'recovery_suggestions' => $this->getRecoverySuggestions($errorCode),
            'should_retry' => $this->shouldRetry($errorCode),
            'retry_delay' => $this->getRetryDelay($errorCode)
        ];
    }

    /**
     * Handle provider connection errors
     */
    public function handleProviderError(\Exception $error, string $provider, string $operation): array
    {
        $errorCode = $this->categorizeProviderError($error, $provider);
        
        $this->logger->error('Provider error occurred', [
            'provider' => $provider,
            'operation' => $operation,
            'error_code' => $errorCode,
            'error_message' => $error->getMessage(),
            'stack_trace' => $error->getTraceAsString()
        ]);

        return [
            'success' => false,
            'error_code' => $errorCode,
            'provider' => $provider,
            'user_message' => $this->getProviderUserMessage($errorCode, $provider),
            'should_fallback' => $this->shouldUseFallback($errorCode),
            'should_retry' => $this->shouldRetry($errorCode),
            'retry_delay' => $this->getRetryDelay($errorCode)
        ];
    }

    /**
     * Handle authentication and authorization errors
     */
    public function handleAuthError(\Exception $error, array $context = []): array
    {
        $errorCode = 'AUTH_ERROR';
        
        if (strpos($error->getMessage(), 'expired') !== false) {
            $errorCode = 'AUTH_EXPIRED';
        } elseif (strpos($error->getMessage(), 'invalid') !== false) {
            $errorCode = 'AUTH_INVALID';
        } elseif (strpos($error->getMessage(), 'unauthorized') !== false) {
            $errorCode = 'AUTH_UNAUTHORIZED';
        }

        $this->logger->error('Authentication error', [
            'error_code' => $errorCode,
            'error_message' => $error->getMessage(),
            'context' => $context
        ]);

        return [
            'success' => false,
            'error_code' => $errorCode,
            'user_message' => $this->getAuthUserMessage($errorCode),
            'requires_admin' => true,
            'recovery_action' => $this->getAuthRecoveryAction($errorCode)
        ];
    }

    /**
     * Handle rate limiting errors
     */
    public function handleRateLimitError(\Exception $error, string $provider): array
    {
        $errorCode = 'RATE_LIMIT_EXCEEDED';
        
        $this->logger->warning('Rate limit exceeded', [
            'provider' => $provider,
            'error_message' => $error->getMessage(),
            'timestamp' => time()
        ]);

        // Extract retry delay from error message if available
        $retryDelay = $this->extractRetryDelay($error->getMessage());

        return [
            'success' => false,
            'error_code' => $errorCode,
            'provider' => $provider,
            'user_message' => 'The AI service is temporarily busy. Please try again in a few moments.',
            'should_retry' => true,
            'retry_delay' => $retryDelay,
            'should_queue' => true
        ];
    }

    /**
     * Handle content generation errors
     */
    public function handleContentError(\Exception $error, string $contentType, array $context = []): array
    {
        $errorCode = $this->categorizeContentError($error, $contentType);
        
        $this->logger->error('Content generation error', [
            'content_type' => $contentType,
            'error_code' => $errorCode,
            'error_message' => $error->getMessage(),
            'context' => $context
        ]);

        return [
            'success' => false,
            'error_code' => $errorCode,
            'content_type' => $contentType,
            'user_message' => $this->getContentUserMessage($errorCode, $contentType),
            'fallback_available' => $this->hasFallbackContent($contentType),
            'should_retry' => $this->shouldRetry($errorCode),
            'alternative_approaches' => $this->getAlternativeApproaches($contentType)
        ];
    }

    /**
     * Handle validation errors
     */
    public function handleValidationError(array $validationErrors, string $context): array
    {
        $errorCode = 'VALIDATION_ERROR';
        
        $this->logger->warning('Validation errors occurred', [
            'context' => $context,
            'validation_errors' => $validationErrors
        ]);

        return [
            'success' => false,
            'error_code' => $errorCode,
            'validation_errors' => $validationErrors,
            'user_message' => 'Please check your input and try again.',
            'field_errors' => $this->formatFieldErrors($validationErrors)
        ];
    }

    /**
     * Get error statistics for monitoring
     */
    public function getErrorStatistics(int $timeframe = 24): array
    {
        $cutoffTime = time() - ($timeframe * 3600); // Hours to seconds
        $stats = get_option('mpc_error_stats', []);
        
        // Filter by timeframe
        $recentStats = array_filter($stats, function($stat) use ($cutoffTime) {
            return $stat['timestamp'] >= $cutoffTime;
        });

        // Aggregate statistics
        $aggregated = [];
        foreach ($recentStats as $stat) {
            $code = $stat['error_code'];
            if (!isset($aggregated[$code])) {
                $aggregated[$code] = [
                    'count' => 0,
                    'first_occurrence' => $stat['timestamp'],
                    'last_occurrence' => $stat['timestamp'],
                    'contexts' => []
                ];
            }
            
            $aggregated[$code]['count']++;
            $aggregated[$code]['last_occurrence'] = max($aggregated[$code]['last_occurrence'], $stat['timestamp']);
            $aggregated[$code]['contexts'][] = $stat['context'];
        }

        return [
            'timeframe_hours' => $timeframe,
            'total_errors' => count($recentStats),
            'error_breakdown' => $aggregated,
            'most_common_error' => $this->getMostCommonError($aggregated),
            'error_rate_per_hour' => count($recentStats) / max($timeframe, 1)
        ];
    }

    /**
     * Initialize error codes and messages
     */
    private function initializeErrorCodes(): void
    {
        $this->errorCodes = [
            'AI_SERVICE_UNAVAILABLE' => 'The AI service is temporarily unavailable',
            'AI_TIMEOUT' => 'The AI service took too long to respond',
            'AI_QUOTA_EXCEEDED' => 'AI service quota has been exceeded',
            'AI_INVALID_RESPONSE' => 'The AI service returned an invalid response',
            'PROVIDER_CONNECTION_FAILED' => 'Failed to connect to the AI provider',
            'PROVIDER_AUTHENTICATION_FAILED' => 'Authentication with AI provider failed',
            'RATE_LIMIT_EXCEEDED' => 'Rate limit exceeded for AI service',
            'CONTENT_GENERATION_FAILED' => 'Failed to generate content',
            'CONTENT_PARSING_FAILED' => 'Failed to parse generated content',
            'VALIDATION_ERROR' => 'Input validation failed',
            'AUTH_ERROR' => 'Authentication error',
            'AUTH_EXPIRED' => 'Authentication credentials have expired',
            'AUTH_INVALID' => 'Invalid authentication credentials',
            'AUTH_UNAUTHORIZED' => 'Unauthorized access',
            'NETWORK_ERROR' => 'Network connectivity issue',
            'CONFIGURATION_ERROR' => 'Service configuration error',
            'UNEXPECTED_ERROR' => 'An unexpected error occurred'
        ];
    }

    /**
     * Categorize error type based on exception
     */
    private function categorizeError(\Exception $error): string
    {
        $message = strtolower($error->getMessage());
        
        if (strpos($message, 'timeout') !== false || strpos($message, 'timed out') !== false) {
            return 'AI_TIMEOUT';
        }
        
        if (strpos($message, 'quota') !== false || strpos($message, 'limit') !== false) {
            return 'AI_QUOTA_EXCEEDED';
        }
        
        if (strpos($message, 'rate limit') !== false || strpos($message, 'too many requests') !== false) {
            return 'RATE_LIMIT_EXCEEDED';
        }
        
        if (strpos($message, 'unauthorized') !== false || strpos($message, 'authentication') !== false) {
            return 'PROVIDER_AUTHENTICATION_FAILED';
        }
        
        if (strpos($message, 'connection') !== false || strpos($message, 'network') !== false) {
            return 'PROVIDER_CONNECTION_FAILED';
        }
        
        if (strpos($message, 'json') !== false || strpos($message, 'parse') !== false) {
            return 'AI_INVALID_RESPONSE';
        }
        
        return 'UNEXPECTED_ERROR';
    }

    /**
     * Categorize provider-specific errors
     */
    private function categorizeProviderError(\Exception $error, string $provider): string
    {
        $baseCategory = $this->categorizeError($error);
        
        // Add provider-specific handling if needed
        $message = strtolower($error->getMessage());
        
        if ($provider === 'anthropic' && strpos($message, 'claude') !== false) {
            return 'ANTHROPIC_SERVICE_ERROR';
        }
        
        if ($provider === 'openai' && strpos($message, 'openai') !== false) {
            return 'OPENAI_SERVICE_ERROR';
        }
        
        return $baseCategory;
    }

    /**
     * Categorize content generation errors
     */
    private function categorizeContentError(\Exception $error, string $contentType): string
    {
        $baseCategory = $this->categorizeError($error);
        
        if ($baseCategory === 'AI_INVALID_RESPONSE') {
            return 'CONTENT_PARSING_FAILED';
        }
        
        if ($baseCategory === 'UNEXPECTED_ERROR') {
            return 'CONTENT_GENERATION_FAILED';
        }
        
        return $baseCategory;
    }

    /**
     * Get user-friendly error message
     */
    private function getUserMessage(string $errorCode): string
    {
        $userMessages = [
            'AI_SERVICE_UNAVAILABLE' => 'The AI service is temporarily unavailable. Please try again later.',
            'AI_TIMEOUT' => 'The request took too long to complete. Please try again with a shorter request.',
            'AI_QUOTA_EXCEEDED' => 'The daily usage limit has been reached. Please try again tomorrow or contact an administrator.',
            'AI_INVALID_RESPONSE' => 'The AI service returned an unexpected response. Please try again.',
            'PROVIDER_CONNECTION_FAILED' => 'Unable to connect to the AI service. Please check your internet connection and try again.',
            'PROVIDER_AUTHENTICATION_FAILED' => 'Authentication with the AI service failed. Please contact an administrator.',
            'RATE_LIMIT_EXCEEDED' => 'Too many requests have been made. Please wait a moment and try again.',
            'CONTENT_GENERATION_FAILED' => 'Failed to generate content. Please try again or contact support.',
            'CONTENT_PARSING_FAILED' => 'Generated content could not be processed. Please try again.',
            'NETWORK_ERROR' => 'Network connectivity issue. Please check your connection and try again.',
            'CONFIGURATION_ERROR' => 'Service configuration issue. Please contact an administrator.',
            'UNEXPECTED_ERROR' => 'An unexpected error occurred. Please try again or contact support.'
        ];
        
        return $userMessages[$errorCode] ?? 'An error occurred. Please try again.';
    }

    /**
     * Get provider-specific user message
     */
    private function getProviderUserMessage(string $errorCode, string $provider): string
    {
        $baseMessage = $this->getUserMessage($errorCode);
        
        if (strpos($errorCode, 'AUTHENTICATION') !== false) {
            return "Authentication failed with {$provider}. Please check your API credentials.";
        }
        
        if (strpos($errorCode, 'CONNECTION') !== false) {
            return "Unable to connect to {$provider} service. Please try again later.";
        }
        
        return $baseMessage;
    }

    /**
     * Get authentication error user message
     */
    private function getAuthUserMessage(string $errorCode): string
    {
        $authMessages = [
            'AUTH_EXPIRED' => 'Your authentication credentials have expired. Please contact an administrator to refresh them.',
            'AUTH_INVALID' => 'Invalid authentication credentials. Please contact an administrator.',
            'AUTH_UNAUTHORIZED' => 'You are not authorized to perform this action. Please contact an administrator.',
            'AUTH_ERROR' => 'Authentication error. Please contact an administrator.'
        ];
        
        return $authMessages[$errorCode] ?? $authMessages['AUTH_ERROR'];
    }

    /**
     * Get content-specific user message
     */
    private function getContentUserMessage(string $errorCode, string $contentType): string
    {
        $contentTypeNames = [
            'course_outline' => 'course outline',
            'lesson_content' => 'lesson content',
            'quiz_questions' => 'quiz questions',
            'assignment' => 'assignment'
        ];
        
        $typeName = $contentTypeNames[$contentType] ?? 'content';
        $baseMessage = $this->getUserMessage($errorCode);
        
        return str_replace('content', $typeName, $baseMessage);
    }

    /**
     * Get recovery suggestions
     */
    private function getRecoverySuggestions(string $errorCode): array
    {
        $suggestions = [
            'AI_TIMEOUT' => [
                'Try breaking your request into smaller parts',
                'Simplify your prompt or requirements',
                'Try again during off-peak hours'
            ],
            'AI_QUOTA_EXCEEDED' => [
                'Wait until the quota resets',
                'Contact administrator to increase quota',
                'Use more efficient generation strategies'
            ],
            'RATE_LIMIT_EXCEEDED' => [
                'Wait a few minutes before trying again',
                'Reduce the frequency of requests',
                'Use batch operations when possible'
            ],
            'PROVIDER_CONNECTION_FAILED' => [
                'Check your internet connection',
                'Try again in a few minutes',
                'Contact support if the problem persists'
            ]
        ];
        
        return $suggestions[$errorCode] ?? ['Try again later', 'Contact support if the problem persists'];
    }

    /**
     * Get authentication recovery action
     */
    private function getAuthRecoveryAction(string $errorCode): string
    {
        $actions = [
            'AUTH_EXPIRED' => 'refresh_credentials',
            'AUTH_INVALID' => 'reconfigure_auth',
            'AUTH_UNAUTHORIZED' => 'check_permissions',
            'AUTH_ERROR' => 'check_configuration'
        ];
        
        return $actions[$errorCode] ?? 'check_configuration';
    }

    /**
     * Determine if operation should be retried
     */
    private function shouldRetry(string $errorCode): bool
    {
        $retryableErrors = [
            'AI_TIMEOUT',
            'PROVIDER_CONNECTION_FAILED',
            'NETWORK_ERROR',
            'RATE_LIMIT_EXCEEDED'
        ];
        
        return in_array($errorCode, $retryableErrors);
    }

    /**
     * Determine if fallback should be used
     */
    private function shouldUseFallback(string $errorCode): bool
    {
        $fallbackErrors = [
            'AI_SERVICE_UNAVAILABLE',
            'PROVIDER_CONNECTION_FAILED',
            'PROVIDER_AUTHENTICATION_FAILED'
        ];
        
        return in_array($errorCode, $fallbackErrors);
    }

    /**
     * Get retry delay in seconds
     */
    private function getRetryDelay(string $errorCode): int
    {
        $delays = [
            'AI_TIMEOUT' => 30,
            'RATE_LIMIT_EXCEEDED' => 60,
            'PROVIDER_CONNECTION_FAILED' => 15,
            'NETWORK_ERROR' => 10
        ];
        
        return $delays[$errorCode] ?? 5;
    }

    /**
     * Check if fallback content is available
     */
    private function hasFallbackContent(string $contentType): bool
    {
        $fallbackTypes = ['lesson_content', 'course_outline'];
        return in_array($contentType, $fallbackTypes);
    }

    /**
     * Get alternative approaches for content generation
     */
    private function getAlternativeApproaches(string $contentType): array
    {
        $approaches = [
            'lesson_content' => [
                'Use a template-based approach',
                'Generate content in smaller sections',
                'Try a different AI model'
            ],
            'course_outline' => [
                'Start with a basic structure',
                'Use existing course templates',
                'Build outline incrementally'
            ],
            'quiz_questions' => [
                'Generate fewer questions at a time',
                'Use simpler question formats',
                'Focus on key concepts only'
            ]
        ];
        
        return $approaches[$contentType] ?? ['Try a different approach', 'Contact support for assistance'];
    }

    /**
     * Extract retry delay from error message
     */
    private function extractRetryDelay(string $message): int
    {
        // Look for patterns like "retry after 60 seconds" or "try again in 2 minutes"
        if (preg_match('/retry after (\d+) seconds?/i', $message, $matches)) {
            return (int)$matches[1];
        }
        
        if (preg_match('/try again in (\d+) minutes?/i', $message, $matches)) {
            return (int)$matches[1] * 60;
        }
        
        return 60; // Default to 1 minute
    }

    /**
     * Format field-specific validation errors
     */
    private function formatFieldErrors(array $validationErrors): array
    {
        $formatted = [];
        
        foreach ($validationErrors as $field => $errors) {
            $formatted[$field] = [
                'field' => $field,
                'errors' => is_array($errors) ? $errors : [$errors],
                'user_message' => is_array($errors) ? implode(', ', $errors) : $errors
            ];
        }
        
        return $formatted;
    }

    /**
     * Log error with context
     */
    private function logError(\Exception $error, string $context, string $errorCode, array $metadata): void
    {
        $this->logger->error('Error handled by ErrorHandlingService', [
            'error_code' => $errorCode,
            'context' => $context,
            'error_message' => $error->getMessage(),
            'error_file' => $error->getFile(),
            'error_line' => $error->getLine(),
            'metadata' => $metadata,
            'stack_trace' => $error->getTraceAsString()
        ]);
    }

    /**
     * Update error statistics
     */
    private function updateErrorStats(string $errorCode): void
    {
        $stats = get_option('mpc_error_stats', []);
        
        $stats[] = [
            'error_code' => $errorCode,
            'timestamp' => time(),
            'context' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        // Keep only last 1000 entries
        if (count($stats) > 1000) {
            $stats = array_slice($stats, -1000);
        }
        
        update_option('mpc_error_stats', $stats);
    }

    /**
     * Get most common error from aggregated stats
     */
    private function getMostCommonError(array $aggregated): ?string
    {
        if (empty($aggregated)) {
            return null;
        }
        
        $maxCount = 0;
        $mostCommon = null;
        
        foreach ($aggregated as $errorCode => $data) {
            if ($data['count'] > $maxCount) {
                $maxCount = $data['count'];
                $mostCommon = $errorCode;
            }
        }
        
        return $mostCommon;
    }
}