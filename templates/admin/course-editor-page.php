<?php

/**
 * Course Editor Page Template
 *
 * @package    MemberPressCoursesCopilot
 * @subpackage Templates/Admin
 */

defined('ABSPATH') || exit;

use MemberPressCoursesCopilot\Security\NonceConstants;

// Variables passed from controller:
// $session_id - string
// $course_id - int
?>

<div class="wrap mpcc-course-editor-wrap">
    <div id="mpcc-editor-container" class="mpcc-editor-container" data-session-id="<?php echo esc_attr($session_id); ?>">
        <!-- Header Bar -->
        <div class="mpcc-editor-header">
            <h1 class="mpcc-header-title">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php echo esc_html__('AI Course Creator', 'memberpress-courses-copilot'); ?>
            </h1>
            <span class="mpcc-status-indicator">
                <span class="mpcc-status-dot"></span>
                <span class="mpcc-status-text"><?php echo esc_html__('Ready', 'memberpress-courses-copilot'); ?></span>
            </span>
            <div class="mpcc-header-buttons">
                <button type="button" class="mpcc-icon-button" id="mpcc-previous-conversations" title="<?php echo esc_attr__('Previous Conversations', 'memberpress-courses-copilot'); ?>">
                    <span class="dashicons dashicons-backup"></span>
                </button>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=mpcs-course')); ?>" class="mpcc-icon-button mpcc-close-button">
                    <span class="dashicons dashicons-no-alt"></span>
                </a>
            </div>
        </div>
        
        <div class="mpcc-editor-layout">
            <!-- Chat/AI Interface -->
            <div class="mpcc-editor-sidebar">
                <div class="mpcc-chat-header">
                    <h2><?php echo esc_html__('AI Assistant', 'memberpress-courses-copilot'); ?></h2>
                    <div class="mpcc-quick-starters">
                        <button type="button" class="mpcc-quick-starter" id="mpcc-new-session" title="<?php echo esc_attr__('New Session', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-plus-alt"></span>
                        </button>
                        <button type="button" class="mpcc-quick-starter" id="mpcc-session-history" title="<?php echo esc_attr__('Session History', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-backup"></span>
                        </button>
                    </div>
                </div>
                <div id="mpcc-chat-container" class="mpcc-chat-container">
                    <div id="mpcc-chat-messages" class="mpcc-chat-messages"></div>
                    
                    <!-- Quick Starter Suggestions -->
                    <div id="mpcc-quick-starter-suggestions" class="mpcc-quick-starter-suggestions">
                        <button type="button" class="mpcc-quick-starter-btn" data-prompt="Create a beginner JavaScript course">
                            <?php echo esc_html__('JavaScript for Beginners', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" class="mpcc-quick-starter-btn" data-prompt="Create a digital marketing fundamentals course">
                            <?php echo esc_html__('Digital Marketing Basics', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" class="mpcc-quick-starter-btn" data-prompt="Create a yoga for wellness course">
                            <?php echo esc_html__('Yoga & Wellness', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" class="mpcc-quick-starter-btn" data-prompt="Create a business startup essentials course">
                            <?php echo esc_html__('Startup Essentials', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                    
                    <div class="mpcc-chat-input-wrapper">
                        <textarea 
                            id="mpcc-chat-input" 
                            class="mpcc-chat-input" 
                            placeholder="<?php echo esc_attr__('Ask AI to help create or modify your course...', 'memberpress-courses-copilot'); ?>"
                            rows="2"
                        ></textarea>
                        <button type="button" id="mpcc-send-message">
                            <span class="dashicons dashicons-arrow-right-alt"></span>
                            <?php echo esc_html__('Send', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Hidden fields for AJAX nonces -->
            <input type="hidden" id="mpcc-ajax-nonce" value="<?php echo esc_attr(NonceConstants::create(NonceConstants::COURSES_INTEGRATION)); ?>" />
            
            <!-- Course Content Editor -->
            <div class="mpcc-editor-main">
                <div class="mpcc-preview-header">
                    <h2><?php echo esc_html__('Course Preview', 'memberpress-courses-copilot'); ?></h2>
                    <div class="mpcc-preview-actions">
                        <button type="button" id="mpcc-view-course" style="display: none;">
                            <span class="dashicons dashicons-external"></span>
                            <?php echo esc_html__('View Course', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" id="mpcc-duplicate-course" style="display: none;">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php echo esc_html__('Duplicate Course', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" id="mpcc-create-course" disabled>
                            <span class="dashicons dashicons-yes"></span>
                            <?php echo esc_html__('Create Course', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                </div>
                
                <div id="mpcc-course-structure" class="mpcc-course-structure">
                    <!-- Course structure will be rendered here -->
                    <div class="mpcc-empty-state">
                        <div class="mpcc-empty-icon">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                        </div>
                        <h3><?php echo esc_html__('Let\'s Create Your Course!', 'memberpress-courses-copilot'); ?></h3>
                        <p><?php echo esc_html__('Start by telling the AI assistant what kind of course you want to create.', 'memberpress-courses-copilot'); ?></p>
                        <p class="mpcc-empty-suggestion"><?php echo esc_html__('Try: "Create a beginner JavaScript course" or "Help me design a marketing fundamentals course"', 'memberpress-courses-copilot'); ?></p>
                    </div>
                </div>
                
                <!-- Lesson Editor Overlay -->
                <div id="mpcc-lesson-editor-overlay" class="mpcc-lesson-editor-overlay"></div>
                
                <!-- Lesson Editor -->
                <div id="mpcc-lesson-editor" class="mpcc-editor-panel mpcc-lesson-editor" style="display: none;">
                    <div class="mpcc-lesson-header">
                        <h3 id="mpcc-lesson-title"></h3>
                        <button type="button" class="button-link" id="mpcc-close-lesson">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                    
                    <div class="mpcc-lesson-toolbar">
                        <button type="button" class="button" id="mpcc-generate-lesson-content">
                            <span class="dashicons dashicons-welcome-write-blog"></span>
                            <?php echo esc_html__('Generate', 'memberpress-courses-copilot'); ?>
                        </button>
                        <span class="mpcc-save-indicator"></span>
                    </div>
                    
                    <div class="mpcc-lesson-content">
                        <textarea 
                            id="mpcc-lesson-textarea" 
                            class="mpcc-lesson-textarea"
                            placeholder="<?php echo esc_attr__('Enter lesson content...', 'memberpress-courses-copilot'); ?>"
                            rows="20"
                        ></textarea>
                    </div>
                    
                    <div class="mpcc-lesson-actions">
                        <button type="button" class="button button-primary" id="mpcc-save-lesson">
                            <?php echo esc_html__('Save Lesson', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" class="button" id="mpcc-cancel-lesson">
                            <?php echo esc_html__('Cancel', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>