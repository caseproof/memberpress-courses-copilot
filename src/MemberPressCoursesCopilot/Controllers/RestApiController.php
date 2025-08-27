<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\CourseGeneratorService;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Models\CourseTemplate;
use MemberPressCoursesCopilot\Models\GeneratedCourse;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Security\NonceConstants;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API Controller
 * 
 * NOTE: This controller is currently not in use. REST API functionality may be
 * implemented in the future. Currently, all API interactions are handled through
 * AJAX endpoints in SimpleAjaxController and CourseAjaxService.
 * 
 * This class is retained for potential future REST API implementation.
 * 
 * @deprecated Consider removing if REST API is not needed
 * @package MemberPressCoursesCopilot\Controllers
 * @since 1.0.0
 */
class RestApiController
{
    /**
     * API namespace
     */
    const NAMESPACE = 'mpcc/v1';

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
     * Session storage for conversations
     * 
     * @var array
     */
    private array $sessionStorage = [];

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
        $this->loadSessionStorage();
    }

    /**
     * Register WordPress hooks
     * 
     * @return void
     */
    public function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        add_action('init', [$this, 'initializeSession']);
    }

    /**
     * Initialize session handling
     * 
     * @return void
     */
    public function initializeSession(): void
    {
        if (!session_id() && !headers_sent()) {
            session_start();
        }
    }

    /**
     * Register REST API routes
     * 
     * @return void
     */
    public function registerRoutes(): void
    {
        // Conversation endpoint
        register_rest_route(self::NAMESPACE, '/conversation', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleConversation'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => $this->getConversationArgs(),
        ]);

        // Generate course endpoint
        register_rest_route(self::NAMESPACE, '/generate-course', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generateCourse'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => $this->generateCourseArgs(),
        ]);

        // Templates endpoint
        register_rest_route(self::NAMESPACE, '/templates', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getTemplates'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => $this->getTemplatesArgs(),
        ]);

        // Patterns endpoint
        register_rest_route(self::NAMESPACE, '/patterns', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getPatterns'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => $this->getPatternsArgs(),
        ]);

        // Validate course endpoint
        register_rest_route(self::NAMESPACE, '/validate-course', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateCourse'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => $this->getValidateCourseArgs(),
        ]);

        // Session management endpoint
        register_rest_route(self::NAMESPACE, '/session/(?P<session_id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSession'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // Delete session endpoint
        register_rest_route(self::NAMESPACE, '/session/(?P<session_id>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteSession'],
            'permission_callback' => [$this, 'checkPermissions'],
            'args' => [
                'session_id' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    /**
     * Handle conversation endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function handleConversation(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $this->logger->info('Conversation request received', [
                'user_id' => get_current_user_id(),
                'params' => $request->get_params()
            ]);

            $message = $request->get_param('message');
            $sessionId = $request->get_param('session_id');
            $action = $request->get_param('action') ?? 'message';

            // Handle different conversation actions
            switch ($action) {
                case 'start':
                    $response = $this->startNewConversation($request);
                    break;

                case 'message':
                    $response = $this->processConversationMessage($sessionId, $message, $request);
                    break;

                case 'template_select':
                    $response = $this->handleTemplateSelection($sessionId, $request);
                    break;

                default:
                    return new WP_Error(
                        'invalid_action',
                        'Invalid conversation action',
                        ['status' => 400]
                    );
            }

            $this->logger->info('Conversation response generated', [
                'session_id' => $response['session_id'] ?? null,
                'state' => $response['state'] ?? null
            ]);

            return new WP_REST_Response($response, 200);

        } catch (\Exception $e) {
            $this->logger->error('Conversation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'conversation_error',
                'Failed to process conversation: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Generate course endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function generateCourse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $this->logger->info('Course generation request received', [
                'user_id' => get_current_user_id(),
                'params' => $request->get_params()
            ]);

            $sessionId = $request->get_param('session_id');
            $action = $request->get_param('action') ?? 'create';

            if (!$sessionId) {
                return new WP_Error(
                    'missing_session',
                    'Session ID is required',
                    ['status' => 400]
                );
            }

            $conversation = $this->getConversationData($sessionId);
            if (!$conversation) {
                return new WP_Error(
                    'invalid_session',
                    'Invalid or expired session',
                    ['status' => 404]
                );
            }

            switch ($action) {
                case 'preview':
                    $response = $this->generateCoursePreview($conversation);
                    break;

                case 'create':
                    $response = $this->createWordPressCourse($conversation);
                    break;

                case 'export':
                    $response = $this->exportCourseData($conversation);
                    break;

                default:
                    return new WP_Error(
                        'invalid_action',
                        'Invalid generation action',
                        ['status' => 400]
                    );
            }

            $this->logger->info('Course generation completed', [
                'session_id' => $sessionId,
                'action' => $action,
                'success' => true
            ]);

            return new WP_REST_Response($response, 200);

        } catch (\Exception $e) {
            $this->logger->error('Course generation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return new WP_Error(
                'generation_error',
                'Failed to generate course: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get templates endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function getTemplates(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $templateType = $request->get_param('type');
            $includeStructure = $request->get_param('include_structure') === 'true';

            $templates = CourseTemplate::getPredefinedTemplates();

            if ($templateType) {
                if (!isset($templates[$templateType])) {
                    return new WP_Error(
                        'template_not_found',
                        'Template type not found',
                        ['status' => 404]
                    );
                }
                $templates = [$templateType => $templates[$templateType]];
            }

            $response = [];
            foreach ($templates as $type => $template) {
                $templateData = [
                    'type' => $type,
                    'title' => ucfirst($type) . ' Course',
                    'description' => $this->getTemplateDescription($type),
                    'lesson_count' => $template->getTotalLessons(),
                    'suggested_questions' => $template->getSuggestedQuestions(),
                ];

                if ($includeStructure) {
                    $templateData['structure'] = $template->getDefaultStructure();
                    $templateData['quality_checks'] = $template->getQualityChecks();
                }

                $response[$type] = $templateData;
            }

            $this->logger->debug('Templates retrieved', [
                'template_type' => $templateType,
                'count' => count($response)
            ]);

            return new WP_REST_Response([
                'templates' => $response,
                'total' => count($response)
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('Templates retrieval error', [
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'templates_error',
                'Failed to retrieve templates: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get patterns endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function getPatterns(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $category = $request->get_param('category');
            
            $patterns = $this->getCoursePatterns();

            if ($category) {
                $patterns = array_filter($patterns, function($pattern) use ($category) {
                    return $pattern['category'] === $category;
                });
            }

            $this->logger->debug('Patterns retrieved', [
                'category' => $category,
                'count' => count($patterns)
            ]);

            return new WP_REST_Response([
                'patterns' => array_values($patterns),
                'total' => count($patterns),
                'categories' => $this->getPatternCategories()
            ], 200);

        } catch (\Exception $e) {
            $this->logger->error('Patterns retrieval error', [
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'patterns_error',
                'Failed to retrieve patterns: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Validate course endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function validateCourse(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $courseData = $request->get_param('course_data');
            $validationType = $request->get_param('validation_type') ?? 'basic';

            if (!$courseData) {
                return new WP_Error(
                    'missing_course_data',
                    'Course data is required',
                    ['status' => 400]
                );
            }

            $validation = $this->performCourseValidation($courseData, $validationType);

            $this->logger->info('Course validation completed', [
                'validation_type' => $validationType,
                'is_valid' => $validation['is_valid'],
                'error_count' => count($validation['errors']),
                'warning_count' => count($validation['warnings'])
            ]);

            return new WP_REST_Response($validation, 200);

        } catch (\Exception $e) {
            $this->logger->error('Course validation error', [
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'validation_error',
                'Failed to validate course: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get session endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function getSession(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $sessionId = $request->get_param('session_id');
            $conversation = $this->getConversationData($sessionId);

            if (!$conversation) {
                return new WP_Error(
                    'session_not_found',
                    'Session not found',
                    ['status' => 404]
                );
            }

            // Remove sensitive data
            $publicData = [
                'session_id' => $conversation['session_id'],
                'current_state' => $conversation['current_state'],
                'created_at' => $conversation['created_at'],
                'updated_at' => $conversation['updated_at'],
                'selected_template' => $conversation['selected_template']?->toArray() ?? null,
                'requirements' => $conversation['requirements'] ?? [],
                'generated_course' => $conversation['generated_course']?->toArray() ?? null,
            ];

            return new WP_REST_Response($publicData, 200);

        } catch (\Exception $e) {
            $this->logger->error('Session retrieval error', [
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'session_error',
                'Failed to retrieve session: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete session endpoint
     * 
     * @param WP_REST_Request $request REST request
     * @return WP_REST_Response|WP_Error Response or error
     */
    public function deleteSession(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $sessionId = $request->get_param('session_id');
            
            if ($this->deleteConversationData($sessionId)) {
                $this->logger->info('Session deleted', ['session_id' => $sessionId]);
                
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Session deleted successfully'
                ], 200);
            } else {
                return new WP_Error(
                    'session_not_found',
                    'Session not found',
                    ['status' => 404]
                );
            }

        } catch (\Exception $e) {
            $this->logger->error('Session deletion error', [
                'error' => $e->getMessage()
            ]);

            return new WP_Error(
                'session_error',
                'Failed to delete session: ' . $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Check permissions for API access
     * 
     * @param WP_REST_Request $request REST request
     * @return bool|WP_Error True if permitted, error otherwise
     */
    public function checkPermissions(WP_REST_Request $request): bool|WP_Error
    {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                'You must be logged in to access this endpoint',
                ['status' => 401]
            );
        }

        // Check for required capability
        if (!$this->userCan('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                'You do not have permission to access this endpoint',
                ['status' => 403]
            );
        }

        // Verify nonce for POST requests
        if (in_array($request->get_method(), ['POST', 'PUT', 'DELETE'])) {
            $nonce = $request->get_header('X-WP-Nonce') ?? $request->get_param('_wpnonce');
            
            if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
                return new WP_Error(
                    'rest_forbidden',
                    'Invalid nonce',
                    ['status' => 403]
                );
            }
        }

        return true;
    }

    /**
     * Start new conversation
     * 
     * @param WP_REST_Request $request REST request
     * @return array Conversation response
     */
    private function startNewConversation(WP_REST_Request $request): array
    {
        $initialData = [
            'user_id' => get_current_user_id(),
            'ip_address' => $this->getClientIp(),
            'user_agent' => $request->get_header('User-Agent'),
        ];

        $response = $this->courseGenerator->startConversation($initialData);
        $this->saveConversationData($response['session_id'], $this->courseGenerator->getConversationState());

        return $response;
    }

    /**
     * Process conversation message
     * 
     * @param string $sessionId Session ID
     * @param string $message User message
     * @param WP_REST_Request $request REST request
     * @return array Conversation response
     */
    private function processConversationMessage(string $sessionId, string $message, WP_REST_Request $request): array
    {
        if (!$sessionId) {
            throw new \Exception('Session ID is required for message processing');
        }

        $conversation = $this->getConversationData($sessionId);
        if (!$conversation) {
            throw new \Exception('Invalid or expired session');
        }

        // Restore conversation state
        $this->restoreConversationState($conversation);

        $context = [
            'user_id' => get_current_user_id(),
            'timestamp' => current_time('timestamp'),
            'additional_data' => $request->get_param('context') ?? []
        ];

        $response = $this->courseGenerator->processMessage($message, $context);
        $this->saveConversationData($sessionId, $this->courseGenerator->getConversationState());

        return $response;
    }

    /**
     * Handle template selection
     * 
     * @param string $sessionId Session ID
     * @param WP_REST_Request $request REST request
     * @return array Template selection response
     */
    private function handleTemplateSelection(string $sessionId, WP_REST_Request $request): array
    {
        $templateType = $request->get_param('template_type');
        
        if (!$templateType) {
            throw new \Exception('Template type is required');
        }

        $conversation = $this->getConversationData($sessionId);
        if (!$conversation) {
            throw new \Exception('Invalid or expired session');
        }

        $this->restoreConversationState($conversation);
        
        $response = $this->courseGenerator->processMessage($templateType, [
            'action' => 'template_select',
            'template_type' => $templateType
        ]);

        $this->saveConversationData($sessionId, $this->courseGenerator->getConversationState());

        return $response;
    }

    /**
     * Generate course preview
     * 
     * @param array $conversation Conversation data
     * @return array Preview response
     */
    private function generateCoursePreview(array $conversation): array
    {
        $this->restoreConversationState($conversation);
        $generatedCourse = $conversation['generated_course'] ?? null;

        if (!$generatedCourse) {
            throw new \Exception('No course generated in this session');
        }

        return [
            'preview' => $generatedCourse->toArray(),
            'estimated_duration' => $this->calculateCourseDuration($generatedCourse),
            'content_statistics' => $this->generateContentStatistics($generatedCourse),
            'quality_score' => $this->calculateQualityScore($generatedCourse)
        ];
    }

    /**
     * Create WordPress course
     * 
     * @param array $conversation Conversation data
     * @return array Creation response
     */
    private function createWordPressCourse(array $conversation): array
    {
        $this->restoreConversationState($conversation);
        $generatedCourse = $conversation['generated_course'] ?? null;

        if (!$generatedCourse) {
            throw new \Exception('No course generated in this session');
        }

        $courseId = $this->courseGenerator->createWordPressCourse($generatedCourse);

        return [
            'course_id' => $courseId,
            'course_url' => admin_url("post.php?post={$courseId}&action=edit"),
            'edit_url' => admin_url("post.php?post={$courseId}&action=edit"),
            'preview_url' => get_permalink($courseId),
            'success' => true,
            'message' => 'Course created successfully'
        ];
    }

    /**
     * Export course data
     * 
     * @param array $conversation Conversation data
     * @return array Export response
     */
    private function exportCourseData(array $conversation): array
    {
        $generatedCourse = $conversation['generated_course'] ?? null;

        if (!$generatedCourse) {
            throw new \Exception('No course generated in this session');
        }

        $exportData = [
            'course' => $generatedCourse->toArray(),
            'metadata' => [
                'exported_at' => current_time('c'),
                'exported_by' => get_current_user_id(),
                'plugin_version' => MEMBERPRESS_COURSES_COPILOT_VERSION ?? '1.0.0'
            ]
        ];

        return [
            'export_data' => $exportData,
            'download_url' => $this->generateExportDownloadUrl($exportData),
            'format' => 'json'
        ];
    }

    /**
     * Perform course validation
     * 
     * @param array $courseData Course data to validate
     * @param string $validationType Type of validation
     * @return array Validation result
     */
    private function performCourseValidation(array $courseData, string $validationType): array
    {
        $errors = [];
        $warnings = [];
        $suggestions = [];

        // Basic validation
        if (empty($courseData['title'])) {
            $errors[] = 'Course title is required';
        }

        if (empty($courseData['sections']) || !is_array($courseData['sections'])) {
            $errors[] = 'Course must have at least one section';
        } else {
            foreach ($courseData['sections'] as $index => $section) {
                if (empty($section['title'])) {
                    $errors[] = "Section {$index} must have a title";
                }
                if (empty($section['lessons']) || !is_array($section['lessons'])) {
                    $errors[] = "Section {$index} must have at least one lesson";
                }
            }
        }

        // Extended validation for comprehensive type
        if ($validationType === 'comprehensive') {
            $this->performComprehensiveValidation($courseData, $errors, $warnings, $suggestions);
        }

        return [
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'validation_type' => $validationType,
            'validated_at' => current_time('c')
        ];
    }

    /**
     * Perform comprehensive course validation
     * 
     * @param array $courseData Course data
     * @param array &$errors Error array
     * @param array &$warnings Warning array
     * @param array &$suggestions Suggestion array
     * @return void
     */
    private function performComprehensiveValidation(array $courseData, array &$errors, array &$warnings, array &$suggestions): void
    {
        // Check learning objectives
        if (empty($courseData['learning_objectives'])) {
            $warnings[] = 'Course should have defined learning objectives';
        }

        // Check course description
        if (empty($courseData['description']) || strlen($courseData['description']) < 100) {
            $warnings[] = 'Course description should be more detailed (minimum 100 characters)';
        }

        // Check lesson content quality
        $totalLessons = 0;
        $lessonsWithContent = 0;

        foreach ($courseData['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $totalLessons++;
                if (!empty($lesson['content']) && strlen($lesson['content']) > 50) {
                    $lessonsWithContent++;
                }
            }
        }

        if ($lessonsWithContent < $totalLessons * 0.8) {
            $warnings[] = 'Many lessons appear to lack detailed content';
        }

        // Suggestions for improvement
        if ($totalLessons < 5) {
            $suggestions[] = 'Consider adding more lessons for comprehensive coverage';
        }

        if (empty($courseData['difficulty_level'])) {
            $suggestions[] = 'Add a difficulty level to help students understand course requirements';
        }
    }

    /**
     * Get conversation arguments for REST API
     * 
     * @return array Argument configuration
     */
    private function getConversationArgs(): array
    {
        return [
            'message' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function($value) {
                    return is_string($value) && strlen($value) <= 5000;
                }
            ],
            'session_id' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    return is_string($value) && preg_match('/^mpcc_[a-zA-Z0-9_-]+$/', $value);
                }
            ],
            'action' => [
                'required' => false,
                'type' => 'string',
                'default' => 'message',
                'enum' => ['start', 'message', 'template_select'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'template_type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['technical', 'business', 'creative', 'academic'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'context' => [
                'required' => false,
                'type' => 'object',
                'validate_callback' => function($value) {
                    return is_array($value);
                }
            ]
        ];
    }

    /**
     * Get generate course arguments for REST API
     * 
     * @return array Argument configuration
     */
    private function generateCourseArgs(): array
    {
        return [
            'session_id' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function($value) {
                    return is_string($value) && preg_match('/^mpcc_[a-zA-Z0-9_-]+$/', $value);
                }
            ],
            'action' => [
                'required' => false,
                'type' => 'string',
                'default' => 'create',
                'enum' => ['preview', 'create', 'export'],
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get templates arguments for REST API
     * 
     * @return array Argument configuration
     */
    private function getTemplatesArgs(): array
    {
        return [
            'type' => [
                'required' => false,
                'type' => 'string',
                'enum' => ['technical', 'business', 'creative', 'academic'],
                'sanitize_callback' => 'sanitize_text_field'
            ],
            'include_structure' => [
                'required' => false,
                'type' => 'string',
                'default' => 'false',
                'enum' => ['true', 'false'],
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get patterns arguments for REST API
     * 
     * @return array Argument configuration
     */
    private function getPatternsArgs(): array
    {
        return [
            'category' => [
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Get validate course arguments for REST API
     * 
     * @return array Argument configuration
     */
    private function getValidateCourseArgs(): array
    {
        return [
            'course_data' => [
                'required' => true,
                'type' => 'object',
                'validate_callback' => function($value) {
                    return is_array($value) && !empty($value);
                }
            ],
            'validation_type' => [
                'required' => false,
                'type' => 'string',
                'default' => 'basic',
                'enum' => ['basic', 'comprehensive'],
                'sanitize_callback' => 'sanitize_text_field'
            ]
        ];
    }

    /**
     * Helper methods
     */

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
                'description' => 'Gradual introduction with lots of examples and practice',
                'characteristics' => ['Small learning steps', 'Frequent practice', 'Clear explanations']
            ],
            [
                'id' => 'project_based',
                'name' => 'Project-Based Learning',
                'category' => 'methodology',
                'description' => 'Learning through building real-world projects',
                'characteristics' => ['Hands-on projects', 'Practical application', 'Portfolio building']
            ],
            [
                'id' => 'modular_design',
                'name' => 'Modular Design',
                'category' => 'structure',
                'description' => 'Independent modules that can be taken in any order',
                'characteristics' => ['Flexible learning path', 'Reusable content', 'Self-contained modules']
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
            'structure' => 'Course Structure Patterns',
            'methodology' => 'Teaching Methodology Patterns',
            'assessment' => 'Assessment Patterns',
            'engagement' => 'Student Engagement Patterns'
        ];
    }

    /**
     * Session management methods
     */

    /**
     * Load session storage
     * 
     * @return void
     */
    private function loadSessionStorage(): void
    {
        $this->sessionStorage = get_option('mpcc_session_storage', []);
    }

    /**
     * Save session storage
     * 
     * @return void
     */
    private function saveSessionStorage(): void
    {
        update_option('mpcc_session_storage', $this->sessionStorage);
    }

    /**
     * Get conversation data
     * 
     * @param string $sessionId Session ID
     * @return array|null Conversation data
     */
    private function getConversationData(string $sessionId): ?array
    {
        return $this->sessionStorage[$sessionId] ?? null;
    }

    /**
     * Save conversation data
     * 
     * @param string $sessionId Session ID
     * @param array $data Conversation data
     * @return void
     */
    private function saveConversationData(string $sessionId, array $data): void
    {
        $this->sessionStorage[$sessionId] = $data;
        $this->saveSessionStorage();
    }

    /**
     * Delete conversation data
     * 
     * @param string $sessionId Session ID
     * @return bool Success status
     */
    private function deleteConversationData(string $sessionId): bool
    {
        if (isset($this->sessionStorage[$sessionId])) {
            unset($this->sessionStorage[$sessionId]);
            $this->saveSessionStorage();
            return true;
        }
        return false;
    }

    /**
     * Restore conversation state
     * 
     * @param array $conversation Conversation data
     * @return void
     */
    private function restoreConversationState(array $conversation): void
    {
        // This would typically involve setting the CourseGeneratorService state
        // Implementation depends on how the CourseGeneratorService handles state restoration
    }

    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private function getClientIp(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    /**
     * Calculate course duration
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Duration information
     */
    private function calculateCourseDuration(GeneratedCourse $course): array
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
            'average_lesson_duration' => $lessonCount > 0 ? round($totalMinutes / $lessonCount) : 0
        ];
    }

    /**
     * Generate content statistics
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Content statistics
     */
    private function generateContentStatistics(GeneratedCourse $course): array
    {
        $totalWords = 0;
        $totalCharacters = 0;
        $sectionsCount = count($course->getSections());
        $lessonsCount = 0;

        foreach ($course->getSections() as $section) {
            foreach ($section->getLessons() as $lesson) {
                $content = $lesson->getContent();
                $totalWords += str_word_count(strip_tags($content));
                $totalCharacters += strlen(strip_tags($content));
                $lessonsCount++;
            }
        }

        return [
            'sections_count' => $sectionsCount,
            'lessons_count' => $lessonsCount,
            'total_words' => $totalWords,
            'total_characters' => $totalCharacters,
            'average_words_per_lesson' => $lessonsCount > 0 ? round($totalWords / $lessonsCount) : 0,
            'reading_time_minutes' => round($totalWords / 200) // Assuming 200 words per minute
        ];
    }

    /**
     * Calculate quality score
     * 
     * @param GeneratedCourse $course Generated course
     * @return array Quality score information
     */
    private function calculateQualityScore(GeneratedCourse $course): array
    {
        $score = 0;
        $maxScore = 100;
        $factors = [];

        // Check if course has description
        if (!empty($course->getDescription())) {
            $score += 10;
            $factors[] = 'Has description';
        }

        // Check learning objectives
        if (count($course->getLearningObjectives()) >= 3) {
            $score += 15;
            $factors[] = 'Has learning objectives';
        }

        // Check section count
        $sectionsCount = count($course->getSections());
        if ($sectionsCount >= 3 && $sectionsCount <= 8) {
            $score += 20;
            $factors[] = 'Appropriate section count';
        }

        // Check lesson content quality
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
            $score += round($contentRatio * 25);
            if ($contentRatio > 0.8) {
                $factors[] = 'Most lessons have detailed content';
            }
        }

        // Structure quality
        if ($sectionsCount > 0 && $totalLessons / $sectionsCount >= 2) {
            $score += 15;
            $factors[] = 'Good lesson distribution';
        }

        // Completeness
        if ($score >= 80) {
            $score += 15;
            $factors[] = 'Course is comprehensive';
        }

        return [
            'score' => min($score, $maxScore),
            'max_score' => $maxScore,
            'percentage' => round((min($score, $maxScore) / $maxScore) * 100),
            'factors' => $factors,
            'level' => $this->getQualityLevel(min($score, $maxScore))
        ];
    }

    /**
     * Get quality level based on score
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
     * Generate export download URL
     * 
     * @param array $exportData Export data
     * @return string Download URL
     */
    private function generateExportDownloadUrl(array $exportData): string
    {
        // In a real implementation, this would create a temporary file and return its URL
        // For now, we'll return a placeholder
        return admin_url('admin-ajax.php?action=mpcc_download_export&nonce=' . NonceConstants::create(NonceConstants::EXPORT));
    }

    /**
     * Check if current user has the specified capability
     * 
     * @param string $capability The capability to check
     * @return bool True if user has capability, false otherwise
     */
    protected function userCan(string $capability): bool
    {
        return current_user_can($capability);
    }
}