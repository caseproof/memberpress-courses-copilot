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

<div id="mpcc-ai-chat-interface" class="mpcc-ai-interface" data-context="<?php echo esc_attr($context); ?>" data-post-id="<?php echo esc_attr($post_id); ?>">
    <div class="mpcc-chat-messages" style="height: <?php echo $context === 'course_creation' ? '400px' : '300px'; ?>; overflow-y: auto; border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f8f9fa; border-radius: 8px;">
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
            <button type="button" id="mpcc-send-message" class="button button-primary" style="padding: 12px 20px; border-radius: 20px; display: flex; align-items: center; gap: 6px;" disabled>
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
    // Handle quick start buttons
    $(document).on('click', '.mpcc-quick-start', function() {
        var message = $(this).data('message');
        $('#mpcc-chat-input').val(message);
        $('#mpcc-send-message').click();
    });
    
    // Auto-focus input
    $('#mpcc-chat-input').focus();
    
    // Initialize the AI interface manager if it exists
    if (typeof window.initializeMPCCAIInterface === 'function') {
        window.initializeMPCCAIInterface('<?php echo esc_js($context); ?>', <?php echo (int) $post_id; ?>);
    }
});
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