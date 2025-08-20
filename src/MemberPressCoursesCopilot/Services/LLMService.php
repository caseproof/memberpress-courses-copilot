<?php

namespace MemberPressCoursesCopilot\Services;

/**
 * LLM Service
 * 
 * Provides a simple interface to the LiteLLM proxy with hardcoded credentials
 * exactly like MemberPress Copilot, without any user-facing configuration.
 */
class LLMService
{
    // Hardcoded proxy configuration (same as MemberPress Copilot)
    private const PROXY_URL = 'https://wp-ai-proxy-production-9a5aceb50dde.herokuapp.com';
    private const MASTER_KEY = 'sk-litellm-EkFY6Wgp9MaDGjbrkCQx4qmbSH4wa0XrEVJmklFcYgw=';
    
    /**
     * Make a simple request to the AI service
     */
    public function generateContent(string $prompt, string $contentType = 'general', array $options = []): array
    {
        $provider = $this->getProviderForContentType($contentType);
        $model = $this->getModelForProvider($provider, $contentType);
        
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens' => $options['max_tokens'] ?? 2000
        ];
        
        error_log('MPCC LLMService: Making request to ' . self::PROXY_URL . '/chat/completions');
        error_log('MPCC LLMService: Using model ' . $model . ' (provider: ' . $provider . ')');
        error_log('MPCC LLMService: Payload: ' . json_encode($payload));
        
        $response = wp_remote_post(self::PROXY_URL . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . self::MASTER_KEY,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => $options['timeout'] ?? 60
        ]);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('MPCC LLMService ERROR: WP_Error - ' . $error_message);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        $responseCode = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        
        error_log('MPCC LLMService: Response code: ' . $responseCode);
        error_log('MPCC LLMService: Response body: ' . $responseBody);
        
        if ($responseCode >= 400) {
            $error_message = "API error {$responseCode}: {$responseBody}";
            error_log('MPCC LLMService ERROR: ' . $error_message);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        $data = json_decode($responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $error_message = 'Invalid JSON response: ' . json_last_error_msg();
            error_log('MPCC LLMService ERROR: ' . $error_message);
            error_log('MPCC LLMService: Raw response body: ' . $responseBody);
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        if (!isset($data['choices'][0]['message']['content'])) {
            $error_message = 'Unexpected response format - no content in choices';
            error_log('MPCC LLMService ERROR: ' . $error_message);
            error_log('MPCC LLMService: Response structure: ' . json_encode($data));
            return [
                'error' => true,
                'message' => $error_message,
                'content' => ''
            ];
        }
        
        error_log('MPCC LLMService SUCCESS: Generated ' . strlen($data['choices'][0]['message']['content']) . ' characters of content');
        
        return [
            'error' => false,
            'content' => $data['choices'][0]['message']['content'],
            'usage' => $data['usage'] ?? [],
            'model' => $model,
            'provider' => $provider
        ];
    }
    
    /**
     * Determine course template type from user input
     */
    public function determineTemplateType(string $userInput): ?string
    {
        $prompt = "Analyze the following course description and determine which template type best fits:\n\nCourse Description: {$userInput}\n\nAvailable template types:\n- technical: Programming, software development, technical skills\n- business: Marketing, management, entrepreneurship, business skills\n- creative: Art, design, music, creative writing, visual arts\n- academic: Research, theory, formal education, scholarly content\n\nRespond with only the template type name (technical, business, creative, or academic). If none fit well, respond with 'general'.";
        
        $response = $this->generateContent($prompt, 'content_analysis', [
            'temperature' => 0.3,
            'max_tokens' => 100
        ]);
        
        if ($response['error']) {
            error_log('MPCC LLMService ERROR in determineTemplateType: ' . $response['message']);
            throw new \Exception('Failed to determine template type: ' . $response['message']);
        }
        
        return $this->parseTemplateResponse($response['content']);
    }
    
    /**
     * Extract course requirements from user message
     */
    public function extractCourseRequirements(string $message): array
    {
        $prompt = "Extract course requirements from this user message:\n\nMessage: {$message}\n\nExtract and format as JSON:\n{\n  \"title\": \"Course title\",\n  \"description\": \"Course description\",\n  \"difficulty_level\": \"beginner|intermediate|advanced\",\n  \"target_audience\": \"Who this course is for\",\n  \"learning_objectives\": [\"objective1\", \"objective2\"],\n  \"estimated_duration\": \"X hours/weeks\",\n  \"prerequisites\": [\"prerequisite1\", \"prerequisite2\"],\n  \"topics\": [\"topic1\", \"topic2\"]\n}\n\nRespond with only the JSON object.";
        
        $response = $this->generateContent($prompt, 'structured_analysis', [
            'temperature' => 0.2,
            'max_tokens' => 1500
        ]);
        
        if ($response['error']) {
            error_log('MPCC LLMService ERROR in extractCourseRequirements: ' . $response['message']);
            throw new \Exception('Failed to extract course requirements: ' . $response['message']);
        }
        
        $extracted = json_decode(trim($response['content']), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($extracted)) {
            return array_merge($extracted, [
                'raw_input' => $message,
                'timestamp' => current_time('timestamp'),
                'extraction_method' => 'ai'
            ]);
        }
        
        error_log('MPCC LLMService ERROR: Failed to parse AI response as JSON. Response: ' . $response['content']);
        throw new \Exception('Failed to parse AI response as valid JSON');
    }
    
    /**
     * Generate lesson content
     */
    public function generateLessonContent(string $sectionTitle, int $lessonNumber, array $requirements): string
    {
        $courseTitle = $requirements['course_title'] ?? 'Course';
        $lessonTitle = $requirements['lesson_title'] ?? "Lesson {$lessonNumber}";
        $difficulty = $requirements['difficulty_level'] ?? 'intermediate';
        $audience = $requirements['target_audience'] ?? 'general learners';
        
        $prompt = "Generate comprehensive lesson content for:\n\nCourse: {$courseTitle}\nSection: {$sectionTitle}\nLesson {$lessonNumber}: {$lessonTitle}\nDifficulty: {$difficulty}\nAudience: {$audience}\n\nCreate engaging, educational content that includes:\n1. Learning objectives for this lesson\n2. Key concepts and explanations\n3. Practical examples or case studies\n4. Step-by-step instructions where applicable\n5. Summary of key takeaways\n\nMake the content engaging, clear, and appropriate for the difficulty level. Include practical applications and real-world examples.";
        
        $response = $this->generateContent($prompt, 'lesson_content', [
            'temperature' => 0.7,
            'max_tokens' => 6000
        ]);
        
        if ($response['error']) {
            error_log('MPCC LLMService ERROR in generateLessonContent: ' . $response['message']);
            throw new \Exception('Failed to generate lesson content: ' . $response['message']);
        }
        
        return trim($response['content']);
    }
    
    /**
     * Get provider for content type
     */
    private function getProviderForContentType(string $contentType): string
    {
        switch ($contentType) {
            case 'content_analysis':
            case 'lesson_content':
            case 'course_outline':
            case 'advanced_content':
            case 'case_studies':
            case 'project_content':
            case 'content_optimization':
            case 'personalized_content':
                return 'anthropic';
                
            case 'structured_analysis':
            case 'quiz_questions':
            case 'interactive_exercises':
            case 'assessment_rubric':
            case 'learning_activities':
                return 'openai';
                
            default:
                return 'anthropic';
        }
    }
    
    /**
     * Get model for provider and content type
     */
    private function getModelForProvider(string $provider, string $contentType): string
    {
        switch ($provider) {
            case 'anthropic':
                // Use Claude 3.5 Sonnet which is available on the LiteLLM proxy
                return 'claude-3-5-sonnet-20241022';
                
            case 'openai':
                if (in_array($contentType, ['structured_analysis', 'assessment_rubric'])) {
                    return 'gpt-4';
                } else {
                    return 'gpt-3.5-turbo';
                }
                
            default:
                return 'gpt-3.5-turbo'; // Safe default that's available
        }
    }
    
    /**
     * Parse template response
     */
    private function parseTemplateResponse(string $response): ?string
    {
        $response = trim(strtolower($response));
        $validTypes = ['technical', 'business', 'creative', 'academic', 'general'];
        
        foreach ($validTypes as $type) {
            if (strpos($response, $type) !== false) {
                return $type === 'general' ? null : $type;
            }
        }
        
        return null;
    }
    
}