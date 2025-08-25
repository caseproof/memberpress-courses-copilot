<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Controllers\BaseController;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;

/**
 * AJAX Controller
 * 
 * Handles all AJAX requests from the admin interface for MemberPress Courses Copilot
 * Provides secure AJAX endpoints for chat, course preview, template selection,
 * and other interactive features.
 * 
 * @package MemberPressCoursesCopilot\Controllers
 * @since 1.0.0
 */
class AjaxController extends BaseController
{
    /**
     * Course generator service
     * 
     * @var CourseGeneratorService
     */
    private CourseGeneratorService $courseGenerator;

    /**
     * LLM service
     * 
     * @var LLMService
     */
    private LLMService $llmService;


    /**
     * Registered AJAX actions
     * 
     * @var array
     */
    private array $ajaxActions = [
        'mpcc_chat_message',
        'mpcc_start_conversation',
        'mpcc_select_template',
        'mpcc_generate_preview',
        'mpcc_create_course',
        'mpcc_save_progress',
        'mpcc_load_session',
        'mpcc_delete_session',
        'mpcc_validate_course',
        'mpcc_export_course',
        'mpcc_get_templates',
        'mpcc_get_course_patterns',
        'mpcc_upload_content',
        'mpcc_process_bulk_action',
        // Enhanced preview system actions
        'mpcc_stream_generation',
        'mpcc_update_course_element',
        'mpcc_reorder_elements',
        'mpcc_get_metrics',
        'mpcc_validate_element',
        'mpcc_auto_save',
        'mpcc_ping',
        'mpcc_generate_response',
        'mpcc_get_suggestions',
        'mpcc_get_learning_path',
        // Lesson draft editing actions
        // Note: These are handled by CourseAjaxService, not this controller
        // 'mpcc_save_lesson_content',
        // 'mpcc_load_lesson_content', 
        // 'mpcc_generate_lesson_content',
        // 'mpcc_reorder_course_items',
        // 'mpcc_delete_course_item',
        'mpcc_load_all_drafts',
        'mpcc_save_conversation'
    ];

    /**
     * Constructor
     * 
     * @param CourseGeneratorService $courseGenerator Course generator service
     * @param LLMService $llmService LLM service
     */
    public function __construct(
        CourseGeneratorService $courseGenerator,
        LLMService $llmService
    ) {
        $this->courseGenerator = $courseGenerator;
        $this->llmService = $llmService;
    }

    /**
     * Initialize the controller
     * 
     * @return void
     */
    public function init(): void
    {
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    public function registerHooks(): void
    {
        // Register all AJAX actions for both logged-in and logged-out users
        foreach ($this->ajaxActions as $action) {
            add_action("wp_ajax_{$action}", [$this, 'handleAjaxRequest']);
            add_action("wp_ajax_nopriv_{$action}", [$this, 'handleUnauthorizedRequest']);
        }

        // Register cleanup hooks
        add_action('wp_ajax_mpcc_cleanup_sessions', [$this, 'cleanupSessions']);
        add_action('wp_ajax_mpcc_download_export', [$this, 'downloadExport']);
    }

    /**
     * Handle AJAX request router
     * 
     * @return void
     */
    public function handleAjaxRequest(): void
    {
        try {
            // Verify nonce
            $this->verifyAjaxNonce();

            // Check user permissions
            $this->checkAjaxPermissions();

            // Get action
            $action = $_POST['action'] ?? '';
            $action = str_replace('mpcc_', '', $action);

            // Log for debugging
            error_log('MPCC AJAX request received: ' . $action);

            // Route to appropriate handler
            $response = $this->routeAjaxAction($action);

            $this->sendSuccessResponse($response);

        } catch (\Exception $e) {
            error_log('MPCC AJAX request failed: ' . $e->getMessage());
            $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Handle unauthorized AJAX request
     * 
     * @return void
     */
    public function handleUnauthorizedRequest(): void
    {
        error_log('MPCC: Unauthorized AJAX request - ' . ($_POST['action'] ?? 'unknown'));
        $this->sendErrorResponse('You must be logged in to perform this action.', 401);
    }

    /**
     * Verify AJAX nonce
     * 
     * @throws \Exception If nonce verification fails
     */
    private function verifyAjaxNonce(): void
    {
        $nonce = $_POST['nonce'] ?? $_POST['_wpnonce'] ?? '';
        
        // Check all possible nonce types that might be used
        if (!wp_verify_nonce($nonce, 'mpcc_editor_nonce') && 
            !wp_verify_nonce($nonce, 'mpcc_ajax_nonce') &&
            !wp_verify_nonce($nonce, 'mpcc_nonce') &&
            !wp_verify_nonce($nonce, 'mpcc_courses_integration')) {
            throw new \Exception('Security check failed');
        }
    }
    
    /**
     * Check AJAX permissions
     * 
     * @throws \Exception If user doesn't have permissions
     */
    private function checkAjaxPermissions(): void
    {
        if (!current_user_can('edit_posts')) {
            throw new \Exception('You do not have permission to perform this action');
        }
    }
    
    /**
     * Send success response
     * 
     * @param array $data Response data
     */
    private function sendSuccessResponse(array $data): void
    {
        wp_send_json_success($data);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     */
    private function sendErrorResponse(string $message, int $code = 400): void
    {
        status_header($code);
        wp_send_json_error($message);
    }
    
    /**
     * Route AJAX action to appropriate handler
     * 
     * @param string $action Action name
     * @return array Response data
     * @throws \Exception If action not found
     */
    private function routeAjaxAction(string $action): array
    {
        return match($action) {
            'chat_message' => $this->handleChatMessage(),
            'start_conversation' => $this->handleStartConversation(),
            'select_template' => $this->handleSelectTemplate(),
            'generate_preview' => $this->handleGeneratePreview(),
            'create_course' => $this->handleCreateCourse(),
            'save_progress' => $this->handleSaveProgress(),
            'load_session' => $this->handleLoadSession(),
            'delete_session' => $this->handleDeleteSession(),
            'validate_course' => $this->handleValidateCourse(),
            'export_course' => $this->handleExportCourse(),
            'get_templates' => $this->handleGetTemplates(),
            'get_course_patterns' => $this->handleGetCoursePatterns(),
            'upload_content' => $this->handleUploadContent(),
            'process_bulk_action' => $this->handleProcessBulkAction(),
            // Enhanced preview system actions
            'stream_generation' => $this->handleStreamGeneration(),
            'update_course_element' => $this->handleUpdateCourseElement(),
            'reorder_elements' => $this->handleReorderElements(),
            'get_metrics' => $this->handleGetMetrics(),
            'validate_element' => $this->handleValidateElement(),
            'auto_save' => $this->handleAutoSave(),
            'ping' => $this->handlePing(),
            'generate_response' => $this->handleGenerateResponse(),
            'get_suggestions' => $this->handleGetSuggestions(),
            'get_learning_path' => $this->handleGetLearningPath(),
            // Lesson draft editing actions - COMMENTED OUT to avoid conflicts with CourseAjaxService
            // 'save_lesson_content' => $this->handleSaveLessonContent(),
            // 'load_lesson_content' => $this->handleLoadLessonContent(),
            // 'generate_lesson_content' => $this->handleGenerateLessonContent(),
            // 'reorder_course_items' => $this->handleReorderCourseItems(),
            // 'delete_course_item' => $this->handleDeleteCourseItem(),
            // 'load_all_drafts' => $this->handleLoadAllDrafts(),
            // 'save_conversation' => $this->handleSaveConversation(),
            default => throw new \Exception("Unknown action: {$action}")
        };
    }

    /**
     * Handle chat message
     * 
     * @return array Response data
     */
    private function handleChatMessage(): array
    {
        $message = $this->sanitizeInput($_POST['message'] ?? '', 'textarea');
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $context = $_POST['context'] ?? [];

        if (empty($message)) {
            throw new \Exception('Message is required');
        }

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        // Load conversation state
        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Restore conversation context
        $this->restoreConversationContext($conversationState);

        // Process message
        $response = $this->courseGenerator->processMessage($message, $context);

        // Save updated state
        $this->saveSessionData($sessionId, $this->courseGenerator->getConversationState());

        // Log conversation interaction
        $this->logger->info('Chat message processed', [
            'session_id' => $sessionId,
            'message_length' => strlen($message),
            'response_state' => $response['state'] ?? 'unknown'
        ]);

        return [
            'success' => true,
            'data' => $response,
            'timestamp' => current_time('c')
        ];
    }

    /**
     * Handle start conversation
     * 
     * @return array Response data
     */
    private function handleStartConversation(): array
    {
        $initialData = [
            'user_id' => get_current_user_id(),
            'started_from' => $_POST['source'] ?? 'admin',
            'user_preferences' => $_POST['preferences'] ?? [],
        ];

        $response = $this->courseGenerator->startConversation($initialData);
        $sessionId = $response['session_id'];

        // Save conversation state
        $this->saveSessionData($sessionId, $this->courseGenerator->getConversationState());

        $this->logger->info('New conversation started', [
            'session_id' => $sessionId,
            'user_id' => get_current_user_id()
        ]);

        return [
            'success' => true,
            'data' => $response,
            'session_id' => $sessionId
        ];
    }

    /**
     * Handle template selection
     * 
     * @return array Response data
     */
    private function handleSelectTemplate(): array
    {
        $templateType = $this->sanitizeInput($_POST['template_type'] ?? '');
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');

        if (empty($templateType)) {
            throw new \Exception('Template type is required');
        }

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        // Validate template type
        $validTemplates = ['technical', 'business', 'creative', 'academic'];
        if (!in_array($templateType, $validTemplates)) {
            throw new \Exception('Invalid template type');
        }

        // Load conversation state
        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $this->restoreConversationContext($conversationState);

        // Process template selection
        $response = $this->courseGenerator->processMessage($templateType, [
            'action' => 'template_select',
            'template_type' => $templateType
        ]);

        // Save updated state
        $this->saveSessionData($sessionId, $this->courseGenerator->getConversationState());

        $this->logger->info('Template selected', [
            'session_id' => $sessionId,
            'template_type' => $templateType
        ]);

        return [
            'success' => true,
            'data' => $response,
            'template_type' => $templateType
        ];
    }

    /**
     * Handle generate preview
     * 
     * @return array Response data
     */
    private function handleGeneratePreview(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            throw new \Exception('No course has been generated yet');
        }

        $preview = [
            'course_data' => $generatedCourse->toArray(),
            'statistics' => $this->generateCourseStatistics($generatedCourse),
            'quality_check' => $this->performQualityCheck($generatedCourse),
            'estimated_time' => $this->calculateEstimatedTime($generatedCourse)
        ];

        return [
            'success' => true,
            'data' => $preview
        ];
    }

    /**
     * Handle create course
     * 
     * @return array Response data
     */
    private function handleCreateCourse(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $courseOptions = $_POST['course_options'] ?? [];

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            throw new \Exception('No course has been generated yet');
        }

        // Apply any custom options
        if (!empty($courseOptions)) {
            $this->applyCourseOptions($generatedCourse, $courseOptions);
        }

        // Apply drafted lesson content before creating the course
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $courseStructure = $generatedCourse->toArray();
        $courseStructure = $draftService->mapDraftsToStructure($sessionId, $courseStructure);
        
        // Update the generated course with drafted content
        if (isset($courseStructure['sections']) && is_array($courseStructure['sections'])) {
            foreach ($courseStructure['sections'] as $sectionIndex => $sectionData) {
                $section = $generatedCourse->getSection($sectionIndex);
                if ($section && isset($sectionData['lessons']) && is_array($sectionData['lessons'])) {
                    foreach ($sectionData['lessons'] as $lessonIndex => $lessonData) {
                        if (isset($lessonData['content']) && !empty($lessonData['content'])) {
                            $lesson = $section->getLesson($lessonIndex);
                            if ($lesson) {
                                $lesson->setContent($lessonData['content']);
                            }
                        }
                    }
                }
            }
        }

        // Create the WordPress course
        $this->restoreConversationContext($conversationState);
        $courseId = $this->courseGenerator->createWordPressCourse($generatedCourse);
        
        // Clean up drafts after successful course creation
        $draftService->deleteSessionDrafts($sessionId);

        // Update session with course creation info
        $conversationState['created_course_id'] = $courseId;
        $conversationState['course_created_at'] = current_time('timestamp');
        $this->saveSessionData($sessionId, $conversationState);

        $this->logger->info('Course created successfully', [
            'session_id' => $sessionId,
            'course_id' => $courseId,
            'user_id' => get_current_user_id()
        ]);

        return [
            'success' => true,
            'data' => [
                'course_id' => $courseId,
                'edit_url' => admin_url("post.php?post={$courseId}&action=edit"),
                'preview_url' => get_permalink($courseId),
                'course_title' => $generatedCourse->getTitle()
            ]
        ];
    }

    /**
     * Handle save progress
     * 
     * @return array Response data
     */
    private function handleSaveProgress(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $progressData = $_POST['progress_data'] ?? [];

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Update progress data
        $conversationState['progress'] = array_merge(
            $conversationState['progress'] ?? [],
            $progressData
        );
        $conversationState['last_saved'] = current_time('timestamp');

        $this->saveSessionData($sessionId, $conversationState);

        return [
            'success' => true,
            'data' => [
                'saved_at' => current_time('c'),
                'progress_percentage' => $this->calculateProgressPercentage($conversationState)
            ]
        ];
    }

    /**
     * Handle load session
     * 
     * @return array Response data
     */
    private function handleLoadSession(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Session not found');
        }

        // Remove sensitive data before sending to client
        $publicData = $this->filterSensitiveData($conversationState);

        return [
            'success' => true,
            'data' => $publicData
        ];
    }

    /**
     * Handle delete session
     * 
     * @return array Response data
     */
    private function handleDeleteSession(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $deleted = $this->deleteSessionData($sessionId);

        if (!$deleted) {
            throw new \Exception('Session not found');
        }

        $this->logger->info('Session deleted', [
            'session_id' => $sessionId,
            'user_id' => get_current_user_id()
        ]);

        return [
            'success' => true,
            'data' => [
                'message' => 'Session deleted successfully'
            ]
        ];
    }

    /**
     * Handle validate course
     * 
     * @return array Response data
     */
    private function handleValidateCourse(): array
    {
        $courseData = $_POST['course_data'] ?? [];
        $validationType = $this->sanitizeInput($_POST['validation_type'] ?? 'basic');

        if (empty($courseData)) {
            throw new \Exception('Course data is required');
        }

        $validation = $this->performCourseValidation($courseData, $validationType);

        return [
            'success' => true,
            'data' => $validation
        ];
    }

    /**
     * Handle export course
     * 
     * @return array Response data
     */
    private function handleExportCourse(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $format = $this->sanitizeInput($_POST['format'] ?? 'json');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            throw new \Exception('No course has been generated yet');
        }

        $exportData = $this->generateExportData($generatedCourse, $format);

        return [
            'success' => true,
            'data' => $exportData
        ];
    }

    /**
     * Handle get templates
     * 
     * @return array Response data
     */
    private function handleGetTemplates(): array
    {
        $includeDetails = $_POST['include_details'] === 'true';
        
        $templates = CourseTemplate::getPredefinedTemplates();
        $formattedTemplates = [];

        foreach ($templates as $type => $template) {
            $templateData = [
                'type' => $type,
                'title' => ucfirst($type) . ' Course Template',
                'description' => $this->getTemplateDescription($type),
                'lesson_count' => $template->getTotalLessons(),
            ];

            if ($includeDetails) {
                $templateData['structure'] = $template->getDefaultStructure();
                $templateData['suggested_questions'] = $template->getSuggestedQuestions();
                $templateData['quality_checks'] = $template->getQualityChecks();
            }

            $formattedTemplates[] = $templateData;
        }

        return [
            'success' => true,
            'data' => [
                'templates' => $formattedTemplates,
                'total' => count($formattedTemplates)
            ]
        ];
    }

    /**
     * Handle get course patterns
     * 
     * @return array Response data
     */
    private function handleGetCoursePatterns(): array
    {
        $category = $this->sanitizeInput($_POST['category'] ?? '');
        
        $patterns = $this->getCoursePatterns();

        if ($category) {
            $patterns = array_filter($patterns, function($pattern) use ($category) {
                return $pattern['category'] === $category;
            });
        }

        return [
            'success' => true,
            'data' => [
                'patterns' => array_values($patterns),
                'categories' => $this->getPatternCategories()
            ]
        ];
    }

    /**
     * Handle upload content
     * 
     * @return array Response data
     */
    private function handleUploadContent(): array
    {
        if (!isset($_FILES['content_file'])) {
            throw new \Exception('No file uploaded');
        }

        $file = $_FILES['content_file'];
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');

        // Validate file
        $this->validateUploadedFile($file);

        // Process uploaded content
        $processedContent = $this->processUploadedContent($file);

        // If session ID provided, attach to session
        if ($sessionId) {
            $conversationState = $this->getSessionData($sessionId);
            if ($conversationState) {
                $conversationState['uploaded_content'] = $processedContent;
                $this->saveSessionData($sessionId, $conversationState);
            }
        }

        return [
            'success' => true,
            'data' => [
                'content' => $processedContent,
                'message' => 'Content uploaded and processed successfully'
            ]
        ];
    }

    /**
     * Handle process bulk action
     * 
     * @return array Response data
     */
    private function handleProcessBulkAction(): array
    {
        $action = $this->sanitizeInput($_POST['bulk_action'] ?? '');
        $sessionIds = $_POST['session_ids'] ?? [];

        if (empty($action)) {
            throw new \Exception('Bulk action is required');
        }

        if (empty($sessionIds) || !is_array($sessionIds)) {
            throw new \Exception('Session IDs are required');
        }

        $results = [];

        foreach ($sessionIds as $sessionId) {
            $sessionId = $this->sanitizeInput($sessionId);
            try {
                $result = $this->processBulkActionForSession($action, $sessionId);
                $results[$sessionId] = $result;
            } catch (\Exception $e) {
                $results[$sessionId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $successCount = count(array_filter($results, function($result) {
            return $result['success'] ?? false;
        }));

        return [
            'success' => true,
            'data' => [
                'results' => $results,
                'total_processed' => count($sessionIds),
                'successful' => $successCount,
                'failed' => count($sessionIds) - $successCount
            ]
        ];
    }

    /**
     * Cleanup old sessions
     * 
     * @return void
     */
    public function cleanupSessions(): void
    {
        try {
            $this->verifyAjaxNonce();
            $this->checkAjaxPermissions('manage_options');

            $daysToKeep = (int) ($_POST['days_to_keep'] ?? 30);
            $cutoffTime = current_time('timestamp') - ($daysToKeep * DAY_IN_SECONDS);

            $sessions = get_option('mpcc_session_storage', []);
            $cleanedCount = 0;

            foreach ($sessions as $sessionId => $sessionData) {
                $createdAt = $sessionData['created_at'] ?? 0;
                if ($createdAt < $cutoffTime) {
                    unset($sessions[$sessionId]);
                    $cleanedCount++;
                }
            }

            update_option('mpcc_session_storage', $sessions);

            $this->logger->info('Session cleanup completed', [
                'sessions_cleaned' => $cleanedCount,
                'days_kept' => $daysToKeep
            ]);

            $this->sendSuccessResponse([
                'sessions_cleaned' => $cleanedCount,
                'message' => "Cleaned {$cleanedCount} old sessions"
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Session cleanup failed', [
                'error' => $e->getMessage()
            ]);

            $this->sendErrorResponse($e->getMessage());
        }
    }

    /**
     * Download export
     * 
     * @return void
     */
    public function downloadExport(): void
    {
        try {
            $nonce = $_GET['nonce'] ?? '';
            if (!wp_verify_nonce($nonce, 'mpcc_export')) {
                throw new \Exception('Invalid nonce');
            }

            $sessionId = $_GET['session_id'] ?? '';
            if (empty($sessionId)) {
                throw new \Exception('Session ID is required');
            }

            $conversationState = $this->getSessionData($sessionId);
            if (!$conversationState) {
                throw new \Exception('Session not found');
            }

            $generatedCourse = $conversationState['generated_course'] ?? null;
            if (!$generatedCourse) {
                throw new \Exception('No course data to export');
            }

            $this->outputCourseExport($generatedCourse);

        } catch (\Exception $e) {
            wp_die('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Security and validation methods
     */


    /**
     * Check AJAX permissions
     * 
     * @param string $capability Required capability
     * @throws \Exception If user lacks permission
     */
    private function checkAjaxPermissions(string $capability = 'edit_posts'): void
    {
        if (!$this->userCan($capability)) {
            throw new \Exception('Insufficient permissions');
        }
    }

    /**
     * Session management methods
     */

    /**
     * Get session data
     * 
     * @param string $sessionId Session ID
     * @return array|null Session data
     */
    private function getSessionData(string $sessionId): ?array
    {
        $sessions = get_option('mpcc_session_storage', []);
        return $sessions[$sessionId] ?? null;
    }

    /**
     * Save session data
     * 
     * @param string $sessionId Session ID
     * @param array $data Session data
     * @return void
     */
    private function saveSessionData(string $sessionId, array $data): void
    {
        $sessions = get_option('mpcc_session_storage', []);
        $sessions[$sessionId] = $data;
        update_option('mpcc_session_storage', $sessions);
    }

    /**
     * Delete session data
     * 
     * @param string $sessionId Session ID
     * @return bool Success status
     */
    private function deleteSessionData(string $sessionId): bool
    {
        $sessions = get_option('mpcc_session_storage', []);
        
        if (isset($sessions[$sessionId])) {
            unset($sessions[$sessionId]);
            update_option('mpcc_session_storage', $sessions);
            return true;
        }
        
        return false;
    }

    /**
     * Restore conversation context
     * 
     * @param array $conversationState Conversation state
     * @return void
     */
    private function restoreConversationContext(array $conversationState): void
    {
        // This would restore the CourseGeneratorService state
        // Implementation depends on how state restoration is handled
    }

    /**
     * Helper methods
     */

    /**
     * Send success response
     * 
     * @param array $data Response data
     * @return void
     */
    private function sendSuccessResponse(array $data): void
    {
        wp_send_json_success($data);
    }

    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code Error code
     * @return void
     */
    private function sendErrorResponse(string $message, int $code = 500): void
    {
        wp_send_json_error([
            'message' => $message,
            'code' => $code
        ], $code);
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Generate course statistics
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Statistics
     */
    private function generateCourseStatistics(GeneratedCourse $course): array
    {
        $sectionsCount = count($course->getSections());
        $lessonsCount = 0;
        $totalWords = 0;
        $totalDuration = 0;

        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $lessonsCount++;
                $totalWords += str_word_count(strip_tags($lesson->getContent()));
                $totalDuration += $lesson->getEstimatedDuration();
            }
        }

        return [
            'sections_count' => $sectionsCount,
            'lessons_count' => $lessonsCount,
            'total_words' => $totalWords,
            'estimated_duration_minutes' => $totalDuration,
            'estimated_duration_hours' => round($totalDuration / 60, 1),
            'average_lesson_duration' => $lessonsCount > 0 ? round($totalDuration / $lessonsCount) : 0,
            'reading_time_minutes' => round($totalWords / 200), // 200 words per minute
        ];
    }

    /**
     * Perform quality check
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Quality check results
     */
    private function performQualityCheck(GeneratedCourse $course): array
    {
        $issues = [];
        $suggestions = [];
        $score = 0;

        // Check basic requirements
        if (empty($course->getTitle())) {
            $issues[] = 'Course title is missing';
        } else {
            $score += 10;
        }

        if (empty($course->getDescription())) {
            $issues[] = 'Course description is missing';
        } else {
            $score += 10;
        }

        // Check learning objectives
        if (count($course->getLearningObjectives()) < 3) {
            $suggestions[] = 'Consider adding more learning objectives (minimum 3 recommended)';
        } else {
            $score += 15;
        }

        // Check sections and lessons
        $sectionsCount = count($course->getSections());
        if ($sectionsCount < 3) {
            $suggestions[] = 'Consider adding more sections for better organization';
        } else {
            $score += 15;
        }

        // Check lesson content
        $lessonsWithContent = 0;
        $totalLessons = 0;
        
        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $totalLessons++;
                if (strlen($lesson->getContent()) > 100) {
                    $lessonsWithContent++;
                }
            }
        }

        if ($totalLessons > 0) {
            $contentRatio = $lessonsWithContent / $totalLessons;
            if ($contentRatio < 0.8) {
                $suggestions[] = 'Some lessons need more detailed content';
            } else {
                $score += 25;
            }
        }

        // Final score adjustments
        if (empty($issues)) {
            $score += 25;
        }

        return [
            'score' => min($score, 100),
            'percentage' => min($score, 100),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'level' => $this->getQualityLevel($score)
        ];
    }

    /**
     * Calculate estimated time
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Time estimates
     */
    private function calculateEstimatedTime(GeneratedCourse $course): array
    {
        $totalMinutes = 0;
        $lessonCount = 0;

        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $totalMinutes += $lesson->getEstimatedDuration();
                $lessonCount++;
            }
        }

        return [
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 1),
            'lesson_count' => $lessonCount,
            'average_lesson_minutes' => $lessonCount > 0 ? round($totalMinutes / $lessonCount) : 0,
            'estimated_completion_days' => max(1, round($totalMinutes / 120)), // 2 hours per day
        ];
    }

    /**
     * Apply course options
     * 
     * @param GeneratedCourse $course Generated course
     * @param array $options Course options
     * @return void
     */
    private function applyCourseOptions(GeneratedCourse $course, array $options): void
    {
        if (isset($options['title'])) {
            $course->setTitle($this->sanitizeInput($options['title']));
        }

        if (isset($options['description'])) {
            $course->setDescription($this->sanitizeInput($options['description'], 'textarea'));
        }

        if (isset($options['difficulty_level'])) {
            $course->setDifficultyLevel($this->sanitizeInput($options['difficulty_level']));
        }
    }

    /**
     * Calculate progress percentage
     * 
     * @param array $conversationState Conversation state
     * @return int Progress percentage
     */
    private function calculateProgressPercentage(array $conversationState): int
    {
        $currentState = $conversationState['current_state'] ?? '';
        
        $stateProgress = [
            'initial' => 0,
            'template_selection' => 20,
            'gathering_requirements' => 40,
            'structure_review' => 60,
            'content_generation' => 80,
            'final_review' => 90,
            'completed' => 100
        ];

        return $stateProgress[$currentState] ?? 0;
    }

    /**
     * Filter sensitive data from conversation state
     * 
     * @param array $conversationState Conversation state
     * @return array Filtered data
     */
    private function filterSensitiveData(array $conversationState): array
    {
        return [
            'session_id' => $conversationState['session_id'] ?? '',
            'current_state' => $conversationState['current_state'] ?? '',
            'created_at' => $conversationState['created_at'] ?? '',
            'updated_at' => $conversationState['updated_at'] ?? '',
            'progress' => $conversationState['progress'] ?? [],
            'selected_template' => $conversationState['selected_template']?->toArray() ?? null,
            'requirements' => $conversationState['requirements'] ?? [],
            'generated_course' => $conversationState['generated_course']?->toArray() ?? null,
        ];
    }

    /**
     * Perform course validation
     * 
     * @param array $courseData Course data
     * @param string $validationType Validation type
     * @return array Validation results
     */
    private function performCourseValidation(array $courseData, string $validationType): array
    {
        $errors = [];
        $warnings = [];

        // Basic validation
        if (empty($courseData['title'])) {
            $errors[] = 'Course title is required';
        }

        if (empty($courseData['sections'])) {
            $errors[] = 'Course must have at least one section';
        }

        // Extended validation
        if ($validationType === 'comprehensive') {
            if (empty($courseData['description'])) {
                $warnings[] = 'Course description is recommended';
            }

            if (empty($courseData['learning_objectives'])) {
                $warnings[] = 'Learning objectives are recommended';
            }
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'validation_type' => $validationType
        ];
    }

    /**
     * Generate export data
     * 
     * @param GeneratedCourse $course Generated course
     * @param string $format Export format
     * @return array Export data
     */
    private function generateExportData(GeneratedCourse $course, string $format): array
    {
        $data = [
            'course' => $course->toArray(),
            'metadata' => [
                'exported_at' => current_time('c'),
                'exported_by' => get_current_user_id(),
                'format' => $format,
                'plugin_version' => '1.0.0'
            ]
        ];

        return [
            'data' => $data,
            'filename' => sanitize_file_name($course->getTitle() . '_export.' . $format),
            'format' => $format,
            'size' => strlen(json_encode($data))
        ];
    }

    /**
     * Get template description
     * 
     * @param string $type Template type
     * @return string Description
     */
    private function getTemplateDescription(string $type): string
    {
        $descriptions = [
            'technical' => 'Perfect for programming, software development, and technical skills courses',
            'business' => 'Ideal for business strategy, marketing, management, and entrepreneurship courses',
            'creative' => 'Designed for art, design, writing, and other creative disciplines',
            'academic' => 'Structured for formal education, research-based, and theoretical courses'
        ];

        return $descriptions[$type] ?? 'Course template';
    }

    /**
     * Get course patterns
     * 
     * @return array Course patterns
     */
    private function getCoursePatterns(): array
    {
        return [
            [
                'id' => 'beginner_friendly',
                'name' => 'Beginner Friendly',
                'category' => 'structure',
                'description' => 'Gradual introduction with lots of examples and practice'
            ],
            [
                'id' => 'project_based',
                'name' => 'Project-Based Learning',
                'category' => 'methodology',
                'description' => 'Learning through building real-world projects'
            ],
            [
                'id' => 'modular_design',
                'name' => 'Modular Design',
                'category' => 'structure',
                'description' => 'Independent modules that can be taken in any order'
            ]
        ];
    }

    /**
     * Get pattern categories
     * 
     * @return array Pattern categories
     */
    private function getPatternCategories(): array
    {
        return [
            'structure' => 'Course Structure',
            'methodology' => 'Teaching Methodology',
            'assessment' => 'Assessment Methods',
            'engagement' => 'Student Engagement'
        ];
    }

    /**
     * Validate uploaded file
     * 
     * @param array $file Uploaded file data
     * @throws \Exception If file is invalid
     */
    private function validateUploadedFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception('File upload failed');
        }

        $allowedTypes = ['text/plain', 'text/csv', 'application/json', 'application/pdf'];
        if (!in_array($file['type'], $allowedTypes)) {
            throw new \Exception('File type not allowed');
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new \Exception('File size too large');
        }
    }

    /**
     * Process uploaded content
     * 
     * @param array $file Uploaded file data
     * @return array Processed content
     */
    private function processUploadedContent(array $file): array
    {
        $content = file_get_contents($file['tmp_name']);
        
        return [
            'filename' => $file['name'],
            'type' => $file['type'],
            'size' => $file['size'],
            'content' => $content,
            'processed_at' => current_time('c')
        ];
    }

    /**
     * Process bulk action for session
     * 
     * @param string $action Bulk action
     * @param string $sessionId Session ID
     * @return array Result
     */
    private function processBulkActionForSession(string $action, string $sessionId): array
    {
        return match($action) {
            'delete' => [
                'success' => $this->deleteSessionData($sessionId),
                'action' => 'deleted'
            ],
            'archive' => [
                'success' => $this->archiveSession($sessionId),
                'action' => 'archived'
            ],
            default => throw new \Exception("Unknown bulk action: {$action}")
        };
    }

    /**
     * Archive session
     * 
     * @param string $sessionId Session ID
     * @return bool Success status
     */
    private function archiveSession(string $sessionId): bool
    {
        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            return false;
        }

        $conversationState['archived'] = true;
        $conversationState['archived_at'] = current_time('timestamp');
        $this->saveSessionData($sessionId, $conversationState);

        return true;
    }

    /**
     * Get quality level
     * 
     * @param int $score Quality score
     * @return string Quality level
     */
    private function getQualityLevel(int $score): string
    {
        if ($score >= 90) return 'Excellent';
        if ($score >= 80) return 'Very Good';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Fair';
        return 'Needs Improvement';
    }

    /**
     * Output course export
     * 
     * @param GeneratedCourse $course Generated course
     * @return void
     */
    private function outputCourseExport(GeneratedCourse $course): void
    {
        $filename = sanitize_file_name($course->getTitle() . '_export.json');
        $data = json_encode($course->toArray(), JSON_PRETTY_PRINT);

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));

        echo $data;
        exit;
    }

    // ==================================================================================
    // Enhanced Preview System Handlers
    // ==================================================================================

    /**
     * Handle streaming generation for real-time updates
     * 
     * @return array Response data
     */
    private function handleStreamGeneration(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $streamType = $this->sanitizeInput($_POST['stream_type'] ?? '');
        $lastUpdateId = $this->sanitizeInput($_POST['last_update_id'] ?? '');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Check for new updates since last poll
        $updates = $this->getStreamUpdates($sessionId, $lastUpdateId, $streamType);
        
        // Add generation progress if streaming is active
        $isGenerating = $conversationState['is_generating'] ?? false;
        $generationProgress = $conversationState['generation_progress'] ?? [];

        return [
            'success' => true,
            'data' => [
                'updates' => $updates,
                'is_generating' => $isGenerating,
                'progress' => $generationProgress,
                'last_update_id' => $updates ? end($updates)['id'] : $lastUpdateId,
                'timestamp' => current_time('c')
            ]
        ];
    }

    /**
     * Handle course element updates for real-time editing
     * 
     * @return array Response data
     */
    private function handleUpdateCourseElement(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $elementType = $this->sanitizeInput($_POST['element_type'] ?? '');
        $elementId = $this->sanitizeInput($_POST['element_id'] ?? '');
        $updates = $_POST['updates'] ?? [];

        if (empty($sessionId) || empty($elementType) || empty($elementId)) {
            throw new \Exception('Session ID, element type, and element ID are required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Update the specific element
        $result = $this->updateCourseElement($conversationState, $elementType, $elementId, $updates);
        
        if ($result['success']) {
            // Save updated state
            $this->saveSessionData($sessionId, $conversationState);
            
            // Add to update stream
            $this->addStreamUpdate($sessionId, [
                'type' => 'element_updated',
                'element_type' => $elementType,
                'element_id' => $elementId,
                'updates' => $updates,
                'timestamp' => current_time('c')
            ]);
        }

        return [
            'success' => $result['success'],
            'data' => [
                'element_type' => $elementType,
                'element_id' => $elementId,
                'updated_fields' => $result['updated_fields'] ?? [],
                'validation' => $this->validateElementUpdates($elementType, $updates),
                'metrics' => $this->calculateElementMetrics($conversationState, $elementType, $elementId)
            ]
        ];
    }

    /**
     * Handle element reordering for drag-and-drop functionality
     * 
     * @return array Response data
     */
    private function handleReorderElements(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sourceElement = $_POST['source_element'] ?? [];
        $targetElement = $_POST['target_element'] ?? [];
        $operation = $this->sanitizeInput($_POST['operation'] ?? 'move'); // move, copy, swap

        if (empty($sessionId) || empty($sourceElement) || empty($targetElement)) {
            throw new \Exception('Session ID, source element, and target element are required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Perform reordering operation
        $result = $this->reorderCourseElements($conversationState, $sourceElement, $targetElement, $operation);
        
        if ($result['success']) {
            // Save updated state
            $this->saveSessionData($sessionId, $conversationState);
            
            // Add to update stream
            $this->addStreamUpdate($sessionId, [
                'type' => 'elements_reordered',
                'operation' => $operation,
                'source' => $sourceElement,
                'target' => $targetElement,
                'timestamp' => current_time('c')
            ]);
        }

        return [
            'success' => $result['success'],
            'data' => [
                'operation' => $operation,
                'affected_elements' => $result['affected_elements'] ?? [],
                'new_structure' => $this->getCourseStructure($conversationState),
                'validation' => $this->validateCourseStructure($conversationState)
            ]
        ];
    }

    /**
     * Handle metrics calculation for course analytics
     * 
     * @return array Response data
     */
    private function handleGetMetrics(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $metricsType = $this->sanitizeInput($_POST['metrics_type'] ?? 'all');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            throw new \Exception('No course data available');
        }

        $metrics = [];

        if ($metricsType === 'all' || $metricsType === 'basic') {
            $metrics['basic'] = $this->generateCourseStatistics($generatedCourse);
        }

        if ($metricsType === 'all' || $metricsType === 'quality') {
            $metrics['quality'] = $this->performQualityCheck($generatedCourse);
        }

        if ($metricsType === 'all' || $metricsType === 'engagement') {
            $metrics['engagement'] = $this->calculateEngagementMetrics($generatedCourse);
        }

        if ($metricsType === 'all' || $metricsType === 'learning_path') {
            $metrics['learning_path'] = $this->generateLearningPathMetrics($generatedCourse);
        }

        return [
            'success' => true,
            'data' => [
                'metrics' => $metrics,
                'calculated_at' => current_time('c'),
                'metrics_type' => $metricsType
            ]
        ];
    }

    /**
     * Handle element validation for real-time feedback
     * 
     * @return array Response data
     */
    private function handleValidateElement(): array
    {
        $elementType = $this->sanitizeInput($_POST['element_type'] ?? '');
        $elementData = $_POST['element_data'] ?? [];
        $validationLevel = $this->sanitizeInput($_POST['validation_level'] ?? 'basic');

        if (empty($elementType)) {
            throw new \Exception('Element type is required');
        }

        $validation = $this->validateElement($elementType, $elementData, $validationLevel);

        return [
            'success' => true,
            'data' => [
                'validation' => $validation,
                'element_type' => $elementType,
                'validation_level' => $validationLevel,
                'suggestions' => $this->getElementSuggestions($elementType, $elementData, $validation)
            ]
        ];
    }

    /**
     * Handle auto-save functionality
     * 
     * @return array Response data
     */
    private function handleAutoSave(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $autoSaveData = $_POST['auto_save_data'] ?? [];

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Update auto-save data
        $conversationState['auto_save'] = array_merge(
            $conversationState['auto_save'] ?? [],
            $autoSaveData
        );
        $conversationState['last_auto_save'] = current_time('timestamp');

        $this->saveSessionData($sessionId, $conversationState);

        $this->logger->debug('Auto-save completed', [
            'session_id' => $sessionId,
            'data_size' => strlen(json_encode($autoSaveData))
        ]);

        return [
            'success' => true,
            'data' => [
                'saved_at' => current_time('c'),
                'size' => strlen(json_encode($autoSaveData)),
                'next_save_in' => 30 // seconds
            ]
        ];
    }

    /**
     * Handle ping for connection monitoring
     * 
     * @return array Response data
     */
    private function handlePing(): array
    {
        return [
            'success' => true,
            'data' => [
                'status' => 'online',
                'timestamp' => current_time('c'),
                'server_time' => time(),
                'user_id' => get_current_user_id()
            ]
        ];
    }

    /**
     * Handle AI response generation with streaming support
     * 
     * @return array Response data
     */
    private function handleGenerateResponse(): array
    {
        $message = $this->sanitizeInput($_POST['message'] ?? '', 'textarea');
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $context = $_POST['context'] ?? [];
        $streaming = $_POST['streaming'] === 'true';

        if (empty($message)) {
            throw new \Exception('Message is required');
        }

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        // Set generation flag for streaming
        if ($streaming) {
            $conversationState['is_generating'] = true;
            $conversationState['generation_progress'] = ['stage' => 'starting', 'percentage' => 0];
            $this->saveSessionData($sessionId, $conversationState);
        }

        try {
            // Restore conversation context
            $this->restoreConversationContext($conversationState);

            // Process message with progress tracking
            $response = $this->courseGenerator->processMessage($message, $context, [
                'streaming' => $streaming,
                'progress_callback' => $streaming ? function($progress) use ($sessionId) {
                    $this->updateGenerationProgress($sessionId, $progress);
                } : null
            ]);

            // Update conversation state
            $conversationState['is_generating'] = false;
            $conversationState['generation_progress'] = ['stage' => 'completed', 'percentage' => 100];
            $this->saveSessionData($sessionId, $this->courseGenerator->getConversationState());

            // Add response to stream
            if ($streaming) {
                $this->addStreamUpdate($sessionId, [
                    'type' => 'ai_response',
                    'message' => $response['message'] ?? '',
                    'course_updates' => $response['course'] ?? null,
                    'timestamp' => current_time('c')
                ]);
            }

            return [
                'success' => true,
                'data' => $response,
                'streaming' => $streaming,
                'timestamp' => current_time('c')
            ];

        } catch (\Exception $e) {
            // Clear generation flag on error
            $conversationState['is_generating'] = false;
            $conversationState['generation_error'] = $e->getMessage();
            $this->saveSessionData($sessionId, $conversationState);
            
            throw $e;
        }
    }

    /**
     * Handle getting contextual suggestions
     * 
     * @return array Response data
     */
    private function handleGetSuggestions(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $suggestionType = $this->sanitizeInput($_POST['suggestion_type'] ?? 'general');
        $context = $_POST['context'] ?? [];

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $suggestions = $this->generateSuggestions($conversationState, $suggestionType, $context);

        return [
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
                'suggestion_type' => $suggestionType,
                'context' => $context,
                'generated_at' => current_time('c')
            ]
        ];
    }

    /**
     * Handle learning path generation
     * 
     * @return array Response data
     */
    private function handleGetLearningPath(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $pathType = $this->sanitizeInput($_POST['path_type'] ?? 'linear');

        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }

        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            throw new \Exception('Invalid or expired session');
        }

        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            throw new \Exception('No course data available');
        }

        $learningPath = $this->generateLearningPath($generatedCourse, $pathType);

        return [
            'success' => true,
            'data' => [
                'learning_path' => $learningPath,
                'path_type' => $pathType,
                'course_title' => $generatedCourse->getTitle(),
                'generated_at' => current_time('c')
            ]
        ];
    }

    // ==================================================================================
    // Enhanced Preview System Helper Methods
    // ==================================================================================

    /**
     * Get stream updates since last poll
     * 
     * @param string $sessionId Session ID
     * @param string $lastUpdateId Last update ID
     * @param string $streamType Stream type filter
     * @return array Updates
     */
    private function getStreamUpdates(string $sessionId, string $lastUpdateId, string $streamType): array
    {
        $streamKey = "mpcc_stream_{$sessionId}";
        $updates = get_transient($streamKey) ?: [];
        
        if ($lastUpdateId) {
            // Find the index of the last update and return only newer ones
            $lastIndex = -1;
            foreach ($updates as $index => $update) {
                if ($update['id'] === $lastUpdateId) {
                    $lastIndex = $index;
                    break;
                }
            }
            
            if ($lastIndex >= 0) {
                $updates = array_slice($updates, $lastIndex + 1);
            }
        }

        // Filter by stream type if specified
        if ($streamType && $streamType !== 'all') {
            $updates = array_filter($updates, function($update) use ($streamType) {
                return ($update['stream_type'] ?? 'general') === $streamType;
            });
        }

        return array_values($updates);
    }

    /**
     * Add update to stream
     * 
     * @param string $sessionId Session ID
     * @param array $update Update data
     * @return void
     */
    private function addStreamUpdate(string $sessionId, array $update): void
    {
        $streamKey = "mpcc_stream_{$sessionId}";
        $updates = get_transient($streamKey) ?: [];
        
        $update['id'] = uniqid('update_', true);
        $update['timestamp'] = current_time('c');
        
        $updates[] = $update;
        
        // Keep only last 50 updates
        $updates = array_slice($updates, -50);
        
        set_transient($streamKey, $updates, HOUR_IN_SECONDS);
    }

    /**
     * Update specific course element
     * 
     * @param array &$conversationState Conversation state (by reference)
     * @param string $elementType Element type
     * @param string $elementId Element ID
     * @param array $updates Update data
     * @return array Result
     */
    private function updateCourseElement(array &$conversationState, string $elementType, string $elementId, array $updates): array
    {
        $generatedCourse = $conversationState['generated_course'] ?? null;
        if (!$generatedCourse) {
            return ['success' => false, 'error' => 'No course data available'];
        }

        $updatedFields = [];

        try {
            switch ($elementType) {
                case 'course':
                    foreach ($updates as $field => $value) {
                        $method = 'set' . ucfirst($field);
                        if (method_exists($generatedCourse, $method)) {
                            $generatedCourse->$method($this->sanitizeInput($value, $field === 'description' ? 'textarea' : 'text'));
                            $updatedFields[] = $field;
                        }
                    }
                    break;

                case 'section':
                    $sections = $generatedCourse->getSections();
                    $sectionIndex = (int) $elementId;
                    if (isset($sections[$sectionIndex])) {
                        $section = $sections[$sectionIndex];
                        foreach ($updates as $field => $value) {
                            $method = 'set' . ucfirst($field);
                            if (method_exists($section, $method)) {
                                $section->$method($this->sanitizeInput($value, $field === 'description' ? 'textarea' : 'text'));
                                $updatedFields[] = $field;
                            }
                        }
                    }
                    break;

                case 'lesson':
                    list($sectionIndex, $lessonIndex) = explode('.', $elementId);
                    $sections = $generatedCourse->getSections();
                    if (isset($sections[(int)$sectionIndex])) {
                        $lessons = $sections[(int)$sectionIndex]->getLessons();
                        if (isset($lessons[(int)$lessonIndex])) {
                            $lesson = $lessons[(int)$lessonIndex];
                            foreach ($updates as $field => $value) {
                                $method = 'set' . ucfirst($field);
                                if (method_exists($lesson, $method)) {
                                    $lesson->$method($this->sanitizeInput($value, $field === 'content' ? 'textarea' : 'text'));
                                    $updatedFields[] = $field;
                                }
                            }
                        }
                    }
                    break;
            }

            $conversationState['generated_course'] = $generatedCourse;
            $conversationState['last_modified'] = current_time('timestamp');

            return ['success' => true, 'updated_fields' => $updatedFields];

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update generation progress for streaming
     * 
     * @param string $sessionId Session ID
     * @param array $progress Progress data
     * @return void
     */
    private function updateGenerationProgress(string $sessionId, array $progress): void
    {
        $conversationState = $this->getSessionData($sessionId);
        if ($conversationState) {
            $conversationState['generation_progress'] = $progress;
            $this->saveSessionData($sessionId, $conversationState);
            
            // Add progress update to stream
            $this->addStreamUpdate($sessionId, [
                'type' => 'generation_progress',
                'progress' => $progress,
                'stream_type' => 'progress'
            ]);
        }
    }

    /**
     * Generate contextual suggestions
     * 
     * @param array $conversationState Conversation state
     * @param string $suggestionType Suggestion type
     * @param array $context Context data
     * @return array Suggestions
     */
    private function generateSuggestions(array $conversationState, string $suggestionType, array $context): array
    {
        $suggestions = [];
        $generatedCourse = $conversationState['generated_course'] ?? null;

        switch ($suggestionType) {
            case 'quick_replies':
                $suggestions = [
                    'Add more sections',
                    'Improve lesson content',
                    'Add assessments',
                    'Include resources',
                    'Review structure'
                ];
                break;

            case 'content_improvement':
                if ($generatedCourse) {
                    $qualityCheck = $this->performQualityCheck($generatedCourse);
                    $suggestions = $qualityCheck['suggestions'] ?? [];
                }
                break;

            case 'next_steps':
                $currentState = $conversationState['current_state'] ?? '';
                $suggestions = $this->getNextStepSuggestions($currentState);
                break;

            case 'template_specific':
                $selectedTemplate = $conversationState['selected_template'] ?? null;
                if ($selectedTemplate) {
                    $suggestions = $this->getTemplateSuggestions($selectedTemplate);
                }
                break;
        }

        return $suggestions;
    }

    /**
     * Generate learning path visualization data
     * 
     * @param GeneratedCourse $course Generated course
     * @param string $pathType Path type
     * @return array Learning path data
     */
    private function generateLearningPath(GeneratedCourse $course, string $pathType): array
    {
        $sections = $course->getSections();
        $pathData = [
            'nodes' => [],
            'connections' => [],
            'metadata' => [
                'total_sections' => count($sections),
                'path_type' => $pathType,
                'estimated_duration' => $this->calculateEstimatedTime($course)
            ]
        ];

        foreach ($sections as $index => $section) {
            $node = [
                'id' => "section_{$index}",
                'type' => 'section',
                'title' => $section->getTitle(),
                'description' => $section->getDescription(),
                'lessons_count' => count($section->getLessons()),
                'position' => ['x' => $index * 200, 'y' => 100]
            ];

            $pathData['nodes'][] = $node;

            // Add connection to next section
            if ($index < count($sections) - 1) {
                $pathData['connections'][] = [
                    'from' => "section_{$index}",
                    'to' => "section_" . ($index + 1),
                    'type' => 'sequential'
                ];
            }
        }

        return $pathData;
    }

    /**
     * Calculate engagement metrics
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Engagement metrics
     */
    private function calculateEngagementMetrics(GeneratedCourse $course): array
    {
        $totalLessons = 0;
        $interactiveLessons = 0;
        $assessments = 0;
        $resources = 0;

        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $totalLessons++;
                
                $lessonType = $lesson->getType() ?? 'video';
                if (in_array($lessonType, ['quiz', 'assignment', 'discussion'])) {
                    $interactiveLessons++;
                }
                
                if ($lessonType === 'quiz' || $lessonType === 'assignment') {
                    $assessments++;
                }
            }
        }

        $interactivityRatio = $totalLessons > 0 ? ($interactiveLessons / $totalLessons) * 100 : 0;

        return [
            'total_lessons' => $totalLessons,
            'interactive_lessons' => $interactiveLessons,
            'interactivity_ratio' => round($interactivityRatio, 1),
            'assessments_count' => $assessments,
            'resources_count' => $resources,
            'engagement_score' => $this->calculateEngagementScore($interactivityRatio, $assessments, $resources)
        ];
    }

    /**
     * Calculate engagement score
     * 
     * @param float $interactivityRatio Interactivity ratio
     * @param int $assessments Number of assessments
     * @param int $resources Number of resources
     * @return int Engagement score (0-100)
     */
    private function calculateEngagementScore(float $interactivityRatio, int $assessments, int $resources): int
    {
        $score = 0;
        
        // Base score from interactivity
        $score += min($interactivityRatio, 50);
        
        // Bonus for assessments
        $score += min($assessments * 10, 30);
        
        // Bonus for resources
        $score += min($resources * 5, 20);
        
        return min(round($score), 100);
    }

    /**
     * Generate learning path metrics
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Learning path metrics
     */
    private function generateLearningPathMetrics(GeneratedCourse $course): array
    {
        $sections = $course->getSections();
        $pathMetrics = [
            'complexity_score' => 0,
            'progression_difficulty' => 'linear',
            'knowledge_dependencies' => [],
            'learning_outcomes_alignment' => 0
        ];

        // Calculate complexity based on section and lesson count
        $totalSections = count($sections);
        $totalLessons = array_sum(array_map(function($section) {
            return count($section->getLessons());
        }, $sections));

        $pathMetrics['complexity_score'] = min(($totalSections * 10 + $totalLessons * 5), 100);

        // Analyze progression difficulty
        $lessonCounts = array_map(function($section) {
            return count($section->getLessons());
        }, $sections);

        $variance = $this->calculateVariance($lessonCounts);
        $pathMetrics['progression_difficulty'] = $variance < 2 ? 'linear' : ($variance < 5 ? 'moderate' : 'complex');

        return $pathMetrics;
    }

    /**
     * Calculate variance for array of numbers
     * 
     * @param array $numbers Array of numbers
     * @return float Variance
     */
    private function calculateVariance(array $numbers): float
    {
        if (empty($numbers)) {
            return 0;
        }

        $mean = array_sum($numbers) / count($numbers);
        $squaredDiffs = array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $numbers);

        return array_sum($squaredDiffs) / count($numbers);
    }

    /**
     * Handle save lesson content
     * 
     * @return array Response data
     */
    private function handleSaveLessonContent(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sectionId = $this->sanitizeInput($_POST['section_id'] ?? '');
        $lessonId = $this->sanitizeInput($_POST['lesson_id'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        
        if (empty($sessionId) || empty($sectionId) || empty($lessonId)) {
            throw new \Exception('Session ID, section ID, and lesson ID are required');
        }
        
        // Validate session exists
        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            $this->logger->warning('Invalid session for save lesson content', ['session_id' => $sessionId]);
            // Don't throw exception, just create the session if it doesn't exist
            // This allows drafts to work even if the main session is missing
        }
        
        // Create draft service and save
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $saved = $draftService->saveDraft($sessionId, $sectionId, $lessonId, $content);
        
        if (!$saved) {
            throw new \Exception('Failed to save lesson content');
        }
        
        $this->logger->info('Lesson content saved', [
            'session_id' => $sessionId,
            'lesson_id' => $lessonId
        ]);
        
        return [
            'success' => true,
            'message' => 'Content saved successfully'
        ];
    }
    
    /**
     * Handle load lesson content
     * 
     * @return array Response data
     */
    private function handleLoadLessonContent(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sectionId = $this->sanitizeInput($_POST['section_id'] ?? '');
        $lessonId = $this->sanitizeInput($_POST['lesson_id'] ?? '');
        
        if (empty($sessionId) || empty($sectionId) || empty($lessonId)) {
            throw new \Exception('Session ID, section ID, and lesson ID are required');
        }
        
        // Load draft content
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $draft = $draftService->getDraft($sessionId, $sectionId, $lessonId);
        
        return [
            'success' => true,
            'content' => $draft ? $draft->content : '',
            'has_content' => !empty($draft)
        ];
    }
    
    /**
     * Handle generate lesson content
     * 
     * @return array Response data
     */
    private function handleGenerateLessonContent(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sectionId = $this->sanitizeInput($_POST['section_id'] ?? '');
        $lessonId = $this->sanitizeInput($_POST['lesson_id'] ?? '');
        $lessonTitle = $this->sanitizeInput($_POST['lesson_title'] ?? '');
        $sectionTitle = $this->sanitizeInput($_POST['section_title'] ?? '');
        $courseTitle = $this->sanitizeInput($_POST['course_title'] ?? '');
        
        if (empty($lessonTitle)) {
            throw new \Exception('Lesson title is required');
        }
        
        try {
            // Generate content using LLM service
            $llmService = new \MemberPressCoursesCopilot\Services\LLMService();
            $content = $llmService->generateLessonContent(
                $sectionTitle,
                1, // Default lesson number
                [
                    'lesson_title' => $lessonTitle,
                    'course_title' => $courseTitle,
                    'difficulty_level' => 'beginner',
                    'target_audience' => 'general learners'
                ]
            );
            
            // Ensure content is not empty
            if (empty($content)) {
                throw new \Exception('Generated content is empty');
            }
            
            // Save the generated content
            if (!empty($sessionId) && !empty($sectionId) && !empty($lessonId)) {
                $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
                $draftService->saveDraft($sessionId, $sectionId, $lessonId, $content);
            }
            
            $this->logger->info('Lesson content generated', [
                'lesson_title' => $lessonTitle,
                'content_length' => strlen($content),
                'first_100_chars' => substr($content, 0, 100)
            ]);
            
            return [
                'success' => true,
                'content' => $content,
                'content_length' => strlen($content)
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate lesson content', [
                'error' => $e->getMessage(),
                'lesson_title' => $lessonTitle
            ]);
            
            throw new \Exception('Failed to generate content: ' . $e->getMessage());
        }
    }
    
    /**
     * Handle reorder course items
     * 
     * @return array Response data
     */
    private function handleReorderCourseItems(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sectionId = $this->sanitizeInput($_POST['section_id'] ?? '');
        $lessonOrders = $_POST['lesson_orders'] ?? [];
        
        if (empty($sessionId) || empty($sectionId) || empty($lessonOrders)) {
            throw new \Exception('Session ID, section ID, and lesson orders are required');
        }
        
        // Update order in database
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $updated = $draftService->updateOrder($sessionId, $sectionId, $lessonOrders);
        
        if (!$updated) {
            throw new \Exception('Failed to update lesson order');
        }
        
        $this->logger->info('Lesson order updated', [
            'session_id' => $sessionId,
            'section_id' => $sectionId
        ]);
        
        return [
            'success' => true,
            'message' => 'Order updated successfully'
        ];
    }
    
    /**
     * Handle delete course item
     * 
     * @return array Response data
     */
    private function handleDeleteCourseItem(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $sectionId = $this->sanitizeInput($_POST['section_id'] ?? '');
        $lessonId = $this->sanitizeInput($_POST['lesson_id'] ?? '');
        
        if (empty($sessionId) || empty($sectionId) || empty($lessonId)) {
            throw new \Exception('Session ID, section ID, and lesson ID are required');
        }
        
        // Delete draft
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $deleted = $draftService->deleteDraft($sessionId, $sectionId, $lessonId);
        
        if (!$deleted) {
            throw new \Exception('Failed to delete lesson');
        }
        
        $this->logger->info('Lesson deleted', [
            'session_id' => $sessionId,
            'lesson_id' => $lessonId
        ]);
        
        return [
            'success' => true,
            'message' => 'Lesson deleted successfully'
        ];
    }
    
    /**
     * Handle load all drafts
     * 
     * @return array Response data
     */
    private function handleLoadAllDrafts(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        
        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }
        
        // Validate session exists
        $conversationState = $this->getSessionData($sessionId);
        if (!$conversationState) {
            $this->logger->warning('Invalid session for load all drafts', ['session_id' => $sessionId]);
            // Don't throw exception, allow loading drafts even if main session is missing
            // This supports draft persistence across sessions
        }
        
        // Load all drafts for session
        $draftService = new \MemberPressCoursesCopilot\Services\LessonDraftService();
        $drafts = $draftService->getSessionDrafts($sessionId);
        
        // Convert to associative array for easy access
        $draftsMap = [];
        foreach ($drafts as $draft) {
            $key = $draft->section_id . '::' . $draft->lesson_id;
            $draftsMap[$key] = $draft->content;
        }
        
        return [
            'success' => true,
            'drafts' => $draftsMap,
            'count' => count($drafts)
        ];
    }
    
    /**
     * Handle save conversation
     * 
     * @return array Response data
     */
    private function handleSaveConversation(): array
    {
        $sessionId = $this->sanitizeInput($_POST['session_id'] ?? '');
        $conversationHistory = $_POST['conversation_history'] ?? [];
        $conversationState = $_POST['conversation_state'] ?? [];
        
        if (empty($sessionId)) {
            throw new \Exception('Session ID is required');
        }
        
        // Create or update session data
        $sessionData = [
            'session_id' => $sessionId,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('timestamp'),
            'updated_at' => current_time('timestamp'),
            'conversation_history' => $conversationHistory,
            'conversation_state' => $conversationState,
            'status' => 'active'
        ];
        
        // Check if session already exists
        $existingSession = $this->getSessionData($sessionId);
        if ($existingSession) {
            // Update existing session, preserving created_at
            $sessionData['created_at'] = $existingSession['created_at'] ?? current_time('timestamp');
        }
        
        // Save session data
        $this->saveSessionData($sessionId, $sessionData);
        
        $this->logger->info('Conversation saved', [
            'session_id' => $sessionId,
            'history_count' => count($conversationHistory)
        ]);
        
        return [
            'success' => true,
            'session_id' => $sessionId,
            'message' => 'Conversation saved successfully'
        ];
    }
}