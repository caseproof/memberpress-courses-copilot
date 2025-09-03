<?php

namespace MemberPressCoursesCopilot\Admin;

use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Standalone Course Editor Page
 *
 * Provides a dedicated page for AI-powered course editing
 * without the complexities of modal/overlay interfaces
 */
class CourseEditorPage
{
    /**
     * Hook suffix for the admin page
     */
    private string $hookSuffix = '';

    /**
     * Initialize the course editor page
     */
    public function init(): void
    {
        // Menu will be added when addMenuPage is called directly
        // Asset enqueuing is handled by AssetManager service
    }

    /**
     * Add menu page under MemberPress Courses
     */
    public function addMenuPage(): void
    {
        // Try as a top-level menu first to ensure it works
        $hookSuffix = add_menu_page(
            __('AI Course Editor', 'memberpress-courses-copilot'),
            __('AI Course Editor', 'memberpress-courses-copilot'),
            'edit_posts',
            'mpcc-course-editor',
            [$this, 'renderPage'],
            'dashicons-welcome-learn-more',
            58 // Position after MemberPress
        );

        $this->setHookSuffix($hookSuffix);

        // Also try to add as submenu if parent exists
        global $submenu;
        if (isset($submenu['edit.php?post_type=mpcs-course'])) {
            add_submenu_page(
                'edit.php?post_type=mpcs-course',
                __('AI Course Editor', 'memberpress-courses-copilot'),
                __('AI Course Editor', 'memberpress-courses-copilot'),
                'edit_posts',
                'mpcc-course-editor-sub',
                [$this, 'renderPage']
            );
        }
    }

    /**
     * Store the hook suffix when menu page is added
     *
     * @param string $hookSuffix
     */
    public function setHookSuffix(string $hookSuffix): void
    {
        $this->hookSuffix = $hookSuffix;
    }

    /**
     * Get the hook suffix
     *
     * @return string
     */
    public function getHookSuffix(): string
    {
        return $this->hookSuffix;
    }

    /**
     * Get or create session ID
     */
    private function getOrCreateSessionId(): string
    {
        // Check if we have a session ID in the URL
        $sessionId = $_GET['session'] ?? '';

        if (empty($sessionId)) {
            // Don't create a session ID yet - let JavaScript handle it
            // This prevents creating empty sessions when loading previous conversations
            return '';
        }

        return sanitize_text_field($sessionId);
    }

    /**
     * Render the editor page
     */
    public function renderPage(): void
    {
        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'memberpress-courses-copilot'));
        }

        // Auto-cleanup expired sessions on page load (runs occasionally)
        // Only run if we don't have an active session to avoid interfering with current work
        if (wp_rand(1, 10) === 1 && empty($_GET['session'])) { // 10% chance to run cleanup.
            $conversationManager = new \MemberPressCoursesCopilot\Services\ConversationManager();
            $conversationManager->cleanupExpiredSessions();
        }

        // Log that we're rendering the page
        error_log('MPCC: Rendering course editor page');

        $sessionId = $this->getOrCreateSessionId();

        // If no session ID, we'll let JavaScript create one when needed
        if (empty($sessionId)) {
            $sessionId = 'pending';
        }
        $courseId = isset($_GET['course_id']) ? (int) $_GET['course_id'] : 0;

        // Include the template
        include MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/admin/course-editor-page.php';
    }
}
