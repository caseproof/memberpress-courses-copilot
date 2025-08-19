<?php
/**
 * Course Generator Admin Template
 *
 * @package MemberPressCoursesCopilot
 * @subpackage Templates
 */

defined('ABSPATH') || exit;

// Get current user info
$current_user = wp_get_current_user();
?>

<div class="wrap mpcc-course-generator">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('AI Course Generator', 'memberpress-courses-copilot'); ?>
    </h1>
    
    <hr class="wp-header-end">

    <div class="mpcc-generator-container">
        <!-- Chat Panel -->
        <div class="mpcc-chat-panel">
            <div class="mpcc-chat-header">
                <h2><?php esc_html_e('Course Creation Assistant', 'memberpress-courses-copilot'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Describe your course idea and let AI help you create a comprehensive curriculum.', 'memberpress-courses-copilot'); ?>
                </p>
            </div>

            <!-- Course Templates -->
            <div class="mpcc-templates-section">
                <h3><?php esc_html_e('Choose a Template', 'memberpress-courses-copilot'); ?></h3>
                <div class="mpcc-template-grid">
                    <div class="mpcc-template-card" data-template="technical">
                        <div class="mpcc-template-icon">
                            <span class="dashicons dashicons-laptop"></span>
                        </div>
                        <h4><?php esc_html_e('Technical Training', 'memberpress-courses-copilot'); ?></h4>
                        <p><?php esc_html_e('Programming, software, and technical skills', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    
                    <div class="mpcc-template-card" data-template="business">
                        <div class="mpcc-template-icon">
                            <span class="dashicons dashicons-businessperson"></span>
                        </div>
                        <h4><?php esc_html_e('Business & Professional', 'memberpress-courses-copilot'); ?></h4>
                        <p><?php esc_html_e('Business skills, management, and professional development', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    
                    <div class="mpcc-template-card" data-template="creative">
                        <div class="mpcc-template-icon">
                            <span class="dashicons dashicons-art"></span>
                        </div>
                        <h4><?php esc_html_e('Creative Arts', 'memberpress-courses-copilot'); ?></h4>
                        <p><?php esc_html_e('Design, arts, music, and creative skills', 'memberpress-courses-copilot'); ?></p>
                    </div>
                    
                    <div class="mpcc-template-card" data-template="academic">
                        <div class="mpcc-template-icon">
                            <span class="dashicons dashicons-welcome-learn-more"></span>
                        </div>
                        <h4><?php esc_html_e('Academic & Educational', 'memberpress-courses-copilot'); ?></h4>
                        <p><?php esc_html_e('Academic subjects, research, and educational content', 'memberpress-courses-copilot'); ?></p>
                    </div>
                </div>
            </div>

            <!-- Chat Interface -->
            <div class="mpcc-chat-interface">
                <div class="mpcc-chat-messages" id="mpcc-chat-messages">
                    <!-- Initial welcome message -->
                    <div class="mpcc-message mpcc-message-assistant">
                        <div class="mpcc-message-avatar">
                            <span class="dashicons dashicons-superhero-alt"></span>
                        </div>
                        <div class="mpcc-message-content">
                            <p><?php esc_html_e('Hello! I\'m your AI course creation assistant. Let\'s build an amazing course together!', 'memberpress-courses-copilot'); ?></p>
                            <p><?php esc_html_e('Start by selecting a template above or tell me about your course idea.', 'memberpress-courses-copilot'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="mpcc-chat-input-container">
                    <div class="mpcc-chat-input-wrapper">
                        <textarea 
                            id="mpcc-chat-input" 
                            placeholder="<?php esc_attr_e('Describe your course idea...', 'memberpress-courses-copilot'); ?>"
                            rows="3"
                        ></textarea>
                        <button id="mpcc-send-message" class="button button-primary">
                            <span class="dashicons dashicons-paperplane"></span>
                            <?php esc_html_e('Send', 'memberpress-courses-copilot'); ?>
                        </button>
                    </div>
                    
                    <div class="mpcc-input-actions">
                        <button id="mpcc-clear-chat" class="button button-link">
                            <?php esc_html_e('Clear Chat', 'memberpress-courses-copilot'); ?>
                        </button>
                        <div class="mpcc-word-count">
                            <span id="mpcc-word-count">0</span> <?php esc_html_e('words', 'memberpress-courses-copilot'); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Enhanced Preview Panel -->
        <div class="mpcc-preview-panel">
            <div class="mpcc-preview-header">
                <h2><?php esc_html_e('Course Preview', 'memberpress-courses-copilot'); ?></h2>
                <div class="mpcc-preview-actions">
                    <button id="mpcc-save-draft" class="button button-secondary" disabled>
                        <?php esc_html_e('Save as Draft', 'memberpress-courses-copilot'); ?>
                    </button>
                    <button id="mpcc-create-course" class="button button-primary" disabled>
                        <?php esc_html_e('Create Course', 'memberpress-courses-copilot'); ?>
                    </button>
                </div>
            </div>

            <div class="mpcc-preview-content" id="mpcc-preview-content">
                <div class="mpcc-preview-placeholder">
                    <div class="mpcc-preview-icon">
                        <span class="dashicons dashicons-welcome-learn-more"></span>
                    </div>
                    <h3><?php esc_html_e('Course Preview', 'memberpress-courses-copilot'); ?></h3>
                    <p><?php esc_html_e('Your generated course structure will appear here as you chat with the AI assistant.', 'memberpress-courses-copilot'); ?></p>
                </div>
            </div>

            <!-- Progress Indicator -->
            <div class="mpcc-generation-progress" id="mpcc-generation-progress" style="display: none;">
                <div class="mpcc-progress-bar">
                    <div class="mpcc-progress-fill"></div>
                </div>
                <p class="mpcc-progress-text"><?php esc_html_e('Generating course content...', 'memberpress-courses-copilot'); ?></p>
            </div>
        </div>
    </div>

    <!-- Hidden form for course creation -->
    <form id="mpcc-course-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: none;">
        <input type="hidden" name="action" value="mpcc_create_course">
        <input type="hidden" name="course_data" id="mpcc-course-data" value="">
        <?php wp_nonce_field('mpcc_create_course', 'mpcc_create_course_nonce'); ?>
    </form>
</div>

<!-- React/Vue App Mount Point -->
<div id="mpcc-app-root" style="display: none;"></div>

<script type="text/template" id="mpcc-message-template">
    <div class="mpcc-message mpcc-message-{{type}}">
        <div class="mpcc-message-avatar">
            <span class="dashicons dashicons-{{avatar}}"></span>
        </div>
        <div class="mpcc-message-content">
            {{content}}
        </div>
        <div class="mpcc-message-time">
            {{time}}
        </div>
    </div>
</script>

<script type="text/template" id="mpcc-course-preview-template">
    <div class="mpcc-course-structure">
        <div class="mpcc-course-header">
            <h3>{{title}}</h3>
            <p class="mpcc-course-description">{{description}}</p>
            <div class="mpcc-course-meta">
                <span class="mpcc-duration">{{duration}}</span>
                <span class="mpcc-difficulty">{{difficulty}}</span>
                <span class="mpcc-lessons-count">{{lessonsCount}} lessons</span>
            </div>
        </div>
        
        <div class="mpcc-course-objectives">
            <h4><?php esc_html_e('Learning Objectives', 'memberpress-courses-copilot'); ?></h4>
            <ul>
                {{#each objectives}}
                <li>{{this}}</li>
                {{/each}}
            </ul>
        </div>
        
        <div class="mpcc-course-sections">
            <h4><?php esc_html_e('Course Sections', 'memberpress-courses-copilot'); ?></h4>
            {{#each sections}}
            <div class="mpcc-section">
                <h5>{{title}}</h5>
                <p>{{description}}</p>
                <div class="mpcc-section-lessons">
                    {{#each lessons}}
                    <div class="mpcc-lesson">
                        <span class="mpcc-lesson-title">{{title}}</span>
                        <span class="mpcc-lesson-duration">{{duration}}</span>
                    </div>
                    {{/each}}
                </div>
            </div>
            {{/each}}
        </div>
    </div>
</script>