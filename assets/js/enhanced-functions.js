/**
 * Enhanced error handling and retry functions for AI chat
 * This supplements simple-ai-chat.js with missing functions
 */

jQuery(document).ready(function($) {
    
    // Add missing functions to global scope
    window.showRetryableError = function(message, originalMessage) {
        const errorHtml = `
            <div class="mpcc-error-message mpcc-retryable-error" style="padding: 15px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px;">
                <div style="margin-bottom: 10px;">
                    <strong>Connection Error:</strong> ${message}
                </div>
                <div>
                    <button class="button button-small mpcc-retry-message" data-message="${originalMessage.replace(/"/g, '&quot;')}" style="margin-right: 10px;">
                        <span class="dashicons dashicons-update" style="font-size: 12px; vertical-align: middle;"></span>
                        Retry Message
                    </button>
                    <small style="color: #666;">Click to try sending your message again</small>
                </div>
            </div>
        `;
        $('#mpcc-chat-messages').append(errorHtml);
        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
    };
    
    window.getErrorMessage = function(xhr, status, error) {
        if (status === 'timeout') {
            return 'Request timed out. The AI service may be busy.';
        } else if (status === 'error') {
            if (xhr.status === 0) {
                return 'No internet connection. Please check your network.';
            } else if (xhr.status >= 500) {
                return 'Server error. Please try again in a moment.';
            } else if (xhr.status === 403) {
                return 'Access denied. Please refresh the page and try again.';
            }
        }
        return `Connection failed (${status}). Please try again.`;
    };
    
    // Handle retry button clicks for failed messages
    $(document).on('click', '.mpcc-retry-message', function(e) {
        e.preventDefault();
        const message = $(this).data('message');
        
        if (!message) {
            console.error('No message to retry');
            return;
        }
        
        // Remove the error message
        $(this).closest('.mpcc-retryable-error').fadeOut(300, function() {
            $(this).remove();
        });
        
        // Reset processing state
        if (window.isProcessingMessage !== undefined) {
            window.isProcessingMessage = false;
        }
        
        // Show typing indicator and add message back to input
        $('#mpcc-chat-input').val(message);
        
        // Trigger send button click to retry
        setTimeout(function() {
            $('#mpcc-send-message').click();
        }, 100);
    });
});