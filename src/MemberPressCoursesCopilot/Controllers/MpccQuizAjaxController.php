<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Controllers;

use MemberPressCoursesCopilot\Services\MpccQuizAIService;
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
        $this->quizAIService = $quizAIService ?? new MpccQuizAIService();
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
        error_log('MPCC Quiz: load_hooks() called - registering AJAX actions');
        
        // Register AJAX handlers for quiz generation
        add_action('wp_ajax_mpcc_generate_quiz', [$this, 'generate_quiz'], 10);
        add_action('wp_ajax_mpcc_regenerate_question', [$this, 'regenerate_question'], 10);
        add_action('wp_ajax_mpcc_validate_quiz', [$this, 'validate_quiz'], 10);
        add_action('wp_ajax_mpcc_create_quiz_from_lesson', [$this, 'create_quiz_from_lesson'], 10);
        
        // Also register for non-logged-in users (though they shouldn't have access)
        add_action('wp_ajax_nopriv_mpcc_generate_quiz', function() {
            wp_send_json_error('Not authorized', 401);
        });
        
        error_log('MPCC Quiz: AJAX actions registered');
    }
    
    /**
     * Handle quiz generation AJAX request
     * 
     * @return void
     */
    public function generate_quiz(): void
    {
        error_log('MPCC Quiz: generate_quiz method started');
        
        try {
            // Debug logging
            error_log('MPCC Quiz: generate_quiz called');
            error_log('MPCC Quiz: POST data: ' . print_r($_POST, true));
            error_log('MPCC Quiz: Expected nonce action: ' . NonceConstants::QUIZ_AI);
            
            // Try manual nonce verification for debugging
            $nonce = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
            $action = NonceConstants::QUIZ_AI;
            error_log('MPCC Quiz: About to verify nonce - nonce: ' . $nonce . ', action: ' . $action);
            
            $valid = false;
            if (!empty($nonce)) {
                $valid = wp_verify_nonce($nonce, $action);
            }
            
            error_log('MPCC Quiz: Manual nonce check - nonce: ' . $nonce . ', action: ' . $action . ', valid: ' . ($valid ? 'true' : 'false'));
            
            // Verify nonce
            if (!$valid) {
                error_log('MPCC Quiz: Nonce verification failed');
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }
            
            error_log('MPCC Quiz: Nonce verification passed');
            
            // Check user capabilities - use edit_posts which is more standard
            if (!current_user_can('edit_posts')) {
                error_log('MPCC Quiz: User does not have edit_posts capability');
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_INSUFFICIENT_PERMISSIONS, 403);
                return;
            }
            
            error_log('MPCC Quiz: User capability check passed');
            
            // Sanitize and validate input
            $lessonId = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
            $courseId = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            
            error_log('MPCC Quiz: Input - lessonId: ' . $lessonId . ', courseId: ' . $courseId . ', content length: ' . strlen($content));
            
            // Options might come as array or JSON string
            if (isset($_POST['options']) && is_array($_POST['options'])) {
                $options = $_POST['options'];
            } else {
                $options = json_decode(stripslashes($_POST['options'] ?? '{}'), true);
            }
            
            // Sanitize options array
            if (is_array($options)) {
                $options = $this->sanitizeArray($options);
            } else {
                $options = [];
            }
            
            error_log('MPCC Quiz: Options: ' . print_r($options, true));
            
            // Validate input
            if (empty($content) && empty($lessonId) && empty($courseId)) {
                error_log('MPCC Quiz: Validation failed - no content, lesson ID, or course ID');
                ApiResponse::errorMessage('Content, lesson ID, or course ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }
            
            // Get content if lesson or course ID provided
            if ($lessonId > 0) {
                error_log('MPCC Quiz: Getting content for lesson ID: ' . $lessonId);
                $content = $this->getLessonContent($lessonId);
                error_log('MPCC Quiz: Retrieved lesson content length: ' . strlen($content));
            } elseif ($courseId > 0) {
                error_log('MPCC Quiz: Getting content for course ID: ' . $courseId);
                $content = $this->getCourseContent($courseId);
                error_log('MPCC Quiz: Retrieved course content length: ' . strlen($content));
            }
            
            if (empty($content)) {
                error_log('MPCC Quiz: No content available - returning 400 error');
                ApiResponse::errorMessage('No content available to generate quiz from', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }
            
            // Set default count (focusing on multiple-choice only for MVP)
            $count = intval($options['num_questions'] ?? 10);
            
            // Generate quiz using the Quiz AI Service
            $questions = $this->quizAIService->generateMultipleChoiceQuestions($content, $count);
            
            if (empty($questions)) {
                throw new \Exception('Failed to generate quiz questions');
            }
            
            // Format quiz data for response
            $quizData = [
                'questions' => $questions,
                'total' => count($questions),
                'type' => 'multiple-choice'
            ];
            
            // Log successful quiz generation
            $this->logger->info('Quiz generated successfully', [
                'lesson_id' => $lessonId,
                'course_id' => $courseId,
                'num_questions' => count($quizData['questions']),
                'user_id' => get_current_user_id()
            ]);
            
            wp_send_json_success($quizData);
            
        } catch (\Exception $e) {
            error_log('MPCC Quiz: Exception caught - ' . $e->getMessage());
            $this->logger->error('Quiz generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
        } catch (\Throwable $t) {
            error_log('MPCC Quiz: Throwable caught - ' . $t->getMessage());
            wp_send_json_error([
                'message' => 'Fatal error: ' . $t->getMessage()
            ], 500);
        }
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
            if (!current_user_can('edit_courses')) {
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
            
            // For MVP, regenerate by getting a new set of questions and picking one
            // In future, could implement specific question regeneration
            $questions = $this->quizAIService->generateMultipleChoiceQuestions($content, 5);
            
            if (empty($questions)) {
                throw new \Exception('Failed to regenerate question');
            }
            
            // Return the first question as the regenerated one
            $newQuestion = $questions[0];
            
            wp_send_json_success($newQuestion);
            
        } catch (\Exception $e) {
            $this->logger->error('Question regeneration failed', [
                'error' => $e->getMessage()
            ]);
            
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
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
            if (!current_user_can('edit_courses')) {
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
            $this->logger->error('Quiz validation failed', [
                'error' => $e->getMessage()
            ]);
            
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
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
        error_log('MPCC Quiz: getLessonContent called with ID: ' . $lessonId);
        $post = get_post($lessonId);
        
        if (!$post) {
            error_log('MPCC Quiz: No post found with ID: ' . $lessonId);
            return '';
        }
        
        error_log('MPCC Quiz: Post type: ' . $post->post_type);
        error_log('MPCC Quiz: Post status: ' . $post->post_status);
        error_log('MPCC Quiz: Post content length (raw): ' . strlen($post->post_content));
        
        // Also check post excerpt and title
        error_log('MPCC Quiz: Post title: ' . $post->post_title);
        error_log('MPCC Quiz: Post excerpt length: ' . strlen($post->post_excerpt));
        
        if ($post->post_type !== 'mpcs-lesson') {
            error_log('MPCC Quiz: Post is not a lesson, it is: ' . $post->post_type);
            return '';
        }
        
        // Try to get content from various sources
        $content = wp_strip_all_tags($post->post_content);
        
        // If no content, try using title and excerpt
        if (empty($content) && !empty($post->post_title)) {
            error_log('MPCC Quiz: No content found, using title and excerpt as fallback');
            $content = $post->post_title;
            if (!empty($post->post_excerpt)) {
                $content .= "\n\n" . $post->post_excerpt;
            }
            
            // Also try to get content from parent course
            $course_id = get_post_meta($lessonId, '_mpcs_course_id', true);
            if ($course_id) {
                error_log('MPCC Quiz: Found parent course ID: ' . $course_id);
                $course = get_post($course_id);
                if ($course && !empty($course->post_content)) {
                    $content .= "\n\nCourse Context: " . wp_strip_all_tags($course->post_content);
                    error_log('MPCC Quiz: Added course content, new length: ' . strlen($content));
                }
            }
        }
        
        error_log('MPCC Quiz: Content length after stripping tags: ' . strlen($content));
        
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
            if ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false') {
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
                } elseif (!in_array($question['correct_answer'], $question['options'] ?? [])) {
                    $results['errors'][] = "Question {$questionNum}: Correct answer is not in options";
                    $results['valid'] = false;
                }
            } elseif ($question['type'] === 'short_answer') {
                if (empty($question['correct_answer'])) {
                    $results['errors'][] = "Question {$questionNum}: Correct answer is missing";
                    $results['valid'] = false;
                }
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
            
            // Get course ID from lesson
            $courseId = get_post_meta($lessonId, '_mpcs_course_id', true);
            if ($courseId) {
                update_post_meta($quizId, '_mpcs_course_id', $courseId);
            }
            
            // Log the creation
            $this->logger->info('Quiz created from lesson', [
                'quiz_id' => $quizId,
                'lesson_id' => $lessonId,
                'course_id' => $courseId,
                'user_id' => get_current_user_id()
            ]);
            
            // Build edit URL with lesson context
            $editUrl = add_query_arg([
                'post' => $quizId,
                'action' => 'edit',
                'lesson_id' => $lessonId
            ], admin_url('post.php'));
            
            wp_send_json_success([
                'quiz_id' => $quizId,
                'edit_url' => $editUrl,
                'message' => __('Quiz created successfully!', 'memberpress-courses-copilot')
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to create quiz from lesson', [
                'error' => $e->getMessage(),
                'lesson_id' => $lessonId ?? 0
            ]);
            
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
        }
    }
}