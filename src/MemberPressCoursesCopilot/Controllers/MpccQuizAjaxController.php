<?php

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Services\LLMService;
use MemberPressCoursesCopilot\Utilities\Logger;
use MemberPressCoursesCopilot\Utilities\ApiResponse;
use MemberPressCoursesCopilot\Security\NonceConstants;
use WP_Error;

/**
 * Quiz AJAX Controller for handling quiz generation requests
 *
 * @package MemberPressCoursesCopilot\Controllers
 * @since   1.0.0
 */
class MpccQuizAjaxController
{
    /**
     * @var MpccQuizAIService Service for AI-powered quiz question generation
     */
    private MpccQuizAIService $quizAIService;

    /**
     * @var Logger Logging service for error tracking and debug information
     */
    private Logger $logger;

    /**
     * Constructor - dependencies can be injected
     *
     * @param MpccQuizAIService|null $quizAIService The quiz AI service for generating quiz questions.
     * @param Logger|null            $logger        The logger instance for error and debug logging.
     *
     * @example
     * // Basic instantiation with auto-injection
     * $controller = new MpccQuizAjaxController();
     *
     * @example
     * // Dependency injection for testing
     * $mockQuizService = $this->createMock(MpccQuizAIService::class);
     * $mockLogger = $this->createMock(Logger::class);
     * $controller = new MpccQuizAjaxController($mockQuizService, $mockLogger);
     */
    public function __construct(
        ?MpccQuizAIService $quizAIService = null,
        ?Logger $logger = null
    ) {
        // Use injected dependencies or create new instances
        if ($quizAIService === null) {
            $llmService          = new LLMService();
            $this->quizAIService = new MpccQuizAIService($llmService);
        } else {
            $this->quizAIService = $quizAIService;
        }
        $this->logger = $logger ?? Logger::getInstance();
    }

    /**
     * Load hooks and register AJAX handlers
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @example
     * // Initialize AJAX handlers during plugin initialization
     * $quizController = new MpccQuizAjaxController();
     * $quizController->init();
     *
     * @example
     * // Manual hook registration (if not using init())
     * $quizController = new MpccQuizAjaxController();
     * $quizController->load_hooks();
     */
    public function init(): void
    {
        $this->load_hooks();
    }

    /**
     * Load hooks and register AJAX handlers
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function load_hooks(): void
    {

        // Register AJAX handlers for quiz generation
        add_action('wp_ajax_mpcc_generate_quiz', [$this, 'generate_quiz'], 10);
        add_action('wp_ajax_mpcc_regenerate_question', [$this, 'regenerate_question'], 10);
        add_action('wp_ajax_mpcc_validate_quiz', [$this, 'validate_quiz'], 10);
        add_action('wp_ajax_mpcc_create_quiz_from_lesson', [$this, 'create_quiz_from_lesson'], 10);
        add_action('wp_ajax_mpcc_get_lesson_course', [$this, 'get_lesson_course'], 10);
        add_action('wp_ajax_mpcc_get_course_lessons', [$this, 'get_course_lessons'], 10);

        // Also register for non-logged-in users (though they shouldn't have access)
        add_action('wp_ajax_nopriv_mpcc_generate_quiz', function () {
            wp_send_json_error('Not authorized', 401);
        });
    }

    /**
     * Handle quiz generation AJAX request
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When quiz generation fails.
     * @throws \Throwable When fatal error occurs.
     *
     * @example
     * // AJAX request from JavaScript for lesson-based quiz generation
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_generate_quiz',
     *         lesson_id: 123,
     *         nonce: mpcc_ajax.nonce,
     *         options: JSON.stringify({
     *             num_questions: 10,
     *             difficulty: 'medium',
     *             question_type: 'multiple_choice',
     *             custom_prompt: 'Focus on key concepts'
     *         })
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             console.log('Generated questions:', response.data.questions);
     *             console.log('Total questions:', response.data.total);
     *             console.log('Question type:', response.data.type);
     *         }
     *     }
     * });
     *
     * @example
     * // Direct content generation (no lesson ID)
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_generate_quiz',
     *         content: 'JavaScript is a programming language used for web development...',
     *         nonce: mpcc_ajax.nonce,
     *         options: JSON.stringify({
     *             num_questions: 5,
     *             difficulty: 'easy',
     *             question_type: 'true_false'
     *         })
     *     }
     * });
     *
     * @example
     * // Course-based generation
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_generate_quiz',
     *         course_id: 789,
     *         nonce: mpcc_ajax.nonce,
     *         options: JSON.stringify({
     *             num_questions: 15,
     *             difficulty: 'advanced',
     *             question_type: 'multiple_select'
     *         })
     *     }
     * });
     *
     * @example
     * // Error handling with suggestions
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     // ... other parameters
     *     error: function(xhr) {
     *         const response = xhr.responseJSON;
     *         if (response?.data?.suggestion) {
     *             console.log('AI Suggestion:', response.data.suggestion);
     *         }
     *         console.error('Error:', response?.data?.message || 'Unknown error');
     *     }
     * });
     */
    public function generate_quiz(): void
    {
        try {
            // Check if quiz plugin is active
            if (!defined('\memberpress\quizzes\VERSION')) {
                throw new \Exception(__('MemberPress Course Quizzes plugin is not active', 'memberpress-courses-copilot'));
            }
            // Verify nonce
            if (!$this->verifyQuizNonce()) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user permissions
            if (!$this->verifyUserPermissions()) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Extract and sanitize input data
            $inputData = $this->extractAndSanitizeInput();

            // Parse quiz options
            $options = $this->parseQuizOptions($inputData['options']);

            // Get quiz content
            $content = $this->getQuizContent(
                $inputData['content'],
                $inputData['lessonId'],
                $inputData['courseId']
            );

            if (empty($content)) {
                ApiResponse::errorMessage(
                    'No content available to generate quiz from',
                    ApiResponse::ERROR_MISSING_PARAMETER
                );
                return;
            }

            // Prepare options for quiz generation
            $generationOptions = $this->prepareGenerationOptions($options);

            // Generate quiz using the Quiz AI Service
            $result = $this->quizAIService->generateQuestions($content, $generationOptions);

            // Check if there was an error from content validation
            if (isset($result['error']) && $result['error']) {
                // Create WP_Error with suggestion as additional data
                $error = new \WP_Error(
                    ApiResponse::ERROR_INVALID_PARAMETER,
                    $result['message'] ?? 'Failed to generate questions'
                );

                // Add suggestion as error data if available
                if (!empty($result['suggestion'])) {
                    $error->add_data(['suggestion' => $result['suggestion']], ApiResponse::ERROR_INVALID_PARAMETER);
                }

                ApiResponse::error($error, 400);
                return;
            }

            // Format and send successful response
            $response = $this->formatSuccessfulQuizResponse(
                $result,
                $generationOptions['type'],
                $inputData['lessonId'],
                $inputData['courseId']
            );

            wp_send_json_success($response);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Quiz generation failed');
        } catch (\Throwable $t) {
            wp_send_json_error([
                'message' => 'Fatal error: ' . $t->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify quiz nonce for security validation
     *
     * This method performs CSRF protection by validating the nonce token
     * sent with AJAX requests. The nonce ensures that the request originated
     * from a legitimate source and prevents cross-site request forgery attacks.
     *
     * Security Process:
     * 1. Extracts nonce from POST data and sanitizes it
     * 2. Uses the QUIZ_AI action constant for nonce verification
     * 3. Validates against WordPress nonce system
     * 4. Returns false for any invalid or missing nonce
     *
     * @since 1.0.0
     *
     * @return boolean True if nonce is valid, false otherwise
     */
    private function verifyQuizNonce(): bool
    {
        // Extract and sanitize the nonce from POST data
        // sanitize_text_field() removes any HTML tags and encodes special characters
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';

        // Use the standardized QUIZ_AI action for all quiz-related operations
        // This ensures consistency across all quiz AJAX endpoints
        $action = NonceConstants::QUIZ_AI;

        // Only proceed with verification if we have a non-empty nonce
        // Empty nonces are always considered invalid
        if (!empty($nonce)) {
            // wp_verify_nonce() returns 1 or 2 for valid nonces, false for invalid
            // We explicitly check !== false to handle both 1 and 2 as valid
            return wp_verify_nonce($nonce, $action) !== false;
        }

        // No nonce provided - this is a security violation
        return false;
    }

    /**
     * Verify user permissions for quiz operations
     *
     * This method checks if the current user has sufficient privileges to
     * perform quiz-related operations. We use 'edit_posts' capability as the
     * minimum requirement because:
     *
     * - Quiz creation requires content editing privileges
     * - Users need to be able to create/edit posts to manage quizzes
     * - This aligns with WordPress content management permissions
     * - Prevents unauthorized users from generating quiz content
     *
     * Permission Logic:
     * - Checks WordPress user capability system
     * - 'edit_posts' is typically available to Editor+ role levels
     * - Blocks Subscriber and Contributor roles from quiz generation
     * - Ensures only trusted users can create educational content
     *
     * @since 1.0.0
     *
     * @return boolean True if user can edit posts, false otherwise
     */
    private function verifyUserPermissions(): bool
    {
        // current_user_can() checks against WordPress role/capability system
        // 'edit_posts' is the minimum capability needed for content creation
        return current_user_can('edit_posts');
    }

    /**
     * Extract and sanitize input data from AJAX request
     *
     * This method performs comprehensive input sanitization to prevent
     * security vulnerabilities and ensure data integrity. Each input type
     * receives appropriate sanitization based on its expected format.
     *
     * Sanitization Strategy:
     * - lessonId/courseId: Use absint() to ensure positive integers only
     * - content: Use sanitize_textarea_field() to allow multiline text but strip harmful tags
     * - options: Keep as raw array for further processing (sanitized in parseQuizOptions)
     *
     * Security Considerations:
     * - absint() converts to absolute integer, removing negative values and non-numeric data
     * - sanitize_textarea_field() removes script tags, dangerous HTML, and normalizes line breaks
     * - Raw options array is intentionally unsanitized here to preserve structure for JSON parsing
     *
     * @since 1.0.0
     *
     * @return array Sanitized input data with consistent structure
     */
    private function extractAndSanitizeInput(): array
    {
        return [
            // Convert to absolute integer - ensures only positive whole numbers
            // absint() handles strings, floats, and negative numbers safely
            'lessonId' => isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0,
            'courseId' => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,

            // Sanitize textarea content while preserving line breaks
            // sanitize_textarea_field() strips dangerous HTML but keeps formatting
            'content'  => sanitize_textarea_field($_POST['content'] ?? ''),

            // Keep options raw for JSON parsing - will be sanitized in parseQuizOptions()
            // This preserves the structure needed for complex nested data
            'options'  => $_POST['options'] ?? [],
        ];
    }

    /**
     * Parse quiz options from request with flexible input handling
     *
     * This method handles quiz configuration options that can arrive in multiple
     * formats due to different client-side implementations (JavaScript objects,
     * JSON strings, or direct PHP arrays).
     *
     * Input Format Handling:
     * - Direct arrays: From PHP form submissions or direct AJAX calls
     * - JSON strings: From JavaScript JSON.stringify() operations
     * - Escaped JSON: From WordPress form handling that adds slashes
     *
     * Parsing Strategy:
     * 1. Check if input is already a PHP array (direct submission)
     * 2. If string, attempt JSON decode after stripslashes() to handle WordPress escaping
     * 3. Validate the parsed result is an array
     * 4. Apply recursive sanitization to all nested values
     *
     * Edge Cases Handled:
     * - Malformed JSON strings return empty array (fail safely)
     * - Non-array results return empty array (type safety)
     * - Null/undefined options return empty array (default behavior)
     *
     * @since 1.0.0
     *
     * @param  mixed $options Raw options from request (array or JSON string)
     * @return array Parsed and sanitized options array
     */
    private function parseQuizOptions($options): array
    {
        // Handle case where options are already a PHP array
        // This occurs with direct form submissions or some AJAX libraries
        if (is_array($options)) {
            $parsedOptions = $options;
        } else {
            // Handle JSON string format from JavaScript submissions
            // stripslashes() removes WordPress-added escaping (e.g., \" becomes ")
            // Cast to string to handle numeric or other non-string types safely
            $parsedOptions = json_decode(stripslashes((string)$options), true);
        }

        // Validate that we have a proper array after parsing
        // json_decode() can return null, false, or other types on failure
        if (is_array($parsedOptions)) {
            // Apply recursive sanitization to all nested array values
            // This ensures all option values are properly cleaned
            return $this->sanitizeArray($parsedOptions);
        }

        // Return empty array as safe default for any parsing failures
        // This prevents downstream errors and provides predictable behavior
        return [];
    }

    /**
     * Get quiz content from lesson, course, or provided content
     *
     * @since 1.0.0
     *
     * @param  string  $content  Direct content
     * @param  integer $lessonId Lesson ID
     * @param  integer $courseId Course ID
     * @return string Quiz content
     */
    private function getQuizContent(string $content, int $lessonId, int $courseId): string
    {
        // Validate input
        if (empty($content) && empty($lessonId) && empty($courseId)) {
            return '';
        }

        // Get content if lesson or course ID provided
        if ($lessonId > 0) {
            return $this->getLessonContent($lessonId);
        } elseif ($courseId > 0) {
            return $this->getCourseContent($courseId);
        }

        return $content;
    }

    /**
     * Prepare options for quiz generation
     *
     * @since 1.0.0
     *
     * @param  array $options Parsed options
     * @return array Generation options
     */
    private function prepareGenerationOptions(array $options): array
    {
        return [
            'type'         => $options['questionType'] ?? $options['question_type'] ?? 'multiple_choice',
            'count'        => intval($options['numQuestions'] ?? $options['num_questions'] ?? 10),
            'difficulty'   => $options['difficulty'] ?? 'medium',
            'customPrompt' => $options['customPrompt'] ?? $options['custom_prompt'] ?? '',
        ];
    }

    /**
     * Format successful quiz response
     *
     * @since 1.0.0
     *
     * @param  array   $result       Quiz generation result
     * @param  string  $questionType Question type
     * @param  integer $lessonId     Lesson ID
     * @param  integer $courseId     Course ID
     * @return array Formatted response data
     * @throws \Exception When quiz questions generation fails.
     */
    private function formatSuccessfulQuizResponse(
        array $result,
        string $questionType,
        int $lessonId,
        int $courseId
    ): array {
        // For backward compatibility, if the result is directly an array of questions
        $questions = isset($result['questions']) ? $result['questions'] : $result;

        if (empty($questions)) {
            throw new \Exception('Failed to generate quiz questions');
        }

        // Format quiz data for response
        $quizData = [
            'questions'  => $questions,
            'total'      => count($questions),
            'type'       => $questionType,
            'suggestion' => $result['suggestion'] ?? null,
        ];

        // Log successful quiz generation
        $this->logger->info('Quiz generated successfully', [
            'lessonId'     => $lessonId,
            'courseId'     => $courseId,
            'questionType' => $questionType,
            'numQuestions' => count($quizData['questions']),
            'userId'       => get_current_user_id(),
        ]);

        return $quizData;
    }

    /**
     * Handle regenerate question AJAX request
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When question regeneration fails.
     *
     * @example
     * // Regenerate a specific question
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_regenerate_question',
     *         question: JSON.stringify({
     *             type: 'multiple_choice',
     *             question: 'What is PHP?',
     *             options: ['A scripting language', 'A database', 'An OS']
     *         }),
     *         content: 'PHP is a server-side scripting language...',
     *         nonce: mpcc_ajax.nonce,
     *         options: JSON.stringify({ type: 'multiple_choice' })
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             console.log('New question:', response.data);
     *         }
     *     }
     * });
     *
     * @example
     * // Regenerate with specific options
     * const questionToRegenerate = {
     *     type: 'true_false',
     *     statement: 'PHP is case-sensitive',
     *     correct_answer: false
     * };
     *
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_regenerate_question',
     *         question: JSON.stringify(questionToRegenerate),
     *         content: 'Lesson content about PHP syntax...',
     *         nonce: mpcc_ajax.nonce
     *     }
     * });
     */
    public function regenerate_question(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Get and validate input
            $question = json_decode(stripslashes($_POST['question'] ?? '{}'), true);
            $content  = sanitize_textarea_field($_POST['content'] ?? '');
            $options  = json_decode(stripslashes($_POST['options'] ?? '{}'), true);

            if (empty($question) || empty($content)) {
                ApiResponse::errorMessage('Question and content are required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Get question type from the existing question or options
            $questionType = $question['type'] ?? $options['type'] ?? 'multiple_choice';

            // Log regeneration request
            $this->logger->info('Regenerating question', [
                'questionType'  => $questionType,
                'contentLength' => strlen($content),
            ]);

            // Prepare options for regeneration
            $generationOptions = [
                'type'  => $questionType,
                'count' => 5,  // Generate 5 to pick from
            ];

            // Generate new questions of the same type
            $result = $this->quizAIService->generateQuestions($content, $generationOptions);

            // Check if there was an error from content validation
            if (isset($result['error']) && $result['error']) {
                // Create WP_Error with suggestion as additional data
                $error = new \WP_Error(
                    ApiResponse::ERROR_INVALID_PARAMETER,
                    $result['message'] ?? 'Failed to regenerate question'
                );

                // Add suggestion as error data if available
                if (!empty($result['suggestion'])) {
                    $error->add_data(['suggestion' => $result['suggestion']], ApiResponse::ERROR_INVALID_PARAMETER);
                }

                ApiResponse::error($error, 400);
                return;
            }

            // For backward compatibility, if the result is directly an array of questions
            $questions = isset($result['questions']) ? $result['questions'] : $result;

            if (empty($questions)) {
                throw new \Exception('Failed to regenerate question');
            }

            // Return the first question as the regenerated one
            $newQuestion = $questions[0];

            wp_send_json_success($newQuestion);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Question regeneration failed');
        }
    }

    /**
     * Handle validate quiz AJAX request
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When quiz validation fails.
     *
     * @example
     * // Validate a complete quiz
     * const quizData = {
     *     title: 'PHP Basics Quiz',
     *     questions: [
     *         {
     *             type: 'multiple_choice',
     *             question: 'What does PHP stand for?',
     *             options: {
     *                 'a': 'PHP: Hypertext Preprocessor',
     *                 'b': 'Personal Home Page',
     *                 'c': 'Private Host Protocol'
     *             },
     *             correct_answer: 'a',
     *             points: 1
     *         }
     *     ]
     * };
     *
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_validate_quiz',
     *         quiz_data: JSON.stringify(quizData),
     *         nonce: mpcc_ajax.nonce
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             const results = response.data;
     *             console.log('Valid:', results.valid);
     *             console.log('Errors:', results.errors);
     *             console.log('Warnings:', results.warnings);
     *             console.log('Summary:', results.summary);
     *         }
     *     }
     * });
     *
     * @example
     * // Handling validation errors
     * $.ajax({
     *     // ... quiz validation request
     *     success: function(response) {
     *         if (response.success && !response.data.valid) {
     *             response.data.errors.forEach(error => {
     *                 console.error('Validation error:', error);
     *             });
     *             response.data.warnings.forEach(warning => {
     *                 console.warn('Validation warning:', warning);
     *             });
     *         }
     *     }
     * });
     */
    public function validate_quiz(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Get quiz data
            $quizData = json_decode(stripslashes($_POST['quiz_data'] ?? '{}'), true);

            if (empty($quizData['questions'])) {
                ApiResponse::errorMessage('Quiz questions are required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Validate quiz structure and content
            $validationResults = $this->validateQuizData($quizData);

            wp_send_json_success($validationResults);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Quiz validation failed');
        }
    }

    /**
     * Recursively sanitize array data with type-specific cleaning
     *
     * @since 1.0.0
     *
     * @example
     * // Sanitize quiz options array
     * $rawOptions = [
     *     'num_questions' => '10',
     *     'difficulty' => 'medium',
     *     'question_type' => 'multiple_choice',
     *     'custom_prompt' => '<script>alert("xss")</script>Focus on basics'
     * ];
     * $sanitized = $this->sanitizeArray($rawOptions, 'text');
     * // Result: ['num_questions' => '10', 'difficulty' => 'medium', 'question_type' => 'multiple_choice',
     * //          'custom_prompt' => 'Focus on basics']
     *
     * @example
     * // Sanitize nested question data with HTML content
     * $questionData = [
     *     'question' => 'What is PHP?',
     *     'options' => [
     *         'a' => 'A programming language',
     *         'b' => '<strong>A database</strong>',
     *         'c' => 'An operating system'
     *     ],
     *     'correct_answer' => 'a',
     *     'points' => '1.5'
     * ];
     * $sanitized = $this->sanitizeArray($questionData, 'html');
     * // HTML in options is preserved but sanitized through wp_kses_post
     *
     * @example
     * // Sanitize different data types
     * $mixedData = [
     *     'email' => 'user@example.com',
     *     'url' => 'https://example.com/path',
     *     'count' => '25',
     *     'price' => '19.99',
     *     'active' => 'true'
     * ];
     * $emailSanitized = $this->sanitizeArray(['email' => $mixedData['email']], 'email');
     * $urlSanitized = $this->sanitizeArray(['url' => $mixedData['url']], 'url');
     * $intSanitized = $this->sanitizeArray(['count' => $mixedData['count']], 'int');
     * $floatSanitized = $this->sanitizeArray(['price' => $mixedData['price']], 'float');
     * $boolSanitized = $this->sanitizeArray(['active' => $mixedData['active']], 'boolean');
     *
     * This method provides comprehensive sanitization for complex nested arrays
     * that may contain user input. It applies appropriate WordPress sanitization
     * functions based on the expected data type.
     *
     * Recursive Processing:
     * - Traverses nested arrays to any depth
     * - Maintains array structure while cleaning values
     * - Applies consistent sanitization rules throughout the data
     *
     * Sanitization Types:
     * - 'text' (default): Basic text field sanitization, removes HTML/scripts
     * - 'textarea': Preserves line breaks, allows basic formatting
     * - 'email': WordPress email validation and sanitization
     * - 'url': URL validation and protocol normalization
     * - 'int': Converts to integer, handles non-numeric gracefully
     * - 'float': Converts to float with decimal precision
     * - 'boolean': Strict boolean conversion (handles strings like 'true', '1')
     * - 'html': Allows safe HTML tags via wp_kses_post()
     *
     * Security Benefits:
     * - Prevents XSS attacks through HTML sanitization
     * - Ensures data type consistency for database operations
     * - Removes potentially malicious script content
     * - Normalizes input formats for reliable processing
     *
     * @param  array  $data Data array to sanitize (supports nested arrays)
     * @param  string $type Sanitization strategy to apply ('text', 'textarea', 'email', etc.)
     * @return array Recursively sanitized array with same structure
     */
    protected function sanitizeArray(array $data, string $type = 'text'): array
    {
        return array_map(function ($item) use ($type) {
            // Handle nested arrays recursively
            // This preserves complex data structures while ensuring all values are clean
            if (is_array($item)) {
                return $this->sanitizeArray($item, $type);
            }

            // Apply type-specific sanitization using PHP 8 match expression
            // Each case uses the most appropriate WordPress sanitization function
            return match ($type) {
                // Textarea: Preserves line breaks, removes dangerous HTML
                'textarea' => sanitize_textarea_field($item),

                // Email: Validates format and removes invalid characters
                'email' => sanitize_email($item),

                // URL: Validates protocol, removes dangerous schemes
                'url' => esc_url_raw($item),

                // Integer: Converts to whole number, handles type coercion safely
                'int' => intval($item),

                // Float: Preserves decimal values, handles precision
                'float' => floatval($item),

                // Boolean: Handles string representations ('true', 'false', '1', '0')
                'boolean' => filter_var($item, FILTER_VALIDATE_BOOLEAN),

                // HTML: Allows safe HTML tags for rich content (uses post content rules)
                'html' => wp_kses_post($item),

                // Default text: Standard sanitization for plain text fields
                // Removes all HTML tags and encodes special characters
                default => sanitize_text_field($item)
            };
        }, $data);
    }

    /**
     * Get lesson content
     *
     * @since 1.0.0
     *
     * @param  integer $lessonId The ID of the lesson to retrieve content from.
     * @return string
     *
     * @example
     * // Get content from a lesson with full content
     * $content = $this->getLessonContent(123);
     * // Returns: "This lesson covers PHP variables. Variables in PHP..." (full lesson content)
     *
     * @example
     * // Get content from lesson with only title (fallback behavior)
     * $content = $this->getLessonContent(456);
     * // Returns: "PHP Functions\n\nLearn about PHP functions...\n\nCourse Context: Introduction to PHP..."
     * //          (title + excerpt + course context)
     *
     * @example
     * // Handle non-existent lesson
     * $content = $this->getLessonContent(999999);
     * // Returns: "" (empty string)
     *
     * @example
     * // Handle wrong post type
     * $content = $this->getLessonContent(123); // Where 123 is a regular post, not mpcs-lesson
     * // Returns: "" (empty string)
     */
    private function getLessonContent(int $lessonId): string
    {
        $post = get_post($lessonId);

        if (!$post) {
            return '';
        }

        if ($post->post_type !== 'mpcs-lesson') {
            return '';
        }

        // Try to get content from various sources
        $content = wp_strip_all_tags($post->post_content);

        // If no content, try using title and excerpt
        if (empty($content) && !empty($post->post_title)) {
            $content = $post->post_title;
            if (!empty($post->post_excerpt)) {
                $content .= "\n\n" . $post->post_excerpt;
            }

            // Also try to get content from parent course
            $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
            if ($courseId) {
                $course = get_post($courseId);
                if ($course && !empty($course->post_content)) {
                    $content .= "\n\nCourse Context: " . wp_strip_all_tags($course->post_content);
                }
            }
        }

        return $content;
    }

    /**
     * Get course content
     *
     * @since 1.0.0
     *
     * @param  integer $courseId The ID of the course to retrieve content from.
     * @return string
     *
     * @example
     * // Get complete course content including all lessons
     * $content = $this->getCourseContent(789);
     * // Returns: "Course description...\n\nLesson 1 content...\n\nLesson 2 content..." (course + all lesson content)
     *
     * @example
     * // Get content from course with no lessons
     * $content = $this->getCourseContent(101);
     * // Returns: "This course covers advanced PHP concepts..." (just course description)
     *
     * @example
     * // Handle invalid course ID
     * $content = $this->getCourseContent(999999);
     * // Returns: "" (empty string)
     *
     * @example
     * // Handle wrong post type
     * $content = $this->getCourseContent(123); // Where 123 is not an mpcs-course
     * // Returns: "" (empty string)
     */
    private function getCourseContent(int $courseId): string
    {
        $post = get_post($courseId);

        if (!$post || $post->post_type !== 'mpcs-course') {
            return '';
        }

        $content = wp_strip_all_tags($post->post_content);

        // Get all lessons in the course
        $lessons = get_posts([
            'post_type'   => 'mpcs-lesson',
            'meta_key'    => '_mpcs_course_id',
            'meta_value'  => $courseId,
            'numberposts' => -1,
            'orderby'     => 'menu_order',
            'order'       => 'ASC',
        ]);

        foreach ($lessons as $lesson) {
            $content .= "\n\n" . wp_strip_all_tags($lesson->post_content);
        }

        return $content;
    }


    /**
     * Comprehensive quiz data validation with detailed error reporting
     *
     * This method performs thorough validation of quiz structure and content
     * to ensure data integrity before processing or storage. It checks both
     * required fields and logical consistency across question types.
     *
     * Validation Levels:
     * - ERRORS: Critical issues that prevent quiz functionality
     * - WARNINGS: Issues that should be addressed but don't break functionality
     * - SUMMARY: Statistical information about the quiz structure
     *
     * Question Type Validation Rules:
     *
     * Multiple Choice:
     * - Requires 2+ answer options (minimum for meaningful choice)
     * - Correct answer must exist in the options array
     * - Handles both indexed arrays ['A', 'B'] and associative ['A' => 'Answer A']
     *
     * True/False:
     * - Requires 'statement' field instead of 'question'
     * - Correct answer must be boolean (not string 'true'/'false')
     * - Enforces boolean type for reliable comparison
     *
     * Text Answer:
     * - Must have a correct answer for grading
     * - Alternative answers are optional but must be array format if provided
     * - Supports flexible answer matching
     *
     * Multiple Select:
     * - Requires 3+ options (more choice needed than multiple choice)
     * - Must have 2+ correct answers (defines "multiple" selection)
     * - All correct answers must reference valid option keys
     * - Validates key-value consistency in options array
     *
     * Point System Validation:
     * - Ensures positive numeric values only
     * - Defaults invalid points to 1 (prevents zero-point questions)
     * - Calculates total points for quiz scoring
     *
     * @since 1.0.0
     *
     * @param  array $quizData Complete quiz data structure to validate
     * @return array Validation results with 'valid', 'errors', 'warnings', and 'summary'
     */
    private function validateQuizData(array $quizData): array
    {
        // Initialize validation results structure
        // 'valid' flag determines if quiz can be processed safely
        $results = [
            'valid'    => true,
            'errors'   => [],      // Critical issues that prevent quiz use
            'warnings' => [],    // Non-critical issues that should be addressed
            'summary'  => [],      // Statistical information about quiz structure
        ];

        // Check for quiz title (optional but recommended)
        // Missing titles make quiz management difficult but don't break functionality
        if (empty($quizData['title'])) {
            $results['warnings'][] = 'Quiz title is missing';
        }

        // Validate that questions array exists and is not empty
        // This is a critical requirement - quizzes must have questions
        if (empty($quizData['questions'])) {
            $results['valid']    = false;
            $results['errors'][] = 'No questions found in quiz';
            // Return early - no point validating individual questions if none exist
            return $results;
        }

        // Initialize tracking variables for quiz statistics
        $questionTypes = [];  // Count of each question type for reporting
        $totalPoints   = 0;     // Sum of all question point values

        // Validate each question individually
        foreach ($quizData['questions'] as $index => $question) {
            // Use 1-based numbering for user-friendly error messages
            $questionNum = $index + 1;

            // Validate core required fields for all question types
            // Every question must have text content for display
            if (empty($question['question'])) {
                $results['errors'][] = "Question {$questionNum}: Question text is missing";
                $results['valid']    = false;
            }

            // Every question must specify its type for proper rendering
            if (empty($question['type'])) {
                $results['errors'][] = "Question {$questionNum}: Question type is missing";
                $results['valid']    = false;
            } else {
                // Track question type distribution for summary statistics
                $questionTypes[$question['type']] = ($questionTypes[$question['type']] ?? 0) + 1;
            }

            // Apply type-specific validation rules
            // Each question type has unique requirements for proper functionality
            switch ($question['type']) {
                case 'multiple_choice':
                    // Multiple choice questions need options array for answer choices
                    if (empty($question['options']) || !is_array($question['options'])) {
                        $results['errors'][] = "Question {$questionNum}: Options are missing or invalid";
                        $results['valid']    = false;
                    } elseif (count($question['options']) < 2) {
                        // Minimum 2 options required - single option isn't a "choice"
                        $results['errors'][] = "Question {$questionNum}: At least 2 options are required";
                        $results['valid']    = false;
                    }

                    // Validate correct answer exists and references a valid option
                    if (empty($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid']    = false;
                    } elseif (!in_array($question['correct_answer'], array_values($question['options'] ?? []))) {
                        // Check if correct answer matches any option value
                        // This handles associative arrays where keys != values
                        $results['errors'][] = "Question {$questionNum}: Correct answer is not in options";
                        $results['valid']    = false;
                    }
                    break;

                case 'true_false':
                    // True/false questions use 'statement' instead of 'question' field
                    // This reflects the different UI pattern (statement to evaluate vs question to answer)
                    if (!isset($question['statement']) || empty($question['statement'])) {
                        $results['errors'][] = "Question {$questionNum}: Statement is missing";
                        $results['valid']    = false;
                    }

                    // Correct answer must be boolean for reliable comparison
                    // String values like 'true'/'false' can cause logic errors
                    if (!isset($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid']    = false;
                    } elseif (!is_bool($question['correct_answer'])) {
                        // This is a warning because string values can be converted, but boolean is preferred
                        $results['warnings'][] = "Question {$questionNum}: Correct answer should be boolean";
                    }
                    break;

                case 'text_answer':
                    // Text answer questions must have an expected answer for grading
                    if (empty($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid']    = false;
                    }

                    // Alternative answers enhance grading flexibility but are optional
                    // If provided, they must be in array format for consistent processing
                    if (isset($question['alternative_answers']) && !is_array($question['alternative_answers'])) {
                        $results['warnings'][] = "Question {$questionNum}: Alternative answers should be an array";
                    }
                    break;

                case 'multiple_select':
                    // Multiple select needs more options than multiple choice
                    // Minimum 3 options to provide meaningful multiple selection
                    if (empty($question['options']) || !is_array($question['options'])) {
                        $results['errors'][] = "Question {$questionNum}: Options are missing or invalid";
                        $results['valid']    = false;
                    } elseif (count($question['options']) < 3) {
                        $results['errors'][] = "Question {$questionNum}: "
                            . 'At least 3 options are required for multiple select';
                        $results['valid']    = false;
                    }

                    // Multiple select requires array of correct answers (not single value)
                    if (empty($question['correct_answers']) || !is_array($question['correct_answers'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answers are missing or not an array";
                        $results['valid']    = false;
                    } elseif (count($question['correct_answers']) < 2) {
                        // Minimum 2 correct answers defines "multiple" selection
                        $results['errors'][] = "Question {$questionNum}: "
                            . 'At least 2 correct answers are required for multiple select';
                        $results['valid']    = false;
                    } else {
                        // Validate referential integrity: all correct answers must reference valid options
                        // This prevents orphaned references that would break grading
                        $optionKeys = array_keys($question['options'] ?? []);
                        foreach ($question['correct_answers'] as $answer) {
                            if (!in_array($answer, $optionKeys)) {
                                $results['errors'][] = "Question {$questionNum}: "
                                    . "Correct answer '{$answer}' is not in options";
                                $results['valid']    = false;
                                // Break early to avoid spam errors for multiple invalid answers
                                break;
                            }
                        }
                    }
                    break;

                default:
                    // Unknown question types generate warnings for future compatibility
                    // New question types might be added, so we don't fail validation entirely
                    $results['warnings'][] = "Question {$questionNum}: Unknown question type '{$question['type']}'";
                    break;
            }

            // Validate point values for scoring system
            $points = $question['points'] ?? 1;
            if (!is_numeric($points) || $points <= 0) {
                // Invalid points default to 1 to prevent scoring issues
                // Zero or negative points would break quiz scoring calculations
                $results['warnings'][] = "Question {$questionNum}: Invalid points value, defaulting to 1";
                $points                = 1;
            }
            $totalPoints += $points;
        }

        // Generate comprehensive summary for quiz analysis
        $results['summary'] = [
            'totalQuestions' => count($quizData['questions']),
            'totalPoints'    => $totalPoints,
            'questionTypes'  => $questionTypes,
        ];

        return $results;
    }

    /**
     * Handle create quiz from lesson AJAX request
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When quiz creation from lesson fails.
     *
     * @example
     * // Create a quiz from a specific lesson
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_create_quiz_from_lesson',
     *         lesson_id: 456,
     *         course_id: 789, // Optional - will be detected from lesson if not provided
     *         nonce: mpcc_ajax.nonce
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             const data = response.data;
     *             console.log('Quiz created with ID:', data.quiz_id);
     *             console.log('Edit URL:', data.edit_url);
     *             // Redirect to edit the new quiz
     *             window.location.href = data.edit_url;
     *         }
     *     }
     * });
     *
     * @example
     * // Create quiz and auto-open AI modal
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_create_quiz_from_lesson',
     *         lesson_id: getCurrentLessonId(),
     *         nonce: mpcc_ajax.nonce
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             // URL includes auto_open=true to trigger AI modal
     *             window.location.href = response.data.edit_url;
     *         }
     *     },
     *     error: function(xhr) {
     *         console.error('Failed to create quiz:', xhr.responseJSON?.data?.message);
     *     }
     * });
     */
    public function create_quiz_from_lesson(): void
    {
        try {
            // Check if quiz plugin is active
            if (!defined('\memberpress\quizzes\VERSION')) {
                throw new \Exception(__('MemberPress Course Quizzes plugin is not active', 'memberpress-courses-copilot'));
            }
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Get lesson ID
            $lessonId = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

            if (empty($lessonId)) {
                ApiResponse::errorMessage('Lesson ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Get lesson data
            $lesson = get_post($lessonId);

            if (!$lesson || $lesson->post_type !== 'mpcs-lesson') {
                ApiResponse::errorMessage('Invalid lesson ID', ApiResponse::ERROR_INVALID_PARAMETER);
                return;
            }

            // Get course ID from request or from lesson metadata
            $courseId = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            if (empty($courseId)) {
                $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
                $this->logger->info('Retrieved course ID from lesson metadata', [
                    'lessonId' => $lessonId,
                    'courseId' => $courseId,
                ]);
            }

            // Create quiz post
            // translators: %s: Lesson title
            $quizTitle = sprintf(__('Quiz: %s', 'memberpress-courses-copilot'), $lesson->post_title);

            $quizData = [
                'post_title'   => $quizTitle,
                'post_content' => '',
                'post_status'  => 'draft',
                'post_type'    => 'mpcs-quiz',
                'post_author'  => get_current_user_id(),
            ];

            $quizId = wp_insert_post($quizData);

            if (is_wp_error($quizId)) {
                throw new \Exception('Failed to create quiz: ' . $quizId->get_error_message());
            }

            // Set lesson association
            update_post_meta($quizId, '_mpcs_lesson_id', $lessonId);

            // Get section ID from lesson - THIS IS CRUCIAL for quiz to appear in course!
            $sectionId = get_post_meta($lessonId, '_mpcs_lesson_section_id', true);
            if ($sectionId) {
                update_post_meta($quizId, '_mpcs_lesson_section_id', $sectionId);

                // Get the lesson's order and place quiz right after it
                $lessonOrder = get_post_meta($lessonId, '_mpcs_lesson_lesson_order', true);
                $quizOrder   = $lessonOrder ? (int)$lessonOrder + 1 : 1;
                update_post_meta($quizId, '_mpcs_lesson_lesson_order', $quizOrder);
            }

            // Store course ID if we have it (already retrieved above)
            if ($courseId) {
                update_post_meta($quizId, '_mpcs_course_id', $courseId);
            }

            // Log the creation
            $this->logger->info('Quiz created from lesson', [
                'quizId'    => $quizId,
                'lessonId'  => $lessonId,
                'sectionId' => $sectionId ?? 'none',
                'courseId'  => $courseId ?? 'none',
                'quizOrder' => $quizOrder ?? 'none',
                'userId'    => get_current_user_id(),
            ]);

            // Build edit URL with lesson context, course ID, and auto-open flag
            $editUrlArgs = [
                'post'      => $quizId,
                'action'    => 'edit',
                'lesson_id' => $lessonId,
                'auto_open' => 'true',
            ];

            // Include course ID in the URL if available
            if ($courseId) {
                $editUrlArgs['course_id'] = $courseId;
            }

            $editUrl = add_query_arg($editUrlArgs, admin_url('post.php'));

            wp_send_json_success([
                'quiz_id'   => $quizId,
                'edit_url'  => $editUrl,
                'message'   => __('Quiz created successfully!', 'memberpress-courses-copilot'),
                'course_id' => $courseId,
                'lesson_id' => $lessonId,
            ]);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to create quiz from lesson');
        }
    }

    /**
     * Get the course ID for a lesson
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When getting lesson course fails.
     *
     * @example
     * // Get course information for a lesson
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_get_lesson_course',
     *         lesson_id: 123,
     *         nonce: mpcc_ajax.nonce
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             const data = response.data;
     *             console.log('Lesson ID:', data.lesson_id);
     *             console.log('Course ID:', data.course_id);
     *             console.log('Course Title:', data.course_title);
     *         }
     *     }
     * });
     *
     * @example
     * // Using in a Promise for async/await pattern
     * function getLessonCourseId(lessonId) {
     *     return new Promise((resolve, reject) => {
     *         $.ajax({
     *             url: mpcc_ajax.ajax_url,
     *             type: 'POST',
     *             data: {
     *                 action: 'mpcc_get_lesson_course',
     *                 lesson_id: lessonId,
     *                 nonce: mpcc_ajax.nonce
     *             },
     *             success: function(response) {
     *                 if (response.success) {
     *                     resolve(response.data.course_id);
     *                 } else {
     *                     reject('No course found for lesson');
     *                 }
     *             },
     *             error: reject
     *         });
     *     });
     * }
     *
     * // Usage:
     * try {
     *     const courseId = await getLessonCourseId(123);
     *     console.log('Course ID:', courseId);
     * } catch (error) {
     *     console.error('Error:', error);
     * }
     */
    public function get_lesson_course(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Get lesson ID
            $lessonId = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;

            if (empty($lessonId)) {
                ApiResponse::errorMessage('Lesson ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Get lesson data
            $lesson = get_post($lessonId);

            if (!$lesson || $lesson->post_type !== 'mpcs-lesson') {
                ApiResponse::errorMessage('Invalid lesson ID', ApiResponse::ERROR_INVALID_PARAMETER);
                return;
            }

            // Get course ID from lesson meta
            $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);

            // Get course details if we have a course ID
            $courseTitle = '';
            if ($courseId) {
                $course = get_post($courseId);
                if ($course && $course->post_type === 'mpcs-course') {
                    $courseTitle = $course->post_title;
                }
            }

            wp_send_json_success([
                'lesson_id'    => $lessonId,
                'course_id'    => $courseId,
                'course_title' => $courseTitle,
            ]);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to get lesson course');
        }
    }

    /**
     * Get all lessons for a course
     *
     * @since 1.0.0
     *
     * @return void
     *
     * @throws \Exception When getting course lessons fails.
     *
     * @example
     * // Get all lessons for a course
     * $.ajax({
     *     url: mpcc_ajax.ajax_url,
     *     type: 'POST',
     *     data: {
     *         action: 'mpcc_get_course_lessons',
     *         course_id: 456,
     *         nonce: mpcc_ajax.nonce
     *     },
     *     success: function(response) {
     *         if (response.success) {
     *             const data = response.data;
     *             console.log('Course:', data.course_title);
     *             console.log('Total lessons:', data.lesson_count);
     *
     *             data.lessons.forEach(lesson => {
     *                 console.log(`Lesson ${lesson.id}: ${lesson.title}`);
     *                 console.log(`Section ID: ${lesson.section_id}`);
     *             });
     *         }
     *     }
     * });
     *
     * @example
     * // Populate a lesson dropdown from course lessons
     * function populateLessonDropdown(courseId, selectElement) {
     *     $.ajax({
     *         url: mpcc_ajax.ajax_url,
     *         type: 'POST',
     *         data: {
     *             action: 'mpcc_get_course_lessons',
     *             course_id: courseId,
     *             nonce: mpcc_ajax.nonce
     *         },
     *         success: function(response) {
     *             if (response.success) {
     *                 const lessons = response.data.lessons;
     *                 $(selectElement).empty()
     *                     .append('<option value="">Select a lesson...</option>');
     *
     *                 lessons.forEach(lesson => {
     *                     $(selectElement).append(
     *                         `<option value="${lesson.id}">${lesson.title}</option>`
     *                     );
     *                 });
     *             }
     *         },
     *         error: function() {
     *             $(selectElement).html('<option value="">Failed to load lessons</option>');
     *         }
     *     });
     * }
     */
    public function get_course_lessons(): void
    {
        try {
            // Verify nonce
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::QUIZ_AI, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }

            // Check user capabilities
            if (!current_user_can('edit_posts')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }

            // Get course ID
            $courseId = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;

            if (empty($courseId)) {
                ApiResponse::errorMessage('Course ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }

            // Get course data
            $course = get_post($courseId);

            if (!$course || $course->post_type !== 'mpcs-course') {
                ApiResponse::errorMessage('Invalid course ID', ApiResponse::ERROR_INVALID_PARAMETER);
                return;
            }

            // Get lessons for this course
            $lessons = [];

            // Try to use MemberPress Courses API if available
            if (class_exists('\\memberpress\\courses\\models\\Course')) {
                $courseModel = new \memberpress\courses\models\Course($courseId);
                $sections    = $courseModel->sections();

                foreach ($sections as $section) {
                    $sectionLessons = $section->lessons();
                    foreach ($sectionLessons as $lesson) {
                        if ($lesson->post_type === 'mpcs-lesson') {
                            $lessons[] = [
                                'id'        => $lesson->ID,
                                'title'     => $lesson->post_title,
                                'sectionId' => $section->id,
                            ];
                        }
                    }
                }
            } else {
                // Fallback: Get lessons by meta query
                $lessonPosts = get_posts([
                    'post_type'   => 'mpcs-lesson',
                    'meta_query'  => [
                        [
                            'key'     => '_mpcs_course_id',
                            'value'   => $courseId,
                            'compare' => '=',
                        ],
                    ],
                    'numberposts' => -1,
                    'orderby'     => 'menu_order',
                    'order'       => 'ASC',
                ]);

                foreach ($lessonPosts as $lesson) {
                    $lessons[] = [
                        'id'        => $lesson->ID,
                        'title'     => $lesson->post_title,
                        'sectionId' => get_post_meta($lesson->ID, '_mpcs_lesson_section_id', true),
                    ];
                }
            }

            wp_send_json_success([
                'course_id'    => $courseId,
                'course_title' => $course->post_title,
                'lessons'      => $lessons,
                'lesson_count' => count($lessons),
            ]);
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to get course lessons');
        }
    }

    /**
     * Handle AJAX errors consistently
     *
     * @since 1.0.0
     *
     * @param  \Exception $e       The exception to handle
     * @param  string     $context Context description for logging
     * @return void
     */
    private function handleAjaxError(\Exception $e, string $context): void
    {
        $this->logger->error($context, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
        ApiResponse::error($error);
    }
}
