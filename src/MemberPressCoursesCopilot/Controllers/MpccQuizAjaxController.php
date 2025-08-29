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
        // Register AJAX handlers for quiz generation
        add_action('wp_ajax_mpcc_generate_quiz', [$this, 'generate_quiz']);
        add_action('wp_ajax_mpcc_regenerate_question', [$this, 'regenerate_question']);
        add_action('wp_ajax_mpcc_validate_quiz', [$this, 'validate_quiz']);
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_INTERFACE, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('edit_courses')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_UNAUTHORIZED, 403);
                return;
            }
            
            // Sanitize and validate input
            $lessonId = isset($_POST['lesson_id']) ? absint($_POST['lesson_id']) : 0;
            $courseId = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
            $content = sanitize_textarea_field($_POST['content'] ?? '');
            $options = json_decode(stripslashes($_POST['options'] ?? '{}'), true);
            
            // Sanitize options array
            if (is_array($options)) {
                $options = $this->sanitizeArray($options);
            } else {
                $options = [];
            }
            
            // Validate input
            if (empty($content) && empty($lessonId) && empty($courseId)) {
                ApiResponse::errorMessage('Content, lesson ID, or course ID is required', ApiResponse::ERROR_MISSING_PARAMETER);
                return;
            }
            
            // Get content if lesson or course ID provided
            if ($lessonId > 0) {
                $content = $this->getLessonContent($lessonId);
            } elseif ($courseId > 0) {
                $content = $this->getCourseContent($courseId);
            }
            
            if (empty($content)) {
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
            $this->logger->error('Quiz generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $error = ApiResponse::exceptionToError($e, ApiResponse::ERROR_GENERAL);
            ApiResponse::error($error);
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_INTERFACE, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('edit_courses')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_UNAUTHORIZED, 403);
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
            if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AI_INTERFACE, false)) {
                ApiResponse::errorMessage('Security check failed', ApiResponse::ERROR_INVALID_NONCE, 403);
                return;
            }
            
            // Check user capabilities
            if (!current_user_can('edit_courses')) {
                ApiResponse::errorMessage('Insufficient permissions', ApiResponse::ERROR_UNAUTHORIZED, 403);
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
        $post = get_post($lessonId);
        
        if (!$post || $post->post_type !== 'mpcs-lesson') {
            return '';
        }
        
        return wp_strip_all_tags($post->post_content);
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
}