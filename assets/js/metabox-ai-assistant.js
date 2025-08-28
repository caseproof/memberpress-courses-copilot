/**
 * Metabox AI Assistant
 * 
 * Handles the AI Assistant functionality in the course edit metabox
 * 
 * @package MemberPressCoursesCopilot
 * @since 1.0.0
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize course data for the AI chat
        const courseData = {
            id: mpccMetaboxAI.postId,
            title: mpccMetaboxAI.postTitle,
            status: mpccMetaboxAI.postStatus,
            isNew: mpccMetaboxAI.isNew
        };
        
        // Initialize the course edit AI chat
        if (typeof CourseEditAIChat !== 'undefined') {
            CourseEditAIChat.init(courseData);
        }
    });

})(jQuery);