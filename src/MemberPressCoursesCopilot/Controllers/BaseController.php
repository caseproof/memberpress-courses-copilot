<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

/**
 * Base Controller class
 * 
 * Abstract base class for all controllers in the plugin
 * 
 * @package MemberPressCoursesCopilot\Controllers
 * @since 1.0.0
 */
abstract class BaseController
{
    /**
     * Controller initialization
     * 
     * This method should be implemented by child controllers
     * to handle their specific initialization logic
     *
     * @return void
     */
    abstract public function init(): void;

    /**
     * Register WordPress hooks
     * 
     * This method should be implemented by child controllers
     * to register their specific hooks and filters
     *
     * @return void
     */
    abstract public function registerHooks(): void;

    /**
     * Check if current user has required capability
     *
     * @param string $capability The capability to check
     * @return bool
     */
    protected function userCan(string $capability): bool
    {
        return current_user_can($capability);
    }

    /**
     * Check if we're in WordPress admin
     *
     * @return bool
     */
    protected function isAdmin(): bool
    {
        return is_admin();
    }

    /**
     * Check if current request is AJAX
     *
     * @return bool
     */
    protected function isAjax(): bool
    {
        return wp_doing_ajax();
    }

    /**
     * Check if current request is REST API
     *
     * @return bool
     */
    protected function isRest(): bool
    {
        return defined('REST_REQUEST') && REST_REQUEST;
    }

    /**
     * Verify nonce for security
     *
     * @param string $nonce The nonce to verify
     * @param string $action The action name
     * @return bool
     */
    protected function verifyNonce(string $nonce, string $action): bool
    {
        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Sanitize and validate input data
     *
     * @param mixed $data The data to sanitize
     * @param string $type The type of sanitization to apply
     * @return mixed
     */
    protected function sanitizeInput(mixed $data, string $type = 'text'): mixed
    {
        return match($type) {
            'email' => sanitize_email($data),
            'url' => sanitize_url($data),
            'int' => (int) $data,
            'float' => (float) $data,
            'bool' => (bool) $data,
            'textarea' => sanitize_textarea_field($data),
            'html' => wp_kses_post($data),
            default => sanitize_text_field($data)
        };
    }
}