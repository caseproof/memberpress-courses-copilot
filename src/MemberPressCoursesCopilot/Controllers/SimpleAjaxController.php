<?php

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use MemberPressCoursesCopilot\Security\NonceConstants;
use WP_Error;

/**
 * Simple AJAX Controller for standalone course editor page
 *
 * Handles AJAX operations for the MemberPress Courses Copilot course editor
 * including chat interactions, session management, and course creation.
 *
 * @since 1.0.0
 */
class SimpleAjaxController
{
    /**
     * @var LLMService Service for Language Learning Model interactions and content generation
     */
    private LLMService $llmService;

    /**
     * @var LessonDraftService Service for managing lesson drafts during course editing
     */
    private LessonDraftService $lessonDraftService;

    /**
     * @var ConversationManager Service for managing and persisting conversation sessions
     */
    private ConversationManager $conversationManager;

    /**
     * @var CourseGeneratorService Service for generating complete course structures
     */
    private CourseGeneratorService $courseGenerator;

    /**
     * @var Logger Logging service for debugging and monitoring
     */
    private Logger $logger;

    /**
     * Initialize the controller
     *
     * Registers all AJAX action handlers for the course editor interface.
     *
     * @since  1.0.0
     * @return void
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
     * @since 1.0.0
     * @param LLMService|null             $llmService          The language model service for AI content generation.
     * @param LessonDraftService|null     $lessonDraftService  The service for managing lesson drafts and content.
     * @param ConversationManager|null    $conversationManager The service for managing conversation sessions.
     * @param CourseGeneratorService|null $courseGenerator     The service for generating course structures.
     * @param Logger|null                 $logger              The logger instance for debugging and monitoring.
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

        $this->llmService          = $llmService ?? ($container ? $container->get(LLMService::class) : new LLMService());
        $this->lessonDraftService  = $lessonDraftService ?? ($container ? $container->get(LessonDraftService::class) : new LessonDraftService());
        $this->conversationManager = $conversationManager ?? ($container ? $container->get(ConversationManager::class) : new ConversationManager());
        $this->logger              = $logger ?? Logger::getInstance();
        $this->courseGenerator     = $courseGenerator ?? ($container ? $container->get(CourseGeneratorService::class) : new CourseGeneratorService($this->logger));
    }

    /**
     * Recursively sanitize complex array data with type-aware validation
     *
     * This method provides comprehensive sanitization for deeply nested arrays
     * containing user input from AJAX requests. It's specifically designed to
     * handle complex data structures like conversation histories and course
     * configurations that may contain mixed data types.
     *
     * Recursive Sanitization Strategy:
     * - Preserves array structure while cleaning all scalar values
     * - Applies appropriate WordPress sanitization based on data type
     * - Handles unlimited nesting depth for complex JSON structures
     * - Maintains array keys for associative arrays
     *
     * Type-Specific Sanitization Rules:
     * - 'text': Standard text fields, removes HTML and encodes entities
     * - 'textarea': Preserves line breaks, removes dangerous HTML
     * - 'email': Validates email format and removes invalid characters
     * - 'url': Validates URLs, removes dangerous protocols
     * - 'int': Converts to integer, handles type coercion safely
     * - 'float': Preserves decimal precision for numeric data
     * - 'boolean': Handles string boolean representations ('true', '1')
     * - 'html': Allows safe HTML tags for rich content fields
     *
     * Security Considerations:
     * - Prevents XSS attacks through comprehensive HTML sanitization
     * - Handles malicious nested data structures safely
     * - Ensures type consistency to prevent injection attacks
     * - Uses WordPress core sanitization functions (tested and secure)
     *
     * Use Cases in Course Editor:
     * - Conversation histories with mixed text/metadata
     * - Course structures with nested sections/lessons
     * - User preferences and configuration options
     * - Form data from complex multi-step wizards
     *
     * @since  1.0.0
     * @param  array  $data Nested array data to sanitize (preserves structure)
     * @param  string $type Sanitization strategy to apply recursively. Accepts 'text', 'textarea', 'email', 'url', 'int', 'float', 'boolean', 'html'. Default 'text'.
     * @return array Fully sanitized array with identical structure
     */
    protected function sanitizeArray(array $data, string $type = 'text'): array
    {
        return array_map(function ($item) use ($type) {
            // Handle nested arrays by recursive call
            // This preserves complex data structures while ensuring all values are clean
            if (is_array($item)) {
                return $this->sanitizeArray($item, $type);
            }

            // Apply type-specific sanitization using PHP 8 match expression
            // Each sanitization function is optimized for its specific data type
            return match ($type) {
                // Textarea: Allows line breaks but removes script tags and dangerous HTML
                'textarea' => sanitize_textarea_field($item),

                // Email: Validates format according to RFC standards
                'email' => sanitize_email($item),

                // URL: Validates protocol, removes javascript: and other dangerous schemes
                'url' => esc_url_raw($item),

                // Integer: Converts to whole number, handles non-numeric input gracefully
                'int' => intval($item),

                // Float: Preserves decimal precision for measurements and calculations
                'float' => floatval($item),

                // Boolean: Converts string representations to actual boolean values
                'boolean' => filter_var($item, FILTER_VALIDATE_BOOLEAN),

                // HTML: Allows safe HTML tags based on post content rules
                'html' => wp_kses_post($item),

                // Default text: Removes all HTML and encodes special characters
                default => sanitize_text_field($item)
            };
        }, $data);
    }

    /**
     * Handle chat message
     *
     * Processes user chat messages, generates AI responses, and extracts course structures.
     * Expects POST parameters: nonce, message, session_id, conversation_history, course_structure.
     *
     * @since  1.0.0
     * @return void Sends JSON response with message and course_structure
     * @throws \Exception If an error occurs during processing
     */
    public function handleChatMessage(): void
    {
        try {
            // Critical Security Validation: Verify CSRF protection nonce
            // This prevents unauthorized requests from external sites or malicious scripts
            // Uses EDITOR_NONCE constant for consistency across course editor operations
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if (!NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false)) {
                // Send standardized error response with specific error code
                // 403 status indicates authorization failure (not authentication)
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            $message             = sanitize_textarea_field($_POST['message'] ?? '');
            $sessionId           = sanitize_text_field($_POST['session_id'] ?? '');
            $conversationHistory = json_decode(stripslashes($_POST['conversation_history'] ?? '[]'), true);
            $courseStructure     = json_decode(stripslashes($_POST['course_structure'] ?? '{}'), true);

            // Sanitize arrays after JSON decode
            if (is_array($conversationHistory)) {
                $conversationHistory = $this->sanitizeArray($conversationHistory, 'textarea');
            }
            if (is_array($courseStructure)) {
                $courseStructure = $this->sanitizeArray($courseStructure);
            }

            if (empty($message)) {
                ApiResponse::errorMessage('Message is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Generate AI response
            $prompt     = $this->buildCourseGenerationPrompt($message, $conversationHistory, $courseStructure);
            $aiResponse = $this->llmService->generateContent($prompt);

            // Parse the response for course structure updates
            $content            = $aiResponse['content'] ?? 'I apologize, but I encountered an error. Please try again.';
            $extractedStructure = $this->extractCourseStructure($content, $courseStructure);

            // Log the extraction result for debugging
            $this->logger->debug('Course structure extraction result', [
                'has_current_structure' => !empty($courseStructure),
                'found_new_structure'   => $extractedStructure !== null && $extractedStructure !== $courseStructure,
                'ai_response_length'    => strlen($content),
                'contains_json_block'   => strpos($content, '```json') !== false,
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
                    $displayMessage = "I've created a course structure for \"" . $extractedStructure['title'] . '". ' .
                                    'This course includes ' . count($extractedStructure['sections']) . ' sections ' .
                                    'covering all the essential topics. You can preview the course structure on the right, ' .
                                    "edit individual lessons, or create the course when you're ready.";
                }
            } elseif (empty($displayMessage) && preg_match('/\{[\s\S]*\}/s', $content)) {
                // If we have JSON but failed to extract it, provide a generic response
                $displayMessage = "I've generated a course structure for you. You can preview it on the right side of the screen and make any adjustments needed.";
            }

            // Ensure we always have a valid message
            if (empty($displayMessage)) {
                $displayMessage = "I've processed your request. Please let me know if you need any clarification or have additional questions.";
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
                        'session_id'   => $sessionId,
                        'new_title'    => $sessionTitle,
                        'course_title' => $extractedStructure['title'],
                    ]);
                } else {
                    $this->logger->warning('Session not found for title update', [
                        'session_id' => $sessionId,
                        'title'      => $sessionTitle,
                    ]);
                }
            }

            $responseData = [
                'message'          => $displayMessage,
                'course_structure' => $extractedStructure,
            ];

            // Send response directly to avoid double nesting
            // The JavaScript expects response.data.message structure
            wp_send_json_success($responseData);
        } catch (\Exception $e) {
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
        }
    }

    /**
     * Handle load session
     *
     * Loads a conversation session by ID and returns its data.
     * Expects POST parameters: nonce, session_id.
     *
     * @since  1.0.0
     * @return void Sends JSON response with session data
     * @throws \Exception If session is not found or an error occurs
     */
    public function handleLoadSession(): void
    {
        try {
            // Critical Security Validation: Verify CSRF protection nonce
            // This prevents unauthorized requests from external sites or malicious scripts
            // Uses EDITOR_NONCE constant for consistency across course editor operations
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                // Send standardized error response with specific error code
                // 403 status indicates authorization failure (not authentication)
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');

            if (empty($sessionId)) {
                ApiResponse::errorMessage('Session ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Log the incoming session ID for debugging
            $this->logger->debug('Loading session', ['session_id' => $sessionId]);

            $session = $this->conversationManager->loadSession($sessionId);

            if ($session === null) {
                $this->logger->warning('Session not found', ['session_id' => $sessionId]);
                ApiResponse::notFound('Session not found', ApiResponse::ERROR_SESSION_NOT_FOUND);
                return;
            }

            // Convert ConversationSession to the expected data format
            $context = $session->getContext();

            // Process messages to ensure content is preserved
            $messages          = $session->getMessages();
            $processedMessages = [];
            foreach ($messages as $message) {
                // Extract the role from type field for compatibility
                $role                = $message['type'] === 'user' ? 'user' : 'assistant';
                $processedMessages[] = [
                    'role'      => $role,
                    'content'   => $message['content'],  // Keep content as-is
                    'timestamp' => $message['timestamp'] ?? null,
                ];
            }

            $sessionData = [
                'session_id'           => $session->getSessionId(),
                'title'                => $session->getTitle(),
                'conversation_history' => $processedMessages,
                'conversation_state'   => $context,
                'course_structure'     => $context['course_structure'] ?? [],
                'last_updated'         => date('Y-m-d H:i:s', $session->getLastUpdated()),
                'created_at'           => date('Y-m-d H:i:s', $session->getCreatedAt()),
                'user_id'              => $session->getUserId(),
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
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
        }
    }

    /**
     * Handle save conversation
     *
     * Saves or updates a conversation session with history and state.
     * Expects POST parameters: nonce, session_id, conversation_history, conversation_state.
     *
     * @since  1.0.0
     * @return void Sends JSON response with save status
     * @throws \Exception If save operation fails
     */
    public function handleSaveConversation(): void
    {
        try {
            // Critical Security Validation: Verify CSRF protection nonce
            // This prevents unauthorized requests from external sites or malicious scripts
            // Uses EDITOR_NONCE constant for consistency across course editor operations
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                // Send standardized error response with specific error code
                // 403 status indicates authorization failure (not authentication)
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            $sessionId           = sanitize_text_field($_POST['session_id'] ?? '');
            $conversationHistory = json_decode(stripslashes($_POST['conversation_history'] ?? '[]'), true);
            $conversationState   = json_decode(stripslashes($_POST['conversation_state'] ?? '{}'), true);

            // Sanitize arrays after JSON decode
            if (is_array($conversationHistory)) {
                $conversationHistory = $this->sanitizeArray($conversationHistory, 'textarea');
            }
            if (is_array($conversationState)) {
                $conversationState = $this->sanitizeArray($conversationState);
            }

            if (empty($sessionId)) {
                ApiResponse::errorMessage('Session ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
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
                wp_send_json_success([
                    'saved'   => false,
                    'message' => 'No content to save',
                ]);
                return;
            }

            // Load or create session
            $session = $this->conversationManager->loadSession($sessionId);

            if ($session === null) {
                // Create new session
                $sessionData = [
                    'user_id'      => (int) get_current_user_id(),
                    'context'      => 'course_creation',
                    'state'        => 'initial',
                    'initial_data' => $conversationState,
                    'title'        => 'New Course (Draft)',
                ];

                // Extract title from course structure if available
                $courseTitle = $conversationState['course_data']['title'] ??
                              $conversationState['course_structure']['title'] ?? null;

                if (!empty($courseTitle)) {
                    $sessionData['title'] = 'Course: ' . $courseTitle;
                }

                // Create session with the same session ID
                $sessionData['session_id'] = $sessionId;
                $session                   = $this->conversationManager->createSession($sessionData);

                $this->logger->info('Created new ConversationManager session', [
                    'session_id' => $session->getSessionId(),
                    'title'      => $session->getTitle(),
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
                    // Map frontend 'role' to backend 'type' field
                    $messageType = $msg['role'] === 'user' ? 'user' :
                                  ($msg['role'] === 'assistant' ? 'assistant' : 'system');
                    $metadata    = [];
                    if (isset($msg['timestamp'])) {
                        $metadata['timestamp'] = $msg['timestamp'];
                    }
                    $session->addMessage($messageType, $msg['content'], $metadata);
                }
            }

            // Update context
            $session->setContext($conversationState, null);

            // Save the session
            $this->conversationManager->saveSession($session);

            wp_send_json_success(['saved' => true]);
        } catch (\Exception $e) {
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
        }
    }

    /**
     * Handle create course
     *
     * Creates a new course from the provided course data structure.
     * Expects POST parameters: nonce, session_id, course_data.
     *
     * @since  1.0.0
     * @return void Sends JSON response with course_id and edit_url
     * @throws \Exception If course creation fails
     */
    public function handleCreateCourse(): void
    {
        try {
            // Critical Security Validation: Verify CSRF protection nonce
            // This prevents unauthorized requests from external sites or malicious scripts
            // Uses EDITOR_NONCE constant for consistency across course editor operations
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                // Send standardized error response with specific error code
                // 403 status indicates authorization failure (not authentication)
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            $sessionId  = sanitize_text_field($_POST['session_id'] ?? '');
            $courseData = json_decode(stripslashes($_POST['course_data'] ?? '{}'), true);

            // Custom sanitization for course data to preserve HTML in description
            if (is_array($courseData)) {
                // Sanitize top-level fields
                if (isset($courseData['title'])) {
                    $courseData['title'] = sanitize_text_field($courseData['title']);
                }
                
                // Preserve HTML content in description - use textarea field to preserve line breaks
                if (isset($courseData['description'])) {
                    $courseData['description'] = sanitize_textarea_field($courseData['description']);
                }
                
                // Sanitize sections array
                if (isset($courseData['sections']) && is_array($courseData['sections'])) {
                    foreach ($courseData['sections'] as $sectionIndex => $section) {
                        if (isset($section['title'])) {
                            $courseData['sections'][$sectionIndex]['title'] = sanitize_text_field($section['title']);
                        }
                        if (isset($section['description'])) {
                            $courseData['sections'][$sectionIndex]['description'] = sanitize_textarea_field($section['description']);
                        }
                        
                        // Sanitize lessons within sections
                        if (isset($section['lessons']) && is_array($section['lessons'])) {
                            foreach ($section['lessons'] as $lessonIndex => $lesson) {
                                if (isset($lesson['title'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['title'] = sanitize_text_field($lesson['title']);
                                }
                                if (isset($lesson['content'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['content'] = sanitize_textarea_field($lesson['content']);
                                }
                                if (isset($lesson['duration'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['duration'] = sanitize_text_field($lesson['duration']);
                                }
                            }
                        }
                    }
                }
            }

            if (empty($courseData['title'])) {
                ApiResponse::errorMessage('Course title is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Get any drafted lesson content
            $courseData = $this->lessonDraftService->mapDraftsToStructure($sessionId, $courseData);

            // Use the CourseGeneratorService to create the course
            $result = $this->courseGenerator->generateCourse($courseData);

            if (!$result['success']) {
                $error = new WP_Error(ApiResponse::ERROR_COURSE_GENERATION, $result['error'] ?? 'Failed to create course');
                ApiResponse::error($error);
                return;
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
                        'new_title'  => $sessionTitle,
                        'course_id'  => $result['course_id'],
                    ]);
                } else {
                    $this->logger->warning('Session not found after course creation', [
                        'session_id' => $sessionId,
                        'course_id'  => $result['course_id'],
                    ]);
                }
            }

            // Clean up drafts after successful course creation
            $this->lessonDraftService->deleteSessionDrafts($sessionId);

            wp_send_json_success([
                'course_id' => $result['course_id'],
                'edit_url'  => $result['edit_url'],
            ]);
        } catch (\Exception $e) {
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_COURSE_GENERATION);
            ApiResponse::error($error);
        }
    }

    /**
     * Handle save lesson content
     *
     * Saves a draft of lesson content for later use.
     * Expects POST parameters: nonce, session_id, lesson_id, lesson_title, content.
     *
     * @since  1.0.0
     * @return void Sends JSON response with save status
     * @throws \Exception If save operation fails
     */
    public function handleSaveLessonContent(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }

            $sessionId   = sanitize_text_field($_POST['session_id'] ?? '');
            $lessonId    = sanitize_text_field($_POST['lesson_id'] ?? '');
            $lessonTitle = sanitize_text_field($_POST['lesson_title'] ?? '');
            $content     = sanitize_textarea_field($_POST['content'] ?? '');

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
     *
     * Loads previously saved lesson draft content.
     * Expects POST parameters: nonce, session_id, lesson_id, lesson_title.
     *
     * @since  1.0.0
     * @return void Sends JSON response with lesson content
     * @throws \Exception If lesson draft is not found
     */
    public function handleLoadLessonContent(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }

            $sessionId   = sanitize_text_field($_POST['session_id'] ?? '');
            $lessonId    = sanitize_text_field($_POST['lesson_id'] ?? '');
            $lessonTitle = sanitize_text_field($_POST['lesson_title'] ?? '');

            if (empty($lessonId) || empty($sessionId)) {
                throw new \Exception('Lesson ID and Session ID are required');
            }

            $draft = $this->lessonDraftService->getDraft($sessionId, $lessonId, $lessonTitle);

            wp_send_json_success([
                'content' => $draft['content'] ?? '',
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle generate lesson content
     *
     * Generates AI content for a specific lesson based on title and course context.
     * Expects POST parameters: nonce, lesson_title, course_context.
     *
     * @since  1.0.0
     * @return void Sends JSON response with generated content
     * @throws \Exception If content generation fails
     */
    public function handleGenerateLessonContent(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }

            $lessonTitle   = sanitize_text_field($_POST['lesson_title'] ?? '');
            $courseContext = json_decode(stripslashes($_POST['course_context'] ?? '{}'), true);

            // Sanitize course context array after JSON decode
            if (is_array($courseContext)) {
                $courseContext = $this->sanitizeArray($courseContext);
            }

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
                'content' => $response['content'] ?? 'Failed to generate content. Please try again.',
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Build course generation prompt
     *
     * Constructs a detailed prompt for the AI to generate or modify course structures.
     *
     * @since  1.0.0
     * @param  string $message             The user's current message
     * @param  array  $conversationHistory Array of previous conversation messages
     * @param  array  $courseStructure     Current course structure if exists
     * @return string The formatted prompt for AI processing
     */
    private function buildCourseGenerationPrompt(string $message, array $conversationHistory, array $courseStructure): string
    {
        $prompt = 'You are an AI course creation assistant helping to build online courses. ';

        // Include current course structure if available
        if (!empty($courseStructure['title'])) {
            $prompt .= "\n\nCurrent course structure:\n```json\n" . wp_json_encode($courseStructure, JSON_PRETTY_PRINT) . "\n```\n";
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
        $isAboutCourse  = false;
        $lowerMessage   = strtolower($message);

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
     *
     * Retrieves all conversation sessions for the current user.
     * Expects POST parameters: nonce.
     *
     * @since  1.0.0
     * @return void Sends JSON response with array of sessions
     * @throws \Exception If session retrieval fails
     */
    public function handleGetSessions(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }

            $userId   = (int) get_current_user_id();
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
                    $context            = $session->getContext();
                    $hasCourseStructure = isset($context['course_structure']['title']) &&
                                        !empty($context['course_structure']['title']);

                    // Skip empty sessions
                    if (!$hasUserMessages && !$hasCourseStructure) {
                        continue;
                    }

                    $sessions[] = [
                        'id'            => $session->getSessionId(),
                        'title'         => $session->getTitle(),
                        'last_updated'  => date('Y-m-d H:i:s', $session->getLastUpdated()),
                        'created_at'    => date('Y-m-d H:i:s', $session->getCreatedAt()),
                        'message_count' => count($messages),
                        'source'        => 'conversation_manager',
                        'database_id'   => $session->getDatabaseId(),
                    ];
                }
            } catch (\Exception $e) {
                $this->logger->error('Error loading sessions from ConversationManager', [
                    'error' => $e->getMessage(),
                ]);
            }


            // Sort by last updated, newest first, with session ID as tiebreaker
            usort($sessions, function ($a, $b) {
                $timeCompare = strtotime($b['last_updated']) - strtotime($a['last_updated']);
                // If timestamps are equal, sort by session ID to ensure stable sort order
                if ($timeCompare === 0) {
                    return strcmp($b['id'], $a['id']);
                }
                return $timeCompare;
            });

            wp_send_json_success($sessions);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Extract course structure from AI response
     *
     * Parses AI response to extract JSON course structure data.
     * Looks for JSON in code blocks first, then raw JSON in response.
     *
     * @since  1.0.0
     * @param  string $response         The AI response text to parse
     * @param  array  $currentStructure The current course structure as fallback
     * @return array|null Extracted course structure array or current structure if no new structure found
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
                            'title'          => $structure['title'],
                            'sections_count' => count($structure['sections']),
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
            $jsonEnd   = strrpos($response, '}');

            if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd > $jsonStart) {
                $potentialJson = substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);

                try {
                    $structure = json_decode($potentialJson, true);
                    if (is_array($structure) && isset($structure['title']) && isset($structure['sections'])) {
                        $this->logger->debug('Extracted raw JSON course structure', [
                            'title'          => $structure['title'],
                            'sections_count' => count($structure['sections']),
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
     *
     * Updates the title of an existing session.
     * Expects POST parameters: nonce, session_id, title.
     * Accepts multiple nonce types: EDITOR_NONCE, COURSES_INTEGRATION, AI_INTERFACE.
     *
     * @since  1.0.0
     * @return void Sends JSON response with update status
     * @throws \Exception If session is not found or update fails
     */
    public function handleUpdateSessionTitle(): void
    {
        try {
            // Multi-Nonce Security Validation for Cross-Context Operations
            // This method accepts multiple nonce types because session title updates
            // can be triggered from different parts of the application:
            // - EDITOR_NONCE: From main course editor interface
            // - COURSES_INTEGRATION: From course integration workflows
            // - AI_INTERFACE: From AI chat interface operations
            $nonce = $_POST['nonce'] ?? '';

            // Check against all accepted nonce types (logical OR)
            // At least one must be valid for the operation to proceed
            if (
                !NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false) &&
                !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
                !NonceConstants::verify($nonce, NonceConstants::AI_INTERFACE, false)
            ) {
                // All nonce validations failed - this is a security violation
                throw new \Exception('Security check failed');
            }

            $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
            $title     = sanitize_text_field($_POST['title'] ?? '');

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

            wp_send_json_success([
                'updated' => true,
                'title'   => $title,
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle delete session
     *
     * Deletes a conversation session and its associated drafts.
     * Expects POST parameters: nonce, session_id.
     *
     * @since  1.0.0
     * @return void Sends JSON response with deletion status
     * @throws \Exception If session deletion fails
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
                $this->logger->error('Error deleting session', [
                    'session_id' => $sessionId,
                    'error'      => $e->getMessage(),
                ]);
            }

            if ($deleted) {
                // Also try to delete lesson drafts for this session
                $this->lessonDraftService->deleteSessionDrafts($sessionId);

                $this->logger->info('Session deleted', [
                    'session_id' => $sessionId,
                    'user_id'    => get_current_user_id(),
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
     *
     * Creates a duplicate of an existing course session with '(Draft Copy)' suffix.
     * Expects POST parameters: nonce, session_id, course_data.
     *
     * @since  1.0.0
     * @return void Sends JSON response with new_session_id and course_title
     * @throws \Exception If duplication fails
     */
    public function handleDuplicateCourse(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EDITOR_NONCE, false)) {
                throw new \Exception('Security check failed');
            }

            $sessionId  = sanitize_text_field($_POST['session_id'] ?? '');
            $courseData = json_decode(stripslashes($_POST['course_data'] ?? '{}'), true);

            // Custom sanitization for course data to preserve HTML in description
            if (is_array($courseData)) {
                // Sanitize top-level fields
                if (isset($courseData['title'])) {
                    $courseData['title'] = sanitize_text_field($courseData['title']);
                }
                
                // Preserve HTML content in description - use textarea field to preserve line breaks
                if (isset($courseData['description'])) {
                    $courseData['description'] = sanitize_textarea_field($courseData['description']);
                }
                
                // Sanitize sections array
                if (isset($courseData['sections']) && is_array($courseData['sections'])) {
                    foreach ($courseData['sections'] as $sectionIndex => $section) {
                        if (isset($section['title'])) {
                            $courseData['sections'][$sectionIndex]['title'] = sanitize_text_field($section['title']);
                        }
                        if (isset($section['description'])) {
                            $courseData['sections'][$sectionIndex]['description'] = sanitize_textarea_field($section['description']);
                        }
                        
                        // Sanitize lessons within sections
                        if (isset($section['lessons']) && is_array($section['lessons'])) {
                            foreach ($section['lessons'] as $lessonIndex => $lesson) {
                                if (isset($lesson['title'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['title'] = sanitize_text_field($lesson['title']);
                                }
                                if (isset($lesson['content'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['content'] = sanitize_textarea_field($lesson['content']);
                                }
                                if (isset($lesson['duration'])) {
                                    $courseData['sections'][$sectionIndex]['lessons'][$lessonIndex]['duration'] = sanitize_text_field($lesson['duration']);
                                }
                            }
                        }
                    }
                }
            }

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
            $duplicatedCourseData          = $courseData;
            $duplicatedCourseData['title'] = $duplicatedCourseData['title'] . ' (Draft Copy)';

            $newSessionData = [
                'user_id'      => get_current_user_id(),
                'context'      => 'course_creation',
                'state'        => 'initial',
                'initial_data' => [
                    'course_structure' => $duplicatedCourseData,
                ],
                'title'        => 'Course: ' . $duplicatedCourseData['title'],
            ];

            // Create new session
            $newSession   = $this->conversationManager->createSession($newSessionData);
            $newSessionId = $newSession->getSessionId();

            // Copy lesson drafts from the original session to the new session
            $this->lessonDraftService->copySessionDrafts($sessionId, $newSessionId);

            $this->logger->info('Course duplicated successfully', [
                'original_session_id' => $sessionId,
                'new_session_id'      => $newSessionId,
                'course_title'        => $duplicatedCourseData['title'],
                'user_id'             => get_current_user_id(),
            ]);

            wp_send_json_success([
                'new_session_id' => $newSessionId,
                'course_title'   => $duplicatedCourseData['title'],
            ]);
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Handle get session drafts
     *
     * Retrieves all lesson drafts for a specific session.
     * Expects POST parameters: nonce, session_id.
     *
     * @since  1.0.0
     * @return void Sends JSON response with array of drafts
     * @throws \Exception If draft retrieval fails
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
