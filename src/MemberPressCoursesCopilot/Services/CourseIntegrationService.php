<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Services\ConversationManager;

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
        add_action('wp_ajax_mpcc_ping', [$this, 'handlePing']);
        
        // Conversation persistence endpoints
        add_action('wp_ajax_mpcc_save_conversation', [$this, 'saveConversation']);
        add_action('wp_ajax_mpcc_load_conversation', [$this, 'loadConversation']);
        add_action('wp_ajax_mpcc_create_conversation', [$this, 'createConversation']);
        add_action('wp_ajax_mpcc_list_conversations', [$this, 'listConversations']);
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
                // Create modal for AI course creation with dual pane layout
                var modalHtml = '<div id="mpcc-ai-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">' +
                    '<div style="background-color: #fefefe; margin: 2% auto; padding: 0; border: none; border-radius: 8px; width: 95%; max-width: 1600px; height: 92%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">' +
                        '<div style="display: flex; flex-direction: column; height: 100%;">' +
                            '<div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px 8px 0 0;">' +
                                '<h2 style="margin: 0; color: white;"><?php echo esc_js(__('Create Course with AI Assistant', 'memberpress-courses-copilot')); ?></h2>' +
                                '<span id="mpcc-close-modal" style="cursor: pointer; font-size: 24px; font-weight: bold; color: white;">&times;</span>' +
                            '</div>' +
                            '<div style="display: flex; flex: 1; overflow: hidden;">' +
                                '<div id="mpcc-ai-interface-container" style="flex: 1; height: 100%; border-right: 1px solid #ddd; display: flex; flex-direction: column;">' +
                                    '<div style="display: flex; justify-content: center; align-items: center; height: 100%; color: #666;">' +
                                        '<div style="text-align: center;">' +
                                            '<div class="spinner is-active" style="float: none; margin: 0 auto 20px;"></div>' +
                                            '<p><?php echo esc_js(__('Loading AI Assistant...', 'memberpress-courses-copilot')); ?></p>' +
                                        '</div>' +
                                    '</div>' +
                                '</div>' +
                                '<div id="mpcc-preview-pane" style="flex: 1; height: 100%; background: #f8f9fa; overflow-y: auto; display: none;">' +
                                    '<div style="padding: 20px;">' +
                                        '<h3 style="margin: 0 0 20px 0; color: #333;"><?php echo esc_js(__('Course Preview', 'memberpress-courses-copilot')); ?></h3>' +
                                        '<div id="mpcc-preview-content">' +
                                            '<p style="color: #666; text-align: center; padding: 40px;"><?php echo esc_js(__('Course preview will appear here as you build it...', 'memberpress-courses-copilot')); ?></p>' +
                                        '</div>' +
                                        '<div style="padding: 20px; border-top: 1px solid #ddd; display: flex; gap: 10px; justify-content: flex-end;">' +
                                            '<button id="mpcc-save-draft" class="button" disabled><?php echo esc_js(__('Save Draft', 'memberpress-courses-copilot')); ?></button>' +
                                            '<button id="mpcc-create-course" class="button button-primary" disabled><?php echo esc_js(__('Create Course', 'memberpress-courses-copilot')); ?></button>' +
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
                            $('#mpcc-preview-pane').addClass('active').show(); // Show the preview pane
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
                                    // The interface will initialize itself when ready
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
        
        // Check if we're on the course generator page
        $is_generator_page = isset($_GET['page']) && $_GET['page'] === 'mpcc-course-generator';
        
        // Enqueue AI interface CSS
        wp_enqueue_style(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/courses-integration.css',
            [],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );
        
        // Enqueue AI Copilot CSS
        wp_enqueue_style(
            'mpcc-ai-copilot',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/ai-copilot.css',
            [],
            MEMBERPRESS_COURSES_COPILOT_VERSION
        );
        
        // Enqueue course preview CSS if on generator page
        if ($is_generator_page) {
            wp_enqueue_style(
                'mpcc-course-preview',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/css/course-preview.css',
                [],
                MEMBERPRESS_COURSES_COPILOT_VERSION
            );
        }
        
        // Enqueue AI interface JavaScript
        wp_enqueue_script(
            'mpcc-courses-integration',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/courses-integration.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Enqueue AI Copilot JavaScript
        wp_enqueue_script(
            'mpcc-ai-copilot',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/ai-copilot.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Enqueue enhanced AI chat with persistence
        wp_enqueue_script(
            'mpcc-simple-ai-chat',
            MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/simple-ai-chat.js',
            ['jquery'],
            MEMBERPRESS_COURSES_COPILOT_VERSION,
            true
        );
        
        // Enqueue additional scripts for generator page
        if ($is_generator_page) {
            wp_enqueue_script(
                'mpcc-course-preview',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/course-preview.js',
                ['jquery'],
                MEMBERPRESS_COURSES_COPILOT_VERSION,
                true
            );
            
            wp_enqueue_script(
                'mpcc-preview-integration',
                MEMBERPRESS_COURSES_COPILOT_PLUGIN_URL . 'assets/js/preview-integration.js',
                ['jquery', 'mpcc-course-preview'],
                MEMBERPRESS_COURSES_COPILOT_VERSION,
                true
            );
        }
        
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
        
        // Localize AI copilot script
        wp_localize_script('mpcc-ai-copilot', 'mpccAISettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpcc_courses_integration')
        ]);
        
        // Localize simple AI chat script
        wp_localize_script('mpcc-simple-ai-chat', 'mpccAISettings', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mpcc_courses_integration')
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
            $this->logger->warning('AI interface load failed: invalid nonce', [
                'user_id' => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('AI interface load failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
                'required_capability' => 'edit_posts'
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $context = sanitize_text_field($_POST['context'] ?? '');
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        
        try {
            $this->logger->info('Loading AI interface', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $post_id
            ]);
            
            // Generate the AI interface HTML
            ob_start();
            $this->renderAIInterface($context, $post_id);
            $html = ob_get_clean();
            
            $this->logger->debug('AI interface HTML generated successfully', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $post_id,
                'html_length' => strlen($html)
            ]);
            
            wp_send_json_success([
                'html' => $html,
                'context' => $context,
                'post_id' => $post_id
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load AI interface', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $post_id,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
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
            <div id="mpcc-ai-chat-interface" class="mpcc-ai-interface" data-context="<?php echo esc_attr($context); ?>" data-post-id="<?php echo esc_attr($post_id); ?>" style="height: 100%; display: flex; flex-direction: column;">
                <div class="mpcc-chat-messages" style="flex: 1; overflow-y: auto; border: none; padding: 20px; background: white;">
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
                        
                        <?php if ($context === 'course_creation'): ?>
                        <div style="margin-top: 30px;">
                            <p style="font-size: 14px; color: #999; margin-bottom: 20px;"><?php esc_html_e('Quick starters:', 'memberpress-courses-copilot'); ?></p>
                            <div class="mpcc-quick-starters" style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <button type="button" class="mpcc-quick-start button button-secondary" data-prompt="Programming Course">
                                    <?php esc_html_e('Programming Course', 'memberpress-courses-copilot'); ?>
                                </button>
                                <button type="button" class="mpcc-quick-start button button-secondary" data-prompt="Business Skills">
                                    <?php esc_html_e('Business Skills', 'memberpress-courses-copilot'); ?>
                                </button>
                                <button type="button" class="mpcc-quick-start button button-secondary" data-prompt="Creative Arts">
                                    <?php esc_html_e('Creative Arts', 'memberpress-courses-copilot'); ?>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="mpcc-chat-input-container" style="padding: 20px; border-top: 1px solid #ddd; background: #f8f9fa;">
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="mpcc-chat-input" placeholder="<?php esc_attr_e('Type your message here...', 'memberpress-courses-copilot'); ?>" style="flex: 1; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;">
                        <button type="button" id="mpcc-send-message" class="button button-primary" style="padding: 12px 24px;">
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
            $this->logger->warning('AI chat request failed: invalid nonce', [
                'user_id' => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('AI chat request failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
                'required_capability' => 'edit_posts'
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $message = sanitize_textarea_field($_POST['message'] ?? '');
        $context = sanitize_text_field($_POST['context'] ?? 'course_editing');
        $post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $conversation_history = $_POST['conversation_history'] ?? [];
        $conversation_state = $_POST['conversation_state'] ?? [];
        
        if (empty($message)) {
            $this->logger->warning('AI chat request failed: empty message', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $post_id
            ]);
            wp_send_json_error('Message is required');
            return;
        }
        
        try {
            $this->logger->info('AI chat request initiated', [
                'user_id' => get_current_user_id(),
                'message_length' => strlen($message),
                'context' => $context,
                'post_id' => $post_id,
                'conversation_history_count' => count($conversation_history),
                'conversation_state' => $conversation_state,
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            
            // Use LLMService for AI requests
            $llm_service = new \MemberPressCoursesCopilot\Services\LLMService();
            
            // Build conversation context
            $conversation_context = '';
            if (!empty($conversation_history)) {
                foreach ($conversation_history as $entry) {
                    $role = $entry['role'] ?? 'user';
                    $content = $entry['content'] ?? '';
                    $conversation_context .= "\n{$role}: {$content}";
                }
            }
            
            // Get current conversation state for course creation
            $current_step = $conversation_state['current_step'] ?? 'initial';
            $collected_data = $conversation_state['collected_data'] ?? [];
            
            // Prepare the full prompt
            $system_prompt = $this->getSystemPrompt($context);
            $full_prompt = $system_prompt . "\n\nConversation history:" . $conversation_context . "\n\nUser: " . $message . "\n\nAssistant:";
            
            // Add current state context for course creation
            if ($context === 'course_creation' && !empty($collected_data)) {
                $full_prompt .= "\n\nCurrent collected course data: " . json_encode($collected_data);
            }
            
            $this->logger->debug('Preparing LLM service call', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'current_step' => $current_step,
                'collected_data_keys' => array_keys($collected_data),
                'prompt_length' => strlen($full_prompt)
            ]);
            
            // Make request to AI service
            $response = $llm_service->generateContent($full_prompt, 'course_assistance', [
                'temperature' => 0.7,
                'max_tokens' => 2000
            ]);
            
            $this->logger->debug('LLM service response received', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'has_error' => $response['error'] ?? false,
                'response_content_length' => isset($response['content']) ? strlen($response['content']) : 0,
                'response_keys' => array_keys($response)
            ]);
            
            if ($response['error']) {
                $this->logger->error('LLM service returned error', [
                    'user_id' => get_current_user_id(),
                    'context' => $context,
                    'error_message' => $response['message'] ?? 'Unknown error',
                    'full_response' => $response
                ]);
                wp_send_json_error('AI service error: ' . $response['message']);
                return;
            }
            
            $ai_message = $response['content'];
            
            // Extract any course data from the response if it contains structured data
            $course_data = null;
            $ready_to_create = false;
            
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $ai_message, $matches)) {
                $json_data = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $course_data = $json_data;
                    // Remove JSON from message
                    $ai_message = trim(str_replace($matches[0], '', $ai_message));
                    
                    // Check if we have enough data to create a course
                    if (isset($course_data['title']) && isset($course_data['sections']) && count($course_data['sections']) > 0) {
                        $ready_to_create = true;
                    }
                }
            }
            
            // Update conversation state
            if ($course_data) {
                $collected_data = array_merge($collected_data, $course_data);
            }
            
            // Determine next step
            $next_step = $current_step;
            $actions = [];
            
            if ($ready_to_create) {
                $next_step = 'ready_to_create';
                $actions = [
                    ['action' => 'create_course', 'label' => 'Create Course', 'type' => 'primary'],
                    ['action' => 'modify', 'label' => 'Modify Details', 'type' => 'secondary']
                ];
            }
            
            $this->logger->info('AI chat request completed successfully', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'next_step' => $next_step,
                'ready_to_create' => $ready_to_create,
                'has_course_data' => !empty($course_data),
                'response_message_length' => strlen($ai_message),
                'actions_count' => count($actions)
            ]);
            
            wp_send_json_success([
                'message' => $ai_message,
                'course_data' => $course_data,
                'context' => $context,
                'timestamp' => current_time('timestamp'),
                'conversation_state' => [
                    'current_step' => $next_step,
                    'collected_data' => $collected_data
                ],
                'actions' => $actions,
                'ready_to_create' => $ready_to_create
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('AI chat request failed with exception', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
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
            $this->logger->warning('Course creation failed: invalid nonce', [
                'user_id' => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            wp_die('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('publish_posts')) {
            $this->logger->warning('Course creation failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
                'required_capability' => 'publish_posts'
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $course_data = $_POST['course_data'] ?? [];
        
        if (empty($course_data)) {
            $this->logger->warning('Course creation failed: no course data provided', [
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('No course data provided');
            return;
        }
        
        try {
            $this->logger->info('Course creation initiated', [
                'user_id' => get_current_user_id(),
                'course_title' => $course_data['title'] ?? 'Unknown',
                'sections_count' => count($course_data['sections'] ?? []),
                'course_data_keys' => array_keys($course_data)
            ]);
            
            // Initialize the Course Generator Service
            $logger = \MemberPressCoursesCopilot\Utilities\Logger::getInstance();
            $generator = new \MemberPressCoursesCopilot\Services\CourseGeneratorService($logger);
            
            // Validate course data
            $validation = $generator->validateCourseData($course_data);
            if (!$validation['valid']) {
                $this->logger->error('Course creation failed: validation errors', [
                    'user_id' => get_current_user_id(),
                    'course_title' => $course_data['title'] ?? 'Unknown',
                    'validation_errors' => $validation['errors']
                ]);
                wp_send_json_error([
                    'message' => 'Course data validation failed',
                    'errors' => $validation['errors']
                ]);
                return;
            }
            
            // Generate the course
            $result = $generator->generateCourse($course_data);
            
            if ($result['success']) {
                $this->logger->info('Course created successfully', [
                    'user_id' => get_current_user_id(),
                    'course_id' => $result['course_id'],
                    'course_title' => $course_data['title'] ?? 'Unknown',
                    'sections_count' => count($course_data['sections'] ?? [])
                ]);
                wp_send_json_success([
                    'message' => 'Course created successfully!',
                    'course_id' => $result['course_id'],
                    'edit_url' => $result['edit_url'],
                    'preview_url' => $result['preview_url']
                ]);
            } else {
                $this->logger->error('Course creation failed', [
                    'user_id' => get_current_user_id(),
                    'course_title' => $course_data['title'] ?? 'Unknown',
                    'error' => $result['error']
                ]);
                wp_send_json_error([
                    'message' => 'Failed to create course',
                    'error' => $result['error']
                ]);
            }
            
        } catch (\Exception $e) {
            $this->logger->error('Course creation failed with exception', [
                'user_id' => get_current_user_id(),
                'course_title' => $course_data['title'] ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            wp_send_json_error('Failed to create course: ' . $e->getMessage());
        }
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
                return $base_prompt . " You are helping a user create a new course from scratch. Focus on understanding their topic, target audience, and learning goals. Help them structure a comprehensive curriculum with sections and lessons. 

IMPORTANT: When the user has provided:
1. The subject/topic of the course
2. Target audience
3. Main objectives or what students will build/learn
4. Approximate duration
5. Whether it includes hands-on exercises

You MUST generate a complete course structure immediately. Do not ask for more clarification unless absolutely necessary.

Your conversation should be natural and helpful. If you need clarification, ask only 1-2 specific questions. Once you have the basic information above, generate the course structure in the following JSON format wrapped in ```json``` code blocks:

```json
{
  \"title\": \"Course Title\",
  \"description\": \"Course description\",
  \"sections\": [
    {
      \"title\": \"Section 1 Title\",
      \"description\": \"Section description\",
      \"lessons\": [
        {
          \"title\": \"Lesson Title\",
          \"content\": \"Lesson content (can be HTML)\",
          \"type\": \"text\",
          \"duration\": \"15\"
        }
      ]
    }
  ],
  \"settings\": {
    \"course_progress\": \"enabled\",
    \"auto_advance\": \"enabled\"
  },
  \"categories\": [\"Category 1\"],
  \"tags\": [\"tag1\", \"tag2\"]
}
```

Be conversational and guide the user through the process naturally. When you have the 5 key pieces of information listed above, immediately generate the complete course structure. Do not continue asking questions.

Example: If a user says they want to create a PHP course for people with HTML/CSS knowledge to build a todo app in 4 hours with OOP, PDO, and MVC - you have ALL the information needed. Generate the course immediately.";
                
            case 'course_editing':
                return $base_prompt . " You are helping a user improve an existing course. Focus on enhancing content, improving structure, adding engaging elements, and optimizing the learning experience. Be specific about improvements and provide concrete suggestions. When suggesting course modifications, include structured data in JSON format wrapped in ```json``` code blocks.";
                
            default:
                return $base_prompt . " Provide helpful, specific guidance for course creation and improvement. When providing course data, use JSON format wrapped in ```json``` code blocks.";
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

    /**
     * Handle AJAX ping request for connection testing
     *
     * @return void
     */
    public function handlePing(): void
    {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            $this->logger->warning('Ping request failed: invalid nonce', [
                'user_id' => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        $this->logger->debug('Ping request received', [
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('timestamp')
        ]);
        
        // Simple ping response
        wp_send_json_success([
            'pong' => true,
            'timestamp' => current_time('timestamp')
        ]);
    }
    
    /**
     * Create new conversation session
     *
     * @return void
     */
    public function createConversation(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            $this->logger->warning('Create conversation failed: invalid nonce', [
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            $conversationManager = new ConversationManager();
            
            $session = $conversationManager->createSession([
                'user_id' => get_current_user_id(),
                'context' => sanitize_text_field($_POST['context'] ?? 'course_creation'),
                'title' => sanitize_text_field($_POST['title'] ?? 'New Course Creation'),
                'state' => 'initial',
                'initial_data' => []
            ]);
            
            $this->logger->info('Created new conversation session', [
                'session_id' => $session->getSessionId(),
                'user_id' => get_current_user_id()
            ]);
            
            wp_send_json_success([
                'session_id' => $session->getSessionId(),
                'created_at' => $session->getCreatedAt()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create conversation', [
                'error' => $e->getMessage(),
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Failed to create conversation');
        }
    }
    
    /**
     * Save conversation state
     *
     * @return void
     */
    public function saveConversation(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            $this->logger->warning('Save conversation failed: invalid nonce', [
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            wp_send_json_error('Session ID required');
            return;
        }
        
        try {
            $conversationManager = new ConversationManager();
            $session = $conversationManager->loadSession($sessionId);
            
            if (!$session || $session->getUserId() !== get_current_user_id()) {
                $this->logger->warning('Save conversation failed: access denied', [
                    'session_id' => $sessionId,
                    'user_id' => get_current_user_id()
                ]);
                wp_send_json_error('Session not found or access denied');
                return;
            }
            
            // Update session with new data
            $conversationHistory = $_POST['conversation_history'] ?? [];
            $conversationState = $_POST['conversation_state'] ?? [];
            
            // Clear existing messages and add new ones
            $session->clearMessages();
            foreach ($conversationHistory as $message) {
                $session->addMessage(
                    $message['role'],
                    $message['content'],
                    ['timestamp' => $message['timestamp'] ?? time()]
                );
            }
            
            // Update state
            $session->setCurrentState($conversationState['current_step'] ?? 'initial');
            $session->setContext($conversationState['collected_data'] ?? [], null);
            
            // Save to database
            $saved = $conversationManager->saveSession($session);
            
            $this->logger->info('Saved conversation', [
                'session_id' => $sessionId,
                'user_id' => get_current_user_id(),
                'messages_count' => count($conversationHistory)
            ]);
            
            wp_send_json_success([
                'saved' => $saved,
                'last_saved' => time()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to save conversation', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error('Failed to save conversation');
        }
    }
    
    /**
     * Load conversation session
     *
     * @return void
     */
    public function loadConversation(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            $this->logger->warning('Load conversation failed: invalid nonce', [
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            wp_send_json_error('Session ID required');
            return;
        }
        
        try {
            $conversationManager = new ConversationManager();
            $session = $conversationManager->loadSession($sessionId);
            
            if (!$session || $session->getUserId() !== get_current_user_id()) {
                $this->logger->warning('Load conversation failed: access denied', [
                    'session_id' => $sessionId,
                    'user_id' => get_current_user_id()
                ]);
                wp_send_json_error('Session not found or access denied');
                return;
            }
            
            // Format messages for frontend
            $messages = [];
            foreach ($session->getMessages() as $message) {
                if ($message['type'] !== 'system') {
                    $messages[] = [
                        'role' => $message['type'],
                        'content' => $message['content'],
                        'timestamp' => $message['timestamp']
                    ];
                }
            }
            
            $this->logger->info('Loaded conversation', [
                'session_id' => $sessionId,
                'user_id' => get_current_user_id(),
                'messages_count' => count($messages)
            ]);
            
            wp_send_json_success([
                'session_id' => $session->getSessionId(),
                'conversation_history' => $messages,
                'conversation_state' => [
                    'current_step' => $session->getCurrentState(),
                    'collected_data' => $session->getContext()
                ],
                'created_at' => $session->getCreatedAt(),
                'last_updated' => $session->getLastUpdated()
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to load conversation', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            wp_send_json_error('Failed to load conversation');
        }
    }
    
    /**
     * List user conversations
     *
     * @return void
     */
    public function listConversations(): void
    {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_courses_integration')) {
            $this->logger->warning('List conversations failed: invalid nonce', [
                'user_id' => get_current_user_id()
            ]);
            wp_send_json_error('Security check failed');
            return;
        }
        
        try {
            $conversationManager = new ConversationManager();
            $sessions = $conversationManager->getUserSessions(
                get_current_user_id(),
                10, // limit
                0   // offset
            );
            
            $sessionList = [];
            foreach ($sessions as $session) {
                $sessionList[] = [
                    'session_id' => $session->getSessionId(),
                    'title' => $session->getTitle(),
                    'created_at' => $session->getCreatedAt(),
                    'last_updated' => $session->getLastUpdated(),
                    'is_active' => $session->isActive(),
                    'progress' => $session->getProgress()
                ];
            }
            
            $this->logger->info('Listed conversations', [
                'user_id' => get_current_user_id(),
                'count' => count($sessionList)
            ]);
            
            wp_send_json_success([
                'sessions' => $sessionList
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to list conversations', [
                'error' => $e->getMessage()
            ]);
            wp_send_json_error('Failed to list conversations');
        }
    }
}