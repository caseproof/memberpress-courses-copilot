<?php

declare(strict_types=1);

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
 * @since 1.0.0
 */
class MpccQuizAjaxController
{
    private MpccQuizAIService $quizAIService;
    private Logger $logger;
    
    /**
     * Constructor - dependencies can be injected
     * 
     * @param MpccQuizAIService|null $quizAIService
     * @param Logger|null $logger
     */
    public function __construct(
        ?MpccQuizAIService $quizAIService = null,
        ?Logger $logger = null
    ) {
        // Use injected dependencies or create new instances
        if ($quizAIService === null) {
            $llmService = new LLMService();
            $this->quizAIService = new MpccQuizAIService($llmService);
        } else {
            $this->quizAIService = $quizAIService;
        }
        $this->logger = $logger ?? Logger::getInstance();
    }
    
    /**
     * Load hooks and register AJAX handlers
     * 
     * @return void
     */
    public function init(): void
    {
        $this->load_hooks();
    }
    
    /**
     * Load hooks and register AJAX handlers
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
        add_action('wp_ajax_nopriv_mpcc_generate_quiz', function() {
            wp_send_json_error('Not authorized', 401);
        });
    }
    
    /**
     * Handle quiz generation AJAX request
     * 
     * @return void
     */
    public function generate_quiz(): void
    {
        try {
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
                ApiResponse::errorMessage('No content available to generate quiz from', ApiResponse::ERROR_MISSING_PARAMETER);
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
                'message' => 'Fatal error: ' . $t->getMessage()
            ], 500);
        }
    }
    
    /**
     * Verify quiz nonce
     * 
     * @return bool
     */
    private function verifyQuizNonce(): bool
    {
        $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        $action = NonceConstants::QUIZ_AI;
        
        if (!empty($nonce)) {
            return wp_verify_nonce($nonce, $action) !== false;
        }
        
        return false;
    }
    
    /**
     * Verify user permissions
     * 
     * @return bool
     */
    private function verifyUserPermissions(): bool
    {
        return current_user_can('edit_posts');
    }
    
    /**
     * Extract and sanitize input data
     * 
     * @return array
     */
    private function extractAndSanitizeInput(): array
    {
        return [
            'lessonId' => isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0,
            'courseId' => isset($_POST['course_id']) ? absint($_POST['course_id']) : 0,
            'content' => sanitize_textarea_field($_POST['content'] ?? ''),
            'options' => $_POST['options'] ?? []
        ];
    }
    
    /**
     * Parse quiz options from request
     * 
     * @param mixed $options Raw options from request
     * @return array Parsed and sanitized options
     */
    private function parseQuizOptions($options): array
    {
        // Options might come as array or JSON string
        if (is_array($options)) {
            $parsedOptions = $options;
        } else {
            $parsedOptions = json_decode(stripslashes((string)$options), true);
        }
        
        // Sanitize options array
        if (is_array($parsedOptions)) {
            return $this->sanitizeArray($parsedOptions);
        }
        
        return [];
    }
    
    /**
     * Get quiz content from lesson, course, or provided content
     * 
     * @param string $content Direct content
     * @param int $lessonId Lesson ID
     * @param int $courseId Course ID
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
     * @param array $options Parsed options
     * @return array Generation options
     */
    private function prepareGenerationOptions(array $options): array
    {
        return [
            'type' => $options['question_type'] ?? 'multiple_choice',
            'count' => intval($options['num_questions'] ?? 10),
            'difficulty' => $options['difficulty'] ?? 'medium',
            'custom_prompt' => $options['custom_prompt'] ?? ''
        ];
    }
    
    /**
     * Format successful quiz response
     * 
     * @param array $result Quiz generation result
     * @param string $questionType Question type
     * @param int $lessonId Lesson ID
     * @param int $courseId Course ID
     * @return array Formatted response data
     */
    private function formatSuccessfulQuizResponse(array $result, string $questionType, int $lessonId, int $courseId): array
    {
        // For backward compatibility, if the result is directly an array of questions
        $questions = isset($result['questions']) ? $result['questions'] : $result;
        
        if (empty($questions)) {
            throw new \Exception('Failed to generate quiz questions');
        }
        
        // Format quiz data for response
        $quizData = [
            'questions' => $questions,
            'total' => count($questions),
            'type' => $questionType,
            'suggestion' => $result['suggestion'] ?? null
        ];
        
        // Log successful quiz generation
        $this->logger->info('Quiz generated successfully', [
            'lesson_id' => $lessonId,
            'course_id' => $courseId,
            'question_type' => $questionType,
            'num_questions' => count($quizData['questions']),
            'user_id' => get_current_user_id()
        ]);
        
        return $quizData;
    }
    
    /**
     * Handle regenerate question AJAX request
     * 
     * @return void
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
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            $options = json_decode(stripslashes($_POST['options'] ?? '{}'), true);
            
            if (empty($question) || empty($content)) {
                ApiResponse::errorMessage('Question and content are required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }
            
            // Get question type from the existing question or options
            $questionType = $question['type'] ?? $options['type'] ?? 'multiple_choice';
            
            // Log regeneration request
            $this->logger->info('Regenerating question', [
                'question_type' => $questionType,
                'content_length' => strlen($content)
            ]);
            
            // Prepare options for regeneration
            $generationOptions = [
                'type' => $questionType,
                'count' => 5  // Generate 5 to pick from
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
     * @return void
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
     * Sanitize array data recursively
     *
     * @param array $data Data to sanitize
     * @param string $type Sanitization type
     * @return array Sanitized array
     */
    protected function sanitizeArray(array $data, string $type = 'text'): array 
    {
        return array_map(function($item) use ($type) {
            if (is_array($item)) {
                return $this->sanitizeArray($item, $type);
            }
            return match($type) {
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
     * Get lesson content
     * 
     * @param int $lessonId
     * @return string
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
            $course_id = get_post_meta($lessonId, '_mpcs_course_id', true);
            if ($course_id) {
                $course = get_post($course_id);
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
     * @param int $courseId
     * @return string
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
            'post_type' => 'mpcs-lesson',
            'meta_key' => '_mpcs_course_id',
            'meta_value' => $courseId,
            'numberposts' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
        
        foreach ($lessons as $lesson) {
            $content .= "\n\n" . wp_strip_all_tags($lesson->post_content);
        }
        
        return $content;
    }
    
    
    /**
     * Validate quiz data
     * 
     * @param array $quizData
     * @return array
     */
    private function validateQuizData(array $quizData): array
    {
        $results = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'summary' => []
        ];
        
        // Check quiz title
        if (empty($quizData['title'])) {
            $results['warnings'][] = 'Quiz title is missing';
        }
        
        // Validate questions
        if (empty($quizData['questions'])) {
            $results['valid'] = false;
            $results['errors'][] = 'No questions found in quiz';
            return $results;
        }
        
        $questionTypes = [];
        $totalPoints = 0;
        
        foreach ($quizData['questions'] as $index => $question) {
            $questionNum = $index + 1;
            
            // Check required fields
            if (empty($question['question'])) {
                $results['errors'][] = "Question {$questionNum}: Question text is missing";
                $results['valid'] = false;
            }
            
            if (empty($question['type'])) {
                $results['errors'][] = "Question {$questionNum}: Question type is missing";
                $results['valid'] = false;
            } else {
                $questionTypes[$question['type']] = ($questionTypes[$question['type']] ?? 0) + 1;
            }
            
            // Validate based on question type
            switch ($question['type']) {
                case 'multiple_choice':
                    if (empty($question['options']) || !is_array($question['options'])) {
                        $results['errors'][] = "Question {$questionNum}: Options are missing or invalid";
                        $results['valid'] = false;
                    } elseif (count($question['options']) < 2) {
                        $results['errors'][] = "Question {$questionNum}: At least 2 options are required";
                        $results['valid'] = false;
                    }
                    
                    if (empty($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid'] = false;
                    } elseif (!in_array($question['correct_answer'], array_values($question['options'] ?? []))) {
                        // Check if correct answer is in the values of options (for associative arrays)
                        $results['errors'][] = "Question {$questionNum}: Correct answer is not in options";
                        $results['valid'] = false;
                    }
                    break;
                    
                case 'true_false':
                    if (!isset($question['statement']) || empty($question['statement'])) {
                        $results['errors'][] = "Question {$questionNum}: Statement is missing";
                        $results['valid'] = false;
                    }
                    
                    if (!isset($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid'] = false;
                    } elseif (!is_bool($question['correct_answer'])) {
                        $results['warnings'][] = "Question {$questionNum}: Correct answer should be boolean";
                    }
                    break;
                    
                case 'text_answer':
                    if (empty($question['correct_answer'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                        $results['valid'] = false;
                    }
                    
                    // Alternative answers are optional but should be an array if present
                    if (isset($question['alternative_answers']) && !is_array($question['alternative_answers'])) {
                        $results['warnings'][] = "Question {$questionNum}: Alternative answers should be an array";
                    }
                    break;
                    
                case 'multiple_select':
                    if (empty($question['options']) || !is_array($question['options'])) {
                        $results['errors'][] = "Question {$questionNum}: Options are missing or invalid";
                        $results['valid'] = false;
                    } elseif (count($question['options']) < 3) {
                        $results['errors'][] = "Question {$questionNum}: At least 3 options are required for multiple select";
                        $results['valid'] = false;
                    }
                    
                    if (empty($question['correct_answers']) || !is_array($question['correct_answers'])) {
                        $results['errors'][] = "Question {$questionNum}: Correct answers are missing or not an array";
                        $results['valid'] = false;
                    } elseif (count($question['correct_answers']) < 2) {
                        $results['errors'][] = "Question {$questionNum}: At least 2 correct answers are required for multiple select";
                        $results['valid'] = false;
                    } else {
                        // Validate all correct answers are in options
                        $optionKeys = array_keys($question['options'] ?? []);
                        foreach ($question['correct_answers'] as $answer) {
                            if (!in_array($answer, $optionKeys)) {
                                $results['errors'][] = "Question {$questionNum}: Correct answer '{$answer}' is not in options";
                                $results['valid'] = false;
                                break;
                            }
                        }
                    }
                    break;
                    
                default:
                    $results['warnings'][] = "Question {$questionNum}: Unknown question type '{$question['type']}'";
                    break;
            }
            
            // Check points
            $points = $question['points'] ?? 1;
            if (!is_numeric($points) || $points <= 0) {
                $results['warnings'][] = "Question {$questionNum}: Invalid points value, defaulting to 1";
                $points = 1;
            }
            $totalPoints += $points;
        }
        
        // Add summary
        $results['summary'] = [
            'total_questions' => count($quizData['questions']),
            'total_points' => $totalPoints,
            'question_types' => $questionTypes
        ];
        
        return $results;
    }
    
    /**
     * Handle create quiz from lesson AJAX request
     * 
     * @return void
     */
    public function create_quiz_from_lesson(): void
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
            
            // Get course ID from request or from lesson metadata
            $courseId = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            if (empty($courseId)) {
                $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
                $this->logger->info('Retrieved course ID from lesson metadata', [
                    'lesson_id' => $lessonId,
                    'course_id' => $courseId
                ]);
            }
            
            // Create quiz post
            $quizTitle = sprintf(__('Quiz: %s', 'memberpress-courses-copilot'), $lesson->post_title);
            
            $quizData = [
                'post_title' => $quizTitle,
                'post_content' => '',
                'post_status' => 'draft',
                'post_type' => 'mpcs-quiz',
                'post_author' => get_current_user_id()
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
                $quizOrder = $lessonOrder ? (int)$lessonOrder + 1 : 1;
                update_post_meta($quizId, '_mpcs_lesson_lesson_order', $quizOrder);
            }
            
            // Store course ID if we have it (already retrieved above)
            if ($courseId) {
                update_post_meta($quizId, '_mpcs_course_id', $courseId);
            }
            
            // Log the creation
            $this->logger->info('Quiz created from lesson', [
                'quiz_id' => $quizId,
                'lesson_id' => $lessonId,
                'section_id' => $sectionId ?? 'none',
                'course_id' => $courseId ?? 'none',
                'quiz_order' => $quizOrder ?? 'none',
                'user_id' => get_current_user_id()
            ]);
            
            // Build edit URL with lesson context, course ID, and auto-open flag
            $editUrlArgs = [
                'post' => $quizId,
                'action' => 'edit',
                'lesson_id' => $lessonId,
                'auto_open' => 'true'
            ];
            
            // Include course ID in the URL if available
            if ($courseId) {
                $editUrlArgs['course_id'] = $courseId;
            }
            
            $editUrl = add_query_arg($editUrlArgs, admin_url('post.php'));
            
            wp_send_json_success([
                'quiz_id' => $quizId,
                'edit_url' => $editUrl,
                'message' => __('Quiz created successfully!', 'memberpress-courses-copilot'),
                'course_id' => $courseId,
                'lesson_id' => $lessonId
            ]);
            
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to create quiz from lesson');
        }
    }
    
    /**
     * Get the course ID for a lesson
     * 
     * @return void
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
                'lesson_id' => $lessonId,
                'course_id' => $courseId,
                'course_title' => $courseTitle
            ]);
            
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to get lesson course');
        }
    }
    
    /**
     * Get all lessons for a course
     * 
     * @return void
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
                $sections = $courseModel->sections();
                
                foreach ($sections as $section) {
                    $sectionLessons = $section->lessons();
                    foreach ($sectionLessons as $lesson) {
                        if ($lesson->post_type === 'mpcs-lesson') {
                            $lessons[] = [
                                'id' => $lesson->ID,
                                'title' => $lesson->post_title,
                                'section_id' => $section->id
                            ];
                        }
                    }
                }
            } else {
                // Fallback: Get lessons by meta query
                $lessonPosts = get_posts([
                    'post_type' => 'mpcs-lesson',
                    'meta_query' => [
                        [
                            'key' => '_mpcs_course_id',
                            'value' => $courseId,
                            'compare' => '='
                        ]
                    ],
                    'numberposts' => -1,
                    'orderby' => 'menu_order',
                    'order' => 'ASC'
                ]);
                
                foreach ($lessonPosts as $lesson) {
                    $lessons[] = [
                        'id' => $lesson->ID,
                        'title' => $lesson->post_title,
                        'section_id' => get_post_meta($lesson->ID, '_mpcs_lesson_section_id', true)
                    ];
                }
            }
            
            wp_send_json_success([
                'course_id' => $courseId,
                'course_title' => $course->post_title,
                'lessons' => $lessons,
                'lesson_count' => count($lessons)
            ]);
            
        } catch (\Exception $e) {
            $this->handleAjaxError($e, 'Failed to get course lessons');
        }
    }
    
    /**
     * Handle AJAX errors consistently
     * 
     * @param \Exception $e The exception to handle
     * @param string $context Context description for logging
     * @return void
     */
    private function handleAjaxError(\Exception $e, string $context): void
    {
        $this->logger->error($context, [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
        ApiResponse::error($error);
    }
}