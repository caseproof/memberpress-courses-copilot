/**
 * Simple AI Chat Interface
 * Minimal implementation that just works
 */
jQuery(document).ready(function($) {
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
                    <span>AI is typing...</span>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(typingHtml);
        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
        
        // Make AJAX request
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_ai_chat',
                nonce: $('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
                message: message,
                context: 'course_creation',
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
                        mpccUpdatePreview(response.data.course_data);
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
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'mpcc_create_course_with_ai',
                nonce: jQuery('#mpcc-ajax-nonce').val() || mpccAISettings?.nonce || '',
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