<?php
/**
 * Course Edit AI Assistant Metabox Template
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Templates
 */

defined('ABSPATH') || exit;

use MemberPressCoursesCopilot\Security\NonceConstants;

// Get post data
$post_id = $post_id ?? 0;
$post_title = $post_title ?? '';
$post_status = $post_status ?? '';
$is_new = $is_new ?? false;
?>

<input type="hidden" id="mpcc-course-ajax-nonce" value="<?php echo NonceConstants::create(NonceConstants::AI_ASSISTANT); ?>" />
<div id="mpcc-course-ai-assistant" class="mpcc-course-ai-interface" data-post-id="<?php echo esc_attr($post_id); ?>">
    <div id="mpcc-course-chat-messages" class="mpcc-course-chat-messages">
        <!-- Messages will be dynamically added here -->
    </div>
    
    <div id="mpcc-course-chat-input-container" class="mpcc-course-chat-input-container">
        <div class="mpcc-course-chat-input-wrapper">
            <textarea 
                id="mpcc-course-chat-input" 
                placeholder="<?php esc_attr_e('Ask for help with your course...', 'memberpress-courses-copilot'); ?>" 
                rows="3"
                class="mpcc-course-chat-input"
            ></textarea>
            <button type="button" id="mpcc-course-send-message" class="button button-primary mpcc-course-send-button">
                <span class="dashicons dashicons-arrow-right-alt"></span>
                <?php esc_html_e('Send', 'memberpress-courses-copilot'); ?>
            </button>
        </div>
    </div>
</div>

<?php
// Enqueue the metabox AI assistant script
wp_enqueue_script('mpcc-metabox-ai-assistant');

// Pass data to JavaScript
wp_localize_script('mpcc-metabox-ai-assistant', 'mpccMetaboxAI', [
    'postId' => $post_id,
    'postTitle' => $post_title,
    'postStatus' => $post_status,
    'isNew' => $is_new
]);
?>