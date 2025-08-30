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
            : '1.0.9';
            
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
        $this->registerStyle('mpcc-accessibility', 'assets/css/accessibility.css', []);
        $this->registerStyle('mpcc-courses-integration', 'assets/css/courses-integration.css', ['wp-components', 'mpcc-accessibility']);
        $this->registerStyle('mpcc-toast', 'assets/css/toast.css', ['dashicons']);
        $this->registerStyle('mpcc-course-editor', 'assets/css/course-editor-page.css', ['wp-components', 'mpcc-accessibility']);
        $this->registerStyle('mpcc-course-edit-ai-chat', 'assets/css/course-edit-ai-chat.css', ['dashicons', 'mpcc-accessibility']);
        $this->registerStyle('mpcc-ai-copilot', 'assets/css/ai-copilot.css', ['dashicons', 'mpcc-accessibility']);
        
        // Admin page styles
        $this->registerStyle('mpcc-admin-settings', 'assets/css/admin-settings.css', ['dashicons']);
        $this->registerStyle('mpcc-editor-ai-modal', 'assets/css/editor-ai-modal.css', ['mpcc-ai-copilot']);
        
        // Component styles
        // Removed mpcc-modal-styles and mpcc-metabox-styles - files don't exist
        
        // Quiz AI styles
        $this->registerStyle('mpcc-quiz-ai', 'assets/css/quiz-ai.css', ['dashicons']);
        
        // Quiz AI modal styles (matches course/lesson pattern)
        $this->registerStyle('mpcc-quiz-ai-modal', 'assets/css/quiz-ai-modal.css', ['dashicons', 'wp-components']);
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
            'mpcc-accessibility-utilities',
            'assets/js/accessibility-utilities.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-course-editor',
            'assets/js/course-editor-page.js',
            ['jquery', 'jquery-ui-sortable', 'wp-api', 'wp-components', 'wp-element', 'mpcc-toast', 'mpcc-shared-utilities', 'mpcc-accessibility-utilities']
        );
        
        $this->registerScript(
            'mpcc-course-edit-ai-chat',
            'assets/js/course-edit-ai-chat.js',
            ['jquery', 'mpcc-toast', 'mpcc-shared-utilities', 'mpcc-accessibility-utilities']
        );
        
        // Course Integration scripts (extracted from inline)
        $this->registerScript(
            'mpcc-course-integration-create-button',
            'assets/js/course-integration-create-button.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-course-integration-metabox',
            'assets/js/course-integration-metabox.js',
            ['jquery', 'mpcc-shared-utilities', 'mpcc-accessibility-utilities']
        );
        
        $this->registerScript(
            'mpcc-course-integration-center-ai',
            'assets/js/course-integration-center-ai.js',
            ['jquery']
        );
        
        // Editor AI Integration scripts (extracted from inline)
        $this->registerScript(
            'mpcc-editor-ai-button',
            'assets/js/editor-ai-button.js',
            ['jquery']
        );
        
        $this->registerScript(
            'mpcc-editor-ai-modal',
            'assets/js/editor-ai-modal.js',
            ['jquery', 'mpcc-shared-utilities', 'mpcc-accessibility-utilities']
        );
        
        // Template scripts (extracted from inline)
        $this->registerScript(
            'mpcc-ai-chat-interface',
            'assets/js/ai-chat-interface.js',
            ['jquery', 'mpcc-shared-utilities', 'mpcc-accessibility-utilities']
        );
        
        $this->registerScript(
            'mpcc-metabox-ai-assistant',
            'assets/js/metabox-ai-assistant.js',
            ['jquery']
        );
        
        // Quiz AI scripts
        $this->registerScript(
            'mpcc-quiz-ai-integration',
            'assets/js/quiz-ai-integration.js',
            ['jquery']
        );
        
        // Quiz AI simple version
        $this->registerScript(
            'mpcc-quiz-ai-integration-simple',
            'assets/js/quiz-ai-integration-simple.js',
            ['jquery']
        );
        
        // Quiz AI Copilot version (matches design system)
        $this->registerScript(
            'mpcc-quiz-ai-integration-copilot',
            'assets/js/quiz-ai-integration-copilot.js',
            ['jquery', 'wp-api']
        );
        
        // Quiz AI Modal version (matches course/lesson pattern)
        $this->registerScript(
            'mpcc-quiz-ai-modal',
            'assets/js/quiz-ai-modal.js',
            ['jquery', 'wp-blocks', 'wp-data', 'wp-element', 'wp-block-editor']
        );
        
        // Lesson Quiz Integration script
        $this->registerScript(
            'mpcc-lesson-quiz-integration',
            'assets/js/lesson-quiz-integration.js',
            ['jquery', 'wp-data', 'wp-editor']
        );
        
        // Test scripts
        $this->registerScript(
            'mpcc-ai-response-test',
            'tests/test-ai-response-structure.js',
            ['jquery', 'mpcc-course-editor']
        );
        
        $this->registerScript(
            'mpcc-manual-test-runner',
            'tests/manual-test-runner.js',
            ['jquery', 'mpcc-course-editor']
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
        
        // Course edit AI chat localizations
        wp_localize_script('mpcc-course-edit-ai-chat', 'mpccCourseChat', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::AI_ASSISTANT),
            'strings' => $this->getCourseEditChatStrings()
        ]);
        
        // Quiz AI Modal localizations
        wp_localize_script('mpcc-quiz-ai-modal', 'mpcc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::QUIZ_AI)
        ]);
        
        // Quiz AI Simple localizations
        wp_localize_script('mpcc-quiz-ai-integration-simple', 'mpcc_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::QUIZ_AI)
        ]);
        
        // AI chat interface localizations
        wp_localize_script('mpcc-ai-chat-interface', 'mpccChatInterface', [
            'strings' => [
                'confirmClear' => __('Are you sure you want to clear the chat history?', 'memberpress-courses-copilot'),
                'chatCleared' => __('Chat history has been cleared. How can I help you today?', 'memberpress-courses-copilot')
            ]
        ]);
        
        // Quiz AI localizations
        $quizAILocalization = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => NonceConstants::create(NonceConstants::QUIZ_AI),
            'strings' => [
                'generate_button' => __('Generate with AI', 'memberpress-courses-copilot'),
                'generating' => __('Generating questions...', 'memberpress-courses-copilot'),
                'error' => __('Error generating questions', 'memberpress-courses-copilot')
            ]
        ];
        
        wp_localize_script('mpcc-quiz-ai-integration', 'mpcc_ajax', $quizAILocalization);
        wp_localize_script('mpcc-quiz-ai-integration-simple', 'mpcc_ajax', $quizAILocalization);
        wp_localize_script('mpcc-quiz-ai-integration-copilot', 'mpcc_ajax', $quizAILocalization);
        wp_localize_script('mpcc-quiz-ai-modal', 'mpcc_ajax', $quizAILocalization);
        
        // Lesson quiz integration localizations
        wp_localize_script('mpcc-lesson-quiz-integration', 'mpcc_ajax', array_merge($quizAILocalization, [
            'admin_url' => admin_url(),
            'strings' => array_merge($quizAILocalization['strings'], [
                'create_quiz' => __('Create Quiz', 'memberpress-courses-copilot'),
                'creating_quiz' => __('Creating quiz...', 'memberpress-courses-copilot'),
                'quiz_created' => __('Quiz created successfully!', 'memberpress-courses-copilot')
            ])
        ]));
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
            // Quiz editor page assets
            if ($post && $post->post_type === 'mpcs-quiz') {
                $this->enqueueQuizEditorAssets();
            }
            // Lesson editor page assets
            if ($post && $post->post_type === 'mpcs-lesson') {
                $this->enqueueLessonEditorAssets();
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
        
        // Settings page
        if ($hook === 'mpcs-course_page_mpcc-settings' || $hook === 'settings_page_mpcc-settings') {
            wp_enqueue_style('mpcc-admin-settings');
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
        wp_enqueue_style('mpcc-modal-styles');
        
        wp_enqueue_script('mpcc-modal-component');
    }
    
    /**
     * Enqueue assets for course editor
     */
    private function enqueueCourseEditorAssets(): void
    {
        // Enqueue styles
        wp_enqueue_style('mpcc-toast');
        wp_enqueue_style('mpcc-course-editor'); // Add the course editor styles
        wp_enqueue_style('mpcc-course-edit-ai-chat');
        wp_enqueue_style('mpcc-ai-copilot'); // Needed for chat message styles
        
        // Enqueue scripts
        wp_enqueue_script('mpcc-toast');
        wp_enqueue_script('mpcc-shared-utilities');
        wp_enqueue_script('mpcc-accessibility-utilities');
        wp_enqueue_script('mpcc-course-edit-ai-chat');
        // Removed mpcc-simple-ai-chat - replaced by course-edit-ai-chat
    }
    
    /**
     * Enqueue assets for AI course generator
     */
    private function enqueueGeneratorAssets(): void
    {
        wp_enqueue_style('dashicons');
        wp_enqueue_style('mpcc-courses-integration');
        
        wp_enqueue_script('mpcc-courses-integration');
    }
    
    /**
     * Enqueue assets for quiz editor
     */
    private function enqueueQuizEditorAssets(): void
    {
        // Check if we're in Gutenberg editor or classic
        global $pagenow;
        $is_gutenberg = $pagenow === 'post-new.php' || 
                       ($pagenow === 'post.php' && function_exists('use_block_editor_for_post') && use_block_editor_for_post(get_post()));
        
        if ($is_gutenberg) {
            // Use modal version for Gutenberg
            wp_enqueue_style('mpcc-quiz-ai-modal');
            wp_enqueue_script('mpcc-quiz-ai-modal');
        } else {
            // Use simple version for classic editor
            wp_enqueue_style('mpcc-quiz-ai');
            wp_enqueue_script('mpcc-quiz-ai-integration-simple');
        }
    }
    
    /**
     * Enqueue assets for lesson editor
     */
    private function enqueueLessonEditorAssets(): void
    {
        // Check if we're in Gutenberg editor
        global $pagenow;
        $is_gutenberg = $pagenow === 'post-new.php' || 
                       ($pagenow === 'post.php' && function_exists('use_block_editor_for_post') && use_block_editor_for_post(get_post()));
        
        if ($is_gutenberg) {
            // Enqueue lesson quiz integration script
            wp_enqueue_script('mpcc-lesson-quiz-integration');
        }
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
        wp_enqueue_script('mpcc-shared-utilities');
        wp_enqueue_script('mpcc-accessibility-utilities');
        wp_enqueue_script('mpcc-course-editor');
        
        // Enqueue test scripts if test mode is enabled
        if (isset($_GET['test_mode']) && $_GET['test_mode'] === '1') {
            wp_enqueue_script('mpcc-ai-response-test');
            wp_enqueue_script('mpcc-manual-test-runner');
            error_log('MPCC: Test mode enabled - AI response test scripts enqueued');
        }
        
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
            'confirmClear' => __('Are you sure you want to clear the chat history?', 'memberpress-courses-copilot'),
            'chatCleared' => __('Chat history has been cleared. How can I help you today?', 'memberpress-courses-copilot'),
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
    
    /**
     * Get course edit chat strings for localization
     */
    private function getCourseEditChatStrings(): array
    {
        return [
            'thinking' => __('AI is thinking...', 'memberpress-courses-copilot'),
            'error' => __('Error: ', 'memberpress-courses-copilot'),
            'connectionError' => __('Connection error. Please try again.', 'memberpress-courses-copilot'),
            'send' => __('Send', 'memberpress-courses-copilot'),
            'updateSuccess' => __('Course updated successfully!', 'memberpress-courses-copilot'),
            'failedToLoad' => __('Failed to communicate with the AI. Please try again.', 'memberpress-courses-copilot')
        ];
    }
}