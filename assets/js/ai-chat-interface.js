/**
 * AI Chat Interface
 * 
 * Handles the AI chat interface functionality
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize AI chat interface
        const chatContainer = $('#mpcc-ai-chat-interface');
        const chatMessages = $('#mpcc-chat-messages');
        const chatInput = $('#mpcc-course-chat-input');
        const sendButton = $('#mpcc-course-send-message');
        const typingIndicator = $('#mpcc-typing-indicator');
        
        // Initialize course edit AI chat if available
        if (window.CourseEditAIChat && chatContainer.data('context') === 'course_editing') {
            const courseId = chatContainer.data('course-id');
            // The course data should be passed from the parent page
            if (window.courseData) {
                window.CourseEditAIChat.init(window.courseData);
            }
        }
        
        // Enable/disable send button based on input
        chatInput.on('input', function() {
            sendButton.prop('disabled', !$(this).val().trim());
        });
        
        // Handle Enter key (send) and Shift+Enter (new line)
        chatInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendButton.prop('disabled')) {
                    sendButton.click();
                }
            }
        });
        
        // Clear chat functionality
        $('.mpcc-clear-chat').on('click', function() {
            if (confirm(mpccChatInterface.strings.confirmClear)) {
                chatMessages.empty();
                chatMessages.html(`
                    <div class="mpcc-chat-welcome">
                        <div class="mpcc-chat-message assistant">
                            <div class="message-content">
                                <p>${mpccChatInterface.strings.chatCleared}</p>
                            </div>
                        </div>
                    </div>
                `);
            }
        });
    });

})(jQuery);