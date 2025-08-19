<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Controllers\BaseController;
use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Utilities\Logger;

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
     * Logger instance
     * 
     * @var Logger
     */
    private Logger $logger;

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
        'mpcc_process_bulk_action'
    ];

    /**
     * Constructor
     * 
     * @param CourseGeneratorService $courseGenerator Course generator service
     * @param LLMService $llmService LLM service
     * @param Logger $logger Logger instance
     */
    public function __construct(
        CourseGeneratorService $courseGenerator,
        LLMService $llmService,
        Logger $logger
    ) {
        $this->courseGenerator = $courseGenerator;
        $this->llmService = $llmService;
        $this->logger = $logger;
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

            $this->logger->debug('AJAX request received', [
                'action' => $action,
                'user_id' => get_current_user_id(),
                'session_id' => $_POST['session_id'] ?? null
            ]);

            // Route to appropriate handler
            $response = $this->routeAjaxAction($action);

            $this->sendSuccessResponse($response);

        } catch (\Exception $e) {
            $this->logger->error('AJAX request failed', [
                'action' => $_POST['action'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
        $this->logger->warning('Unauthorized AJAX request', [
            'action' => $_POST['action'] ?? 'unknown',
            'ip' => $this->getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        $this->sendErrorResponse('You must be logged in to perform this action.', 401);
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

        // Create the WordPress course
        $this->restoreConversationContext($conversationState);
        $courseId = $this->courseGenerator->createWordPressCourse($generatedCourse);

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
     * Verify AJAX nonce
     * 
     * @throws \Exception If nonce is invalid
     */
    private function verifyAjaxNonce(): void
    {
        $nonce = $_POST['nonce'] ?? $_POST['_wpnonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'mpcc_ajax_nonce')) {
            throw new \Exception('Security check failed');
        }
    }

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
}