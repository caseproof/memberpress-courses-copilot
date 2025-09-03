<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Tests\Framework;

/**
 * Test Data Factory for creating realistic test data
 * 
 * This class provides methods to generate test data for various
 * WordPress and MemberPress entities used in tests.
 * 
 * @package MemberPressCoursesCopilot\Tests\Framework
 */
class TestDataFactory
{
    /**
     * ID counter for generating unique IDs
     */
    private static int $idCounter = 1;
    
    /**
     * Create a test user
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array User data
     */
    public static function createUser(array $overrides = []): array
    {
        $defaults = [
            'ID' => self::getNextId(),
            'user_login' => 'testuser_' . self::$idCounter,
            'user_email' => 'test' . self::$idCounter . '@example.com',
            'display_name' => 'Test User ' . self::$idCounter,
            'user_nicename' => 'test-user-' . self::$idCounter,
            'user_registered' => date('Y-m-d H:i:s'),
            'user_status' => 0,
            'caps' => ['subscriber' => true],
            'allcaps' => ['read' => true]
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create a test post
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Post data
     */
    public static function createPost(array $overrides = []): array
    {
        $id = self::getNextId();
        $defaults = [
            'ID' => $id,
            'post_author' => 1,
            'post_date' => date('Y-m-d H:i:s'),
            'post_date_gmt' => gmdate('Y-m-d H:i:s'),
            'post_content' => 'Test content for post ' . $id,
            'post_title' => 'Test Post ' . $id,
            'post_excerpt' => 'Test excerpt for post ' . $id,
            'post_status' => 'publish',
            'comment_status' => 'open',
            'ping_status' => 'open',
            'post_password' => '',
            'post_name' => 'test-post-' . $id,
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => date('Y-m-d H:i:s'),
            'post_modified_gmt' => gmdate('Y-m-d H:i:s'),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => 'http://test.local/?p=' . $id,
            'menu_order' => 0,
            'post_type' => 'post',
            'post_mime_type' => '',
            'comment_count' => 0
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create a test course
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Course data
     */
    public static function createCourse(array $overrides = []): array
    {
        $defaults = [
            'post_type' => 'mpcs-course',
            'post_title' => 'Test Course ' . self::$idCounter,
            'post_content' => 'This is a test course with comprehensive content.',
            'meta' => [
                '_mpcs_course_settings' => [
                    'enrollment_type' => 'open',
                    'completion_type' => 'manual',
                    'certificate_enabled' => false
                ]
            ]
        ];
        
        return self::createPost(array_merge($defaults, $overrides));
    }
    
    /**
     * Create a test lesson
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Lesson data
     */
    public static function createLesson(array $overrides = []): array
    {
        $defaults = [
            'post_type' => 'mpcs-lesson',
            'post_title' => 'Test Lesson ' . self::$idCounter,
            'post_content' => 'This is a test lesson with educational content.',
            'meta' => [
                '_mpcs_lesson_settings' => [
                    'duration' => 600, // 10 minutes
                    'has_quiz' => false,
                    'order' => self::$idCounter
                ]
            ]
        ];
        
        return self::createPost(array_merge($defaults, $overrides));
    }
    
    /**
     * Create a test quiz
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Quiz data
     */
    public static function createQuiz(array $overrides = []): array
    {
        $defaults = [
            'lesson_id' => self::getNextId(),
            'questions' => [
                self::createQuizQuestion(),
                self::createQuizQuestion(['type' => 'multiple_choice']),
                self::createQuizQuestion(['type' => 'true_false'])
            ],
            'settings' => [
                'passing_score' => 70,
                'time_limit' => 0,
                'attempts_allowed' => 0,
                'randomize_questions' => false
            ]
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create a test quiz question
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Question data
     */
    public static function createQuizQuestion(array $overrides = []): array
    {
        $id = self::getNextId();
        $defaults = [
            'id' => $id,
            'type' => 'single_choice',
            'question' => 'Test Question ' . $id . '?',
            'options' => [
                ['id' => 'a', 'text' => 'Option A', 'correct' => true],
                ['id' => 'b', 'text' => 'Option B', 'correct' => false],
                ['id' => 'c', 'text' => 'Option C', 'correct' => false],
                ['id' => 'd', 'text' => 'Option D', 'correct' => false]
            ],
            'explanation' => 'The correct answer is A because it is the right choice.',
            'points' => 10
        ];
        
        if (isset($overrides['type'])) {
            switch ($overrides['type']) {
                case 'multiple_choice':
                    $defaults['options'] = [
                        ['id' => 'a', 'text' => 'Option A', 'correct' => true],
                        ['id' => 'b', 'text' => 'Option B', 'correct' => true],
                        ['id' => 'c', 'text' => 'Option C', 'correct' => false],
                        ['id' => 'd', 'text' => 'Option D', 'correct' => false]
                    ];
                    break;
                    
                case 'true_false':
                    $defaults['options'] = [
                        ['id' => 'true', 'text' => 'True', 'correct' => true],
                        ['id' => 'false', 'text' => 'False', 'correct' => false]
                    ];
                    break;
                    
                case 'essay':
                    $defaults['options'] = [];
                    $defaults['sample_answer'] = 'This is a sample answer for the essay question.';
                    break;
            }
        }
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test Copilot settings
     * 
     * @param array $overrides Custom settings to override defaults
     * @return array Settings data
     */
    public static function createCopilotSettings(array $overrides = []): array
    {
        $defaults = [
            'enabled' => true,
            'openai_api_key' => 'sk-test-' . bin2hex(random_bytes(16)),
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.7,
            'max_tokens' => 150,
            'features' => [
                'chat' => true,
                'quiz_generation' => true,
                'content_suggestions' => true
            ],
            'limits' => [
                'daily_requests' => 100,
                'max_conversation_length' => 50
            ]
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create a test chat message
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Message data
     */
    public static function createChatMessage(array $overrides = []): array
    {
        $defaults = [
            'id' => self::getNextId(),
            'conversation_id' => 'conv_' . bin2hex(random_bytes(8)),
            'role' => 'user',
            'content' => 'This is a test message.',
            'timestamp' => time(),
            'user_id' => 1,
            'lesson_id' => null,
            'metadata' => []
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test API response
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array API response data
     */
    public static function createApiResponse(array $overrides = []): array
    {
        $defaults = [
            'id' => 'chatcmpl-' . bin2hex(random_bytes(12)),
            'object' => 'chat.completion',
            'created' => time(),
            'model' => 'gpt-3.5-turbo',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'This is a test response from the AI.'
                    ],
                    'finish_reason' => 'stop'
                ]
            ],
            'usage' => [
                'prompt_tokens' => 50,
                'completion_tokens' => 25,
                'total_tokens' => 75
            ]
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test transient data
     * 
     * @param string $key Transient key
     * @param mixed $value Transient value
     * @param int $expiration Expiration time in seconds
     * @return array Transient data
     */
    public static function createTransient(string $key, $value, int $expiration = 3600): array
    {
        return [
            'key' => $key,
            'value' => $value,
            'expiration' => time() + $expiration,
            'created' => time()
        ];
    }
    
    /**
     * Create test nonce
     * 
     * @param string $action Nonce action
     * @return string Nonce value
     */
    public static function createNonce(string $action): string
    {
        // Create a predictable nonce for testing
        return substr(md5($action . 'test-nonce-salt'), 0, 10);
    }
    
    /**
     * Create test AJAX request data
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array Request data
     */
    public static function createAjaxRequest(array $overrides = []): array
    {
        $defaults = [
            'action' => 'test_ajax_action',
            'nonce' => self::createNonce('test_ajax_action'),
            'data' => [
                'test' => 'value'
            ]
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Create test file upload data
     * 
     * @param array $overrides Custom properties to override defaults
     * @return array File data
     */
    public static function createFileUpload(array $overrides = []): array
    {
        $defaults = [
            'name' => 'test-file.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/test-file-' . self::getNextId(),
            'error' => 0,
            'size' => 1024
        ];
        
        return array_merge($defaults, $overrides);
    }
    
    /**
     * Reset the ID counter
     */
    public static function reset(): void
    {
        self::$idCounter = 1;
    }
    
    /**
     * Get the next available ID
     * 
     * @return int
     */
    private static function getNextId(): int
    {
        return self::$idCounter++;
    }
    
    /**
     * Create multiple instances of data
     * 
     * @param callable $factory Factory method to call
     * @param int $count Number of instances to create
     * @param array $overrides Overrides to apply to each instance
     * @return array Array of created instances
     */
    public static function createMany(callable $factory, int $count, array $overrides = []): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = $factory($overrides);
        }
        return $items;
    }
}