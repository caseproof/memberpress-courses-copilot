<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Simple AJAX Controller for standalone course editor page
 */
class SimpleAjaxController
{
    private LLMService $llmService;
    private LessonDraftService $lessonDraftService;
    private ConversationManager $conversationManager;
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
        add_action('wp_ajax_mpcc_delete_session', [$this, 'handleDeleteSession']);
        add_action('wp_ajax_mpcc_duplicate_course', [$this, 'handleDuplicateCourse']);
        add_action('wp_ajax_mpcc_get_session_drafts', [$this, 'handleGetSessionDrafts']);
        
        // Override CourseAjaxService handlers with higher priority
        add_action('wp_ajax_mpcc_save_conversation', [$this, 'handleSaveConversation'], 5);
        // Note: mpcc_save_lesson_content, mpcc_load_lesson_content, mpcc_generate_lesson_content are handled by CourseAjaxService
    }
    
    /**
     * Constructor - dependencies can be injected
     * 
     * @param LLMService|null $llmService
     * @param LessonDraftService|null $lessonDraftService
     * @param ConversationManager|null $conversationManager
     * @param CourseGeneratorService|null $courseGenerator
     * @param Logger|null $logger
     */
    public function __construct(
        ?LLMService $llmService = null,
        ?LessonDraftService $lessonDraftService = null,
        ?ConversationManager $conversationManager = null,
        ?CourseGeneratorService $courseGenerator = null,
        ?Logger $logger = null
    ) {
        // Use injected dependencies or get from container
        $container = function_exists('mpcc_container') ? mpcc_container() : null;
        
        $this->llmService = $llmService ?? ($container ? $container->get(LLMService::class) : new LLMService());
        $this->lessonDraftService = $lessonDraftService ?? ($container ? $container->get(LessonDraftService::class) : new LessonDraftService());
        $this->conversationManager = $conversationManager ?? ($container ? $container->get(ConversationManager::class) : new ConversationManager());
        $this->logger = $logger ?? Logger::getInstance();
        $this->courseGenerator = $courseGenerator ?? ($container ? $container->get(CourseGeneratorService::class) : new CourseGeneratorService($this->logger));
    }
    
    /**
     * Handle chat message
     */
    public function handleChatMessage(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
            
            // Log the extraction result for debugging
            $this->logger->debug('Course structure extraction result', [
                'has_current_structure' => !empty($courseStructure),
                'found_new_structure' => $extractedStructure !== null && $extractedStructure !== $courseStructure,
                'ai_response_length' => strlen($content),
                'contains_json_block' => strpos($content, '```json') !== false
            ]);
            
            // Clean the message by removing JSON block if course structure was found
            $displayMessage = $content;
            
            // Always try to remove JSON blocks from display (even if extraction failed)
            if (preg_match('/```json\s*[\s\S]*?\s*```/s', $content)) {
                $displayMessage = preg_replace('/```json\s*[\s\S]*?\s*```/s', '', $content);
                $displayMessage = trim($displayMessage);
            }
            
            // Also remove raw JSON that might not be wrapped in code blocks
            if (preg_match('/^\s*\{[\s\S]*\}\s*$/s', $content)) {
                $displayMessage = '';
            }
            
            // If we found a course structure, provide a friendly response
            if ($extractedStructure && $extractedStructure !== $courseStructure) {
                if (empty($displayMessage)) {
                    $displayMessage = "I've created a course structure for \"" . $extractedStructure['title'] . "\". " .
                                    "This course includes " . count($extractedStructure['sections']) . " sections " .
                                    "covering all the essential topics. You can preview the course structure on the right, " .
                                    "edit individual lessons, or create the course when you're ready.";
                }
            } elseif (empty($displayMessage) && preg_match('/\{[\s\S]*\}/s', $content)) {
                // If we have JSON but failed to extract it, provide a generic response
                $displayMessage = "I've generated a course structure for you. You can preview it on the right side of the screen and make any adjustments needed.";
            }
            
            // Update session title when course structure is generated
            if ($extractedStructure && !empty($extractedStructure['title']) && $extractedStructure !== $courseStructure) {
                $sessionTitle = 'Course: ' . $extractedStructure['title'];
                
                // Update ConversationManager session
                $session = $this->conversationManager->loadSession($sessionId);
                if ($session) {
                    $session->setTitle($sessionTitle);
                    $this->conversationManager->saveSession($session);
                    
                    $this->logger->info('Session title updated during chat', [
                        'session_id' => $sessionId,
                        'new_title' => $sessionTitle,
                        'course_title' => $extractedStructure['title']
                    ]);
                } else {
                    $this->logger->warning('Session not found for title update', [
                        'session_id' => $sessionId,
                        'title' => $sessionTitle
                    ]);
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            // Log the incoming session ID for debugging
            $this->logger->debug('Loading session', ['session_id' => $sessionId]);
            
            $session = $this->conversationManager->loadSession($sessionId);
            
            if ($session === null) {
                $this->logger->warning('Session not found', ['session_id' => $sessionId]);
                wp_send_json_error('Session not found');
                return;
            }
            
            // Convert ConversationSession to the expected data format
            $context = $session->getContext();
            
            // Process messages to ensure content is preserved
            $messages = $session->getMessages();
            $processedMessages = [];
            foreach ($messages as $message) {
                // Extract the role from type field for compatibility
                $role = $message['type'] === 'user' ? 'user' : 'assistant';
                $processedMessages[] = [
                    'role' => $role,
                    'content' => $message['content'],  // Keep content as-is
                    'timestamp' => $message['timestamp'] ?? null
                ];
            }
            
            $sessionData = [
                'session_id' => $session->getSessionId(),
                'title' => $session->getTitle(),
                'conversation_history' => $processedMessages,
                'conversation_state' => $context,
                'course_structure' => $context['course_structure'] ?? [],
                'last_updated' => date('Y-m-d H:i:s', $session->getLastUpdated()),
                'created_at' => date('Y-m-d H:i:s', $session->getCreatedAt()),
                'user_id' => $session->getUserId()
            ];
            
            // Add any additional metadata
            $metadata = $session->getMetadata();
            if (isset($metadata['published_course_id'])) {
                $sessionData['published_course_id'] = $metadata['published_course_id'];
            }
            if (isset($metadata['published_course_url'])) {
                $sessionData['published_course_url'] = $metadata['published_course_url'];
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
            
            // Check for user messages (not just welcome message)
            $hasUserMessages = false;
            if (is_array($conversationHistory)) {
                foreach ($conversationHistory as $msg) {
                    if (isset($msg['role']) && $msg['role'] === 'user') {
                        $hasUserMessages = true;
                        break;
                    }
                }
            }
            
            // Don't save empty conversations
            if (!$hasCourseStructure && !$hasUserMessages) {
                wp_send_json_success(['saved' => false, 'message' => 'No content to save']);
                return;
            }
            
            // Load or create session
            $session = $this->conversationManager->loadSession($sessionId);
            
            if ($session === null) {
                // Create new session
                $sessionData = [
                    'user_id' => get_current_user_id(),
                    'context' => 'course_creation',
                    'state' => 'initial',
                    'initial_data' => $conversationState,
                    'title' => 'New Course (Draft)'
                ];
                
                // Extract title from course structure if available
                $courseTitle = $conversationState['course_data']['title'] ?? 
                              $conversationState['course_structure']['title'] ?? null;
                              
                if (!empty($courseTitle)) {
                    $sessionData['title'] = 'Course: ' . $courseTitle;
                }
                
                // Create session with the same session ID
                $sessionData['session_id'] = $sessionId;
                $session = $this->conversationManager->createSession($sessionData);
                
                $this->logger->info('Created new ConversationManager session', [
                    'session_id' => $session->getSessionId(),
                    'title' => $session->getTitle()
                ]);
            }
            
            // Update session title if course structure has title
            if (!empty($conversationState['course_structure']['title'])) {
                $session->setTitle('Course: ' . $conversationState['course_structure']['title']);
            } elseif (!empty($conversationState['course_data']['title'])) {
                $session->setTitle('Course: ' . $conversationState['course_data']['title']);
            }
            
            // Clear existing messages and add new ones
            $session->clearMessages();
            foreach ($conversationHistory as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $type = $msg['role']; // 'user' or 'assistant'
                    $session->addMessage($type, $msg['content'], []);
                }
            }
            
            // Update context
            $session->setContext($conversationState, null);
            
            // Save the session
            $this->conversationManager->saveSession($session);
            
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
            
            // Update session with course creation info
            if (!empty($courseData['title'])) {
                $sessionTitle = 'Course: ' . $courseData['title'];
                
                // Update ConversationManager session
                $session = $this->conversationManager->loadSession($sessionId);
                if ($session) {
                    $session->setTitle($sessionTitle);
                    
                    // Store course creation info in metadata
                    $session->setMetadata('published_course_id', $result['course_id']);
                    $session->setMetadata('published_course_url', $result['edit_url']);
                    $session->setMetadata('published_at', current_time('mysql'));
                    
                    $this->conversationManager->saveSession($session);
                    
                    $this->logger->info('Session updated after course creation', [
                        'session_id' => $sessionId,
                        'new_title' => $sessionTitle,
                        'course_id' => $result['course_id']
                    ]);
                } else {
                    $this->logger->warning('Session not found after course creation', [
                        'session_id' => $sessionId,
                        'course_id' => $result['course_id']
                    ]);
                }
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
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
        
        // Include current course structure if available
        if (!empty($courseStructure['title'])) {
            $prompt .= "\n\nCurrent course structure:\n```json\n" . json_encode($courseStructure, JSON_PRETTY_PRINT) . "\n```\n";
        }
        
        if (!empty($conversationHistory)) {
            $prompt .= "\n\nConversation history:\n";
            foreach (array_slice($conversationHistory, -5) as $msg) {
                $prompt .= "{$msg['role']}: {$msg['content']}\n";
            }
        }
        
        $prompt .= "\n\nUser: {$message}\n\nAssistant: ";
        
        // Check if user is asking about course creation or modification
        $courseKeywords = ['course', 'section', 'lesson', 'module', 'curriculum', 'add', 'create', 'modify', 'update', 'remove', 'delete', 'change'];
        $isAboutCourse = false;
        $lowerMessage = strtolower($message);
        
        foreach ($courseKeywords as $keyword) {
            if (stripos($lowerMessage, $keyword) !== false) {
                $isAboutCourse = true;
                break;
            }
        }
        
        // If discussing course structure, always ask for JSON response
        if ($isAboutCourse || !empty($courseStructure['title'])) {
            $prompt .= "\n\nIMPORTANT: If you are creating or modifying a course structure, you MUST include the complete updated course structure in this exact JSON format at the end of your response:
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
```

If modifying an existing course, include ALL sections and lessons (both existing and new) in your response.";
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }
            
            $userId = get_current_user_id();
            $sessions = [];
            
            // Get sessions from ConversationManager
            try {
                $conversationSessions = $this->conversationManager->getUserSessions($userId);
                
                foreach ($conversationSessions as $session) {
                    $messages = $session->getMessages();
                    
                    // Check if session has user messages
                    $hasUserMessages = false;
                    foreach ($messages as $msg) {
                        if ($msg['type'] === 'user') {
                            $hasUserMessages = true;
                            break;
                        }
                    }
                    
                    // Check if session has course structure
                    $context = $session->getContext();
                    $hasCourseStructure = isset($context['course_structure']['title']) && 
                                        !empty($context['course_structure']['title']);
                    
                    // Skip empty sessions
                    if (!$hasUserMessages && !$hasCourseStructure) {
                        continue;
                    }
                    
                    $sessions[] = [
                        'id' => $session->getSessionId(),
                        'title' => $session->getTitle(),
                        'last_updated' => date('Y-m-d H:i:s', $session->getLastUpdated()),
                        'message_count' => count($messages),
                        'source' => 'conversation_manager'
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->error('Error loading sessions from ConversationManager', [
                    'error' => $e->getMessage()
                ]);
            }
            
            
            // Sort by last updated, newest first
            usort($sessions, function($a, $b) {
                return strtotime($b['last_updated']) - strtotime($a['last_updated']);
            });
            
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
        // First, look for JSON in code blocks
        if (preg_match('/```json\s*([\s\S]*?)\s*```/s', $response, $matches)) {
            try {
                $structure = json_decode($matches[1], true);
                if (is_array($structure) && isset($structure['title'])) {
                    // Validate structure has required fields
                    if (isset($structure['sections']) && is_array($structure['sections'])) {
                        $this->logger->debug('Successfully extracted course structure from JSON code block', [
                            'title' => $structure['title'],
                            'sections_count' => count($structure['sections'])
                        ]);
                        return $structure;
                    }
                }
            } catch (\Exception $e) {
                $this->logger->debug('Failed to parse JSON from code block', ['error' => $e->getMessage()]);
            }
        }
        
        // If no code block, try to find raw JSON in the response
        // Look for JSON that starts with { and ends with } including nested structures
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $response, $matches)) {
            // Find the largest valid JSON structure
            $jsonStart = strpos($response, '{');
            $jsonEnd = strrpos($response, '}');
            
            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $potentialJson = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
                
                try {
                    $structure = json_decode($potentialJson, true);
                    if (is_array($structure) && isset($structure['title']) && isset($structure['sections'])) {
                        $this->logger->debug('Extracted raw JSON course structure', [
                            'title' => $structure['title'],
                            'sections_count' => count($structure['sections'])
                        ]);
                        return $structure;
                    }
                } catch (\Exception $e) {
                    $this->logger->debug('Failed to parse raw JSON', ['error' => $e->getMessage()]);
                }
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
            if (!NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false) && 
                !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
                !NonceConstants::verify($nonce, NonceConstants::AI_INTERFACE, false)) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $title = sanitize_text_field($_POST['title'] ?? '');
            
            if (empty($sessionId) || empty($title)) {
                throw new \Exception('Session ID and title are required');
            }
            
            // Load session from ConversationManager
            $session = $this->conversationManager->loadSession($sessionId);
            
            if (!$session) {
                throw new \Exception('Session not found');
            }
            
            // Update the title
            $session->setTitle($title);
            
            // Save the updated session
            $this->conversationManager->saveSession($session);
            
            wp_send_json_success(['updated' => true, 'title' => $title]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle delete session
     */
    public function handleDeleteSession(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            // Delete from both storage systems
            // Delete from ConversationManager
            $deleted = false;
            try {
                $deleted = $this->conversationManager->deleteSession($sessionId);
            } catch (\Exception $e) {
                $this->logger->error('Error deleting session', ['session_id' => $sessionId, 'error' => $e->getMessage()]);
            }
            
            if ($deleted) {
                // Also try to delete lesson drafts for this session
                $this->lessonDraftService->deleteSessionDrafts($sessionId);
                
                $this->logger->info('Session deleted', [
                    'session_id' => $sessionId,
                    'user_id' => get_current_user_id(),
                    'deleted_from_cm' => $deletedFromCM,
                    'deleted_from_ss' => $deletedFromSS
                ]);
                
                wp_send_json_success(['deleted' => true]);
            } else {
                throw new \Exception('Failed to delete session - not found in either storage');
            }
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle duplicate course
     */
    public function handleDuplicateCourse(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $courseData = json_decode(stripslashes($_POST['course_data'] ?? '{}'), true);
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            if (empty($courseData['title'])) {
                throw new \Exception('Course data is required');
            }
            
            // Get the original session to duplicate
            $originalSession = $this->conversationManager->loadSession($sessionId);
            
            if (!$originalSession) {
                throw new \Exception('Original session not found');
            }
            
            // Create the new session with duplicated course structure marked as draft
            $duplicatedCourseData = $courseData;
            $duplicatedCourseData['title'] = $duplicatedCourseData['title'] . ' (Draft Copy)';
            
            $newSessionData = [
                'user_id' => get_current_user_id(),
                'context' => 'course_creation',
                'state' => 'initial',
                'initial_data' => [
                    'course_structure' => $duplicatedCourseData
                ],
                'title' => 'Course: ' . $duplicatedCourseData['title']
            ];
            
            // Create new session
            $newSession = $this->conversationManager->createSession($newSessionData);
            $newSessionId = $newSession->getSessionId();
            
            // Copy lesson drafts from the original session to the new session
            $this->lessonDraftService->copySessionDrafts($sessionId, $newSessionId);
            
            $this->logger->info('Course duplicated successfully', [
                'original_session_id' => $sessionId,
                'new_session_id' => $newSessionId,
                'course_title' => $duplicatedCourseData['title'],
                'user_id' => get_current_user_id()
            ]);
            
            wp_send_json_success([
                'new_session_id' => $newSessionId,
                'course_title' => $duplicatedCourseData['title']
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    /**
     * Handle get session drafts
     */
    public function handleGetSessionDrafts(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE)) {
                throw new \Exception('Security check failed');
            }
            
            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }
            
            // Get all drafts for the session
            $drafts = $this->lessonDraftService->getSessionDrafts($sessionId);
            
            wp_send_json_success($drafts);
            
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}