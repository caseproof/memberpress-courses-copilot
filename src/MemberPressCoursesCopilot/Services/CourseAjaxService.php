<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Services\BaseService;
use MemberPressCoursesCopilot\Services\ConversationManager;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Services\LessonDraftService;
use MemberPressCoursesCopilot\Security\NonceConstants;
use MemberPressCoursesCopilot\Interfaces\IConversationManager;
use MemberPressCoursesCopilot\Interfaces\ILLMService;
use MemberPressCoursesCopilot\Interfaces\ICourseGenerator;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use WP_Error;

/**
 * Course AJAX Service
 *
 * Handles all AJAX endpoints for the AI Course Assistant functionality
 * Separated from CourseIntegrationService to follow Single Responsibility Principle
 *
 * @package MemberPressCoursesCopilot\Services
 * @since   1.0.0
 */
class CourseAjaxService extends BaseService
{
    /**
     * @var ILLMService|null LLM (Language Learning Model) service for AI content generation
     */
    private ?ILLMService $llmService = null;

    /**
     * @var IConversationManager|null Service for managing conversation sessions and state
     */
    private ?IConversationManager $conversationManager = null;

    /**
     * @var ICourseGenerator|null Service for generating course structure and content
     */
    private ?ICourseGenerator $courseGenerator = null;

    /**
     * @var LessonDraftService|null Service for managing lesson draft content during course creation
     */
    private ?LessonDraftService $draftService = null;

    /**
     * Constructor with dependency injection
     *
     * @param ILLMService|null          $llmService          The language model service for AI content generation.
     * @param IConversationManager|null $conversationManager The conversation manager for session persistence.
     * @param ICourseGenerator|null     $courseGenerator     The course generator service for creating courses.
     * @param LessonDraftService|null   $draftService        The lesson draft service for managing drafts.
     */
    public function __construct(
        ?ILLMService $llmService = null,
        ?IConversationManager $conversationManager = null,
        ?ICourseGenerator $courseGenerator = null,
        ?LessonDraftService $draftService = null
    ) {
        parent::__construct();
        $this->llmService          = $llmService;
        $this->conversationManager = $conversationManager;
        $this->courseGenerator     = $courseGenerator;
        $this->draftService        = $draftService;
    }

    /**
     * Get LLM Service (lazy loaded from container if not injected)
     *
     * @return ILLMService
     */
    private function getLLMService(): ILLMService
    {
        if (!$this->llmService) {
            $container        = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
            $this->llmService = $container->get(ILLMService::class);
        }
        return $this->llmService;
    }

    /**
     * Get Conversation Manager (lazy loaded from container if not injected)
     *
     * @return IConversationManager
     */
    private function getConversationManager(): IConversationManager
    {
        if (!$this->conversationManager) {
            $container                 = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
            $this->conversationManager = $container->get(IConversationManager::class);
        }
        return $this->conversationManager;
    }

    /**
     * Get Course Generator (lazy loaded from container if not injected)
     *
     * @return ICourseGenerator
     */
    private function getCourseGenerator(): ICourseGenerator
    {
        if (!$this->courseGenerator) {
            $container             = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
            $this->courseGenerator = $container->get(ICourseGenerator::class);
        }
        return $this->courseGenerator;
    }

    /**
     * Get Lesson Draft Service (lazy loaded from container if not injected)
     *
     * @return LessonDraftService
     */
    private function getLessonDraftService(): LessonDraftService
    {
        if (!$this->draftService) {
            $container          = \MemberPressCoursesCopilot\Plugin::instance()->getContainer();
            $this->draftService = $container->get(LessonDraftService::class);
        }
        return $this->draftService;
    }

    /**
     * Sanitize array data recursively
     *
     * @param  array  $data Data to sanitize
     * @param  string $type Sanitization type
     * @return array Sanitized array
     */
    protected function sanitizeArray(array $data, string $type = 'text'): array
    {
        return array_map(function ($item) use ($type) {
            if (is_array($item)) {
                return $this->sanitizeArray($item, $type);
            }
            return match ($type) {
                'textarea' => sanitize_textarea_field($item),
                'email' => sanitize_email($item),
                'url' => esc_url_raw($item),
                'int' => intval($item),
                'float' => floatval($item),
                'boolean' => filter_var($item, FILTER_VALIDATE_BOOLEAN),
                'html' => wp_kses_post($item),
                default => sanitize_text_field($item)
            };
        }, $data);
    }

    /**
     * Initialize the service
     *
     * @return void
     */
    public function init(): void
    {
        // Register all AJAX handlers.
        add_action('wp_ajax_mpcc_load_ai_interface', [$this, 'loadAIInterface']);
        add_action('wp_ajax_mpcc_create_course_with_ai', [$this, 'createCourseWithAI']);
        add_action('wp_ajax_mpcc_ai_chat', [$this, 'handleAIChat']);
        add_action('wp_ajax_mpcc_ping', [$this, 'handlePing']);

        // New simple AI chat handler.
        add_action('wp_ajax_mpcc_new_ai_chat', [$this, 'handleNewAIChat']);

        // Course content update handler.
        add_action('wp_ajax_mpcc_update_course_content', [$this, 'updateCourseContent']);

        // Conversation persistence endpoints.
        // Note: mpcc_save_conversation is handled by SimpleAjaxController with higher priority.
        add_action('wp_ajax_mpcc_load_conversation', [$this, 'loadConversation']);
        add_action('wp_ajax_mpcc_create_conversation', [$this, 'createConversation']);
        add_action('wp_ajax_mpcc_list_conversations', [$this, 'listConversations']);

        // Course preview editing endpoints.
        add_action('wp_ajax_mpcc_save_lesson_content', [$this, 'saveLessonContent']);
        add_action('wp_ajax_mpcc_load_lesson_content', [$this, 'loadLessonContent']);
        // Same handler, loads all drafts for session.
        add_action('wp_ajax_mpcc_load_all_drafts', [$this, 'loadLessonContent']);
        add_action('wp_ajax_mpcc_generate_lesson_content', [$this, 'generateLessonContent']);
        add_action('wp_ajax_mpcc_reorder_course_items', [$this, 'reorderCourseItems']);
        add_action('wp_ajax_mpcc_delete_course_item', [$this, 'deleteCourseItem']);

        // Course edit page AI chat.
        add_action('wp_ajax_mpcc_course_chat_message', [$this, 'handleCourseEditChat']);
    }

    /**
     * Handle AJAX request to load AI interface
     *
     * @return void
     */
    public function loadAIInterface(): void
    {
        // Verify nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!NonceConstants::verify($nonce, NonceConstants::AI_INTERFACE, false)) {
            $this->logger->warning('AI interface load failed: invalid nonce', [
                'user_id'    => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);
            ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('AI interface load failed: insufficient permissions', [
                'user_id'             => get_current_user_id(),
                'required_capability' => 'edit_posts',
            ]);
            ApiResponse::forbidden('Insufficient permissions to load AI interface');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

        try {
            $this->logger->info('Loading AI interface', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $postId,
            ]);

            // Generate the AI interface HTML.
            ob_start();
            $this->renderAIInterface($context, $postId);
            $html = ob_get_clean();

            $this->logger->debug('AI interface HTML generated successfully', [
                'user_id'     => get_current_user_id(),
                'context'     => $context,
                'post_id'     => $postId,
                'html_length' => strlen($html),
            ]);

            wp_send_json_success([
                'html'    => $html,
                'context' => $context,
                'post_id' => $postId,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load AI interface', [
                'user_id'       => get_current_user_id(),
                'context'       => $context,
                'post_id'       => $postId,
                'error_message' => $e->getMessage(),
                'error_file'    => $e->getFile(),
                'error_line'    => $e->getLine(),
            ]);
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_AI_SERVICE);
            ApiResponse::error($error);
        }
    }

    /**
     * Render AI interface HTML
     *
     * @param  string  $context Interface context (course_creation, course_editing)
     * @param  integer $postId  Post ID for editing context
     * @return void
     */
    private function renderAIInterface(string $context, int $postId = 0): void
    {
        // Load the AI chat interface template.
        $templatePath = MEMBERPRESS_COURSES_COPILOT_PLUGIN_DIR . 'templates/ai-chat-interface.php';

        if (file_exists($templatePath)) {
            // Prepare data for template.
            $data = [
                'context'   => $context,
                'course_id' => $postId,
                'messages'  => [], // Empty array for initial load.
            ];
            include $templatePath;
        } else {
            // Fallback basic interface.
            ?>
            <div id="mpcc-ai-chat-interface" class="mpcc-ai-interface"
                 data-context="<?php echo esc_attr($context); ?>"
                 data-post-id="<?php echo esc_attr($postId); ?>"
                 style="height: 100%; display: flex; flex-direction: column;">
                <div id="mpcc-course-chat-messages" class="mpcc-chat-messages"
                     style="flex: 1; min-height: 0; overflow-y: auto; border: none;
                            padding: 20px; background: #f9f9f9;">
                    <!-- Messages will be inserted here by JavaScript -->
                </div>
                
                <div id="mpcc-course-chat-input-area"
                     style="border-top: 1px solid #dcdcde; padding: 15px; background: #fff; display: flex; gap: 10px;">
                    <textarea id="mpcc-course-chat-input" 
                              placeholder="<?php
                                esc_attr_e('Ask me anything about your course...', 'memberpress-courses-copilot');
                                ?>"
                              style="flex: 1; padding: 8px 12px; border: 2px solid #dcdcde; border-radius: 6px;
                                     font-size: 14px; resize: none; min-height: 40px; max-height: 120px;"></textarea>
                    <button type="button" id="mpcc-course-send-message" class="button button-primary"
                            style="padding: 8px 16px; display: flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                            <?php esc_html_e('Send', 'memberpress-courses-copilot'); ?>
                    </button>
                </div>
            </div>
            
            <script type="text/javascript">
            // Initialize basic chat functionality.
            jQuery(document).ready(function($) {
                $('#mpcc-course-send-message').on('click', function() {
                    var message = $('#mpcc-course-chat-input').val().trim();
                    if (message) {
                        // Add user message to chat.
                        var userMessage = '<div style="margin-bottom: 15px; text-align: right;">' +
                            '<div style="display: inline-block; background: #0073aa; color: white; ' +
                            'padding: 10px 15px; border-radius: 18px; max-width: 70%;">' + message + '</div></div>';
                        $('#mpcc-chat-messages').append(userMessage);
                        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
                        $('#mpcc-course-chat-input').val('');
                        
                        // Show typing indicator.
                        var typingIndicator = '<div id="mpcc-typing" style="margin-bottom: 15px;">' +
                            '<div style="display: inline-block; background: #f0f0f0; padding: 10px 15px; ' +
                            'border-radius: 18px;">' +
                            '<span style="animation: pulse 1.5s infinite;">AI is typing...</span>' +
                            '</div></div>';
                        $('#mpcc-chat-messages').append(typingIndicator);
                        $('#mpcc-chat-messages').scrollTop($('#mpcc-chat-messages')[0].scrollHeight);
                        
                        // This is handled by the SimpleAjaxController via AJAX.
                        // The inline handler here is just for the UI, actual processing happens server-side.
                    }
                });
                
                $('#mpcc-course-chat-input').on('keypress', function(e) {
                    if (e.which === 13) {
                        $('#mpcc-course-send-message').click();
                    }
                });
            });
            </script>
                <?php
        }
    }

    /**
     * Handle AJAX request for AI chat
     *
     * @return void
     */
    public function handleAIChat(): void
    {
        // Verify nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('AI chat request failed: invalid nonce', [
                'user_id'    => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);
            ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('AI chat request failed: insufficient permissions', [
                'user_id'             => get_current_user_id(),
                'required_capability' => 'edit_posts',
            ]);
            ApiResponse::forbidden('Insufficient permissions to use AI chat');
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $context = isset($_POST['context']) ? sanitize_text_field(wp_unslash($_POST['context'])) : 'course_editing';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $conversationHistory = isset($_POST['conversation_history']) ? wp_unslash($_POST['conversation_history']) : [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $conversationState = isset($_POST['conversation_state']) ? wp_unslash($_POST['conversation_state']) : [];
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Already verified above.
        $sessionId = isset($_POST['session_id']) ? sanitize_text_field(wp_unslash($_POST['session_id'])) : '';

        // Sanitize arrays.
        $conversationHistory = $this->sanitizeArray($conversationHistory, 'textarea');
        $conversationState   = $this->sanitizeArray($conversationState);

        // Load session if provided.
        $session             = null;
        $conversationManager = null;
        if (!empty($sessionId)) {
            try {
                $conversationManager = $this->getConversationManager();
                $session             = $conversationManager->loadSession($sessionId);
                if ($session && $session->getUserId() === get_current_user_id()) {
                    // Update session state from conversation state.
                    $currentStep = $conversationState['current_step'] ?? 'initial';
                    $session->setCurrentState($currentStep);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to load session in AI chat', [
                    'session_id' => $sessionId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if (empty($message)) {
            $this->logger->warning('AI chat request failed: empty message', [
                'user_id' => get_current_user_id(),
                'context' => $context,
                'post_id' => $postId,
            ]);
            ApiResponse::errorMessage('Message is required', ApiResponse::ERROR_MISSING_PARAMETER);
            return;
        }

        try {
            $this->logger->info('AI chat request initiated', [
                'user_id'                    => get_current_user_id(),
                'message_length'             => strlen($message),
                'context'                    => $context,
                'post_id'                    => $postId,
                'conversation_history_count' => count($conversationHistory),
                'conversation_state'         => $conversationState,
                'request_ip'                 => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);

            // Use LLMService for AI requests.
            $llmService = $this->getLLMService();

            // Build conversation context.
            $conversationContext = '';
            if (!empty($conversationHistory)) {
                foreach ($conversationHistory as $entry) {
                    $role                 = $entry['role'] ?? 'user';
                    $content              = $entry['content'] ?? '';
                    $conversationContext .= "\n{$role}: {$content}";
                }
            }

            // Get current conversation state for course creation.
            $currentStep   = $conversationState['current_step'] ?? 'initial';
            $collectedData = $conversationState['collected_data'] ?? [];

            // Prepare the full prompt.
            $systemPrompt = $this->getSystemPrompt($context);
            $fullPrompt   = $systemPrompt . "\n\nConversation history:" . $conversationContext
                     . "\n\nUser: " . $message . "\n\nAssistant:";

            // Add current state context for course creation.
            if ($context === 'course_creation' && !empty($collectedData)) {
                $fullPrompt .= "\n\nCurrent collected course data: " . wp_json_encode($collectedData);
            }

            $this->logger->debug('Preparing LLM service call', [
                'user_id'              => get_current_user_id(),
                'context'              => $context,
                'current_step'         => $currentStep,
                'collected_data_keys'  => array_keys($collectedData),
                'has_course_structure' => isset($collectedData['course_structure']),
                'prompt_length'        => strlen($fullPrompt),
            ]);

            // Make request to AI service.
            $response = $llmService->generateContent($fullPrompt, 'course_assistance', [
                'temperature' => 0.7,
                'max_tokens'  => 2000,
            ]);

            $this->logger->debug('LLM service response received', [
                'user_id'                 => get_current_user_id(),
                'context'                 => $context,
                'has_error'               => $response['error'] ?? false,
                'response_content_length' => isset($response['content']) ? strlen($response['content']) : 0,
                'response_keys'           => array_keys($response),
            ]);

            if ($response['error']) {
                $this->logger->error('LLM service returned error', [
                    'user_id'       => get_current_user_id(),
                    'context'       => $context,
                    'error_message' => $response['message'] ?? 'Unknown error',
                    'full_response' => $response,
                ]);
                $error = new WP_Error(
                    ApiResponse::ERROR_AI_SERVICE,
                    'AI service error: ' . ($response['message'] ?? 'Unknown error')
                );
                ApiResponse::error($error);
                return;
            }

            $aiMessage = $response['content'];

            // Extract any course data from the response if it contains structured data.
            $courseData    = null;
            $readyToCreate = false;

            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $aiMessage, $matches)) {
                $jsonData = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $courseData = $jsonData;
                    // Remove JSON from message.
                    $aiMessage = trim(str_replace($matches[0], '', $aiMessage));

                    // Check if we have enough data to create a course.
                    if (
                        isset($courseData['title']) && isset($courseData['sections'])
                        && count($courseData['sections']) > 0
                    ) {
                        $readyToCreate = true;
                    }
                }
            }

            // Update conversation state.
            if ($courseData) {
                // Store course data under 'course_structure' key to match JavaScript expectations.
                $collectedData['course_structure'] = $courseData;
            }

            // Determine next step.
            $nextStep = $currentStep;
            $actions  = [];

            if ($readyToCreate) {
                $nextStep = 'ready_to_create';
                $actions  = [
                    [
                        'action' => 'create_course',
                        'label'  => 'Create Course',
                        'type'   => 'primary',
                    ],
                    [
                        'action' => 'modify',
                        'label'  => 'Modify Details',
                        'type'   => 'secondary',
                    ],
                ];
            }

            // Update session state and progress if we have a session.
            if ($session && $conversationManager) {
                $session->setCurrentState($nextStep);
                $session->setContext($collectedData, null);

                // Save the session to persist progress.
                try {
                    $conversationManager->saveSession($session);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to save session after AI chat', [
                        'session_id' => $sessionId,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            $this->logger->info('AI chat request completed successfully', [
                'user_id'                 => get_current_user_id(),
                'context'                 => $context,
                'next_step'               => $nextStep,
                'ready_to_create'         => $readyToCreate,
                'has_course_data'         => !empty($courseData),
                'response_message_length' => strlen($aiMessage),
                'actions_count'           => count($actions),
            ]);

            wp_send_json_success([
                'message'            => $aiMessage,
                'course_data'        => $courseData,
                'context'            => $context,
                'timestamp'          => current_time('timestamp'),
                'conversation_state' => [
                    'current_step'   => $nextStep,
                    'collected_data' => $collectedData,
                ],
                'actions'            => $actions,
                'ready_to_create'    => $readyToCreate,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('AI chat request failed with exception', [
                'user_id'       => get_current_user_id(),
                'context'       => $context,
                'error_message' => $e->getMessage(),
                'error_file'    => $e->getFile(),
                'error_line'    => $e->getLine(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_AI_SERVICE);
            ApiResponse::error($error);
        }
    }

    /**
     * Handle AJAX request to create course with AI
     *
     * @return void
     */
    public function createCourseWithAI(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Course creation failed: invalid nonce', [
                'user_id'    => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);
            ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
            return;
        }

        // Check user capabilities.
        if (!current_user_can('publish_posts')) {
            $this->logger->warning('Course creation failed: insufficient permissions', [
                'user_id'             => get_current_user_id(),
                'required_capability' => 'publish_posts',
            ]);
            ApiResponse::forbidden('Insufficient permissions to create courses');
            return;
        }

        $courseData = $_POST['course_data'] ?? [];

        // Sanitize course data before processing.
        if (is_array($courseData)) {
            $courseData = $this->sanitizeArray($courseData);
        }

        // If course_data is a JSON string, decode it.
        if (is_string($courseData)) {
            $decoded = json_decode($courseData, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $courseData = $decoded;
                $this->logger->info('Decoded JSON course_data', [
                    'decoded_keys' => array_keys($courseData),
                    'has_title'    => isset($courseData['title']),
                    'title_value'  => $courseData['title'] ?? 'not set',
                ]);
            }
        }

        // Log the raw POST data to debug.
        $this->logger->info('Raw course_data received', [
            'raw_data'          => wp_json_encode($_POST['course_data'] ?? 'empty'),
            'is_array'          => is_array($courseData),
            'is_empty'          => empty($courseData),
            'course_data_type'  => gettype($courseData),
            'course_data_keys'  => is_array($courseData) ? array_keys($courseData) : 'not an array',
            'has_title_at_root' => isset($courseData['title']),
            'title_at_root'     => $courseData['title'] ?? 'no title at root',
            'full_structure'    => wp_json_encode($courseData),
        ]);

        // Check if course data is nested under 'course_structure' key.
        if (isset($courseData['course_structure']) && is_array($courseData['course_structure'])) {
            $this->logger->info('Found nested course_structure, extracting it', [
                'nested_structure_keys' => array_keys($courseData['course_structure']),
                'nested_has_title'      => isset($courseData['course_structure']['title']),
                'nested_title'          => $courseData['course_structure']['title'] ?? 'no title in nested',
            ]);
            $courseData = $courseData['course_structure'];
        }

        // Log final course data structure after any extraction.
        $this->logger->info('Final course_data structure', [
            'has_title'      => isset($courseData['title']),
            'title'          => $courseData['title'] ?? 'NO TITLE FOUND',
            'keys'           => is_array($courseData) ? array_keys($courseData) : 'not an array',
            'sections_count' => isset($courseData['sections']) ? count($courseData['sections']) : 0,
        ]);

        if (empty($courseData)) {
            $this->logger->warning('Course creation failed: no course data provided', [
                'user_id'   => get_current_user_id(),
                'post_keys' => array_keys($_POST),
            ]);
            wp_send_json_error('No course data provided');
            return;
        }

        try {
            $this->logger->info('Course creation initiated', [
                'user_id'          => get_current_user_id(),
                'course_title'     => $courseData['title'] ?? 'Unknown',
                'sections_count'   => count($courseData['sections'] ?? []),
                'course_data_keys' => array_keys($courseData),
                'first_section'    => isset($courseData['sections'][0])
                    ? wp_json_encode($courseData['sections'][0])
                    : 'no sections',
            ]);

            // Get the Course Generator Service from container.
            $generator = $this->getCourseGenerator();

            // Validate course data.
            $validation = $generator->validateCourseData($courseData);
            if (!$validation['valid']) {
                $this->logger->error('Course creation failed: validation errors', [
                    'user_id'           => get_current_user_id(),
                    'course_title'      => $courseData['title'] ?? 'Unknown',
                    'validation_errors' => $validation['errors'],
                ]);
                wp_send_json_error([
                    'message' => 'Course data validation failed',
                    'errors'  => $validation['errors'],
                ]);
                return;
            }

            // Apply saved draft content if we have a session ID.
            if (isset($_POST['session_id']) && !empty($_POST['session_id'])) {
                $sessionId = sanitize_text_field($_POST['session_id']);
                $this->logger->info('Applying lesson drafts to course structure', [
                    'session_id'   => $sessionId,
                    'course_title' => $courseData['title'] ?? 'Unknown',
                ]);

                // Get lesson draft service and map drafts to course structure.
                $draftService = $this->getLessonDraftService();
                $courseData   = $draftService->mapDraftsToStructure($sessionId, $courseData);

                $this->logger->info('Drafts mapped to course structure', [
                    'session_id'            => $sessionId,
                    'sections_with_content' => array_map(function ($section) {
                        return [
                            'title'                => $section['title'] ?? 'Untitled',
                            'lessons_with_content' => array_map(function ($lesson) {
                                return [
                                    'title'          => $lesson['title'] ?? 'Untitled',
                                    'has_content'    => !empty($lesson['content']),
                                    'content_length' => isset($lesson['content']) ? strlen($lesson['content']) : 0,
                                ];
                            }, $section['lessons'] ?? []),
                        ];
                    }, $courseData['sections'] ?? []),
                ]);
            }

            // Generate the course.
            $result = $generator->generateCourse($courseData);

            if ($result['success']) {
                $this->logger->info('Course created successfully', [
                    'user_id'        => get_current_user_id(),
                    'course_id'      => $result['course_id'],
                    'course_title'   => $courseData['title'] ?? 'Unknown',
                    'sections_count' => count($courseData['sections'] ?? []),
                ]);

                // Update session title if we have a session ID.
                if (isset($_POST['session_id']) && !empty($_POST['session_id'])) {
                    try {
                        $sessionId           = sanitize_text_field($_POST['session_id']);
                        $conversationManager = $this->getConversationManager();
                        $session             = $conversationManager->loadSession($sessionId);

                        if ($session && $session->getUserId() === get_current_user_id()) {
                            $courseTitle = $courseData['title'] ?? 'Unknown Course';
                            $this->logger->info('Attempting to update session title', [
                                'session_id'            => $sessionId,
                                'course_title'          => $courseTitle,
                                'course_data_has_title' => isset($courseData['title']),
                                'course_data_keys'      => array_keys($courseData),
                            ]);

                            $session->setTitle('Course: ' . $courseTitle);
                            $conversationManager->saveSession($session);

                            $this->logger->info('Updated session title after course creation', [
                                'session_id'   => $sessionId,
                                'course_title' => $courseTitle,
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Log but don't fail the course creation.
                        $this->logger->warning('Failed to update session title after course creation', [
                            'error' => $e->getMessage(),
                        ]);
                    }

                    // Clean up lesson drafts after successful course creation.
                    try {
                        $draftService = $this->getLessonDraftService();
                        $deletedCount = $draftService->deleteSessionDrafts($sessionId);
                        $this->logger->info('Cleaned up lesson drafts after course creation', [
                            'session_id'     => $sessionId,
                            'drafts_deleted' => $deletedCount,
                        ]);
                    } catch (\Exception $e) {
                        // Log but don't fail the course creation.
                        $this->logger->warning('Failed to clean up lesson drafts after course creation', [
                            'session_id' => $sessionId,
                            'error'      => $e->getMessage(),
                        ]);
                    }
                }

                wp_send_json_success([
                    'message'     => 'Course created successfully!',
                    'course_id'   => $result['course_id'],
                    'edit_url'    => $result['edit_url'],
                    'preview_url' => $result['preview_url'],
                ]);
            } else {
                $this->logger->error('Course creation failed', [
                    'user_id'      => get_current_user_id(),
                    'course_title' => $courseData['title'] ?? 'Unknown',
                    'error'        => $result['error'],
                ]);
                wp_send_json_error([
                    'message' => 'Failed to create course',
                    'error'   => $result['error'],
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Course creation failed with exception', [
                'user_id'       => get_current_user_id(),
                'course_title'  => $courseData['title'] ?? 'Unknown',
                'error_message' => $e->getMessage(),
                'error_file'    => $e->getFile(),
                'error_line'    => $e->getLine(),
                'stack_trace'   => $e->getTraceAsString(),
            ]);
            wp_send_json_error('Failed to create course: ' . $e->getMessage());
        }
    }

    /**
     * Get system prompt for AI based on context
     *
     * @param  string $context The context (course_creation, course_editing)
     * @return string
     */
    private function getSystemPrompt(string $context): string
    {
        $basePrompt = 'You are an AI assistant specialized in helping create and improve online courses '
                     . 'for MemberPress Courses. You have expertise in curriculum design, learning objectives, '
                     . 'content structuring, and educational best practices.';

        switch ($context) {
            case 'course_creation':
                return $basePrompt . ' You are helping a user create a new course from scratch. '
                    . 'Focus on understanding their topic, target audience, and learning goals. '
                    . 'Help them structure a comprehensive curriculum with sections and lessons. '
                    . "\n\nIMPORTANT: When the user has provided:\n"
                    . "1. The subject/topic of the course\n"
                    . "2. Target audience\n"
                    . "3. Main objectives or what students will build/learn\n"
                    . "4. Approximate duration\n"
                    . "5. Whether it includes hands-on exercises\n\n"
                    . 'You MUST generate a complete course structure immediately. '
                    . 'Do not ask for more clarification unless absolutely necessary.\n\n'
                    . 'Your conversation should be natural and helpful. If you need clarification, '
                    . 'ask only 1-2 specific questions. Once you have the basic information above, '
                    . 'generate the course structure in the following JSON format wrapped in ```json``` code blocks:'
                    . "\n\n```json\n"
                    . '{'
                    . "\n"
                    . '  "title": "Course Title",'
                    . "\n"
                    . '  "description": "Course description",'
                    . "\n"
                    . '  "sections": ['
                    . "\n"
                    . '    {'
                    . "\n"
                    . '      "title": "Section 1 Title",'
                    . "\n"
                    . '      "description": "Section description",'
                    . "\n"
                    . '      "lessons": ['
                    . "\n"
                    . '        {'
                    . "\n"
                    . '          "title": "Lesson Title",'
                    . "\n"
                    . '          "content": "Lesson content (can be HTML)",'
                    . "\n"
                    . '          "type": "text",'
                    . "\n"
                    . '          "duration": "15"'
                    . "\n"
                    . '        }'
                    . "\n"
                    . '      ]'
                    . "\n"
                    . '    }'
                    . "\n"
                    . '  ],'
                    . "\n"
                    . '  "settings": {'
                    . "\n"
                    . '    "course_progress": "enabled",'
                    . "\n"
                    . '    "auto_advance": "enabled"'
                    . "\n"
                    . '  },'
                    . "\n"
                    . '  "categories": ["Category 1"],'
                    . "\n"
                    . '  "tags": ["tag1", "tag2"]'
                    . "\n"
                    . '}'
                    . "\n```\n\n"
                    . 'Be conversational and guide the user through the process naturally . '
                    . 'When you have the 5 key pieces of information listed above, immediately generate '
                    . 'the complete course structure . do not continue asking questions . \n\n'
                    . 'Example: if a user says they want to create a PHP course for people with HTML / CSS '
                    . 'knowledge to build a todo app in 4 hours with OOP, PDO, and MVC - you have ALL '
                    . 'the information needed . Generate the course immediately . ';

            case 'course_editing':
                return $basePrompt . ' You are helping a user improve an existing course . '
                    . 'Focus on enhancing content, improving structure, adding engaging elements, '
                    . ' and optimizing the learning experience . Be specific about improvements '
                    . ' and provide concrete suggestions . When suggesting course modifications, '
                    . 'include structured data in JSON format wrapped in ```json``` code blocks . ';

            default:
                return $basePrompt . ' Provide helpful, specific guidance for course creation '
                    . ' and improvement . When providing course data, use JSON format '
                    . 'wrapped in ```json``` code blocks . ';
        }
    }

    /**
     * Handle AJAX ping request for connection testing
     *
     * @return void
     */
    public function handlePing(): void
    {
        // Verify nonce.
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in the next line.
        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Ping request failed: invalid nonce', [
                'user_id'    => get_current_user_id(),
                'request_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        $this->logger->debug('Ping request received', [
            'user_id'   => get_current_user_id(),
            'timestamp' => current_time('timestamp'),
        ]);

        // Simple ping response.
        wp_send_json_success([
            'pong'      => true,
            'timestamp' => current_time('timestamp'),
        ]);
    }

    /**
     * Create new conversation session
     *
     * @return void
     */
    public function createConversation(): void
    {
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Create conversation failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        try {
            $conversationManager = $this->getConversationManager();

            $session = $conversationManager->createSession([
                'user_id'      => get_current_user_id(),
                'context'      => sanitize_text_field($_POST['context'] ?? 'course_creation'),
                'title'        => sanitize_text_field($_POST['title'] ?? 'new Course(Draft)'),
                'state'        => 'initial',
                'initial_data' => [],
            ]);

            $this->logger->info('Created new conversation session()', [
                'session_id' => $session->getSessionId(),
                'user_id'    => get_current_user_id(),
            ]);

            wp_send_json_success([
                'session_id' => $session->getSessionId(),
                'created_at' => $session->getCreatedAt(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create conversation', [
                'error'   => $e->getMessage(),
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Failed to create conversation');
        }
    }

    /**
     * Save conversation state
     *
     * @return void
     */
    public function saveConversation(): void
    {
        // Verify nonce. - check multiple possible nonce names.
        $nonce = $_POST['nonce'] ?? '';
        if (
            !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
            !NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false)
        ) {
            $this->logger->warning('Save conversation failed: invalid nonce', [
                'user_id'     => get_current_user_id(),
                'nonce_value' => $nonce,
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            wp_send_json_error('Session ID required');
            return;
        }

        try {
            $conversationManager = $this->getConversationManager();
            $session             = $conversationManager->loadSession($sessionId);

            if (!$session || $session->getUserId() !== get_current_user_id()) {
                $this->logger->warning('Save conversation failed: access denied', [
                    'session_id' => $sessionId,
                    'user_id'    => get_current_user_id(),
                ]);
                wp_send_json_error('Session not found or access denied');
                return;
            }

            // Update session with new data.
            $conversationHistory = $_POST['conversation_history'] ?? [];
            $conversationState   = $_POST['conversation_state'] ?? [];

            // Sanitize arrays.
            $conversationHistory = $this->sanitizeArray($conversationHistory, 'textarea');
            $conversationState   = $this->sanitizeArray($conversationState);

            $this->logger->info('Saving conversation - received data', [
                'message_count' => count($conversationHistory),
                'first_message' => count($conversationHistory) > 0 ? $conversationHistory[0] : null,
                'message_keys'  => count($conversationHistory) > 0 ? array_keys($conversationHistory[0]) : [],
            ]);

            // Clear existing messages and add new ones.
            $session->clearMessages();
            foreach ($conversationHistory as $message) {
                // The frontend sends 'role' but ConversationSession expects 'type' for the first parameter.
                // Map 'role' to the message type expected by addMessage.
                $messageType = $message['role'] === 'user' ? 'user' :
                              ($message['role'] === 'assistant' ? 'assistant' : 'system');
                $session->addMessage(
                    $messageType,
                    $message['content'],
                    ['timestamp' => $message['timestamp'] ?? time()]
                );
            }

            // Update state.
            $session->setCurrentState($conversationState['current_step'] ?? 'initial');
            $session->setContext($conversationState['collected_data'] ?? [], null);

            // Update session title if course data is available.
            $collectedData = $conversationState['collected_data'] ?? [];
            if (isset($collectedData['course_structure']['title'])) {
                $courseTitle = $collectedData['course_structure']['title'];
                $session->setTitle('Course: ' . $courseTitle);
                $this->logger->info('Updated session title with course name', [
                    'session_id'   => $sessionId,
                    'course_title' => $courseTitle,
                ]);
            } elseif (isset($collectedData['title']) && isset($collectedData['sections'])) {
                // Fallback for old format.
                $courseTitle = $collectedData['title'];
                $session->setTitle('Course: ' . $courseTitle);
                $this->logger->info('Updated session title with course name(old format)', [
                    'session_id'   => $sessionId,
                    'course_title' => $courseTitle,
                ]);
            }

            // Save to database.
            $saved = $conversationManager->saveSession($session);

            // Verify what was actually saved.
            $savedMessages = $session->getMessages();
            $this->logger->info('Saved conversation', [
                'session_id'           => $sessionId,
                'user_id'              => get_current_user_id(),
                'messages_count'       => count($conversationHistory),
                'saved_messages_count' => count($savedMessages),
                'first_saved_message'  => count($savedMessages) > 0 ? $savedMessages[0] : null,
            ]);

            wp_send_json_success([
                'saved'      => $saved,
                'last_saved' => time(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save conversation', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to save conversation');
        }
    }

    /**
     * Load conversation session
     *
     * @return void
     */
    public function loadConversation(): void
    {
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Load conversation failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        if (empty($sessionId)) {
            wp_send_json_error('Session ID required');
            return;
        }

        try {
            $conversationManager = $this->getConversationManager();
            $session             = $conversationManager->loadSession($sessionId);

            if (!$session || $session->getUserId() !== get_current_user_id()) {
                $this->logger->warning('Load conversation failed: access denied', [
                    'session_id' => $sessionId,
                    'user_id'    => get_current_user_id(),
                ]);
                wp_send_json_error('Session not found or access denied');
                return;
            }

            // Format messages for frontend.
            $messages    = [];
            $allMessages = $session->getMessages();

            $this->logger->info('Processing messages from session', [
                'total_messages'          => count($allMessages),
                'first_message_structure' => count($allMessages) > 0 ? array_keys($allMessages[0]) : 'no messages',
                'sample_message'          => count($allMessages) > 0 ? $allMessages[0] : null,
            ]);

            foreach ($allMessages as $message) {
                if ($message['type'] !== 'system') {
                    // Map backend 'type' to frontend 'role' field.
                    $role       = $message['type'] === 'user' ? 'user' :
                           ($message['type'] === 'assistant' ? 'assistant' : $message['type']);
                    $messages[] = [
                        'role'      => $role,
                        'content'   => $message['content'],
                        'timestamp' => $message['timestamp'],
                    ];
                }
            }

            $this->logger->info('Loaded conversation', [
                'session_id'           => $sessionId,
                'user_id'              => get_current_user_id(),
                'messages_count'       => count($messages),
                'has_course_structure' => isset($session->getContext()['course_structure']),
                'context_keys'         => array_keys($session->getContext()),
            ]);

            wp_send_json_success([
                'session_id'           => $session->getSessionId(),
                'conversation_history' => $messages,
                'conversation_state'   => [
                    'current_step'   => $session->getCurrentState(),
                    'collected_data' => $session->getContext(),
                ],
                'created_at'           => $session->getCreatedAt(),
                'last_updated'         => $session->getLastUpdated(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to load conversation', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to load conversation');
        }
    }

    /**
     * List user conversations
     *
     * @return void
     */
    public function listConversations(): void
    {
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('list conversations failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        try {
            $conversationManager = $this->getConversationManager();
            $sessions            = $conversationManager->getUserSessions(
                get_current_user_id(),
                10, // limit.
                0   // offset.
            );

            $sessionList = [];
            foreach ($sessions as $session) {
                $sessionList[] = [
                    'session_id'   => $session->getSessionId(),
                    'title'        => $session->getTitle(),
                    'created_at'   => $session->getCreatedAt(),
                    'last_updated' => $session->getLastUpdated(),
                    'is_active'    => $session->isActive(),
                    'progress'     => $session->getProgress(),
                ];
            }

            $this->logger->info('Listed conversations', [
                'user_id' => get_current_user_id(),
                'count'   => count($sessionList),
            ]);

            wp_send_json_success([
                'sessions' => $sessionList,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to list conversations', [
                'error' => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to list conversations');
        }
    }

    /**
     * Save lesson content to draft
     *
     * @return void
     */
    public function saveLessonContent(): void
    {
        // Verify nonce. - check multiple possible nonce names.
        $nonce = $_POST['nonce'] ?? '';
        if (
            !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
            !NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false)
        ) {
            $this->logger->warning('Save lesson content failed: invalid nonce', [
                'user_id'     => get_current_user_id(),
                'nonce_value' => $nonce,
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('Save lesson content failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $sessionId  = sanitize_text_field($_POST['session_id'] ?? '');
        $sectionId  = sanitize_text_field($_POST['section_id'] ?? '');
        $lessonId   = sanitize_text_field($_POST['lesson_id'] ?? '');
        $content    = sanitize_textarea_field($_POST['content'] ?? '');
        $orderIndex = isset($_POST['order_index']) ? (int) $_POST['order_index'] : 0;

        $this->logger->info('Save lesson content request', [
            'session_id'     => $sessionId,
            'section_id'     => $sectionId,
            'lesson_id'      => $lessonId,
            'content_length' => strlen($content),
            'has_session'    => !empty($sessionId),
            'has_section'    => !empty($sectionId),
            'has_lesson'     => !empty($lessonId),
            'post_keys'      => array_keys($_POST),
        ]);

        if (empty($sessionId) || $sectionId === '' || $lessonId === '') {
            wp_send_json_error('Missing required parameters');
            return;
        }

        try {
            // Get lesson draft service from container.
            $draftService = $this->getLessonDraftService();

            // Save draft.
            $result = $draftService->saveDraft($sessionId, $sectionId, $lessonId, $content, $orderIndex);

            if ($result !== false) {
                $this->logger->info('Lesson content saved', [
                    'session_id' => $sessionId,
                    'section_id' => $sectionId,
                    'lesson_id'  => $lessonId,
                ]);

                wp_send_json_success([
                    'saved'    => true,
                    'saved_at' => current_time('c'),
                    'message'  => 'Lesson content saved successfully',
                ]);
            } else {
                throw new \Exception('Failed to save lesson content');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to save lesson content', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to save lesson content: ' . $e->getMessage());
        }
    }

    /**
     * Load lesson content from draft
     *
     * @return void
     */
    public function loadLessonContent(): void
    {
        // Verify nonce. - check multiple possible nonce names.
        $nonce = $_POST['nonce'] ?? '';
        if (
            !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
            !NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false)
        ) {
            $this->logger->warning('Load lesson content failed: invalid nonce', [
                'user_id'     => get_current_user_id(),
                'nonce_value' => $nonce,
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('Load lesson content failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        $sectionId = sanitize_text_field($_POST['section_id'] ?? '');
        $lessonId  = sanitize_text_field($_POST['lesson_id'] ?? '');

        if (empty($sessionId)) {
            wp_send_json_error('Session ID is required');
            return;
        }

        try {
            // Get lesson draft service from container.
            $draftService = $this->getLessonDraftService();

            // Load specific lesson or all session drafts.
            if ($sectionId !== '' && $lessonId !== '') {
                // Load specific lesson.
                $draft = $draftService->getDraft($sessionId, $sectionId, $lessonId);

                if ($draft) {
                    wp_send_json_success([
                        'draft' => [
                            'content'     => $draft->content,
                            'order_index' => $draft->order_index,
                            'updated_at'  => $draft->updated_at,
                        ],
                    ]);
                } else {
                    wp_send_json_success([
                        'draft'   => null,
                        'message' => 'No draft found',
                    ]);
                }
            } else {
                // Load all drafts for session.
                $drafts = $draftService->getSessionDrafts($sessionId);

                // Group drafts by section.
                $groupedDrafts = [];
                foreach ($drafts as $draft) {
                    if (!isset($groupedDrafts[$draft->section_id])) {
                        $groupedDrafts[$draft->section_id] = [];
                    }
                    $groupedDrafts[$draft->section_id][$draft->lesson_id] = [
                        'content'     => $draft->content,
                        'order_index' => $draft->order_index,
                        'updated_at'  => $draft->updated_at,
                    ];
                }

                $this->logger->info('Loaded session drafts', [
                    'session_id'   => $sessionId,
                    'drafts_count' => count($drafts),
                ]);

                wp_send_json_success([
                    'drafts' => $groupedDrafts,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to load lesson content', [
                'session_id' => $sessionId,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to load lesson content: ' . $e->getMessage());
        }
    }

    /**
     * Generate lesson content using AI
     *
     * @return void
     */
    public function generateLessonContent(): void
    {
        // Debug logging.
        $this->logger->info('CourseAjaxService::generateLessonContent called', [
            'nonce'   => $_POST['nonce'] ?? 'not set',
            'action'  => $_POST['action'] ?? 'not set',
            'user_id' => get_current_user_id(),
        ]);

        // Verify nonce. - check multiple possible nonce names.
        $nonce = $_POST['nonce'] ?? '';
        if (
            !NonceConstants::verify($nonce, NonceConstants::COURSES_INTEGRATION, false) &&
            !NonceConstants::verify($nonce, NonceConstants::EDITOR_NONCE, false)
        ) {
            $this->logger->warning('Generate lesson content failed: invalid nonce', [
                'user_id'     => get_current_user_id(),
                'nonce_value' => $nonce,
                'expected'    => 'mpcc_courses_integration or mpcc_editor_nonce',
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('Generate lesson content failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $sessionId    = sanitize_text_field($_POST['session_id'] ?? '');
        $sectionTitle = sanitize_text_field($_POST['section_title'] ?? '');
        $lessonTitle  = sanitize_text_field($_POST['lesson_title'] ?? '');
        $courseTitle  = sanitize_text_field($_POST['course_title'] ?? '');
        $context      = $_POST['context'] ?? [];

        // Sanitize context array.
        $context = $this->sanitizeArray($context);

        if (empty($lessonTitle)) {
            wp_send_json_error('Lesson title is required');
            return;
        }

        try {
            // Use LLMService for content generation.
            $llmService = $this->getLLMService();

            // Build prompt for lesson content generation.
            $prompt = $this->buildLessonContentPrompt($courseTitle, $sectionTitle, $lessonTitle, $context);

            $this->logger->debug('Generating lesson content', [
                'session_id'    => $sessionId,
                'lesson_title'  => $lessonTitle,
                'prompt_length' => strlen($prompt),
            ]);

            // Generate content.
            $response = $llmService->generateContent($prompt, 'lesson_content', [
                'temperature' => 0.7,
                'max_tokens'  => 3000,
            ]);

            if ($response['error']) {
                throw new \Exception($response['message'] ?? 'Unknown error');
            }

            $generatedContent = $response['content'];

            // Save generated content as draft if session ID provided.
            if (!empty($sessionId) && !empty($_POST['section_id']) && !empty($_POST['lesson_id'])) {
                $draftService = $this->getLessonDraftService();

                $sectionId  = sanitize_text_field($_POST['section_id']);
                $lessonId   = sanitize_text_field($_POST['lesson_id']);
                $orderIndex = isset($_POST['order_index']) ? (int) $_POST['order_index'] : 0;

                $draftId = $draftService->saveDraft($sessionId, $sectionId, $lessonId, $generatedContent, $orderIndex);
            }

            $this->logger->info('Lesson content generated', [
                'session_id'     => $sessionId,
                'lesson_title'   => $lessonTitle,
                'content_length' => strlen($generatedContent),
            ]);

            wp_send_json_success([
                'content'      => $generatedContent,
                'generated_at' => current_time('c'),
                'message'      => 'Lesson content generated successfully',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate lesson content', [
                'lesson_title' => $lessonTitle,
                'error'        => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to generate lesson content: ' . $e->getMessage());
        }
    }

    /**
     * Reorder course items (sections or lessons)
     *
     * @return void
     */
    public function reorderCourseItems(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Reorder course items failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('Reorder course items failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $sessionId   = sanitize_text_field($_POST['session_id'] ?? '');
        $itemType    = sanitize_text_field($_POST['item_type'] ?? ''); // 'section' or 'lesson'.
        $reorderData = $_POST['reorder_data'] ?? [];

        // Sanitize reorder data array.
        if (is_array($reorderData)) {
            $reorderData = $this->sanitizeArray($reorderData);
        }

        if (empty($sessionId) || empty($itemType) || empty($reorderData)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        try {
            if ($itemType === 'lesson') {
                // Reorder lessons within a section.
                $sectionId = sanitize_text_field($_POST['section_id'] ?? '');
                if (empty($sectionId)) {
                    wp_send_json_error('Section ID is required for lesson reordering');
                    return;
                }

                $draftService = $this->getLessonDraftService();

                // Validate lesson order array.
                $lessonOrder = array_map('sanitize_text_field', $reorderData);

                $success = $draftService->updateLessonOrder($sessionId, $sectionId, $lessonOrder);

                if ($success) {
                    $this->logger->info('Lessons reordered', [
                        'session_id'   => $sessionId,
                        'section_id'   => $sectionId,
                        'lesson_count' => count($lessonOrder),
                    ]);

                    wp_send_json_success([
                        'message'    => 'Lessons reordered successfully',
                        'updated_at' => current_time('c'),
                    ]);
                } else {
                    throw new \Exception('Failed to update lesson order');
                }
            } elseif ($itemType === 'section') {
                // Handle section reordering.
                // This would update the course structure in the conversation state.
                $conversationManager = $this->getConversationManager();
                $session             = $conversationManager->loadSession($sessionId);

                if (!$session || $session->getUserId() !== get_current_user_id()) {
                    wp_send_json_error('Session not found or access denied');
                    return;
                }

                // Get current context.
                $context = $session->getContext();

                // Update section order in course structure.
                if (isset($context['course_structure']) && isset($context['course_structure']['sections'])) {
                    $sections    = $context['course_structure']['sections'];
                    $newSections = [];

                    // Reorder sections based on provided order.
                    foreach ($reorderData as $sectionIndex) {
                        $index = (int) $sectionIndex;
                        if (isset($sections[$index])) {
                            $newSections[] = $sections[$index];
                        }
                    }

                    $context['course_structure']['sections'] = $newSections;
                    $session->setContext($context, null);

                    $conversationManager->saveSession($session);

                    $this->logger->info('Sections reordered', [
                        'session_id'    => $sessionId,
                        'section_count' => count($newSections),
                    ]);

                    wp_send_json_success([
                        'message'    => 'Sections reordered successfully',
                        'updated_at' => current_time('c'),
                    ]);
                } else {
                    throw new \Exception('No course structure found in session');
                }
            } else {
                wp_send_json_error('Invalid item type');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to reorder course items', [
                'session_id' => $sessionId,
                'item_type'  => $itemType,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to reorder items: ' . $e->getMessage());
        }
    }

    /**
     * Delete course item (section or lesson)
     *
     * @return void
     */
    public function deleteCourseItem(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::COURSES_INTEGRATION, false)) {
            $this->logger->warning('Delete course item failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user capabilities.
        if (!current_user_can('edit_posts')) {
            $this->logger->warning('Delete course item failed: insufficient permissions', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        $itemType  = sanitize_text_field($_POST['item_type'] ?? ''); // 'section' or 'lesson'.
        $sectionId = sanitize_text_field($_POST['section_id'] ?? '');
        $lessonId  = sanitize_text_field($_POST['lesson_id'] ?? '');

        if (empty($sessionId) || empty($itemType)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        try {
            if ($itemType === 'lesson') {
                if (empty($sectionId) || empty($lessonId)) {
                    wp_send_json_error('Section ID and Lesson ID are required');
                    return;
                }

                // Delete lesson draft.
                $draftService = $this->getLessonDraftService();

                $success = $draftService->deleteDraft($sessionId, $sectionId, $lessonId);

                if ($success) {
                    // Also update the course structure in conversation state.
                    $conversationManager = $this->getConversationManager();
                    $session             = $conversationManager->loadSession($sessionId);

                    if ($session && $session->getUserId() === get_current_user_id()) {
                        $context = $session->getContext();

                        // Remove lesson from course structure.
                        if (isset($context['course_structure']['sections'])) {
                            foreach ($context['course_structure']['sections'] as &$section) {
                                if (isset($section['lessons'])) {
                                    $section['lessons'] = array_filter(
                                        $section['lessons'],
                                        function ($lesson) use ($lessonId) {
                                            return ($lesson['id'] ?? '') !== $lessonId;
                                        }
                                    );
                                    $section['lessons'] = array_values($section['lessons']); // Re-index.
                                }
                            }

                            $session->setContext($context, null);
                            $conversationManager->saveSession($session);
                        }
                    }

                    $this->logger->info('Lesson deleted', [
                        'session_id' => $sessionId,
                        'section_id' => $sectionId,
                        'lesson_id'  => $lessonId,
                    ]);

                    wp_send_json_success([
                        'message'    => 'Lesson deleted successfully',
                        'deleted_at' => current_time('c'),
                    ]);
                } else {
                    throw new \Exception('Failed to delete lesson');
                }
            } elseif ($itemType === 'section') {
                if (empty($sectionId)) {
                    wp_send_json_error('Section ID is required');
                    return;
                }

                // Delete all lessons in the section.
                $draftService = $this->getLessonDraftService();

                $deletedCount = $draftService->deleteSectionDrafts($sessionId, $sectionId);

                // Update course structure in conversation state.
                $conversationManager = new ConversationManager();
                $session             = $conversationManager->loadSession($sessionId);

                if ($session && $session->getUserId() === get_current_user_id()) {
                    $context = $session->getContext();

                    // Remove section from course structure.
                    if (isset($context['course_structure']['sections'])) {
                        $context['course_structure']['sections'] = array_filter(
                            $context['course_structure']['sections'],
                            function ($section) use ($sectionId) {
                                return ($section['id'] ?? '') !== $sectionId;
                            }
                        );
                        // Re-index.
                        $context['course_structure']['sections'] = array_values(
                            $context['course_structure']['sections']
                        );

                        $session->setContext($context, null);
                        $conversationManager->saveSession($session);
                    }
                }

                $this->logger->info('Section deleted', [
                    'session_id'      => $sessionId,
                    'section_id'      => $sectionId,
                    'lessons_deleted' => $deletedCount,
                ]);

                wp_send_json_success([
                    'message'         => 'Section deleted successfully',
                    'lessons_deleted' => $deletedCount,
                    'deleted_at'      => current_time('c'),
                ]);
            } else {
                wp_send_json_error('Invalid item type');
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete course item', [
                'session_id' => $sessionId,
                'item_type'  => $itemType,
                'error'      => $e->getMessage(),
            ]);
            wp_send_json_error('Failed to delete item: ' . $e->getMessage());
        }
    }

    /**
     * Build prompt for lesson content generation
     *
     * @param  string $courseTitle  Course title
     * @param  string $sectionTitle Section title
     * @param  string $lessonTitle  Lesson title
     * @param  array  $context      Additional context
     * @return string
     */
    private function buildLessonContentPrompt(
        string $courseTitle,
        string $sectionTitle,
        string $lessonTitle,
        array $context
    ): string {
        $prompt = "Generate comprehensive lesson content for an online course.\n\n";

        if (!empty($courseTitle)) {
            $prompt .= "Course: {$courseTitle}\n";
        }

        if (!empty($sectionTitle)) {
            $prompt .= "Section: {$sectionTitle}\n";
        }

        $prompt .= "Lesson: {$lessonTitle}\n\n";

        if (!empty($context['course_description'])) {
            $prompt .= "Course Description: {$context['course_description']}\n\n";
        }

        if (!empty($context['target_audience'])) {
            $prompt .= "Target Audience: {$context['target_audience']}\n\n";
        }

        if (!empty($context['learning_objectives'])) {
            $prompt .= "Learning Objectives:\n";
            foreach ($context['learning_objectives'] as $objective) {
                $prompt .= "- {$objective}\n";
            }
            $prompt .= "\n";
        }

        $prompt .= "Please generate engaging and educational lesson content that:\n";
        $prompt .= "1. Introduces the topic clearly\n";
        $prompt .= "2. Explains concepts with examples\n";
        $prompt .= "3. Includes practical applications\n";
        $prompt .= "4. Summarizes key points\n";
        $prompt .= "5. Uses clear formatting with proper headings, paragraphs, and lists\n\n";
        $prompt .= 'Format the content as clean, readable plain text with proper spacing and structure . ';
        $prompt .= 'use line breaks between sections, bullet points for lists, and clear headings . ';
        $prompt .= 'do NOT use HTML tags or markdown - just plain, well - formatted text . ';

        return $prompt;
    }

    /**
     * Handle AI chat for course editing page
     *
     * @return void
     */
    public function handleCourseEditChat(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_ASSISTANT, false)) {
            $this->logger->warning('Course edit chat failed: invalid nonce', [
                'user_id' => get_current_user_id(),
            ]);
            wp_send_json_error('Security verification failed');
            return;
        }

        // Check permissions.
        $courseId = isset($_POST['course_id']) ? (int) $_POST['course_id'] : 0;
        if ($courseId && !current_user_can('edit_post', $courseId)) {
            wp_send_json_error('Insufficient permissions');
            return;
        } elseif (!$courseId && !current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $message             = sanitize_text_field($_POST['message'] ?? '');
        $courseData          = isset($_POST['course_data']) ? json_decode(wp_unslash($_POST['course_data']), true) : [];
        $conversationHistory = isset($_POST['conversation_history'])
            ? json_decode(wp_unslash($_POST['conversation_history']), true)
            : [];

        // Sanitize arrays.
        if (is_array($courseData)) {
            $courseData = $this->sanitizeArray($courseData);
        }
        if (is_array($conversationHistory)) {
            $conversationHistory = $this->sanitizeArray($conversationHistory, 'textarea');
        }

        if (empty($message)) {
            wp_send_json_error('Message cannot be empty');
            return;
        }

        if (!$courseId) {
            wp_send_json_error('Invalid course ID');
            return;
        }

        // Verify user can edit this course.
        if (!current_user_can('edit_post', $courseId)) {
            wp_send_json_error('You do not have permission to edit this course');
            return;
        }

        try {
            $this->logger->info('Processing course edit chat message', [
                'user_id'        => get_current_user_id(),
                'course_id'      => $courseId,
                'message_length' => strlen($message),
            ]);

            // Build system prompt for course editing context.
            $systemPrompt = $this->buildCourseEditSystemPrompt($courseData);

            // Build conversation messages.
            $messages = [
                [
                    'role'    => 'system',
                    'content' => $systemPrompt,
                ],
            ];

            // Add conversation history.
            foreach ($conversationHistory as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = [
                        'role'    => $msg['role'],
                        'content' => $msg['content'],
                    ];
                }
            }

            // Add current message.
            $messages[] = [
                'role'    => 'user',
                'content' => $message,
            ];

            // Get AI response.
            $llmResponse = $llmService->generateContent('', [
                'messages'   => $messages,
                'max_tokens' => 2000,
            ]);

            if (!$llmResponse['success']) {
                throw new \Exception($llmResponse['error'] ?? 'AI service error');
            }

            $aiResponse = $llmResponse['content'];

            // Check if AI response contains course update instructions.
            $courseUpdates = $this->extractCourseUpdates($aiResponse, $courseData);

            // Build response.
            $responseData = [
                'message'        => $aiResponse,
                'course_updates' => $courseUpdates,
            ];

            // If course updates were suggested, prepare them.
            if ($courseUpdates && isset($courseUpdates['sections'])) {
                // Update course metadata if sections were modified.
                update_post_meta($courseId, '_mpcs_sections', $courseUpdates['sections']);
                $responseData['require_refresh'] = true;
            }

            $this->logger->info('Course edit chat completed successfully', [
                'user_id'     => get_current_user_id(),
                'course_id'   => $courseId,
                'has_updates' => !empty($courseUpdates),
            ]);

            wp_send_json_success($responseData);
        } catch (\Exception $e) {
            $this->logger->error('Course edit chat failed', [
                'user_id'       => get_current_user_id(),
                'course_id'     => $courseId,
                'error_message' => $e->getMessage(),
            ]);

            wp_send_json_error('Failed to process your request: ' . $e->getMessage());
        }
    }

    /**
     * Build system prompt for course editing context
     *
     * @param  array $courseData Course data
     * @return string
     */
    private function buildCourseEditSystemPrompt(array $courseData): string
    {
        $prompt  = 'You are an AI assistant helping to improve and update an online course . ';
        $prompt .= "You have access to the current course structure and can suggest improvements.\n\n";

        $prompt .= "Current Course Information:\n";
        $prompt .= 'Title: ' . ($courseData['title'] ?? 'Untitled Course') . "\n";
        $prompt .= 'Status: ' . ($courseData['status'] ?? 'draft') . "\n";

        if (!empty($courseData['content'])) {
            $prompt .= 'Description: ' . substr($courseData['content'], 0, 200) . "...\n";
        }

        if (!empty($courseData['sections'])) {
            $prompt .= "\nCourse Structure:\n";
            foreach ($courseData['sections'] as $sIndex => $section) {
                $prompt .= sprintf("Section %d: %s\n", $sIndex + 1, $section['title'] ?? 'Untitled Section');
                if (!empty($section['lessons'])) {
                    foreach ($section['lessons'] as $lIndex => $lesson) {
                        $prompt .= sprintf(
                            "  - Lesson %d.%d: %s\n",
                            $sIndex + 1,
                            $lIndex + 1,
                            $lesson['title'] ?? 'Untitled Lesson'
                        );
                    }
                }
            }
        }

        $prompt .= "\nYour role is to:\n";
        $prompt .= "1. Provide helpful suggestions for improving the course\n";
        $prompt .= "2. Help create new content when requested\n";
        $prompt .= "3. Assist with structuring lessons and sections\n";
        $prompt .= "4. Offer pedagogical advice for better learning outcomes\n";
        $prompt .= "5. Help write engaging and educational content\n\n";
        $prompt .= "When suggesting course structure changes, format them clearly so they can be implemented.\n";
        $prompt .= 'Focus on practical, actionable advice that improves the learning experience . ';

        return $prompt;
    }

    /**
     * Extract course updates from AI response
     *
     * @param  string $aiResponse        AI response text
     * @param  array  $currentCourseData Current course data
     * @return array|null
     */
    private function extractCourseUpdates(string $aiResponse, array $currentCourseData): ?array
    {
        // This is a placeholder for more sophisticated update extraction.
        // In a real implementation, you might parse specific formats or JSON blocks.
        // For now, return null to indicate no automatic updates.
        return null;
    }

    /**
     * Handle new simple AI chat
     */
    public function handleNewAIChat(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_ASSISTANT, false)) {
            wp_send_json_error('Security verification failed');
            return;
        }

        // Check permissions.
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if ($postId && !current_user_can('edit_post', $postId)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $message    = sanitize_text_field($_POST['message'] ?? '');
        $courseData = isset($_POST['course_data']) ? $_POST['course_data'] : [];

        // Sanitize course data array.
        if (is_array($courseData)) {
            $courseData = $this->sanitizeArray($courseData);
        }

        if (empty($message)) {
            wp_send_json_error('Message cannot be empty');
            return;
        }

        try {
            // Get LLM service from container.
            $llmService = $this->getLLMService();

            // Build comprehensive system prompt with course context.
            $contextualPrompt = $this->buildCourseContextPrompt($courseData, $message);

            $response = $llmService->generateContent(
                $contextualPrompt,
                'course_assistance',
                [
                    'temperature' => 0.7,
                    'max_tokens'  => 500,
                ]
            );

            if (!$response['error']) {
                $content          = $response['content'];
                $hasContentUpdate = strpos($content, '[CONTENT_UPDATE]') !== false;

                // Remove the tag from display.
                $content = str_replace('[CONTENT_UPDATE]', '', $content);

                wp_send_json_success([
                    'message'            => trim($content),
                    'has_content_update' => $hasContentUpdate,
                ]);
            } else {
                wp_send_json_error($response['message'] ?? 'Failed to generate AI response');
            }
        } catch (\Exception $e) {
            error_log('MPCC: new AI Chat error(): ' . $e->getMessage());
            wp_send_json_error('An error occurred while processing your request');
        }
    }

    /**
     * Handle course content update request
     */
    public function updateCourseContent(): void
    {
        // Verify nonce.
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_ASSISTANT, false)) {
            wp_send_json_error('Security verification failed');
            return;
        }

        // Check permissions.
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        if (!$postId || !current_user_can('edit_post', $postId)) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $content = wp_kses_post($_POST['content'] ?? '');

        if (empty($content)) {
            wp_send_json_error('Content cannot be empty');
            return;
        }

        try {
            // Update the course post content.
            $result = wp_update_post([
                'ID'           => $postId,
                'post_content' => $content,
            ]);

            if (is_wp_error($result)) {
                wp_send_json_error('Failed to update course content: ' . $result->get_error_message());
                return;
            }

            // Log the update.
            $this->logger->info('Course content updated', [
                'post_id'        => $postId,
                'user_id'        => get_current_user_id(),
                'content_length' => strlen($content),
            ]);

            wp_send_json_success([
                'message'    => 'Course content updated successfully',
                'post_id'    => $postId,
                'updated_at' => current_time('c'),
            ]);
        } catch (\Exception $e) {
            error_log('MPCC: Update course content error: ' . $e->getMessage());
            wp_send_json_error('An error occurred while updating the course content');
        }
    }

    /**
     * Build contextual prompt with comprehensive course data
     */
    private function buildCourseContextPrompt(array $courseData, string $userMessage): string
    {
        $prompt  = 'You are an AI course development expert helping a user improve their '
                 . 'online course overview / description . ';
        $prompt .= 'Your focus is on the main course content / description, '
                 . "NOT the lessons or curriculum structure.\n";
        $prompt .= 'When the user asks you to update, enhance, or rewrite the course content, '
                 . "provide the ACTUAL new content they can use.\n";
        $prompt .= "If the user asks for content changes, include [CONTENT_UPDATE] tag in your response.\n\n";

        $prompt .= "=== CURRENT COURSE CONTEXT ===\n";

        // Basic course information.
        if (!empty($courseData['title'])) {
            $prompt .= 'Course Title: ' . $courseData['title'] . "\n";
        }

        if (!empty($courseData['content'])) {
            $content = wp_strip_all_tags($courseData['content']);
            $prompt .= 'Course Description: ' . substr($content, 0, 300) . (strlen($content) > 300 ? '...' : '') . "\n";
        }

        // Course metadata.
        if (!empty($courseData['difficulty_level'])) {
            $prompt .= 'Difficulty Level: ' . $courseData['difficulty_level'] . "\n";
        }

        if (!empty($courseData['target_audience'])) {
            $prompt .= 'Target Audience: ' . $courseData['target_audience'] . "\n";
        }

        if (!empty($courseData['course_category'])) {
            $prompt .= 'Category: ' . $courseData['course_category'] . "\n";
        }

        if (!empty($courseData['template_type'])) {
            $prompt .= 'Template Type: ' . $courseData['template_type'] . "\n";
        }

        // Learning objectives.
        if (!empty($courseData['learning_objectives']) && is_array($courseData['learning_objectives'])) {
            $prompt .= "Learning Objectives:\n";
            foreach ($courseData['learning_objectives'] as $objective) {
                $prompt .= ' - ' . $objective . "\n";
            }
        }

        // Prerequisites.
        if (!empty($courseData['prerequisites']) && is_array($courseData['prerequisites'])) {
            $prompt .= "Prerequisites:\n";
            foreach ($courseData['prerequisites'] as $prereq) {
                $prompt .= ' - ' . $prereq . "\n";
            }
        }

        // Course structure.
        $sectionCount = $courseData['section_count'] ?? 0;
        $lessonCount  = $courseData['lesson_count'] ?? 0;
        $prompt      .= "Course Structure: {$sectionCount} sections, {$lessonCount} lessons\n";

        if (!empty($courseData['total_estimated_duration'])) {
            $prompt .= 'Total Estimated Duration: ' . $courseData['total_estimated_duration'] . " minutes\n";
        }

        // Sections overview.
        if (!empty($courseData['sections']) && is_array($courseData['sections'])) {
            $prompt .= "\nSECTION STRUCTURE:\n";
            foreach ($courseData['sections'] as $section) {
                $lessonCount = count($section['lessons'] ?? []);
                $prompt     .= '• ' . $section['title'] . " ({$lessonCount} lessons)\n";

                if (!empty($section['description'])) {
                    $prompt .= '  Description: ' . substr($section['description'], 0, 100) . "\n";
                }

                // Include lesson titles for context.
                if (!empty($section['lessons'])) {
                    $prompt      .= '  Lessons: ';
                    $lessonTitles = array_column($section['lessons'], 'title');
                    $prompt      .= implode(', ', array_slice($lessonTitles, 0, 3));
                    if (count($lessonTitles) > 3) {
                        $prompt .= ', and ' . (count($lessonTitles) - 3) . ' more';
                    }
                    $prompt .= "\n";
                }
            }
        }

        $prompt .= "\n=== USER QUESTION ===\n";
        $prompt .= $userMessage . "\n\n";

        $prompt .= "=== YOUR RESPONSE ===\n";

        // Check if user wants to update course content/description.
        $contentKeywords = ['description', 'overview', 'content', 'rewrite', 'enhance', 'improve the course text'];
        $updateKeywords  = ['update', 'change', 'edit', 'write', 'rewrite', 'enhance', 'improve'];

        $wantsContentUpdate = false;
        foreach ($contentKeywords as $contentKeyword) {
            if (stripos($userMessage, $contentKeyword) !== false) {
                foreach ($updateKeywords as $updateKeyword) {
                    if (stripos($userMessage, $updateKeyword) !== false) {
                        $wantsContentUpdate = true;
                        break 2;
                    }
                }
            }
        }

        if ($wantsContentUpdate) {
            $prompt .= 'The user wants to update the course overview / description content . ';
            $prompt .= 'Provide the ACTUAL new course description they should() use . ';
            $prompt .= "Write compelling, engaging course content that will attract students.\n";
            $prompt .= "After providing the new content, add '[CONTENT_UPDATE]' tag at the end.\n\n";
        } else {
            $prompt .= 'Provide advice about the course overview and content . ';
            $prompt .= 'Focus on the main course description, not individual lessons . ';
            $prompt .= "Be specific and actionable in your suggestions.\n\n";
        }

        return $prompt;
    }
}