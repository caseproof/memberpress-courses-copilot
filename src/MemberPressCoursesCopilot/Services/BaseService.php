<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Base Service class
 *
 * Abstract base class for all services in the plugin
 * Provides centralized logging and common utilities
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
abstract class BaseService
{
    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Constructor - initialize logger for all services
     */
    public function __construct()
    {
        $this->logger = Logger::getInstance();
    }
    /**
     * Service initialization
     *
     * This method should be implemented by child services
     * to handle their specific initialization logic
     *
     * @return void
     */
    abstract public function init(): void;

    /**
     * Get WordPress option with default value
     *
     * @param  string $option_name The option name
     * @param  mixed  $default     Default value if option doesn't exist
     * @return mixed
     */
    protected function getOption(string $option_name, mixed $default = null): mixed
    {
        return get_option($option_name, $default);
    }

    /**
     * Update WordPress option
     *
     * @param  string $option_name The option name
     * @param  mixed  $value       The option value
     * @return boolean
     */
    protected function updateOption(string $option_name, mixed $value): bool
    {
        return update_option($option_name, $value);
    }

    /**
     * Delete WordPress option
     *
     * @param  string $option_name The option name
     * @return boolean
     */
    protected function deleteOption(string $option_name): bool
    {
        return delete_option($option_name);
    }

    /**
     * Log message using the centralized Logger (enhanced version)
     *
     * @param  string $message The message to log
     * @param  string $level   Log level (debug, info, warning, error, critical)
     * @param  array  $context Additional context data
     * @return void
     */
    protected function log(string $message, string $level = 'info', array $context = []): void
    {
        // Add service context automatically
        $context['service'] = static::class;

        // Map level to Logger methods
        switch (strtolower($level)) {
            case 'debug':
                $this->logger->debug($message, $context);
                break;
            case 'warning':
                $this->logger->warning($message, $context);
                break;
            case 'error':
                $this->logger->error($message, $context);
                break;
            case 'critical':
                $this->logger->critical($message, $context);
                break;
            case 'info':
            default:
                $this->logger->info($message, $context);
                break;
        }
    }

    /**
     * Log API call with cost tracking
     *
     * @param  string $provider Provider name (anthropic, openai, etc.)
     * @param  string $model    Model name
     * @param  array  $usage    Usage statistics
     * @param  float  $cost     Estimated cost
     * @param  array  $context  Additional context
     * @return void
     */
    protected function logApiCall(string $provider, string $model, array $usage, float $cost, array $context = []): void
    {
        $context['service'] = static::class;
        $this->logger->logApiCall($provider, $model, $usage, $cost, $context);
    }

    /**
     * Get logger instance for advanced logging features
     *
     * @return Logger
     */
    protected function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * Check if a WordPress plugin is active
     *
     * @param  string $plugin_file Plugin file path
     * @return boolean
     */
    protected function isPluginActive(string $plugin_file): bool
    {
        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin_file);
    }

    /**
     * Schedule a WordPress cron event
     *
     * @param  integer      $timestamp When to run the event
     * @param  string       $hook      The action hook to execute
     * @param  array<mixed> $args      Arguments to pass to the hook
     * @return boolean
     */
    protected function scheduleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        return wp_schedule_single_event($timestamp, $hook, $args) !== false;
    }

    /**
     * Unschedule a WordPress cron event
     *
     * @param  integer      $timestamp When the event was scheduled
     * @param  string       $hook      The action hook
     * @param  array<mixed> $args      Arguments passed to the hook
     * @return boolean
     */
    protected function unscheduleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        return wp_unschedule_event($timestamp, $hook, $args) !== false;
    }

    /**
     * Get current user ID
     *
     * @return integer
     */
    protected function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Check if user is logged in
     *
     * @return boolean
     */
    protected function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}
