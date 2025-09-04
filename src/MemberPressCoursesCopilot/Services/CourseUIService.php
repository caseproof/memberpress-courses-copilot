<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Services\EnhancedTemplateEngine;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Course UI Service
 *
 * Handles all UI rendering responsibilities for the MemberPress Courses Copilot.
 * This service is responsible for rendering buttons, modals, meta boxes, and other
 * UI components, separating presentation concerns from business logic.
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class CourseUIService extends BaseService
{
    /**
     * Template engine instance
     *
     * @var EnhancedTemplateEngine
     */
    private EnhancedTemplateEngine $templateEngine;

    /**
     * Constructor
     *
     * @param EnhancedTemplateEngine $templateEngine Template engine instance
     */
    public function __construct(EnhancedTemplateEngine $templateEngine)
    {
        parent::__construct();
        $this->templateEngine = $templateEngine;
    }

    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Set up global template data
        $this->templateEngine->setGlobalDataArray([
            'plugin_version' => defined('MEMBERPRESS_COURSES_COPILOT_VERSION')
                ? MEMBERPRESS_COURSES_COPILOT_VERSION
                : '1.0.0',
            'text_domain'    => 'memberpress-courses-copilot',
            'ajax_url'       => admin_url('admin-ajax.php'),
            'nonce'          => NonceConstants::create(NonceConstants::COURSES_INTEGRATION),
        ]);

        // Register hooks for UI rendering
        add_action('admin_footer-edit.php', [$this, 'renderCreateWithAIButton']);
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes'], 20);
    }

    /**
     * Render "Generate" button on courses listing page
     *
     * @return void
     */
    public function renderCreateWithAIButton(): void
    {
        global $post_type;

        if ($post_type !== 'mpcs-course') {
            return;
        }

        $this->logger->debug('Rendering Generate button');

        // Use template engine to render button
        echo $this->templateEngine->renderComponent('buttons/create-with-ai', [
            'button_text'  => __('Generate', 'memberpress-courses-copilot'),
            'button_class' => 'page-title-action mpcc-gradient-button',
            'icon'         => 'dashicons-lightbulb',
        ]);

        // Enqueue necessary scripts
        $this->enqueueModalScripts();
    }

    /**
     * Register meta boxes
     *
     * @return void
     */
    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'mpcc-ai-assistant',
            __('AI Course Assistant', 'memberpress-courses-copilot'),
            [$this, 'renderAIAssistantMetaBox'],
            'mpcs-course',
            'side',
            'high'
        );
    }

    /**
     * Render AI Assistant meta box
     *
     * @param  \WP_Post $post Current post object
     * @return void
     */
    public function renderAIAssistantMetaBox(\WP_Post $post): void
    {
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_assistant_nonce');

        $this->logger->debug('Rendering AI Assistant meta box', [
            'post_id' => $post->ID,
        ]);

        // Use template engine to render meta box
        echo $this->templateEngine->renderComponent('metabox/course-edit-ai-assistant', [
            'post_id'     => $post->ID,
            'post_title'  => $post->post_title,
            'post_status' => $post->post_status,
            'is_new'      => $post->post_status === 'auto-draft',
        ]);

        // Enqueue meta box scripts
        $this->enqueueMetaBoxScripts();
    }

    /**
     * Render AI interface container
     *
     * @param  string  $context Interface context
     * @param  integer $postId  Optional post ID
     * @return string HTML output
     */
    public function renderAIInterface(string $context = 'course_creation', int $postId = 0): string
    {
        $this->logger->info('Rendering AI interface', [
            'context' => $context,
            'post_id' => $postId,
        ]);

        // Load the appropriate template based on context
        $template = $context === 'course_editing'
            ? 'components/chat/editing-interface'
            : 'components/chat/creation-interface';

        return $this->templateEngine->render($template, [
            'context'         => $context,
            'post_id'         => $postId,
            'welcome_message' => $this->getWelcomeMessage($context),
            'quick_actions'   => $this->getQuickActions($context),
            'session_enabled' => true,
        ]);
    }

    /**
     * Render course preview
     *
     * @param  array $courseData Course data structure
     * @return string HTML output
     */
    public function renderCoursePreview(array $courseData): string
    {
        $this->logger->debug('Rendering course preview', [
            'course_title'   => $courseData['title'] ?? 'Untitled',
            'sections_count' => count($courseData['sections'] ?? []),
        ]);

        return $this->templateEngine->renderComponent('preview/course-structure', [
            'course'       => $courseData,
            'show_actions' => true,
        ]);
    }

    /**
     * Render modal wrapper
     *
     * @param  array $options Modal options
     * @return string HTML output
     */
    public function renderModal(array $options = []): string
    {
        $defaults = [
            'id'            => 'mpcc-ai-modal',
            'title'         => __('Create Course with AI', 'memberpress-courses-copilot'),
            'loading_text'  => __('Loading AI Assistant...', 'memberpress-courses-copilot'),
            'preview_title' => __('Course Preview', 'memberpress-courses-copilot'),
            'show_close'    => true,
            'dual_pane'     => true,
        ];

        $options = array_merge($defaults, $options);

        return $this->templateEngine->renderComponent('modal/ai-creation-modal', $options);
    }

    /**
     * Render loading indicator
     *
     * @param  string $message Loading message
     * @return string HTML output
     */
    public function renderLoadingIndicator(string $message = ''): string
    {
        if (empty($message)) {
            $message = __('Loading...', 'memberpress-courses-copilot');
        }

        return $this->templateEngine->renderComponent('ui/loading-indicator', [
            'message' => $message,
        ]);
    }

    /**
     * Render error message
     *
     * @param  string $message Error message
     * @param  array  $details Additional error details
     * @return string HTML output
     */
    public function renderError(string $message, array $details = []): string
    {
        return $this->templateEngine->renderComponent('ui/error-message', [
            'message'     => $message,
            'details'     => $details,
            'dismissible' => true,
        ]);
    }

    /**
     * Render success message
     *
     * @param  string $message Success message
     * @param  array  $actions Optional action buttons
     * @return string HTML output
     */
    public function renderSuccess(string $message, array $actions = []): string
    {
        return $this->templateEngine->renderComponent('ui/success-message', [
            'message'     => $message,
            'actions'     => $actions,
            'dismissible' => true,
        ]);
    }

    /**
     * Get welcome message based on context
     *
     * @param  string $context Interface context
     * @return string Welcome message
     */
    private function getWelcomeMessage(string $context): string
    {
        $messages = [
            'course_creation' => __('Hi! I\'m here to help you create an amazing course. What kind of course would you like to build?', 'memberpress-courses-copilot'),
            'course_editing'  => __('How can I help you improve this course?', 'memberpress-courses-copilot'),
            'lesson_creation' => __('Let\'s create engaging lesson content together!', 'memberpress-courses-copilot'),
            'default'         => __('How can I assist you today?', 'memberpress-courses-copilot'),
        ];

        return $messages[$context] ?? $messages['default'];
    }

    /**
     * Get quick actions based on context
     *
     * @param  string $context Interface context
     * @return array Quick action buttons
     */
    private function getQuickActions(string $context): array
    {
        $actions = [
            'course_creation' => [
                [
                    'text'   => __('Programming Course', 'memberpress-courses-copilot'),
                    'prompt' => __('I want to create a programming course', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-editor-code',
                ],
                [
                    'text'   => __('Business Course', 'memberpress-courses-copilot'),
                    'prompt' => __('I want to create a business skills course', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-chart-line',
                ],
                [
                    'text'   => __('Creative Course', 'memberpress-courses-copilot'),
                    'prompt' => __('I want to create a creative arts course', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-art',
                ],
            ],
            'course_editing'  => [
                [
                    'text'   => __('Add Lessons', 'memberpress-courses-copilot'),
                    'prompt' => __('Help me add more lessons to this course', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-plus-alt2',
                ],
                [
                    'text'   => __('Improve Content', 'memberpress-courses-copilot'),
                    'prompt' => __('Suggest improvements for the course content', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-edit',
                ],
                [
                    'text'   => __('Create Quiz', 'memberpress-courses-copilot'),
                    'prompt' => __('Help me create a quiz for this course', 'memberpress-courses-copilot'),
                    'icon'   => 'dashicons-forms',
                ],
            ],
        ];

        return $actions[$context] ?? [];
    }

    /**
     * Enqueue modal scripts
     *
     * @return void
     */
    private function enqueueModalScripts(): void
    {
        // Asset enqueuing is now handled by AssetManager service
        // Scripts and styles are already registered and will be enqueued based on context
    }

    /**
     * Enqueue meta box scripts
     *
     * @return void
     */
    private function enqueueMetaBoxScripts(): void
    {
        // Asset enqueuing is now handled by AssetManager service
        // Scripts and styles are already registered and will be enqueued based on context
        // Removed localization for non-existent mpcc-metabox-component script
    }

    /**
     * Render inline styles
     *
     * @return void
     */
    public function renderInlineStyles(): void
    {
        echo $this->templateEngine->render('admin/partials/inline-styles', [
            'primary_color'   => '#667eea',
            'secondary_color' => '#764ba2',
            'gradient'        => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
        ]);
    }

    /**
     * Render inline scripts
     *
     * @return void
     */
    public function renderInlineScripts(): void
    {
        echo $this->templateEngine->render('admin/partials/inline-scripts', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => NonceConstants::create(NonceConstants::COURSES_INTEGRATION),
        ]);
    }
}
