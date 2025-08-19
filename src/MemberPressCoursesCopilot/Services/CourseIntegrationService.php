<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;

/**
 * Course Integration Service
 * 
 * Handles the integration of AI Copilot functionality directly into
 * MemberPress Courses admin interface
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
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
        add_action('admin_enqueue_scripts', [$this, 'enqueueIntegrationAssets']);
        
        // Hook into courses listing page to add "Create with AI" button
        add_action('admin_footer-edit.php', [$this, 'addCreateWithAIButton']);
        
        // Hook into course editor to add AI chat interface
        add_action('add_meta_boxes', [$this, 'addAIAssistantMetaBox'], 20);
        
        // Handle AJAX requests for AI integration
        add_action('wp_ajax_mpcc_load_ai_interface', [$this, 'loadAIInterface']);
        add_action('wp_ajax_mpcc_create_course_with_ai', [$this, 'createCourseWithAI']);
        add_action('wp_ajax_mpcc_ai_chat', [$this, 'handleAIChat']);
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
     * @return bool
     */
    private function isCoursesAdminPage(): bool
    {
        global $pagenow, $post_type;
        
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
     * Add "Create with AI" button to courses listing page
     *
     * @return void
     */
    public function addCreateWithAIButton(): void
    {
        global $post_type;
        
        // Only add button on courses listing page
        if ($post_type !== 'mpcs-course') {
            return;
        }
        
        ?>
        <script type="text/javascript">
        // Define global function that uses jQuery properly
        window.mpccOpenAIInterface = function() {
            jQuery(document).ready(function($) {
                // Create modal for AI course creation
                var modalHtml = '<div id="mpcc-ai-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">' +
                    '<div style="background-color: #fefefe; margin: 5% auto; padding: 0; border: none; border-radius: 8px; width: 90%; max-width: 1200px; height: 85%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                        '<div style="display: flex; height: 100%;">' +
                            '<div style="flex: 1; padding: 0; height: 100%;">' +
                                '<div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0;">' +
                                    '<h2 style="margin: 0; color: white;"><?php echo esc_js(__('Create Course with AI', 'memberpress-courses-copilot')); ?></h2>' +
                                    '<span id="mpcc-close-modal" style="cursor: pointer; font-size: 24px; font-weight: bold; color: white;">&times;</span>' +
                                '</div>' +
                                '<div id="mpcc-ai-interface-container" style="height: calc(100% - 80px); padding: 0;">' +
                                    '<div style="display: flex; justify-content: center; align-items: center; height: 100%; color: #666;">' +
                                        '<div style="text-align: center;">' +
                                            '<div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>' +
                                            '<p><?php echo esc_js(__('Loading AI Assistant...', 'memberpress-courses-copilot')); ?></p>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
                
                $('body').append(modalHtml);
                $('#mpcc-ai-modal').show();
                
                // Close modal events
                $('#mpcc-close-modal, #mpcc-ai-modal').on('click', function(e) {
                    if (e.target === this) {
                        $('#mpcc-ai-modal').remove();
                    }
                });
                
                // Load AI interface via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_load_ai_interface',
                        nonce: '<?php echo wp_create_nonce('mpcc_ai_interface'); ?>',
                        context: 'course_creation'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#mpcc-ai-interface-container').html(response.data.html);
                            // Initialize the AI chat interface
                            if (typeof window.initializeMPCCAIInterface === 'function') {
                                window.initializeMPCCAIInterface('course_creation');
                            }
                        } else {
                            $('#mpcc-ai-interface-container').html('<div style="padding: 20px; text-align: center; color: #d63638;"><p>' + (response.data || '<?php echo esc_js(__('Failed to load AI interface', 'memberpress-courses-copilot')); ?>') + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#mpcc-ai-interface-container').html('<div style="padding: 20px; text-align: center; color: #d63638;"><p><?php echo esc_js(__('Failed to load AI interface', 'memberpress-courses-copilot')); ?></p></div>');
                    }
                });
            });
        };
        
        jQuery(document).ready(function($) {
            // Add "Create with AI" button next to "Add New Course"
            var createWithAIButton = '<a href="#" id="mpcc-create-with-ai" class="page-title-action" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-shadow: none;">' + 
                '<span class="dashicons dashicons-robot" style="margin-right: 5px; vertical-align: middle; line-height: 1;"></span>' +
                '<?php echo esc_js(__('Create with AI', 'memberpress-courses-copilot')); ?>' +
                '</a>';
            
            $('.wrap .wp-header-end').before(createWithAIButton);
            
            // Handle click event
            $('#mpcc-create-with-ai').on('click', function(e) {
                e.preventDefault();
                window.mpccOpenAIInterface();
            });
        });
        </script>
        <?php
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
     * @param \WP_Post $post Current post object
     * @return void
     */
    public function renderAIAssistantMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('mpcc_ai_assistant', 'mpcc_ai_assistant_nonce');
        
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
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#mpcc-toggle-ai-chat').on('click', function() {
                var $container = $('#mpcc-ai-chat-container');
                var $button = $(this);
                
                if ($container.is(':visible')) {
                    $container.slideUp();
                    $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-format-chat');
                    $button.find('span:not(.dashicons)').text('<?php echo esc_js(__('Open AI Chat', 'memberpress-courses-copilot')); ?>');
                } else {
                    $container.slideDown();
                    $button.find('.dashicons').removeClass('dashicons-format-chat').addClass('dashicons-dismiss');
                    $button.find('span:not(.dashicons)').text('<?php echo esc_js(__('Close AI Chat', 'memberpress-courses-copilot')); ?>');
                    
                    // Load AI interface if not already loaded
                    if (!$container.data('loaded')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'mpcc_load_ai_interface',
                                nonce: '<?php echo wp_create_nonce('mpcc_ai_interface'); ?>',
                                context: 'course_editing',
                                post_id: <?php echo (int) $post->ID; ?>
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#mpcc-ai-chat-container').html(response.data.html).data('loaded', true);
                                    // Initialize the AI chat interface
                                    if (typeof window.initializeMPCCAIInterface === 'function') {
                                        window.initializeMPCCAIInterface('course_editing', <?php echo (int) $post->ID; ?>);
                                    }
                                } else {
                                    $('#mpcc-ai-chat-container').html('<div style="text-align: center; color: #d63638;"><p>' + (response.data || '<?php echo esc_js(__('Failed to load AI interface', 'memberpress-courses-copilot')); ?>') + '</p></div>');
                                }
                            },
                            error: function() {
                                $('#mpcc-ai-chat-container').html('<div style="text-align: center; color: #d63638;"><p><?php echo esc_js(__('Failed to load AI interface', 'memberpress-courses-copilot')); ?></p></div>');
                            }
                        });
                    }
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Enqueue integration assets
     *
     * @param string $hook_suffix Current admin page hook suffix
     * @return void
     */
    public function enqueueIntegrationAssets(string $hook_suffix): void
    {
        if (!$this->isCoursesAdminPage()) {
            return;
        }
        
        // Enqueue AI interface CSS
        wp_enqueue_style(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/courses-integration.css',
            [],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );
        
        // Enqueue AI interface JavaScript
        wp_enqueue_script(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/courses-integration.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Localize script with needed data
        wp_localize_script('mpcc-courses-integration', 'mpccCoursesIntegration', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpcc_courses_integration'),
            'strings' => [
                'createWithAI' => __('Create with AI', 'memberpress-courses-copilot'),
                'aiAssistant' => __('AI Assistant', 'memberpress-courses-copilot'),
                'loading' => __('Loading...', 'memberpress-courses-copilot'),
                'error' => __('An error occurred. Please try again.', 'memberpress-courses-copilot'),
            ]
        ]);
    }

    /**
     * Handle AJAX request to load AI interface
     *
     * @return void
     */
    public function loadAIInterface(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_ai_interface')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $context = sanitize_text_field($_POST['context'] ?? '');
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        
        try {
            // Generate the AI interface HTML
            ob_start();
            $this->renderAIInterface($context, $post_id);
            $html = ob_get_clean();
            
            wp_send_json_success([
                'html' => $html,
                'context' => $context,
                'post_id' => $post_id
            ]);
        } catch (\Exception $e) {
            wp_send_json_error('Failed to load AI interface: ' . $e->getMessage());
        }
    }

    /**
     * Render AI interface HTML
     *
     * @param string $context Interface context (course_creation, course_editing)
     * @param int $post_id Post ID for editing context
     * @return void
     */
    private function renderAIInterface(string $context, int $post_id = 0): void
    {
        // Load the AI chat interface template
        $template_path = MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/ai-chat-interface.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            // Fallback basic interface
            ?>
            <div id="mpcc-ai-chat-interface" class="mpcc-ai-interface" data-context="<?php echo esc_attr($context); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
                <div class="mpcc-chat-messages" style="height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: white;">
                    <div class="mpcc-welcome-message" style="padding: 20px; text-align: center; color: #666;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🤖</div>
                        <h3 style="margin: 0 0 10px 0;"><?php esc_html_e('AI Course Assistant', 'memberpress-courses-copilot'); ?></h3>
                        <p style="margin: 0;">
                            <?php if ($context === 'course_creation'): ?>
                                <?php esc_html_e('Hi! I\'m here to help you create an amazing course. What kind of course would you like to build?', 'memberpress-courses-copilot'); ?>
                            <?php else: ?>
                                <?php esc_html_e('Hi! I\'m here to help you improve your course. What would you like to work on?', 'memberpress-courses-copilot'); ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="mpcc-chat-input-container">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="mpcc-chat-input" placeholder="<?php esc_attr_e('Type your message here...', 'memberpress-courses-copilot'); ?>" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <button type="button" id="mpcc-send-message" class="button button-primary" style="padding: 10px 20px;">
                            <?php esc_html_e('Send', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <script type="text/javascript">
            // Initialize basic chat functionality
            jQuery(document).ready(function($) {
                $('#mpcc-send-message').on('click', function() {
                    var message = $('#mpcc-chat-input').val().trim();
                    if (message) {
                        // Add user message to chat
                        var userMessage = '<div style="margin-bottom: 15px; text-align: right;"><div style="display: inline-block; background: #0073aa; color: white; padding: 10px 15px; border-radius: 18px; max-width: 70%;">' + message + '</div></div>';
                        $('.mpcc-chat-messages').append(userMessage);
                        $('.mpcc-chat-messages').scrollTop($('.mpcc-chat-messages')[0].scrollHeight);
                        $('#mpcc-chat-input').val('');
                        
                        // Show typing indicator
                        var typingIndicator = '<div id="mpcc-typing" style="margin-bottom: 15px;"><div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px;"><span style="animation: pulse 1.5s infinite;">AI is typing...</span></div></div>';
                        $('.mpcc-chat-messages').append(typingIndicator);
                        $('.mpcc-chat-messages').scrollTop($('.mpcc-chat-messages')[0].scrollHeight);
                        
                        // TODO: Implement actual AI communication
                        setTimeout(function() {
                            $('#mpcc-typing').remove();
                            var aiResponse = '<div style="margin-bottom: 15px;"><div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px; max-width: 70%;">I\'m still learning! This will be connected to the AI service soon.</div></div>';
                            $('.mpcc-chat-messages').append(aiResponse);
                            $('.mpcc-chat-messages').scrollTop($('.mpcc-chat-messages')[0].scrollHeight);
                        }, 2000);
                    }
                });
                
                $('#mpcc-chat-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#mpcc-send-message').click();
                    }
                });
            });
            </script>
            <?php
        }
    }

    /**
     * Handle AJAX request for AI chat
     *
     * @return void
     */
    public function handleAIChat(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $context = sanitize_text_field($_POST['context'] ?? 'course_editing');
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $conversation_history = $_POST['conversation_history'] ?? [];
        
        if (empty($message)) {
            wp_send_json_error('Message is required');
            return;
        }
        
        try {
            // Get copilot proxy service
            $copilot_proxy = new \MemberPressCoursesCopilot\Services\CopilotProxyService();
            
            // Prepare the AI request
            $ai_request_data = [
                'message' => $message,
                'context' => [
                    'type' => 'course_assistance',
                    'action' => $context,
                    'post_id' => $post_id,
                    'plugin' => 'memberpress-courses-copilot'
                ],
                'conversation_history' => $conversation_history,
                'system_prompt' => $this->getSystemPrompt($context)
            ];
            
            // Make request to AI service via proxy
            $response = $copilot_proxy->makeProxyRequest('/api/v1/chat', $ai_request_data);
            
            if (is_wp_error($response)) {
                wp_send_json_error('AI service error: ' . $response->get_error_message());
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);
            
            if ($response_code !== 200) {
                wp_send_json_error('AI service returned error: ' . ($response_body['message'] ?? 'Unknown error'));
                return;
            }
            
            $ai_message = $response_body['message'] ?? 'I apologize, but I\'m having trouble processing your request right now.';
            $course_data = $response_body['course_data'] ?? null;
            
            wp_send_json_success([
                'message' => $ai_message,
                'course_data' => $course_data,
                'context' => $context,
                'timestamp' => current_time('timestamp')
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error('Failed to process AI request: ' . $e->getMessage());
        }
    }

    /**
     * Handle AJAX request to create course with AI
     *
     * @return void
     */
    public function createCourseWithAI(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('publish_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // TODO: Implement course creation with AI
        wp_send_json_success([
            'message' => 'AI course creation functionality will be implemented here',
            'redirect' => admin_url('post-new.php?post_type=mpcs-course')
        ]);
    }

    /**
     * Get system prompt for AI based on context
     *
     * @param string $context The context (course_creation, course_editing)
     * @return string
     */
    private function getSystemPrompt(string $context): string
    {
        $base_prompt = "You are an AI assistant specialized in helping create and improve online courses for MemberPress Courses. You have expertise in curriculum design, learning objectives, content structuring, and educational best practices.";
        
        switch ($context) {
            case 'course_creation':
                return $base_prompt . " You are helping a user create a new course from scratch. Focus on understanding their topic, target audience, and learning goals. Help them structure a comprehensive curriculum with sections and lessons. Provide specific, actionable content suggestions.";
                
            case 'course_editing':
                return $base_prompt . " You are helping a user improve an existing course. Focus on enhancing content, improving structure, adding engaging elements, and optimizing the learning experience. Be specific about improvements and provide concrete suggestions.";
                
            default:
                return $base_prompt . " Provide helpful, specific guidance for course creation and improvement.";
        }
    }

    /**
     * Enhance screen options for courses pages
     *
     * @param bool $show_screen Whether to show screen options
     * @param \WP_Screen $screen Current screen object
     * @return bool
     */
    public function enhanceScreenOptions(bool $show_screen, \WP_Screen $screen): bool
    {
        if ($screen->post_type === 'mpcs-course') {
            // Add additional screen options for AI features if needed
        }
        
        return $show_screen;
    }
}