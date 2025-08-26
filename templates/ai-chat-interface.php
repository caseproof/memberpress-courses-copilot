<?php
/**
 * AI Chat Interface Template
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$context = $data['context'] ?? 'general';
$courseId = $data['course_id'] ?? 0;
$initialMessages = $data['messages'] ?? [];
?>

<div id="mpcc-ai-chat-interface" class="mpcc-ai-chat-container" data-context="<?php echo esc_attr($context); ?>" data-course-id="<?php echo esc_attr($courseId); ?>">
    <div class="mpcc-chat-header">
        <h3 class="mpcc-chat-title">
            <span class="dashicons dashicons-format-chat"></span>
            <?php esc_html_e('AI Assistant', 'memberpress-courses-copilot'); ?>
        </h3>
        <div class="mpcc-chat-actions">
            <button type="button" class="mpcc-chat-action-btn mpcc-clear-chat" title="<?php esc_attr_e('Clear Chat', 'memberpress-courses-copilot'); ?>">
                <span class="dashicons dashicons-trash"></span>
            </button>
        </div>
    </div>

    <div class="mpcc-chat-messages-wrapper">
        <div id="mpcc-chat-messages" class="mpcc-chat-messages">
            <?php if (empty($initialMessages)): ?>
                <div class="mpcc-chat-welcome">
                    <div class="mpcc-chat-message assistant">
                        <div class="message-content">
                            <p><?php esc_html_e('Hi! I\'m your AI course assistant. I can help you:', 'memberpress-courses-copilot'); ?></p>
                            <ul>
                                <li><?php esc_html_e('Update course content and structure', 'memberpress-courses-copilot'); ?></li>
                                <li><?php esc_html_e('Generate lesson content', 'memberpress-courses-copilot'); ?></li>
                                <li><?php esc_html_e('Create quizzes and assessments', 'memberpress-courses-copilot'); ?></li>
                                <li><?php esc_html_e('Improve learning objectives', 'memberpress-courses-copilot'); ?></li>
                            </ul>
                            <p><?php esc_html_e('What would you like to work on today?', 'memberpress-courses-copilot'); ?></p>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($initialMessages as $message): ?>
                    <div class="mpcc-chat-message <?php echo esc_attr($message['role']); ?>">
                        <div class="message-content">
                            <?php echo wp_kses_post($message['content']); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div id="mpcc-typing-indicator" class="mpcc-typing-indicator" style="display: none;">
            <div class="typing-dots">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </div>

    <div class="mpcc-chat-input-wrapper">
        <div class="mpcc-chat-input-container">
            <textarea 
                id="mpcc-course-chat-input" 
                class="mpcc-chat-input" 
                placeholder="<?php esc_attr_e('Ask me anything about your course...', 'memberpress-courses-copilot'); ?>"
                rows="2"
            ></textarea>
            <button type="button" id="mpcc-course-send-message" class="mpcc-send-button" disabled>
                <span class="dashicons dashicons-arrow-right-alt"></span>
                <span class="button-text"><?php esc_html_e('Send', 'memberpress-courses-copilot'); ?></span>
            </button>
        </div>
        <div class="mpcc-chat-helper-text">
            <?php esc_html_e('Press Enter to send, Shift+Enter for new line', 'memberpress-courses-copilot'); ?>
        </div>
    </div>
</div>

<style>
/* Ensure proper styling for chat interface */
.mpcc-ai-chat-container {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 400px;
}

.mpcc-chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    background: #f8f9fa;
    border-bottom: 1px solid #e1e4e8;
}

.mpcc-chat-title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.mpcc-chat-messages-wrapper {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.mpcc-chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
}

.mpcc-chat-message {
    margin-bottom: 16px;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.mpcc-chat-message.user {
    display: flex;
    justify-content: flex-end;
}

.mpcc-chat-message.user .message-content {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 18px;
    border-radius: 18px 18px 4px 18px;
    max-width: 70%;
}

.mpcc-chat-message.assistant .message-content {
    background: #f0f4f8;
    color: #1d2327;
    padding: 12px 18px;
    border-radius: 18px 18px 18px 4px;
    max-width: 70%;
    display: inline-block;
}

.mpcc-typing-indicator {
    padding: 0 20px 10px;
}

.typing-dots {
    display: inline-flex;
    gap: 4px;
    padding: 12px 18px;
    background: #f0f4f8;
    border-radius: 18px;
}

.typing-dots span {
    width: 8px;
    height: 8px;
    background: #646970;
    border-radius: 50%;
    animation: typing 1.4s infinite;
}

.typing-dots span:nth-child(2) {
    animation-delay: 0.2s;
}

.typing-dots span:nth-child(3) {
    animation-delay: 0.4s;
}

@keyframes typing {
    0%, 60%, 100% {
        opacity: 0.2;
        transform: scale(0.8);
    }
    30% {
        opacity: 1;
        transform: scale(1);
    }
}

.mpcc-chat-input-wrapper {
    padding: 15px;
    background: #f8f9fa;
    border-top: 1px solid #e1e4e8;
}

.mpcc-chat-input-container {
    display: flex;
    gap: 10px;
}

.mpcc-chat-input {
    flex: 1;
    padding: 10px 15px;
    border: 2px solid #e1e4e8;
    border-radius: 8px;
    font-size: 14px;
    resize: none;
    transition: border-color 0.2s;
}

.mpcc-chat-input:focus {
    outline: none;
    border-color: #667eea;
}

.mpcc-send-button {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 10px 20px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
}

.mpcc-send-button:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.mpcc-send-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.mpcc-chat-helper-text {
    margin-top: 8px;
    font-size: 12px;
    color: #646970;
    text-align: center;
}

.mpcc-chat-action-btn {
    background: none;
    border: none;
    padding: 5px;
    cursor: pointer;
    color: #646970;
    transition: color 0.2s;
}

.mpcc-chat-action-btn:hover {
    color: #d63638;
}
</style>

<script>
jQuery(document).ready(function($) {
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
        if (confirm('<?php echo esc_js(__('Are you sure you want to clear the chat?', 'memberpress-courses-copilot')); ?>')) {
            chatMessages.empty();
            chatMessages.html(`
                <div class="mpcc-chat-welcome">
                    <div class="mpcc-chat-message assistant">
                        <div class="message-content">
                            <p><?php echo esc_js(__('Chat cleared. How can I help you with your course?', 'memberpress-courses-copilot')); ?></p>
                        </div>
                    </div>
                </div>
            `);
        }
    });
});
</script>