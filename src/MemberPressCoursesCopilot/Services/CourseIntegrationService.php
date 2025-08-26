<?php

declare(strict_types=1);

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
        
        // Hook into courses listing page to add "Create with AI" button
        add_action('admin_footer-edit.php', [$this, 'addCreateWithAIButton']);
        
        // Hook into course editor to add AI chat interface
        add_action('add_meta_boxes', [$this, 'addAIAssistantMetaBox'], 20);
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
        jQuery(document).ready(function($) {
            // Add "Create with AI" button next to "Add New Course"
            var createWithAIButton = '<a href="#" id="mpcc-create-with-ai" class="page-title-action" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; text-shadow: none;">' + 
                '<span class="dashicons dashicons-admin-generic" style="margin-right: 5px; vertical-align: middle; line-height: 1;"></span>' +
                '<?php echo esc_js(__('Create with AI', 'memberpress-courses-copilot')); ?>' +
                '</a>';
            
            $('.wrap .wp-header-end').before(createWithAIButton);
            
            // Handle click event - redirect to standalone page
            $('#mpcc-create-with-ai').on('click', function(e) {
                e.preventDefault();
                // Redirect to standalone AI Course Editor page
                window.location.href = '<?php echo admin_url('admin.php?page=mpcc-course-editor&action=new'); ?>';
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
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_assistant_nonce');
        
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
                                nonce: '<?php echo NonceConstants::create(NonceConstants::AI_INTERFACE); ?>',
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