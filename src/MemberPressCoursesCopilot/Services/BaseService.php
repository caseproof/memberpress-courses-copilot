<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

/**
 * Base Service class
 * 
 * Abstract base class for all services in the plugin
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
abstract class BaseService
{
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
     * @param string $option_name The option name
     * @param mixed $default Default value if option doesn't exist
     * @return mixed
     */
    protected function getOption(string $option_name, mixed $default = null): mixed
    {
        return get_option($option_name, $default);
    }

    /**
     * Update WordPress option
     *
     * @param string $option_name The option name
     * @param mixed $value The option value
     * @return bool
     */
    protected function updateOption(string $option_name, mixed $value): bool
    {
        return update_option($option_name, $value);
    }

    /**
     * Delete WordPress option
     *
     * @param string $option_name The option name
     * @return bool
     */
    protected function deleteOption(string $option_name): bool
    {
        return delete_option($option_name);
    }

    /**
     * Log message for debugging
     *
     * @param string $message The message to log
     * @param string $level Log level (info, warning, error)
     * @return void
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[MemberPress Courses Copilot] [%s] %s',
                strtoupper($level),
                $message
            ));
        }
    }

    /**
     * Check if a WordPress plugin is active
     *
     * @param string $plugin_file Plugin file path
     * @return bool
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
     * @param int $timestamp When to run the event
     * @param string $hook The action hook to execute
     * @param array<mixed> $args Arguments to pass to the hook
     * @return bool
     */
    protected function scheduleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        return wp_schedule_single_event($timestamp, $hook, $args) !== false;
    }

    /**
     * Unschedule a WordPress cron event
     *
     * @param int $timestamp When the event was scheduled
     * @param string $hook The action hook
     * @param array<mixed> $args Arguments passed to the hook
     * @return bool
     */
    protected function unscheduleEvent(int $timestamp, string $hook, array $args = []): bool
    {
        return wp_unschedule_event($timestamp, $hook, $args) !== false;
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    protected function getCurrentUserId(): int
    {
        return get_current_user_id();
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    protected function isUserLoggedIn(): bool
    {
        return is_user_logged_in();
    }
}