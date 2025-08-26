<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Centralized Asset Management Service
 * 
 * Handles all script and style registration/enqueuing for the plugin
 */
class AssetManager extends BaseService
{
    /**
     * Asset version for cache busting
     */
    private string $version;
    
    /**
     * Plugin URL for asset paths
     */
    private string $pluginUrl;
    
    /**
     * Registered scripts
     */
    private array $scripts = [];
    
    /**
     * Registered styles
     */
    private array $styles = [];
    
    /**
     * Initialize the asset manager
     */
    public function init(): void
    {
        $this->version = defined('MEMBERPRESS_COURSES_COPILOT_VERSION') 
            ? MEMBERPRESS_COURSES_COPILOT_VERSION 
            : '1.0.0';
            
        $this->pluginUrl = defined('MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL') 
            ? MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL 
            : '';
            
        // Register all assets early
        add_action('init', [$this, 'registerAssets'], 5);
        
        // Hook into various enqueue points
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }
    
    /**
     * Register all plugin assets
     */
    public function registerAssets(): void
    {
        error_log('MPCC: Registering assets');
        $this->registerStyles();
        $this->registerScripts();
        error_log('MPCC: Assets registered');
    }
    
    /**
     * Register all styles
     */
    private function registerStyles(): void
    {
        // Core styles
        $this->registerStyle('mpcc-courses-integration', 'assets/css/courses-integration.css', ['wp-components']);
        $this->registerStyle('mpcc-ai-copilot', 'assets/css/ai-copilot.css', ['dashicons']);
        $this->registerStyle('mpcc-toast', 'assets/css/toast.css', ['dashicons']);
        $this->registerStyle('mpcc-course-editor', 'assets/css/course-editor-page.css', ['wp-components']);
        
        // Component styles
        $this->registerStyle('mpcc-modal-styles', 'assets/css/modal.css');
        $this->registerStyle('mpcc-metabox-styles', 'assets/css/metabox.css');
    }
    
    /**
     * Register all scripts
     */
    private function registerScripts(): void
    {
        // Core scripts
        $this->registerScript(
            'mpcc-courses-integration',
            'assets/js/courses-integration.js',
            ['jquery', 'wp-element', 'wp-components']
        );
        
        $this->registerScript(
            'mpcc-simple-ai-chat',
            'assets/js/simple-ai-chat.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-toast',
            'assets/js/toast.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-shared-utilities',
            'assets/js/shared-utilities.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-course-editor',
            'assets/js/course-editor-page.js',
            ['jquery', 'wp-api', 'wp-components', 'wp-element', 'mpcc-toast', 'mpcc-shared-utilities']
        );
        
        // Component scripts
        $this->registerScript(
            'mpcc-modal-component',
            'assets/js/modal-component.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-metabox-component',
            'assets/js/metabox-component.js',
            ['jquery']
        );
        
        // Add localizations
        $this->addScriptLocalizations();
    }
    
    /**
     * Register a style
     */
    private function registerStyle(string $handle, string $path, array $deps = []): void
    {
        $this->styles[$handle] = [
            'path' => $path,
            'deps' => $deps,
            'registered' => false
        ];
        
        wp_register_style(
            $handle,
            $this->pluginUrl . $path,
            $deps,
            $this->version
        );
    }
    
    /**
     * Register a script
     */
    private function registerScript(string $handle, string $path, array $deps = [], bool $inFooter = true): void
    {
        $this->scripts[$handle] = [
            'path' => $path,
            'deps' => $deps,
            'in_footer' => $inFooter,
            'registered' => false
        ];
        
        wp_register_script(
            $handle,
            $this->pluginUrl . $path,
            $deps,
            $this->version,
            $inFooter
        );
    }
    
    /**
     * Add script localizations
     */
    private function addScriptLocalizations(): void
    {
        // Course integration localizations
        wp_localize_script('mpcc-courses-integration', 'mpccCoursesIntegration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::COURSES_INTEGRATION),
            'strings' => $this->getIntegrationStrings()
        ]);
        
        // Course editor localizations
        wp_localize_script('mpcc-course-editor', 'mpccEditorSettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiUrl' => home_url('/wp-json/wp/v2/'),
            'nonce' => NonceConstants::create(NonceConstants::EDITOR_NONCE),
            'strings' => $this->getEditorStrings()
        ]);
        
        // Simple AI chat localizations
        wp_localize_script('mpcc-simple-ai-chat', 'mpccSimpleChat', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::COURSES_INTEGRATION),
            'strings' => $this->getChatStrings()
        ]);
    }
    
    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        // Currently no frontend assets needed
    }
    
    /**
     * Enqueue admin assets based on context
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Debug logging
        error_log('MPCC: AssetManager enqueueAdminAssets called with hook: ' . $hook);
        
        // Course listing page assets
        if ($hook === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'mpcs-course') {
            $this->enqueueCourseListingAssets();
        }
        
        // Course editor page assets
        if ($hook === 'post.php' || $hook === 'post-new.php') {
            global $post;
            if ($post && $post->post_type === 'mpcs-course') {
                $this->enqueueCourseEditorAssets();
            }
        }
        
        // AI Course Generator page
        if ($hook === 'mpcs-course_page_mpcc-course-generator') {
            $this->enqueueGeneratorAssets();
        }
        
        // Status page
        if ($hook === 'mpcs-course_page_mpcc-status') {
            wp_enqueue_style('mpcc-courses-integration');
        }
        
        // Standalone editor page - check multiple possible hook formats
        if (strpos($hook, 'mpcc-course-editor') !== false || 
            $hook === 'toplevel_page_mpcc-course-editor' ||
            $hook === 'admin_page_mpcc-course-editor') {
            error_log('MPCC: Enqueuing standalone editor assets for hook: ' . $hook);
            $this->enqueueStandaloneEditorAssets();
        }
    }
    
    /**
     * Enqueue assets for course listing page
     */
    private function enqueueCourseListingAssets(): void
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('mpcc-courses-integration');
        wp_enqueue_style('mpcc-ai-copilot');
        wp_enqueue_style('mpcc-modal-styles');
        
        wp_enqueue_script('mpcc-simple-ai-chat');
        wp_enqueue_script('mpcc-modal-component');
    }
    
    /**
     * Enqueue assets for course editor
     */
    private function enqueueCourseEditorAssets(): void
    {
        wp_enqueue_style('mpcc-metabox-styles');
        wp_enqueue_script('mpcc-metabox-component');
    }
    
    /**
     * Enqueue assets for AI course generator
     */
    private function enqueueGeneratorAssets(): void
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('mpcc-courses-integration');
        wp_enqueue_style('mpcc-ai-copilot');
        
        wp_enqueue_script('mpcc-courses-integration');
    }
    
    /**
     * Enqueue assets for standalone editor
     */
    private function enqueueStandaloneEditorAssets(): void
    {
        // Ensure dependencies are enqueued first
        wp_enqueue_style('dashicons');
        wp_enqueue_style('wp-components');
        
        // Enqueue our styles
        wp_enqueue_style('mpcc-course-editor');
        wp_enqueue_style('mpcc-toast');
        
        // Ensure script dependencies
        wp_enqueue_script('jquery');
        wp_enqueue_script('wp-api');
        wp_enqueue_script('wp-components');
        wp_enqueue_script('wp-element');
        
        // Enqueue our scripts
        wp_enqueue_script('mpcc-toast');
        wp_enqueue_script('mpcc-course-editor');
        
        error_log('MPCC: Standalone editor assets enqueued');
    }
    
    /**
     * Get integration strings for localization
     */
    private function getIntegrationStrings(): array
    {
        return [
            'generating' => __('Generating course...', 'memberpress-courses-copilot'),
            'error' => __('An error occurred. Please try again.', 'memberpress-courses-copilot'),
            'success' => __('Course generated successfully!', 'memberpress-courses-copilot'),
            'createWithAI' => __('Create with AI', 'memberpress-courses-copilot'),
            'aiAssistant' => __('AI Assistant', 'memberpress-courses-copilot'),
            'loading' => __('Loading...', 'memberpress-courses-copilot')
        ];
    }
    
    /**
     * Get editor strings for localization
     */
    private function getEditorStrings(): array
    {
        return [
            'saving' => __('Saving...', 'memberpress-courses-copilot'),
            'saved' => __('Saved', 'memberpress-courses-copilot'),
            'error' => __('Error', 'memberpress-courses-copilot'),
            'generateContent' => __('Generate with AI', 'memberpress-courses-copilot'),
            'generating' => __('Generating...', 'memberpress-courses-copilot')
        ];
    }
    
    /**
     * Get chat strings for localization
     */
    private function getChatStrings(): array
    {
        return [
            'thinking' => __('AI is thinking...', 'memberpress-courses-copilot'),
            'error' => __('Error: ', 'memberpress-courses-copilot'),
            'connectionError' => __('Connection error. Please try again.', 'memberpress-courses-copilot'),
            'send' => __('Send', 'memberpress-courses-copilot'),
            'placeholder' => __('Ask me to create a course or help with course content...', 'memberpress-courses-copilot'),
            'welcome' => __('Hi! I can help you create engaging courses. What would you like to build today?', 'memberpress-courses-copilot'),
            'networkError' => __('Network error. Please check your connection and try again.', 'memberpress-courses-copilot'),
            'processingError' => __('Error processing response. Please try again.', 'memberpress-courses-copilot'),
            'createCourse' => __('Create Course', 'memberpress-courses-copilot'),
            'creatingCourse' => __('Creating course...', 'memberpress-courses-copilot'),
            'previousConversations' => __('Previous Conversations', 'memberpress-courses-copilot'),
            'newConversation' => __('New Conversation', 'memberpress-courses-copilot'),
            'conversationHistory' => __('Conversation History', 'memberpress-courses-copilot'),
            'noHistory' => __('No previous conversations found.', 'memberpress-courses-copilot'),
            'loadConversation' => __('Load', 'memberpress-courses-copilot'),
            'deleteConversation' => __('Delete', 'memberpress-courses-copilot'),
            'confirmDelete' => __('Are you sure you want to delete this conversation?', 'memberpress-courses-copilot'),
            'today' => __('Today', 'memberpress-courses-copilot'),
            'yesterday' => __('Yesterday', 'memberpress-courses-copilot'),
            'daysAgo' => __('%d days ago', 'memberpress-courses-copilot'),
            'conversationDeleted' => __('Conversation deleted successfully.', 'memberpress-courses-copilot'),
            'conversationLoadError' => __('Failed to load conversation. Please try again.', 'memberpress-courses-copilot')
        ];
    }
    
    /**
     * Enqueue a specific style by handle
     */
    public function enqueueStyle(string $handle): void
    {
        if (isset($this->styles[$handle])) {
            wp_enqueue_style($handle);
        }
    }
    
    /**
     * Enqueue a specific script by handle
     */
    public function enqueueScript(string $handle): void
    {
        if (isset($this->scripts[$handle])) {
            wp_enqueue_script($handle);
        }
    }
}