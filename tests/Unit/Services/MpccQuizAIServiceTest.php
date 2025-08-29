<?php
/**
 * Tests for MpccQuizAIService
 *
 * @package MemberPressCoursesCopilot\Tests\Unit\Services
 */

namespace MemberPressCoursesCopilot\Tests\Unit\Services;

use MemberPressCoursesCopilot\Services\MpccQuizAIService;
use MemberPressCoursesCopilot\Services\OpenAIService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test class for MpccQuizAIService
 */
class MpccQuizAIServiceTest extends TestCase {
    /**
     * @var MpccQuizAIService
     */
    private $service;

    /**
     * @var MockObject|OpenAIService
     */
    private $openAIServiceMock;

    /**
     * Set up test fixtures
     */
    protected function setUp(): void {
        parent::setUp();
        
        $this->openAIServiceMock = $this->createMock(OpenAIService::class);
        $this->service = new MpccQuizAIService($this->openAIServiceMock);
    }

    /**
     * Test generating multiple choice questions from content
     */
    public function test_generate_questions_returns_array_of_questions() {
        // Arrange
        $content = "The water cycle consists of evaporation, condensation, and precipitation. " .
                  "Evaporation occurs when water changes from liquid to vapor. " .
                  "Condensation happens when water vapor forms clouds. " .
                  "Precipitation includes rain, snow, sleet, and hail.";
        
        $num_questions = 3;
        
        $expected_api_response = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            [
                                'question' => 'What are the three main stages of the water cycle?',
                                'options' => [
                                    'A' => 'Evaporation, condensation, precipitation',
                                    'B' => 'Melting, freezing, boiling',
                                    'C' => 'Rain, snow, hail',
                                    'D' => 'Absorption, reflection, refraction'
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'The three main stages of the water cycle are evaporation, condensation, and precipitation.'
                            ],
                            [
                                'question' => 'What happens during evaporation?',
                                'options' => [
                                    'A' => 'Water vapor forms clouds',
                                    'B' => 'Water falls from the sky',
                                    'C' => 'Water changes from liquid to vapor',
                                    'D' => 'Water freezes into ice'
                                ],
                                'correct_answer' => 'C',
                                'explanation' => 'During evaporation, water changes from liquid to vapor.'
                            ],
                            [
                                'question' => 'Which of the following is NOT a form of precipitation?',
                                'options' => [
                                    'A' => 'Rain',
                                    'B' => 'Evaporation',
                                    'C' => 'Snow',
                                    'D' => 'Hail'
                                ],
                                'correct_answer' => 'B',
                                'explanation' => 'Evaporation is the process of water changing to vapor, not a form of precipitation.'
                            ]
                        ])
                    ]
                ]
            ]
        ];
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function($messages) {
                    return is_array($messages) && 
                           count($messages) === 2 &&
                           $messages[0]['role'] === 'system' &&
                           $messages[1]['role'] === 'user';
                }),
                $this->equalTo([
                    'model' => 'gpt-4o-mini',
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.7,
                    'max_tokens' => 2000
                ])
            )
            ->willReturn($expected_api_response);
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertCount(3, $questions);
        
        foreach ($questions as $question) {
            $this->assertArrayHasKey('question', $question);
            $this->assertArrayHasKey('options', $question);
            $this->assertArrayHasKey('correct_answer', $question);
            $this->assertArrayHasKey('explanation', $question);
            
            $this->assertIsString($question['question']);
            $this->assertIsArray($question['options']);
            $this->assertCount(4, $question['options']);
            $this->assertContains($question['correct_answer'], ['A', 'B', 'C', 'D']);
            $this->assertIsString($question['explanation']);
        }
    }

    /**
     * Test handling API errors gracefully
     */
    public function test_generate_questions_handles_api_error() {
        // Arrange
        $content = "Test content";
        $num_questions = 2;
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->willThrowException(new \Exception('API error: Rate limit exceeded'));
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    /**
     * Test handling malformed API response
     */
    public function test_generate_questions_handles_malformed_response() {
        // Arrange
        $content = "Test content";
        $num_questions = 2;
        
        $malformed_response = [
            'choices' => [
                [
                    'message' => [
                        'content' => 'This is not valid JSON'
                    ]
                ]
            ]
        ];
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->willReturn($malformed_response);
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    /**
     * Test handling missing required fields in API response
     */
    public function test_generate_questions_validates_question_structure() {
        // Arrange
        $content = "Test content";
        $num_questions = 2;
        
        $invalid_questions_response = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            [
                                'question' => 'Valid question?',
                                'options' => [
                                    'A' => 'Option 1',
                                    'B' => 'Option 2',
                                    'C' => 'Option 3',
                                    'D' => 'Option 4'
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'This is valid'
                            ],
                            [
                                // Missing 'question' field
                                'options' => [
                                    'A' => 'Option 1',
                                    'B' => 'Option 2',
                                    'C' => 'Option 3',
                                    'D' => 'Option 4'
                                ],
                                'correct_answer' => 'B',
                                'explanation' => 'Missing question field'
                            ],
                            [
                                'question' => 'Invalid options?',
                                'options' => [
                                    'A' => 'Option 1',
                                    'B' => 'Option 2'
                                    // Only 2 options instead of 4
                                ],
                                'correct_answer' => 'A',
                                'explanation' => 'Not enough options'
                            ],
                            [
                                'question' => 'Invalid answer?',
                                'options' => [
                                    'A' => 'Option 1',
                                    'B' => 'Option 2',
                                    'C' => 'Option 3',
                                    'D' => 'Option 4'
                                ],
                                'correct_answer' => 'E', // Invalid answer
                                'explanation' => 'Answer not in options'
                            ]
                        ])
                    ]
                ]
            ]
        ];
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->willReturn($invalid_questions_response);
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertCount(1, $questions); // Only the first valid question should be included
        $this->assertEquals('Valid question?', $questions[0]['question']);
    }

    /**
     * Test prompt building includes all required elements
     */
    public function test_prompt_includes_all_required_elements() {
        // Arrange
        $content = "Test content for prompt";
        $num_questions = 5;
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function($messages) use ($content, $num_questions) {
                    $system_message = $messages[0]['content'];
                    $user_message = $messages[1]['content'];
                    
                    // Check system message contains key instructions
                    $this->assertStringContainsString('multiple-choice questions', $system_message);
                    $this->assertStringContainsString('JSON format', $system_message);
                    $this->assertStringContainsString('exactly 4 options', $system_message);
                    $this->assertStringContainsString('labeled A, B, C, and D', $system_message);
                    
                    // Check user message contains content and number
                    $this->assertStringContainsString($content, $user_message);
                    $this->assertStringContainsString((string)$num_questions, $user_message);
                    
                    return true;
                }),
                $this->anything()
            )
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([])
                        ]
                    ]
                ]
            ]);
        
        // Act
        $this->service->generate_questions($content, $num_questions);
        
        // Assert - expectations are set in the callback above
    }

    /**
     * Test handling empty content
     */
    public function test_generate_questions_handles_empty_content() {
        // Arrange
        $content = "";
        $num_questions = 3;
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    /**
     * Test handling zero questions requested
     */
    public function test_generate_questions_handles_zero_questions() {
        // Arrange
        $content = "Test content";
        $num_questions = 0;
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    /**
     * Test handling negative number of questions
     */
    public function test_generate_questions_handles_negative_questions() {
        // Arrange
        $content = "Test content";
        $num_questions = -5;
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions);
    }

    /**
     * Test that service limits extremely large question requests
     */
    public function test_generate_questions_limits_large_requests() {
        // Arrange
        $content = "Test content";
        $num_questions = 100; // Requesting too many questions
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->with(
                $this->callback(function($messages) {
                    $user_message = $messages[1]['content'];
                    // Should limit to maximum of 20 questions
                    $this->assertStringContainsString('20', $user_message);
                    return true;
                }),
                $this->anything()
            )
            ->willReturn([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([])
                        ]
                    ]
                ]
            ]);
        
        // Act
        $this->service->generate_questions($content, $num_questions);
        
        // Assert - expectations are set in the callback above
    }

    /**
     * Test response parsing handles non-array response
     */
    public function test_parse_response_handles_non_array_json() {
        // Arrange
        $content = "Test content";
        $num_questions = 2;
        
        $non_array_response = [
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'questions' => [ // Wrapped in object instead of direct array
                                [
                                    'question' => 'Test?',
                                    'options' => ['A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D'],
                                    'correct_answer' => 'A',
                                    'explanation' => 'Test'
                                ]
                            ]
                        ])
                    ]
                ]
            ]
        ];
        
        $this->openAIServiceMock->expects($this->once())
            ->method('chat')
            ->willReturn($non_array_response);
        
        // Act
        $questions = $this->service->generate_questions($content, $num_questions);
        
        // Assert
        $this->assertIsArray($questions);
        $this->assertEmpty($questions); // Should return empty as format is unexpected
    }
}