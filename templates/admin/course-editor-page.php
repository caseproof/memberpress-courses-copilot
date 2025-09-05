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
    <!-- Skip Links for Keyboard Navigation -->
    <div class="mpcc-skip-links">
        <a href="#mpcc-chat-container" class="screen-reader-text"><?php echo esc_html__('Skip to AI chat', 'memberpress-courses-copilot'); ?></a>
        <a href="#mpcc-course-structure" class="screen-reader-text"><?php echo esc_html__('Skip to course structure', 'memberpress-courses-copilot'); ?></a>
        <a href="#mpcc-preview-actions" class="screen-reader-text"><?php echo esc_html__('Skip to main actions', 'memberpress-courses-copilot'); ?></a>
    </div>
    
    <div id="mpcc-editor-container" class="mpcc-editor-container" data-session-id="<?php echo esc_attr($session_id); ?>" role="main" aria-label="<?php echo esc_attr__('AI Course Creator Interface', 'memberpress-courses-copilot'); ?>">
        <!-- Header Bar -->
        <div class="mpcc-editor-header" role="banner" aria-label="<?php echo esc_attr__('Course Editor Header', 'memberpress-courses-copilot'); ?>">
            <h1 class="mpcc-header-title">
                <span class="dashicons dashicons-welcome-learn-more"></span>
                <?php echo esc_html__('AI Course Creator', 'memberpress-courses-copilot'); ?>
            </h1>
            <span class="mpcc-status-indicator" role="status" aria-live="polite" aria-label="<?php echo esc_attr__('Connection Status', 'memberpress-courses-copilot'); ?>">
                <span class="mpcc-status-dot" aria-hidden="true"></span>
                <span class="mpcc-status-text"><?php echo esc_html__('Ready', 'memberpress-courses-copilot'); ?></span>
            </span>
            <div class="mpcc-header-buttons">
                <button type="button" class="mpcc-icon-button" id="mpcc-previous-conversations" title="<?php echo esc_attr__('Previous Conversations', 'memberpress-courses-copilot'); ?>" aria-label="<?php echo esc_attr__('View previous conversations', 'memberpress-courses-copilot'); ?>">
                    <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                </button>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=mpcs-course')); ?>" class="mpcc-icon-button mpcc-close-button" aria-label="<?php echo esc_attr__('Close course editor and return to courses list', 'memberpress-courses-copilot'); ?>">
                    <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
                </a>
            </div>
        </div>
        
        <div class="mpcc-editor-layout">
            <!-- Chat/AI Interface -->
            <div class="mpcc-editor-sidebar" role="complementary" aria-label="<?php echo esc_attr__('AI Assistant Chat Interface', 'memberpress-courses-copilot'); ?>">
                <div class="mpcc-chat-header">
                    <h2><?php echo esc_html__('AI Assistant', 'memberpress-courses-copilot'); ?></h2>
                    <div class="mpcc-quick-starters">
                        <button type="button" class="mpcc-quick-starter" id="mpcc-new-session" title="<?php echo esc_attr__('New Session', 'memberpress-courses-copilot'); ?>" aria-label="<?php echo esc_attr__('Start a new chat session', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="mpcc-quick-starter" id="mpcc-session-history" title="<?php echo esc_attr__('Session History', 'memberpress-courses-copilot'); ?>" aria-label="<?php echo esc_attr__('View chat session history', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                        </button>
                    </div>
                </div>
                <div id="mpcc-chat-container" class="mpcc-chat-container">
                    <div id="mpcc-chat-messages" class="mpcc-chat-messages" role="log" aria-label="<?php echo esc_attr__('Chat messages', 'memberpress-courses-copilot'); ?>" aria-live="polite"></div>
                    
                    <!-- Quick Starter Suggestions -->
                    <div id="mpcc-quick-starter-suggestions" class="mpcc-quick-starter-suggestions" role="region" aria-label="<?php echo esc_attr__('Quick starter course suggestions', 'memberpress-courses-copilot'); ?>">
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
                            aria-label="<?php echo esc_attr__('Message input for AI assistant', 'memberpress-courses-copilot'); ?>"
                        ></textarea>
                        <button type="button" id="mpcc-send-message" aria-label="<?php echo esc_attr__('Send message to AI assistant', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
                            <?php echo esc_html__('Send', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Hidden fields for AJAX nonces -->
            <input type="hidden" id="mpcc-ajax-nonce" value="<?php echo esc_attr(NonceConstants::create(NonceConstants::COURSES_INTEGRATION)); ?>" />
            
            <!-- Course Content Editor -->
            <div class="mpcc-editor-main" role="region" aria-label="<?php echo esc_attr__('Course Content Preview and Editor', 'memberpress-courses-copilot'); ?>">
                <div class="mpcc-preview-header">
                    <h2><?php echo esc_html__('Course Preview', 'memberpress-courses-copilot'); ?></h2>
                    <div id="mpcc-preview-actions" class="mpcc-preview-actions">
                        <button type="button" id="mpcc-view-course" style="display: none;" aria-label="<?php echo esc_attr__('View published course in new window', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-external" aria-hidden="true"></span>
                            <?php echo esc_html__('View Course', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" id="mpcc-duplicate-course" style="display: none;" aria-label="<?php echo esc_attr__('Create a duplicate of this course', 'memberpress-courses-copilot'); ?>">
                            <span class="dashicons dashicons-admin-page" aria-hidden="true"></span>
                            <?php echo esc_html__('Duplicate Course', 'memberpress-courses-copilot'); ?>
                        </button>
                        <button type="button" id="mpcc-create-course" disabled aria-label="<?php echo esc_attr__('Create course from AI-generated content', 'memberpress-courses-copilot'); ?>" aria-describedby="mpcc-create-course-desc">
                            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                            <?php echo esc_html__('Create Course', 'memberpress-courses-copilot'); ?>
                        </button>
                        <span id="mpcc-create-course-desc" class="screen-reader-text"><?php echo esc_html__('This button will be enabled when you have created course content with the AI assistant', 'memberpress-courses-copilot'); ?></span>
                    </div>
                </div>
                
                <div id="mpcc-course-structure" class="mpcc-course-structure" role="region" aria-label="<?php echo esc_attr__('Course structure preview', 'memberpress-courses-copilot'); ?>">
                    <!-- Course structure will be rendered here -->
                    <div class="mpcc-empty-state" role="status" aria-live="polite">
                        <div class="mpcc-empty-icon" aria-hidden="true">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                        </div>
                        <h3><?php echo esc_html__('Let\'s Create Your Course!', 'memberpress-courses-copilot'); ?></h3>
                        <p><?php echo esc_html__('Start by telling the AI assistant what kind of course you want to create.', 'memberpress-courses-copilot'); ?></p>
                        <p class="mpcc-empty-suggestion"><?php echo esc_html__('Try: "Create a beginner JavaScript course" or "Help me design a marketing fundamentals course"', 'memberpress-courses-copilot'); ?></p>
                    </div>
                </div>
                
                <!-- Lesson Editor Overlay -->
                <div id="mpcc-lesson-editor-overlay" class="mpcc-lesson-editor-overlay" aria-hidden="true"></div>
                
                <!-- Lesson Editor -->
                <div id="mpcc-lesson-editor" class="mpcc-lesson-editor" style="display: none;" role="dialog" aria-labelledby="mpcc-lesson-title" aria-modal="true" aria-hidden="true">
                    <div class="mpcc-lesson-editor-content">
                        <div class="mpcc-lesson-header">
                            <h3 id="mpcc-lesson-title"></h3>
                            <button type="button" class="button-link" id="mpcc-close-lesson" aria-label="<?php echo esc_attr__('Close lesson editor', 'memberpress-courses-copilot'); ?>">
                                <span class="dashicons dashicons-no" aria-hidden="true"></span>
                            </button>
                        </div>
                        
                        <div class="mpcc-lesson-toolbar">
                            <button type="button" class="button" id="mpcc-generate-lesson-content" aria-label="<?php echo esc_attr__('Generate lesson content using AI', 'memberpress-courses-copilot'); ?>">
                                <span class="dashicons dashicons-welcome-write-blog" aria-hidden="true"></span>
                                <?php echo esc_html__('Generate', 'memberpress-courses-copilot'); ?>
                            </button>
                            <span class="mpcc-save-indicator" role="status" aria-live="polite" aria-label="<?php echo esc_attr__('Save status', 'memberpress-courses-copilot'); ?>"></span>
                        </div>
                        
                        <div class="mpcc-lesson-body">
                            <textarea 
                                id="mpcc-lesson-textarea" 
                                class="mpcc-lesson-textarea"
                                placeholder="<?php echo esc_attr__('Enter lesson content...', 'memberpress-courses-copilot'); ?>"
                            rows="20"
                            aria-label="<?php echo esc_attr__('Lesson content editor', 'memberpress-courses-copilot'); ?>"
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