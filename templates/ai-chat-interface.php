<?php
/**
 * AI Chat Interface Template
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Templates
 */

defined('ABSPATH') || exit;

// Context can be 'course_creation' or 'course_editing'
$context = $context ?? 'course_editing';
$post_id = $post_id ?? 0;
?>

<input type="hidden" id="mpcc-ajax-nonce" value="<?php echo wp_create_nonce('mpcc_courses_integration'); ?>" />
<div id="mpcc-ai-chat-interface" class="mpcc-ai-interface" data-context="<?php echo esc_attr($context); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
    <div id="mpcc-chat-messages" class="mpcc-chat-messages" style="height: <?php echo $context === 'course_creation' ? '400px' : '300px'; ?>; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 8px;">
        <div class="mpcc-welcome-message" style="text-align: center; padding: 20px; color: #666;">
            <div style="font-size: 32px; margin-bottom: 15px;">🤖</div>
            <h3 style="margin: 0 0 10px 0; color: #1a73e8;"><?php esc_html_e('AI Course Assistant', 'memberpress-courses-copilot'); ?></h3>
            <p style="margin: 0; line-height: 1.5;">
                <?php if ($context === 'course_creation'): ?>
                    <?php esc_html_e('Hi! I\'m here to help you create an amazing course. What kind of course would you like to build today?', 'memberpress-courses-copilot'); ?>
                <?php else: ?>
                    <?php esc_html_e('Hi! I\'m here to help you improve your course. What would you like to work on?', 'memberpress-courses-copilot'); ?>
                <?php endif; ?>
            </p>
            
            <?php if ($context === 'course_creation'): ?>
            <div style="margin-top: 20px;">
                <p style="font-size: 14px; color: #888; margin-bottom: 10px;"><?php esc_html_e('Quick starters:', 'memberpress-courses-copilot'); ?></p>
                <div style="display: flex; flex-wrap: wrap; gap: 8px; justify-content: center;">
                    <button type="button" class="button button-small mpcc-quick-start" data-message="Help me create a programming course for beginners">
                        <?php esc_html_e('Programming Course', 'memberpress-courses-copilot'); ?>
                    </button>
                    <button type="button" class="button button-small mpcc-quick-start" data-message="I want to create a business skills course">
                        <?php esc_html_e('Business Skills', 'memberpress-courses-copilot'); ?>
                    </button>
                    <button type="button" class="button button-small mpcc-quick-start" data-message="Help me design a creative arts course">
                        <?php esc_html_e('Creative Arts', 'memberpress-courses-copilot'); ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="mpcc-chat-input-container">
        <div class="mpcc-chat-input-wrapper" style="display: flex; gap: 10px; align-items: flex-end;">
            <textarea 
                id="mpcc-chat-input" 
                placeholder="<?php esc_attr_e('Type your message here...', 'memberpress-courses-copilot'); ?>" 
                rows="2"
                style="flex: 1; padding: 12px 16px; border: 2px solid #e8eaed; border-radius: 24px; resize: none; font-family: inherit; font-size: 14px; line-height: 1.4; outline: none;"
            ></textarea>
            <button type="button" id="mpcc-send-message" class="button button-primary" style="padding: 12px 20px; border-radius: 20px; display: flex; align-items: center; gap: 6px;">
                <span class="dashicons dashicons-paperplane" style="font-size: 16px; width: 16px; height: 16px;"></span>
                <?php esc_html_e('Send', 'memberpress-courses-copilot'); ?>
            </button>
        </div>
        
        <div style="margin-top: 8px; text-align: center;">
            <small style="color: #666; font-size: 12px;">
                <?php esc_html_e('Press Enter to send, Shift+Enter for new line', 'memberpress-courses-copilot'); ?>
            </small>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    console.log('AI Chat Interface template loaded');
    
    // Auto-focus input
    $('#mpcc-chat-input').focus();
    
    // Initialize conversation state
    window.mpccConversationHistory = window.mpccConversationHistory || [];
    window.mpccConversationState = window.mpccConversationState || { current_step: 'initial', collected_data: {} };
    
    // Enable/disable send button based on input
    $('#mpcc-chat-input').on('input keyup', function() {
        const hasText = $(this).val().trim().length > 0;
        $('#mpcc-send-message').prop('disabled', !hasText);
    });
    
    // Handle Enter key
    $('#mpcc-chat-input').on('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $('#mpcc-send-message').click();
        }
    });
    
    // Handle send button click
    $('#mpcc-send-message').on('click', function() {
        const message = $('#mpcc-chat-input').val().trim();
        if (!message) return;
        
        // Disable button to prevent double-click
        $(this).prop('disabled', true);
        
        // Add user message to conversation history
        window.mpccConversationHistory.push({ role: 'user', content: message });
        
        // Add user message to chat
        const userHtml = `
            <div style="margin-bottom: 15px; text-align: right;">
                <div style="display: inline-block; background: #0073aa; color: white; padding: 10px 15px; border-radius: 18px; max-width: 70%;">
                    ${$('<div>').text(message).html()}
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(userHtml);
        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
        
        // Clear input
        $('#mpcc-chat-input').val('').focus();
        
        // Show typing indicator
        const typingHtml = `
            <div id="mpcc-typing" style="margin-bottom: 15px;">
                <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px;">
                    <span>AI is thinking...</span>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(typingHtml);
        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mpcc_ai_chat',
                nonce: $('#mpcc-ajax-nonce').val(),
                message: message,
                context: '<?php echo esc_js($context); ?>',
                conversation_history: window.mpccConversationHistory,
                conversation_state: window.mpccConversationState
            },
            success: function(response) {
                $('#mpcc-typing').remove();
                
                if (response.success) {
                    // Add AI response to conversation history
                    window.mpccConversationHistory.push({ role: 'assistant', content: response.data.message });
                    
                    // Update conversation state
                    if (response.data.conversation_state) {
                        window.mpccConversationState = response.data.conversation_state;
                    }
                    
                    // Add AI response
                    const aiHtml = `
                        <div style="margin-bottom: 15px;">
                            <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px; max-width: 70%;">
                                ${response.data.message}
                            </div>
                        </div>
                    `;
                    $('#mpcc-chat-messages').append(aiHtml);
                    
                    // Show action buttons if available
                    if (response.data.actions && response.data.actions.length > 0) {
                        const actionsHtml = `
                            <div class="mpcc-actions" style="margin: 15px 0; text-align: center;">
                                ${response.data.actions.map(action => 
                                    `<button class="button ${action.type === 'primary' ? 'button-primary' : ''}" 
                                        onclick="mpccHandleAction('${action.action}')" style="margin: 0 5px;">
                                        ${action.label}
                                    </button>`
                                ).join('')}
                            </div>
                        `;
                        $('#mpcc-chat-messages').append(actionsHtml);
                    }
                    
                    // Update course preview if data available
                    if (response.data.course_data) {
                        window.mpccCurrentCourse = response.data.course_data;
                        if (typeof mpccUpdatePreview === 'function') {
                            mpccUpdatePreview(response.data.course_data);
                        }
                    }
                } else {
                    const errorHtml = `
                        <div style="margin-bottom: 15px;">
                            <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px; max-width: 70%; color: #d63638;">
                                Error: ${response.data || 'Something went wrong'}
                            </div>
                        </div>
                    `;
                    $('#mpcc-chat-messages').append(errorHtml);
                }
                
                $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
                
                // Re-enable send button
                $('#mpcc-send-message').prop('disabled', false);
            },
            error: function() {
                $('#mpcc-typing').remove();
                const errorHtml = `
                    <div style="margin-bottom: 15px;">
                        <div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; border-radius: 18px; max-width: 70%; color: #d63638;">
                            Sorry, I encountered an error. Please try again.
                        </div>
                    </div>
                `;
                $('#mpcc-chat-messages').append(errorHtml);
                $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
                
                // Re-enable send button
                $('#mpcc-send-message').prop('disabled', false);
            }
        });
    });
    
    // Handle quick start button clicks (prevent duplicate bindings)
    $('.mpcc-quick-start').off('click').on('click', function(e) {
        e.preventDefault();
        console.log('Quick start button clicked');
        var message = $(this).data('message');
        if (message) {
            $('#mpcc-chat-input').val(message);
            $('#mpcc-send-message').trigger('click');
            
            // Prevent further clicks for 2 seconds
            $('.mpcc-quick-start').prop('disabled', true);
            setTimeout(function() {
                $('.mpcc-quick-start').prop('disabled', false);
            }, 2000);
        }
    });
});

// Global functions for action handling
window.mpccHandleAction = function(action) {
    if (action === 'create_course' && window.mpccCurrentCourse) {
        // Show creating message
        jQuery('#mpcc-chat-messages').append(`
            <div style="margin: 15px 0; text-align: center; color: #0073aa;">
                <strong>Creating your course...</strong>
            </div>
        `);
        
        jQuery.ajax({
            url: ajaxurl || '/wp-admin/admin-ajax.php',
            type: 'POST',
            data: {
                action: 'mpcc_create_course_with_ai',
                nonce: jQuery('#mpcc-ajax-nonce').val(),
                course_data: window.mpccCurrentCourse
            },
            success: function(response) {
                if (response.success) {
                    jQuery('#mpcc-chat-messages').append(`
                        <div style="margin: 15px 0; text-align: center; color: #46b450;">
                            <strong>✓ Course created successfully!</strong><br>
                            <a href="${response.data.edit_url}" class="button button-primary" style="margin-top: 10px;">
                                Edit Course
                            </a>
                        </div>
                    `);
                } else {
                    jQuery('#mpcc-chat-messages').append(`
                        <div style="margin: 15px 0; text-align: center; color: #d63638;">
                            <strong>Failed to create course: ${response.data.message || 'Unknown error'}</strong>
                        </div>
                    `);
                }
            }
        });
    }
};

window.mpccUpdatePreview = function(courseData) {
    const previewHtml = `
        <h3>${courseData.title || 'Untitled Course'}</h3>
        <p>${courseData.description || ''}</p>
        <h4>Course Structure:</h4>
        <ul>
            ${(courseData.sections || []).map(section => `
                <li>
                    <strong>${section.title}</strong>
                    <ul>
                        ${(section.lessons || []).map(lesson => 
                            `<li>${lesson.title}</li>`
                        ).join('')}
                    </ul>
                </li>
            `).join('')}
        </ul>
    `;
    jQuery('#mpcc-preview-content').html(previewHtml);
};
</script>

<style>
/* Additional inline styles for template-specific elements */
.mpcc-quick-start {
    font-size: 12px !important;
    padding: 4px 8px !important;
    height: auto !important;
    line-height: 1.2 !important;
}

.mpcc-quick-start:hover {
    background: #f0f0f1 !important;
}

#mpcc-chat-input:focus {
    border-color: #1a73e8 !important;
    box-shadow: 0 0 0 3px rgba(26, 115, 232, 0.1) !important;
}

#mpcc-send-message:disabled {
    background: #f1f3f4 !important;
    border-color: #f1f3f4 !important;
    color: #9aa0a6 !important;
    cursor: not-allowed !important;
}
</style>