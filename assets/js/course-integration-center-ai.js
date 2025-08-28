/**
 * Course Integration - Center Column AI Chat
 * 
 * Handles the AI chat interface in the center column of course edit pages
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize AI chat for course editing
        var courseData = window.courseData || {};
        
        // Load the AI chat interface
        MPCCUtils.ajax.request('mpcc_load_ai_interface', {
            context: 'course_editing',
            post_id: mpccCenterAI.postId,
            course_data: JSON.stringify(courseData),
            nonce: mpccCenterAI.nonce
        }, {
            success: function(response) {
                if (response.success) {
                    $('#mpcc-course-ai-chat-container').html(response.data.html);
                    
                    // Initialize the chat with course context
                    if (typeof window.initializeCourseAIChat === 'function') {
                        window.initializeCourseAIChat(courseData);
                    }
                } else {
                    $('#mpcc-course-ai-chat-container').html(
                        '<div style="padding: 20px; text-align: center; color: #d63638;">' +
                        '<p>' + MPCCUtils.escapeHtml(response.data || mpccCenterAI.strings.failedToLoad) + '</p>' +
                        '</div>'
                    );
                }
            },
            error: function() {
                $('#mpcc-course-ai-chat-container').html(
                    '<div style="padding: 20px; text-align: center; color: #d63638;">' +
                    '<p>' + MPCCUtils.escapeHtml(mpccCenterAI.strings.failedToLoadNetwork) + '</p>' +
                    '</div>'
                );
            }
        });
    });

})(jQuery);