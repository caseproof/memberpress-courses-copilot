<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\SessionService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Simple AJAX Controller for standalone course editor page
 */
class SimpleAjaxController
{
    private LLMService $llmService;
    private LessonDraftService $lessonDraftService;
    private SessionService $sessionService;
    private CourseGeneratorService $courseGenerator;
    private Logger $logger;
    
    /**
     * Initialize the controller
     */
    public function init(): void
    {
        // Register AJAX handlers
        add_action('wp_ajax_mpcc_chat_message', [$this, 'handleChatMessage']);
        add_action('wp_ajax_mpcc_load_session', [$this, 'handleLoadSession']);
        add_action('wp_ajax_mpcc_create_course', [$this, 'handleCreateCourse']);
        add_action('wp_ajax_mpcc_get_sessions', [$this, 'handleGetSessions']);
        add_action('wp_ajax_mpcc_update_session_title', [$this, 'handleUpdateSessionTitle']);
        // Note: mpcc_save_conversation is handled by CourseAjaxService
        // Note: mpcc_save_lesson_content, mpcc_load_lesson_content, mpcc_generate_lesson_content are handled by CourseAjaxService
    }
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->llmService = new LLMService();
        $this->lessonDraftService = new LessonDraftService();
        $this->sessionService = new SessionService();
        $this->logger = Logger::getInstance();
        $this->courseGenerator = new CourseGeneratorService($this->logger);
    }
    
    /**
     * Handle chat message
     */
    public function handleChatMessage(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $message = sanitize_textarea_field($_POST['message'] ?? '');
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $conversationHistory = json_decode(stripslashes($_POST['conversation_history'] ?? '[]'), true);
            $courseStructure = json_decode(stripslashes($_POST['course_structure'] ?? '{}'), true);
            
            if (empty($message)) {
                throw new \Exception('Message is required');
            }
            
            // Generate AI response
            $prompt = $this->buildCourseGenerationPrompt($message, $conversationHistory, $courseStructure);
            $aiResponse = $this->llmService->generateContent($prompt);
            
            // Parse the response for course structure updates
            $content = $aiResponse['content'] ?? 'I apologize, but I encountered an error. Please try again.';
            $extractedStructure = $this->extractCourseStructure($content, $courseStructure);
            
            // Clean the message by removing JSON block if course structure was found
            $displayMessage = $content;
            if ($extractedStructure && $extractedStructure !== $courseStructure) {
                // Remove the JSON block from the display message
                $displayMessage = preg_replace('/```json\s*[\s\S]*?\s*```/s', '', $content);
                $displayMessage = trim($displayMessage);
                
                // If the message is now empty, provide a friendly response
                if (empty($displayMessage)) {
                    $displayMessage = "I've created a course structure for \"" . $extractedStructure['title'] . "\". " .
                                    "This course includes " . count($extractedStructure['sections']) . " sections " .
                                    "covering all the essential topics. You can preview the course structure on the right, " .
                                    "edit individual lessons, or create the course when you're ready.";
                }
            }
            
            $responseData = [
                'message' => $displayMessage,
                'course_structure' => $extractedStructure
            ];
            
            wp_send_json_success($responseData);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle load session
     */
    public function handleLoadSession(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            $sessionData = $this->sessionService->getSession($sessionId);
            
            wp_send_json_success($sessionData);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle save conversation
     */
    public function handleSaveConversation(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $conversationHistory = json_decode(stripslashes($_POST['conversation_history'] ?? '[]'), true);
            $conversationState = json_decode(stripslashes($_POST['conversation_state'] ?? '{}'), true);
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            $this->sessionService->saveSession($sessionId, [
                'conversation_history' => $conversationHistory,
                'conversation_state' => $conversationState,
                'last_updated' => current_time('mysql')
            ]);
            
            wp_send_json_success(['saved' => true]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle create course
     */
    public function handleCreateCourse(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $courseData = json_decode(stripslashes($_POST['course_data'] ?? '{}'), true);
            
            if (empty($courseData['title'])) {
                throw new \Exception('Course title is required');
            }
            
            // Get any drafted lesson content
            $courseData = $this->lessonDraftService->mapDraftsToStructure($sessionId, $courseData);
            
            // Use the CourseGeneratorService to create the course
            $result = $this->courseGenerator->generateCourse($courseData);
            
            if (!$result['success']) {
                throw new \Exception($result['error'] ?? 'Failed to create course');
            }
            
            // Clean up drafts after successful course creation
            $this->lessonDraftService->deleteSessionDrafts($sessionId);
            
            wp_send_json_success([
                'course_id' => $result['course_id'],
                'edit_url' => $result['edit_url']
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle save lesson content
     */
    public function handleSaveLessonContent(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $lessonId = sanitize_text_field($_POST['lesson_id'] ?? '');
            $lessonTitle = sanitize_text_field($_POST['lesson_title'] ?? '');
            $content = wp_kses_post($_POST['content'] ?? '');
            
            if (empty($lessonId) || empty($sessionId)) {
                throw new \Exception('Lesson ID and Session ID are required');
            }
            
            $this->lessonDraftService->saveDraft($sessionId, $lessonId, $lessonTitle, $content);
            
            wp_send_json_success(['saved' => true]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle load lesson content
     */
    public function handleLoadLessonContent(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $lessonId = sanitize_text_field($_POST['lesson_id'] ?? '');
            $lessonTitle = sanitize_text_field($_POST['lesson_title'] ?? '');
            
            if (empty($lessonId) || empty($sessionId)) {
                throw new \Exception('Lesson ID and Session ID are required');
            }
            
            $draft = $this->lessonDraftService->getDraft($sessionId, $lessonId, $lessonTitle);
            
            wp_send_json_success([
                'content' => $draft['content'] ?? ''
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle generate lesson content
     */
    public function handleGenerateLessonContent(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $lessonTitle = sanitize_text_field($_POST['lesson_title'] ?? '');
            $courseContext = json_decode(stripslashes($_POST['course_context'] ?? '{}'), true);
            
            if (empty($lessonTitle)) {
                throw new \Exception('Lesson title is required');
            }
            
            $prompt = "Generate detailed content for a lesson titled: '{$lessonTitle}'.\n\n";
            
            if (!empty($courseContext['title'])) {
                $prompt .= "This lesson is part of the course: '{$courseContext['title']}'.\n";
            }
            
            if (!empty($courseContext['description'])) {
                $prompt .= "Course description: {$courseContext['description']}\n";
            }
            
            $prompt .= "\nPlease provide comprehensive lesson content including:
- Introduction to the topic
- Key concepts and explanations
- Examples and demonstrations
- Practice exercises
- Summary and key takeaways

Format the content with clear headings and sections.";
            
            $response = $this->llmService->generateContent($prompt);
            
            wp_send_json_success([
                'content' => $response['content'] ?? 'Failed to generate content. Please try again.'
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Build course generation prompt
     */
    private function buildCourseGenerationPrompt(string $message, array $conversationHistory, array $courseStructure): string
    {
        $prompt = "You are an AI course creation assistant helping to build online courses. ";
        
        if (!empty($courseStructure['title'])) {
            $prompt .= "Current course: {$courseStructure['title']}. ";
        }
        
        if (!empty($conversationHistory)) {
            $prompt .= "\n\nConversation history:\n";
            foreach (array_slice($conversationHistory, -5) as $msg) {
                $prompt .= "{$msg['role']}: {$msg['content']}\n";
            }
        }
        
        $prompt .= "\n\nUser: {$message}\n\nAssistant: ";
        
        if (stripos($message, 'create') !== false && stripos($message, 'course') !== false) {
            $prompt .= "\n\nPlease respond with a course structure in this exact JSON format:
```json
{
  \"title\": \"Course Title\",
  \"description\": \"Course description\",
  \"sections\": [
    {
      \"title\": \"Section Title\",
      \"lessons\": [
        {
          \"title\": \"Lesson Title\",
          \"duration\": \"15 min\"
        }
      ]
    }
  ]
}
```";
        }
        
        return $prompt;
    }
    
    /**
     * Handle get sessions
     */
    public function handleGetSessions(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessions = $this->sessionService->getAllSessions();
            
            wp_send_json_success($sessions);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Extract course structure from AI response
     */
    private function extractCourseStructure(string $response, array $currentStructure): ?array
    {
        // Look for JSON in the response
        if (preg_match('/```json\s*([\s\S]*?)\s*```/s', $response, $matches)) {
            try {
                $structure = json_decode($matches[1], true);
                if (is_array($structure) && isset($structure['title'])) {
                    return $structure;
                }
            } catch (\Exception $e) {
                // Invalid JSON, ignore
            }
        }
        
        // Return current structure if no new structure found
        return !empty($currentStructure) ? $currentStructure : null;
    }
    
    /**
     * Handle update session title
     */
    public function handleUpdateSessionTitle(): void
    {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'mpcc_editor_nonce')) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $title = sanitize_text_field($_POST['title'] ?? '');
            
            if (empty($sessionId) || empty($title)) {
                throw new \Exception('Session ID and title are required');
            }
            
            // Get current session data
            $sessionData = $this->sessionService->getSession($sessionId);
            
            // Update the title
            $sessionData['title'] = $title;
            $sessionData['last_updated'] = current_time('mysql');
            
            // Save the updated session
            $this->sessionService->saveSession($sessionId, $sessionData);
            
            wp_send_json_success(['updated' => true, 'title' => $title]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}