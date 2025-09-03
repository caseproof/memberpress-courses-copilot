<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Course Integration Service
 *
 * Handles the integration of AI Copilot functionality directly into
 * MemberPress Courses admin interface
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class CourseIntegrationService extends BaseService
{
    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Hook into MemberPress Courses admin pages
        add_action('admin_init', [$this, 'initializeCoursesIntegration']);

        // Hook into courses listing page to add "Create with AI" button
        // Use admin_enqueue_scripts to ensure proper timing
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueueCreateButton']);

        // Hook into course editor to add AI chat interface
        // Disabled metabox to prevent conflicts with center column implementation
        // add_action('add_meta_boxes', [$this, 'addAIAssistantMetaBox'], 20);
        // Add AI chat to center column of course edit page
        add_action('edit_form_after_editor', [$this, 'addAIChatToCenterColumn']);
    }

    /**
     * Initialize courses integration
     *
     * @return void
     */
    public function initializeCoursesIntegration(): void
    {
        // Only initialize if we're on MemberPress Courses pages
        if (!$this->isCoursesAdminPage()) {
            return;
        }

        // Add additional hooks for courses-specific functionality
        add_filter('screen_options_show_screen', [$this, 'enhanceScreenOptions'], 10, 2);
    }

    /**
     * Check if we're on a MemberPress Courses admin page
     *
     * @return boolean
     */
    private function isCoursesAdminPage(): bool
    {
        global $pagenow, $post_type;

        // Check if we're on the AI Course Generator page
        if (isset($_GET['page']) && $_GET['page'] === 'mpcc-course-generator') {
            return true;
        }

        // Check if we're on courses listing page
        if ($pagenow === 'edit.php' && $post_type === 'mpcs-course') {
            return true;
        }

        // Check if we're on course edit page
        if ($pagenow === 'post.php' || $pagenow === 'post-new.php') {
            if (isset($_GET['post_type']) && $_GET['post_type'] === 'mpcs-course') {
                return true;
            }

            // Check if editing existing course
            if (isset($_GET['post'])) {
                $post = get_post((int) $_GET['post']);
                if ($post && $post->post_type === 'mpcs-course') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Maybe enqueue "Create with AI" button script on courses listing page
     *
     * @param  string $hook The current admin page hook
     * @return void
     */
    public function maybeEnqueueCreateButton(string $hook): void
    {
        global $post_type;

        // Only proceed on the edit.php page
        if ($hook !== 'edit.php') {
            return;
        }

        // Check both global $post_type and $_GET parameter
        $current_post_type = $post_type ?: (isset($_GET['post_type']) ? $_GET['post_type'] : '');

        // Only add button on courses listing page
        if ($current_post_type !== 'mpcs-course') {
            return;
        }

        // Enqueue the button script
        wp_enqueue_script('mpcc-course-integration-create-button');

        // Pass data to JavaScript
        wp_localize_script('mpcc-course-integration-create-button', 'mpccCreateButton', [
            'strings'   => [
                'createWithAI' => __('Create with AI', 'memberpress-courses-copilot'),
            ],
            'editorUrl' => admin_url('admin.php?page=mpcc-course-editor&action=new'),
        ]);
    }

    /**
     * Add AI Assistant meta box to course editor
     *
     * @return void
     */
    public function addAIAssistantMetaBox(): void
    {
        global $post_type;

        if ($post_type !== 'mpcs-course') {
            return;
        }

        add_meta_box(
            'mpcc-ai-assistant',
            __('AI Assistant', 'memberpress-courses-copilot'),
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

        // Enqueue metabox script
        wp_enqueue_script('mpcc-course-integration-metabox');

        // Pass data to JavaScript
        wp_localize_script('mpcc-course-integration-metabox', 'mpccMetabox', [
            'nonce'   => NonceConstants::create(NonceConstants::AI_INTERFACE),
            'postId'  => $post->ID,
            'strings' => [
                'openAIChat'   => __('Open AI Chat', 'memberpress-courses-copilot'),
                'closeAIChat'  => __('Close AI Chat', 'memberpress-courses-copilot'),
                'failedToLoad' => __('Failed to load AI interface', 'memberpress-courses-copilot'),
            ],
        ]);

        ?>
        <div id="mpcc-ai-assistant-metabox">
            <p style="margin-bottom: 15px;">
                <?php esc_html_e('Get AI assistance while creating or editing your course.', 'memberpress-courses-copilot'); ?>
            </p>
            
            <div style="margin-bottom: 15px;">
                <button type="button" id="mpcc-toggle-ai-chat" class="button button-secondary" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; padding: 8px;">
                    <span class="dashicons dashicons-format-chat" style="line-height: 1;"></span>
                    <?php esc_html_e('Open AI Chat', 'memberpress-courses-copilot'); ?>
                </button>
            </div>
            
            <div id="mpcc-ai-chat-container" style="display: none; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9; padding: 15px; max-height: 400px; overflow-y: auto;">
                <div id="mpcc-ai-chat-loading" style="text-align: center; color: #666;">
                    <div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div>
                    <p style="margin: 0;"><?php esc_html_e('Loading AI Chat...', 'memberpress-courses-copilot'); ?></p>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p style="margin: 0; font-size: 12px; color: #666;">
                    <span class="dashicons dashicons-info-outline" style="font-size: 12px; vertical-align: middle;"></span>
                    <?php esc_html_e('The AI can help with content creation, course structure, and lesson planning.', 'memberpress-courses-copilot'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Enhance screen options for courses pages
     *
     * @param  boolean    $show_screen Whether to show screen options
     * @param  \WP_Screen $screen      Current screen object
     * @return boolean
     */
    public function enhanceScreenOptions(bool $show_screen, \WP_Screen $screen): bool
    {
        if ($screen->post_type === 'mpcs-course') {
            // Add additional screen options for AI features if needed
        }

        return $show_screen;
    }

    /**
     * Add AI Chat to center column of course edit page
     *
     * @param  \WP_Post $post Current post object
     * @return void
     */
    public function addAIChatToCenterColumn(\WP_Post $post): void
    {
        // Debug to see if this method is being called
        ?>
        <!-- MPCC Debug: addAIChatToCenterColumn called for post type: <?php echo esc_html($post->post_type); ?> -->
        <?php

        // Only add for course post type
        if ($post->post_type !== 'mpcs-course') {
            ?>
            <!-- MPCC Debug: Not a course post type (<?php echo esc_html($post->post_type); ?>), skipping AI chat -->
            <?php
            return;
        }

        ?>
        <!-- MPCC Debug: Adding AI chat for course ID: <?php echo esc_html($post->ID); ?> -->
        <?php

        // Get course metadata
        $sections   = get_post_meta($post->ID, '_mpcs_sections', true) ?: [];
        $courseData = [
            'id'       => $post->ID,
            'title'    => $post->post_title,
            'content'  => $post->post_content,
            'sections' => $sections,
            'status'   => $post->post_status,
        ];

        ?>
        <div id="mpcc-course-ai-chat-section" style="margin-top: 30px;">
            <h2><?php esc_html_e('AI Course Assistant', 'memberpress-courses-copilot'); ?></h2>
            
            <div id="mpcc-course-ai-chat-wrapper" style="background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 20px;">
                <div class="mpcc-course-ai-intro" style="margin-bottom: 20px; padding: 15px; background: #f0f7ff; border-left: 4px solid #667eea; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #1d2327;">
                        <span class="dashicons dashicons-format-chat" style="color: #667eea; vertical-align: middle;"></span>
                        <?php esc_html_e('Chat with AI to update your course', 'memberpress-courses-copilot'); ?>
                    </h3>
                    <p style="margin-bottom: 0; color: #50575e;">
                        <?php esc_html_e('Use the AI assistant below to help you improve your course content, add new lessons, create quizzes, or enhance learning objectives.', 'memberpress-courses-copilot'); ?>
                    </p>
                </div>
                
                <div id="mpcc-course-ai-chat-container" style="min-height: 400px; max-height: 600px; display: flex; flex-direction: column; border: 1px solid #dcdcde; border-radius: 8px; overflow: hidden;">
                    <div id="mpcc-course-ai-chat-loading" style="flex: 1; display: flex; align-items: center; justify-content: center;">
                        <div style="text-align: center;">
                            <div class="spinner is-active" style="float: none; margin: 0 auto 10px;"></div>
                            <p style="color: #666;"><?php esc_html_e('Loading AI Assistant...', 'memberpress-courses-copilot'); ?></p>
                        </div>
                    </div>
                </div>
                
                <div class="mpcc-course-context" style="margin-top: 15px; padding: 10px; background: #f6f7f7; border-radius: 4px; font-size: 12px; color: #646970;">
                    <strong><?php esc_html_e('Course Context:', 'memberpress-courses-copilot'); ?></strong>
                    <?php
                    $sectionCount = is_array($sections) ? count($sections) : 0;
                    $lessonCount  = 0;
                    if (is_array($sections)) {
                        foreach ($sections as $section) {
                            $lessonCount += isset($section['lessons']) && is_array($section['lessons']) ? count($section['lessons']) : 0;
                        }
                    }
                    ?>
                    <?php printf(
                        esc_html__('This course has %1$d sections and %2$d lessons.', 'memberpress-courses-copilot'),
                        $sectionCount,
                        $lessonCount
                    ); ?>
                </div>
            </div>
        </div>
        
        <?php
        // Enqueue center AI script
        wp_enqueue_script('mpcc-course-integration-center-ai');

        // Pass data to JavaScript
        wp_localize_script('mpcc-course-integration-center-ai', 'mpccCenterAI', [
            'nonce'   => NonceConstants::create(NonceConstants::AI_INTERFACE),
            'postId'  => $post->ID,
            'strings' => [
                'failedToLoad'        => __('Failed to load AI interface', 'memberpress-courses-copilot'),
                'failedToLoadNetwork' => __('Failed to load AI interface. Please try refreshing the page.', 'memberpress-courses-copilot'),
            ],
        ]);

        // Make course data available globally
        wp_add_inline_script('mpcc-course-integration-center-ai', 'window.courseData = ' . wp_json_encode($courseData) . ';', 'before');
        ?>
        <?php
    }
}