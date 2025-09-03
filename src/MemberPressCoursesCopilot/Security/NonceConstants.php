<?php


namespace MemberPressCoursesCopilot\Security;

/**
 * Centralized nonce constants for security
 *
 * This class defines all nonce actions used throughout the plugin
 * to ensure consistency and security.
 *
 * @package MemberPressCoursesCopilot\Security
 * @since   1.0.0
 */
class NonceConstants
{
    /**
     * Main nonces used across the plugin
     */
    // Primary nonce for course editor operations.
    public const EDITOR_NONCE = 'mpcc_editor_nonce';

    // Primary nonce for course integration/generation.
    public const COURSES_INTEGRATION = 'mpcc_courses_integration';

    // Primary nonce for AI interface operations.
    public const AI_INTERFACE = 'mpcc_ai_interface';

    // Legacy nonces (for backward compatibility).
    public const AJAX_NONCE    = 'mpcc_ajax_nonce';
    public const GENERIC_NONCE = 'mpcc_nonce';

    // Export/Import operations.
    public const EXPORT = 'mpcc_export';

    // AI Assistant and Course Creation.
    public const AI_ASSISTANT  = 'mpcc_ai_assistant';
    public const CREATE_COURSE = 'mpcc_create_course';

    // Settings.
    public const SAVE_SETTINGS = 'mpcc_save_settings';

    // Quiz AI operations.
    public const QUIZ_AI = 'mpcc_quiz_ai_nonce';

    /**
     * Session management nonces
     */
    public const AUTO_SAVE_SESSION = 'mpcc_auto_save_nonce';
    public const EXTEND_SESSION    = 'mpcc_extend_session_nonce';
    public const CLEANUP_SESSIONS  = 'mpcc_cleanup_sessions';

    /**
     * Quality and review nonces
     */
    public const QUALITY_FEEDBACK  = 'mpcc_quality_feedback';
    public const QUALITY_GATES     = 'mpcc_quality_gates';
    public const APPLY_IMPROVEMENT = 'mpcc_apply_improvement';
    public const REQUEST_REVIEW    = 'mpcc_request_review';
    public const CERTIFY_QUALITY   = 'mpcc_certify_quality';

    /**
     * Verify nonce with standardized error handling
     *
     * @param  string  $nonce  The nonce value to verify.
     * @param  string  $action The nonce action (use constants from this class).
     * @param  boolean $die    Whether to die on failure (default: true).
     * @return boolean True if valid, false if not (only when $die is false).
     */
    public static function verify(string $nonce, string $action, bool $die = true): bool
    {
        $valid = wp_verify_nonce($nonce, $action);

        if (!$valid && $die) {
            wp_die('Security check failed', 'Unauthorized', ['response' => 403]);
        }

        return (bool) $valid;
    }

    /**
     * Verify AJAX nonce with standardized error handling
     *
     * @param  string  $action   The nonce action (use constants from this class).
     * @param  string  $queryArg The query argument name (default: 'nonce').
     * @param  boolean $die      Whether to die on failure (default: true).
     * @return boolean True if valid.
     */
    public static function verifyAjax(string $action, string $queryArg = 'nonce', bool $die = true): bool
    {
        if ($die) {
            check_ajax_referer($action, $queryArg);
            return true;
        }

        return check_ajax_referer($action, $queryArg, false) !== false;
    }

    /**
     * Create a nonce for a given action
     *
     * @param  string $action The nonce action (use constants from this class).
     * @return string The nonce value.
     */
    public static function create(string $action): string
    {
        return wp_create_nonce($action);
    }

    /**
     * Create a nonce field for forms
     *
     * @param  string  $action  The nonce action (use constants from this class).
     * @param  string  $name    The nonce field name (default: '_wpnonce').
     * @param  boolean $referer Whether to add the referer field (default: true).
     * @param  boolean $echo    Whether to echo the field (default: true).
     * @return string The nonce field HTML.
     */
    public static function field(
        string $action,
        string $name = '_wpnonce',
        bool $referer = true,
        bool $echo = true
    ): string {
        return wp_nonce_field($action, $name, $referer, $echo);
    }
}
