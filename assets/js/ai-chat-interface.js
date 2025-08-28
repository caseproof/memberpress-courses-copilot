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
        
        // Enhance chat interface for accessibility
        if (window.MPCCAccessibility && chatContainer.length) {
            MPCCAccessibility.enhanceChatInterface(chatContainer);
            
            // Add ARIA attributes
            chatMessages.attr({
                'role': 'log',
                'aria-label': 'Chat conversation',
                'aria-live': 'polite',
                'aria-relevant': 'additions text',
                'tabindex': '0'
            });
            
            chatInput.attr({
                'aria-label': 'Enter your message',
                'aria-describedby': 'mpcc-chat-help'
            });
            
            sendButton.attr({
                'aria-label': 'Send message'
            });
            
            // Add help text for screen readers
            if (!$('#mpcc-chat-help').length) {
                $('<span id="mpcc-chat-help" class="sr-only">Press Enter to send message, Shift+Enter for new line</span>')
                    .insertAfter(chatInput);
            }
        }
        
        // Initialize course edit AI chat if available
        if (window.CourseEditAIChat && chatContainer.data('context') === 'course_editing') {
            const courseId = chatContainer.data('course-id');
            // The course data should be passed from the parent page
            if (window.courseData) {
                window.CourseEditAIChat.init(window.courseData);
            }
        }
        
        // Enable/disable send button based on input with debouncing
        const debouncedInputHandler = MPCCUtils.debounce(function() {
            const hasContent = chatInput.val().trim();
            sendButton.prop('disabled', !hasContent);
            // Update ARIA state
            sendButton.attr('aria-disabled', !hasContent ? 'true' : 'false');
        }, 300);
        
        chatInput.off('input.chathandler').on('input.chathandler', debouncedInputHandler);
        
        // Handle Enter key (send) and Shift+Enter (new line)
        chatInput.on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (!sendButton.prop('disabled')) {
                    sendButton.click();
                }
            }
        });
        
        // Clear chat functionality - use event delegation
        $(document).off('click.mpcc-clear-chat').on('click.mpcc-clear-chat', '.mpcc-clear-chat', function() {
            if (confirm(mpccChatInterface.strings.confirmClear)) {
                chatMessages.empty();
                chatMessages.html(`
                    <div class="mpcc-chat-welcome">
                        <div class="mpcc-chat-message assistant" role="article" aria-label="Assistant message">
                            <div class="message-content">
                                <p>${mpccChatInterface.strings.chatCleared}</p>
                            </div>
                        </div>
                    </div>
                `);
                
                // Announce to screen readers
                if (window.MPCCAccessibility) {
                    MPCCAccessibility.announce('Chat history cleared', 'assertive');
                }
                
                // Return focus to input
                chatInput.focus();
            }
        });
        
        // Enhance clear button
        $('.mpcc-clear-chat').attr({
            'aria-label': 'Clear chat history',
            'role': 'button'
        });
    });

    // Add cleanup method to remove event handlers
    window.MPCCChatInterface = {
        destroy: function() {
            // Remove event handlers
            chatInput.off('input.chathandler');
            chatInput.off('keydown');
            $(document).off('click.mpcc-clear-chat');
            
            // Clear references
            chatContainer = null;
            chatMessages = null;
            chatInput = null;
            sendButton = null;
            typingIndicator = null;
        }
    };
    
    // Cleanup on page unload
    $(window).on('beforeunload.mpcc-chat', function() {
        if (window.MPCCChatInterface && window.MPCCChatInterface.destroy) {
            window.MPCCChatInterface.destroy();
        }
    });

})(jQuery);