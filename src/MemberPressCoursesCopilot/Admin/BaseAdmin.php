<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Admin;

/**
 * Base Admin class
 * 
 * Abstract base class for all admin functionality in the plugin
 * 
 * @package MemberPressCoursesCopilot\Admin
 * @since 1.0.0
 */
abstract class BaseAdmin
{
    /**
     * Admin page hook suffix
     *
     * @var string
     */
    protected string $hookSuffix = '';

    /**
     * Admin initialization
     * 
     * This method should be implemented by child admin classes
     * to handle their specific initialization logic
     *
     * @return void
     */
    abstract public function init(): void;

    /**
     * Register admin hooks
     * 
     * This method should be implemented by child admin classes
     * to register their specific admin hooks and filters
     *
     * @return void
     */
    abstract public function registerHooks(): void;

    /**
     * Check if current user can manage options
     *
     * @return bool
     */
    protected function canManageOptions(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Check if we're on a specific admin page
     *
     * @param string $page The page slug to check
     * @return bool
     */
    protected function isAdminPage(string $page): bool
    {
        global $pagenow;
        return $pagenow === $page;
    }

    /**
     * Check if we're on our plugin's admin page
     *
     * @return bool
     */
    protected function isPluginAdminPage(): bool
    {
        return isset($_GET['page']) && str_contains($_GET['page'], 'memberpress-courses-copilot');
    }

    /**
     * Get admin URL for a specific page
     *
     * @param string $page The page slug
     * @param array<string, mixed> $args Additional URL parameters
     * @return string
     */
    protected function getAdminUrl(string $page, array $args = []): string
    {
        $args['page'] = $page;
        return add_query_arg($args, admin_url('admin.php'));
    }

    /**
     * Add admin notice
     *
     * @param string $message The notice message
     * @param string $type Notice type (success, error, warning, info)
     * @param bool $dismissible Whether the notice is dismissible
     * @return void
     */
    protected function addNotice(string $message, string $type = 'info', bool $dismissible = true): void
    {
        $class = 'notice notice-' . $type;
        if ($dismissible) {
            $class .= ' is-dismissible';
        }

        add_action('admin_notices', function() use ($message, $class) {
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), wp_kses_post($message));
        });
    }

    /**
     * Enqueue admin styles
     *
     * @param string $handle Style handle
     * @param string $src Style source URL
     * @param array<string> $deps Dependencies
     * @param string|bool|null $ver Version
     * @return void
     */
    protected function enqueueStyle(string $handle, string $src, array $deps = [], string|bool|null $ver = false): void
    {
        wp_enqueue_style($handle, $src, $deps, $ver);
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $handle Script handle
     * @param string $src Script source URL
     * @param array<string> $deps Dependencies
     * @param string|bool|null $ver Version
     * @param bool $in_footer Whether to enqueue in footer
     * @return void
     */
    protected function enqueueScript(string $handle, string $src, array $deps = [], string|bool|null $ver = false, bool $in_footer = true): void
    {
        wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
    }

    /**
     * Localize script data
     *
     * @param string $handle Script handle
     * @param string $object_name Object name in JavaScript
     * @param array<string, mixed> $data Data to localize
     * @return void
     */
    protected function localizeScript(string $handle, string $object_name, array $data): void
    {
        wp_localize_script($handle, $object_name, $data);
    }

    /**
     * Render admin template
     *
     * @param string $template Template name
     * @param array<string, mixed> $data Data to pass to template
     * @return void
     */
    protected function renderTemplate(string $template, array $data = []): void
    {
        $template_path = MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/admin/' . $template . '.php';
        
        if (file_exists($template_path)) {
            extract($data, EXTR_SKIP);
            include $template_path;
        } else {
            wp_die(
                sprintf(
                    /* translators: %s: template name */
                    esc_html__('Admin template "%s" not found.', 'memberpress-courses-copilot'),
                    esc_html($template)
                )
            );
        }
    }

    /**
     * Get form field HTML
     *
     * @param string $type Field type
     * @param string $name Field name
     * @param mixed $value Field value
     * @param array<string, mixed> $attributes Additional attributes
     * @return string
     */
    protected function getFormField(string $type, string $name, mixed $value = '', array $attributes = []): string
    {
        $attrs = [];
        foreach ($attributes as $attr => $attr_value) {
            $attrs[] = esc_attr($attr) . '="' . esc_attr($attr_value) . '"';
        }
        $attrs_string = implode(' ', $attrs);

        return match($type) {
            'text', 'email', 'url', 'number' => sprintf(
                '<input type="%s" name="%s" value="%s" %s />',
                esc_attr($type),
                esc_attr($name),
                esc_attr($value),
                $attrs_string
            ),
            'textarea' => sprintf(
                '<textarea name="%s" %s>%s</textarea>',
                esc_attr($name),
                $attrs_string,
                esc_textarea($value)
            ),
            'select' => $this->getSelectField($name, $value, $attributes),
            'checkbox' => sprintf(
                '<input type="checkbox" name="%s" value="1" %s %s />',
                esc_attr($name),
                checked($value, true, false),
                $attrs_string
            ),
            default => ''
        };
    }

    /**
     * Get select field HTML
     *
     * @param string $name Field name
     * @param mixed $value Selected value
     * @param array<string, mixed> $attributes Field attributes
     * @return string
     */
    private function getSelectField(string $name, mixed $value, array $attributes): string
    {
        $options = $attributes['options'] ?? [];
        unset($attributes['options']);

        $attrs = [];
        foreach ($attributes as $attr => $attr_value) {
            $attrs[] = esc_attr($attr) . '="' . esc_attr($attr_value) . '"';
        }
        $attrs_string = implode(' ', $attrs);

        $html = sprintf('<select name="%s" %s>', esc_attr($name), $attrs_string);
        
        foreach ($options as $option_value => $option_label) {
            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($option_value),
                selected($value, $option_value, false),
                esc_html($option_label)
            );
        }
        
        $html .= '</select>';
        
        return $html;
    }
}