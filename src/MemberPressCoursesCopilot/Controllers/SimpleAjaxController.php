<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\SessionService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\ConversationManager;
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
        
        // Override CourseAjaxService handlers with higher priority
        add_action('wp_ajax_mpcc_save_conversation', [$this, 'handleSaveConversation'], 5);
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
            
            // Update session title when course structure is generated
            if ($extractedStructure && !empty($extractedStructure['title']) && $extractedStructure !== $courseStructure) {
                // Update the session title in both storage systems
                $sessionTitle = 'Course: ' . $extractedStructure['title'];
                
                // Update WordPress options-based session
                $sessionData = $this->sessionService->getSession($sessionId);
                $sessionData['title'] = $sessionTitle;
                $sessionData['last_updated'] = current_time('mysql');
                $this->sessionService->saveSession($sessionId, $sessionData);
                
                // Also update ConversationManager session if it exists
                try {
                    $conversationManager = new ConversationManager();
                    $session = $conversationManager->loadSession($sessionId);
                    if ($session) {
                        // The ConversationManager doesn't have updateSessionTitle method
                        // We'll just log that the title should be updated in ConversationManager
                        $this->logger->info('Session title should be updated in ConversationManager', [
                            'session_id' => $sessionId,
                            'title' => $sessionTitle
                        ]);
                    }
                } catch (\Exception $e) {
                    // ConversationManager session might not exist, that's OK
                    $this->logger->debug('ConversationManager session not found', [
                        'session_id' => $sessionId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $this->logger->info('Session title updated during chat', [
                    'session_id' => $sessionId,
                    'new_title' => $sessionTitle,
                    'course_title' => $extractedStructure['title']
                ]);
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
            
            // Log the incoming session ID for debugging
            $this->logger->debug('Loading session', ['session_id' => $sessionId]);
            
            $sessionData = $this->sessionService->getSession($sessionId);
            
            if ($sessionData === null) {
                $this->logger->warning('Session not found', ['session_id' => $sessionId]);
                wp_send_json_error('Session not found');
                return;
            }
            
            // Ensure course_structure is properly extracted from conversation_state
            if (isset($sessionData['conversation_state']['course_structure']) && !isset($sessionData['course_structure'])) {
                $sessionData['course_structure'] = $sessionData['conversation_state']['course_structure'];
            }
            
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
            
            // Check if conversation has meaningful content
            $hasCourseStructure = isset($conversationState['course_structure']['title']) && 
                                !empty($conversationState['course_structure']['title']);
            $hasMessages = is_array($conversationHistory) && count($conversationHistory) > 0;
            
            // Don't save empty conversations
            if (!$hasCourseStructure && !$hasMessages) {
                wp_send_json_success(['saved' => false, 'message' => 'No content to save']);
                return;
            }
            
            // Get existing session data to preserve title
            $existingData = $this->sessionService->getSession($sessionId);
            
            // If session doesn't exist, create it with default values
            if ($existingData === null) {
                $existingData = [
                    'created_at' => current_time('mysql'),
                    'user_id' => get_current_user_id()
                ];
            }
            
            // Merge with new data, preserving title if it exists
            $sessionData = array_merge($existingData, [
                'conversation_history' => $conversationHistory,
                'conversation_state' => $conversationState,
                'last_updated' => current_time('mysql')
            ]);
            
            // Extract title from course structure if available and not already set
            if (empty($sessionData['title'])) {
                // Check both course_data and course_structure for compatibility
                $courseTitle = $conversationState['course_data']['title'] ?? 
                              $conversationState['course_structure']['title'] ?? null;
                              
                if (!empty($courseTitle)) {
                    $sessionData['title'] = 'Course: ' . $courseTitle;
                }
            }
            
            $this->sessionService->saveSession($sessionId, $sessionData);
            
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
            
            // Update session title after successful course creation
            if (!empty($courseData['title'])) {
                $sessionTitle = 'Course: ' . $courseData['title'];
                
                // Update WordPress options-based session
                $sessionData = $this->sessionService->getSession($sessionId);
                $sessionData['title'] = $sessionTitle;
                $sessionData['last_updated'] = current_time('mysql');
                $this->sessionService->saveSession($sessionId, $sessionData);
                
                // Also update ConversationManager session if it exists
                try {
                    $conversationManager = new ConversationManager();
                    $session = $conversationManager->loadSession($sessionId);
                    if ($session) {
                        // The ConversationManager doesn't have updateSessionTitle method
                        // We'll just log that the title should be updated in ConversationManager
                        $this->logger->info('Session title should be updated in ConversationManager', [
                            'session_id' => $sessionId,
                            'title' => $sessionTitle
                        ]);
                    }
                } catch (\Exception $e) {
                    // ConversationManager session might not exist, that's OK
                    $this->logger->debug('ConversationManager session not found', [
                        'session_id' => $sessionId,
                        'error' => $e->getMessage()
                    ]);
                }
                
                $this->logger->info('Session title updated after course creation', [
                    'session_id' => $sessionId,
                    'new_title' => $sessionTitle,
                    'course_id' => $result['course_id']
                ]);
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
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            
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
            // Verify nonce - accept multiple nonce types
            $nonce = $_POST['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'mpcc_editor_nonce') && 
                !wp_verify_nonce($nonce, 'mpcc_courses_integration') &&
                !wp_verify_nonce($nonce, 'mpcc_ai_interface')) {
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