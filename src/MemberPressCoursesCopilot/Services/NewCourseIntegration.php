<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * New Course Integration Service
 * 
 * Fresh, simple implementation of AI chat for course pages
 * 
 * @package MemberPressCoursesCopilot\Services
 * @since 1.0.0
 */
class NewCourseIntegration extends BaseService
{
    /**
     * Initialize the service
     */
    public function init(): void
    {
        // Add metabox to course edit pages
        add_action('add_meta_boxes', [$this, 'addAIChatMetabox']);
    }

    /**
     * Add AI Chat metabox to course edit pages
     */
    public function addAIChatMetabox(): void
    {
        global $post;
        
        // Only add to course post type
        if (!$post || $post->post_type !== 'mpcs-course') {
            return;
        }

        add_meta_box(
            'mpcc-ai-chat-metabox',
            'AI Course Assistant',
            [$this, 'renderAIChatMetabox'],
            'mpcs-course',
            'side',
            'high'
        );
    }

    /**
     * Render AI Chat metabox content
     */
    public function renderAIChatMetabox(\WP_Post $post): void
    {
        // Add nonce for security
        NonceConstants::field(NonceConstants::AI_ASSISTANT, 'mpcc_ai_nonce');
        
        ?>
        <div id="mpcc-new-ai-chat" style="padding: 10px;">
            <p><strong>AI Course Assistant</strong></p>
            <p>Get help with your course content, structure, and lessons.</p>
            
            <button type="button" id="mpcc-open-ai-chat" class="button button-primary" style="width: 100%; margin-bottom: 10px;">
                <span class="dashicons dashicons-format-chat"></span>
                Open AI Chat
            </button>
            
            <div id="mpcc-ai-chat-container" style="display: none; border: 1px solid #ddd; border-radius: 4px; background: #f9f9f9;">
                <div id="mpcc-ai-messages" style="height: 300px; overflow-y: auto; padding: 10px; background: white; border-bottom: 1px solid #ddd;">
                    <div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">
                        <strong>AI Assistant:</strong> Hi! I can help you improve your course. What would you like to work on?
                    </div>
                </div>
                
                <div style="padding: 10px;">
                    <textarea id="mpcc-ai-input" 
                              placeholder="Ask me anything about your course..." 
                              style="width: 100%; height: 60px; border: 1px solid #ddd; border-radius: 3px; padding: 5px; resize: vertical;"></textarea>
                    <button type="button" id="mpcc-ai-send" class="button button-primary" style="margin-top: 5px; width: 100%;">
                        Send Message
                    </button>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            console.log('MPCC: New AI Chat metabox loaded');
            
            // Toggle chat visibility
            $('#mpcc-open-ai-chat').on('click', function() {
                var container = $('#mpcc-ai-chat-container');
                var button = $(this);
                
                if (container.is(':visible')) {
                    container.slideUp();
                    button.html('<span class="dashicons dashicons-format-chat"></span> Open AI Chat');
                } else {
                    container.slideDown();
                    button.html('<span class="dashicons dashicons-dismiss"></span> Close AI Chat');
                }
            });
            
            // Handle send message
            $('#mpcc-ai-send').on('click', function() {
                var input = $('#mpcc-ai-input');
                var message = input.val().trim();
                
                if (!message) {
                    alert('Please enter a message');
                    return;
                }
                
                // Add user message to chat
                var userMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #f0f0f0; border-radius: 4px; text-align: right;">' +
                    '<strong>You:</strong> ' + $('<div>').text(message).html() + '</div>';
                $('#mpcc-ai-messages').append(userMsg);
                
                // Clear input
                input.val('');
                
                // Scroll to bottom
                var messages = $('#mpcc-ai-messages');
                messages.scrollTop(messages[0].scrollHeight);
                
                // Show typing indicator
                var typingMsg = '<div id="mpcc-typing" class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                    '<strong>AI Assistant:</strong> <em>Typing...</em></div>';
                $('#mpcc-ai-messages').append(typingMsg);
                messages.scrollTop(messages[0].scrollHeight);
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'mpcc_new_ai_chat',
                        nonce: $('#mpcc_ai_nonce').val(),
                        message: message,
                        post_id: <?php echo $post->ID; ?>,
                        course_data: {
                            title: '<?php echo esc_js($post->post_title); ?>',
                            content: <?php echo json_encode($post->post_content); ?>
                        }
                    },
                    success: function(response) {
                        $('#mpcc-typing').remove();
                        
                        if (response.success) {
                            var aiMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #e7f3ff; border-radius: 4px;">' +
                                '<strong>AI Assistant:</strong> ' + response.data.message + '</div>';
                            $('#mpcc-ai-messages').append(aiMsg);
                        } else {
                            var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                                '<strong>Error:</strong> ' + (response.data || 'Failed to get AI response') + '</div>';
                            $('#mpcc-ai-messages').append(errorMsg);
                        }
                        
                        var messages = $('#mpcc-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    },
                    error: function() {
                        $('#mpcc-typing').remove();
                        var errorMsg = '<div class="mpcc-ai-message" style="margin-bottom: 10px; padding: 8px; background: #ffe7e7; border-radius: 4px;">' +
                            '<strong>Error:</strong> Network error. Please try again.</div>';
                        $('#mpcc-ai-messages').append(errorMsg);
                        
                        var messages = $('#mpcc-ai-messages');
                        messages.scrollTop(messages[0].scrollHeight);
                    }
                });
            });
            
            // Handle Enter key
            $('#mpcc-ai-input').on('keypress', function(e) {
                if (e.which === 13 && !e.shiftKey) {
                    e.preventDefault();
                    $('#mpcc-ai-send').click();
                }
            });
        });
        </script>
        <?php
    }
}