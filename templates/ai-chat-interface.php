<?php

/**
 * AI Chat Interface Template
 *
 * @package MemberPressCoursesCopilot
 * @since   1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$context         = $data['context'] ?? 'general';
$courseId        = $data['course_id'] ?? 0;
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
            <?php if (empty($initialMessages)) : ?>
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
            <?php else : ?>
                <?php foreach ($initialMessages as $message) : ?>
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

<?php
// Enqueue the chat interface script
wp_enqueue_script('mpcc-ai-chat-interface');

// Pass data to JavaScript
wp_localize_script('mpcc-ai-chat-interface', 'mpccChatInterface', [
    'strings' => [
        'confirmClear' => __('Are you sure you want to clear the chat?', 'memberpress-courses-copilot'),
        'chatCleared'  => __('Chat cleared. How can I help you with your course?', 'memberpress-courses-copilot'),
    ],
]);
?>