<?php
/**
 * Course Content Router for MemberPress Courses Copilot
 *
 * Implements content-aware provider selection to route different types
 * of course content to the most appropriate LLM provider.
 *
 * @package MemberPressCoursesCopilot
 */

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Router for content-aware provider selection
 */
class CourseContentRouter {
    /**
     * Logger instance
     *
     * @var Logger
     */
    private $logger;

    /**
     * Proxy configuration service
     *
     * @var ProxyConfigService
     */
    private $proxyConfig;

    /**
     * Content type to provider mapping
     *
     * @var array
     */
    private $contentTypeMapping = [
        'course_outline' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229',
            'reason' => 'Anthropic excels at structured content creation and educational planning'
        ],
        'lesson_content' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229',
            'reason' => 'Anthropic provides high-quality, engaging educational content'
        ],
        'quiz_questions' => [
            'provider' => 'openai',
            'model' => 'gpt-4',
            'reason' => 'OpenAI handles structured data generation well for assessments'
        ],
        'assignment' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229',
            'reason' => 'Anthropic creates thoughtful, well-designed assignments'
        ],
        'course_metadata' => [
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'reason' => 'OpenAI efficiently handles metadata and structured information'
        ],
        'content_analysis' => [
            'provider' => 'openai',
            'model' => 'gpt-4',
            'reason' => 'OpenAI excels at content analysis and data processing'
        ]
    ];

    /**
     * Provider fallback chain
     *
     * @var array
     */
    private $fallbackChain = [
        'anthropic' => [
            'provider' => 'openai',
            'model' => 'gpt-4'
        ],
        'openai' => [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229'
        ]
    ];

    /**
     * Provider capabilities matrix
     *
     * @var array
     */
    private $providerCapabilities = [
        'anthropic' => [
            'strengths' => [
                'creative_writing',
                'educational_content',
                'structured_thinking',
                'detailed_explanations',
                'course_design'
            ],
            'content_types' => [
                'course_outline',
                'lesson_content',
                'assignment',
                'creative_exercises'
            ],
            'max_tokens' => 100000,
            'models' => [
                'claude-3-opus-20240229',
                'claude-3-sonnet-20240229',
                'claude-3-haiku-20240307'
            ]
        ],
        'openai' => [
            'strengths' => [
                'structured_data',
                'json_generation',
                'function_calling',
                'data_analysis',
                'quick_responses'
            ],
            'content_types' => [
                'quiz_questions',
                'course_metadata',
                'content_analysis',
                'structured_assessments'
            ],
            'max_tokens' => 4096,
            'models' => [
                'gpt-4',
                'gpt-4-turbo-preview',
                'gpt-3.5-turbo'
            ]
        ]
    ];

    /**
     * Content complexity scoring weights
     *
     * @var array
     */
    private $complexityWeights = [
        'word_count' => 0.3,
        'technical_terms' => 0.2,
        'structure_requirements' => 0.3,
        'creativity_requirements' => 0.2
    ];

    /**
     * Constructor
     *
     * @param ProxyConfigService $proxyConfig Proxy configuration service
     * @param Logger $logger Logger instance
     */
    public function __construct(ProxyConfigService $proxyConfig, Logger $logger) {
        $this->proxyConfig = $proxyConfig;
        $this->logger = $logger;
    }

    /**
     * Get the appropriate provider configuration for a content type
     *
     * @param string $contentType Type of content to generate
     * @param array $context Additional context for routing decisions
     * @return array Provider configuration
     */
    public function getProviderForContentType(string $contentType, array $context = []): array {
        // Check if content type has explicit mapping
        if (isset($this->contentTypeMapping[$contentType])) {
            $config = $this->contentTypeMapping[$contentType];
        } else {
            // Use intelligent routing based on content characteristics
            $config = $this->routeByContentCharacteristics($contentType, $context);
        }

        // Ensure the provider is available in the proxy
        $availableProviders = $this->proxyConfig->getAvailableProviders();
        if (!in_array($config['provider'], $availableProviders)) {
            $this->logger->warning('CourseContentRouter: Primary provider not available, using fallback', [
                'content_type' => $contentType,
                'requested_provider' => $config['provider'],
                'available_providers' => $availableProviders
            ]);

            $config = $this->getFallbackProvider($contentType);
        }

        // Add provider-specific options
        $config['options'] = $this->getProviderOptions($config['provider'], $contentType, $context);

        $this->logger->debug('CourseContentRouter: Provider selected', [
            'content_type' => $contentType,
            'provider' => $config['provider'],
            'model' => $config['model'],
            'reason' => $config['reason'] ?? 'Fallback or intelligent routing'
        ]);

        return $config;
    }

    /**
     * Get fallback provider configuration
     *
     * @param string $contentType Content type
     * @return array Fallback provider configuration
     */
    public function getFallbackProvider(string $contentType): array {
        $primaryProvider = $this->contentTypeMapping[$contentType]['provider'] ?? 'anthropic';
        
        if (isset($this->fallbackChain[$primaryProvider])) {
            $fallback = $this->fallbackChain[$primaryProvider];
            $fallback['reason'] = 'Fallback from ' . $primaryProvider;
            return $fallback;
        }

        // Ultimate fallback
        return [
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'reason' => 'Ultimate fallback provider'
        ];
    }

    /**
     * Route content based on characteristics when no explicit mapping exists
     *
     * @param string $contentType Content type
     * @param array $context Context information
     * @return array Provider configuration
     */
    private function routeByContentCharacteristics(string $contentType, array $context): array {
        $complexity = $this->calculateContentComplexity($context);
        $needsCreativity = $this->requiresCreativity($contentType, $context);
        $needsStructure = $this->requiresStructuredOutput($contentType, $context);

        $this->logger->debug('CourseContentRouter: Analyzing content characteristics', [
            'content_type' => $contentType,
            'complexity' => $complexity,
            'needs_creativity' => $needsCreativity,
            'needs_structure' => $needsStructure
        ]);

        // Route based on characteristics
        if ($needsCreativity && $complexity > 0.6) {
            return [
                'provider' => 'anthropic',
                'model' => 'claude-3-sonnet-20240229',
                'reason' => 'High creativity and complexity requirements'
            ];
        }

        if ($needsStructure && !$needsCreativity) {
            return [
                'provider' => 'openai',
                'model' => 'gpt-4',
                'reason' => 'Structured output with low creativity requirements'
            ];
        }

        if ($complexity < 0.4) {
            return [
                'provider' => 'openai',
                'model' => 'gpt-3.5-turbo',
                'reason' => 'Low complexity content'
            ];
        }

        // Default to Anthropic for educational content
        return [
            'provider' => 'anthropic',
            'model' => 'claude-3-sonnet-20240229',
            'reason' => 'Default for educational content'
        ];
    }

    /**
     * Calculate content complexity score
     *
     * @param array $context Context information
     * @return float Complexity score (0-1)
     */
    private function calculateContentComplexity(array $context): float {
        $score = 0.0;

        // Word count complexity
        $wordCount = $context['expected_word_count'] ?? 500;
        $wordComplexity = min($wordCount / 2000, 1.0);
        $score += $wordComplexity * $this->complexityWeights['word_count'];

        // Technical terms
        $technicalTerms = count($context['technical_terms'] ?? []);
        $technicalComplexity = min($technicalTerms / 10, 1.0);
        $score += $technicalComplexity * $this->complexityWeights['technical_terms'];

        // Structure requirements
        $structureRequirements = count($context['structure_requirements'] ?? []);
        $structureComplexity = min($structureRequirements / 5, 1.0);
        $score += $structureComplexity * $this->complexityWeights['structure_requirements'];

        // Creativity requirements
        $creativityLevel = $context['creativity_level'] ?? 'medium';
        $creativityComplexity = $this->getCreativityScore($creativityLevel);
        $score += $creativityComplexity * $this->complexityWeights['creativity_requirements'];

        return min($score, 1.0);
    }

    /**
     * Check if content type requires creativity
     *
     * @param string $contentType Content type
     * @param array $context Context information
     * @return bool True if creativity is required
     */
    private function requiresCreativity(string $contentType, array $context): bool {
        $creativeContentTypes = [
            'lesson_content',
            'course_outline',
            'assignment',
            'creative_exercises',
            'storytelling',
            'case_studies'
        ];

        if (in_array($contentType, $creativeContentTypes)) {
            return true;
        }

        // Check context for creativity indicators
        $creativityLevel = $context['creativity_level'] ?? 'medium';
        return in_array($creativityLevel, ['high', 'very_high']);
    }

    /**
     * Check if content type requires structured output
     *
     * @param string $contentType Content type
     * @param array $context Context information
     * @return bool True if structured output is required
     */
    private function requiresStructuredOutput(string $contentType, array $context): bool {
        $structuredContentTypes = [
            'quiz_questions',
            'course_metadata',
            'content_analysis',
            'assessments',
            'rubrics',
            'taxonomies'
        ];

        if (in_array($contentType, $structuredContentTypes)) {
            return true;
        }

        // Check context for structure requirements
        return !empty($context['output_format']) && $context['output_format'] === 'json';
    }

    /**
     * Get creativity score for a given level
     *
     * @param string $level Creativity level
     * @return float Creativity score (0-1)
     */
    private function getCreativityScore(string $level): float {
        $scores = [
            'very_low' => 0.0,
            'low' => 0.2,
            'medium' => 0.5,
            'high' => 0.8,
            'very_high' => 1.0
        ];

        return $scores[$level] ?? 0.5;
    }

    /**
     * Get provider-specific options
     *
     * @param string $provider Provider name
     * @param string $contentType Content type
     * @param array $context Context information
     * @return array Provider options
     */
    private function getProviderOptions(string $provider, string $contentType, array $context): array {
        $options = [];

        switch ($provider) {
            case 'anthropic':
                $options = $this->getAnthropicOptions($contentType, $context);
                break;
            case 'openai':
                $options = $this->getOpenAIOptions($contentType, $context);
                break;
        }

        return $options;
    }

    /**
     * Get Anthropic-specific options
     *
     * @param string $contentType Content type
     * @param array $context Context information
     * @return array Anthropic options
     */
    private function getAnthropicOptions(string $contentType, array $context): array {
        $options = [];

        // Adjust temperature based on creativity requirements
        if ($this->requiresCreativity($contentType, $context)) {
            $options['temperature'] = 0.8;
        } else {
            $options['temperature'] = 0.6;
        }

        // Adjust max tokens based on content type
        switch ($contentType) {
            case 'course_outline':
                $options['max_tokens'] = 4000;
                break;
            case 'lesson_content':
                $options['max_tokens'] = 6000;
                break;
            default:
                $options['max_tokens'] = 2500;
        }

        return $options;
    }

    /**
     * Get OpenAI-specific options
     *
     * @param string $contentType Content type
     * @param array $context Context information
     * @return array OpenAI options
     */
    private function getOpenAIOptions(string $contentType, array $context): array {
        $options = [];

        // Lower temperature for structured content
        if ($this->requiresStructuredOutput($contentType, $context)) {
            $options['temperature'] = 0.3;
        } else {
            $options['temperature'] = 0.7;
        }

        // Adjust max tokens based on content type
        switch ($contentType) {
            case 'quiz_questions':
                $options['max_tokens'] = 3000;
                break;
            case 'course_metadata':
                $options['max_tokens'] = 1500;
                break;
            default:
                $options['max_tokens'] = 2000;
        }

        // Enable function calling for structured outputs
        if ($this->requiresStructuredOutput($contentType, $context)) {
            $options['functions'] = $this->getStructuredOutputFunctions($contentType);
        }

        return $options;
    }

    /**
     * Get function definitions for structured outputs
     *
     * @param string $contentType Content type
     * @return array Function definitions
     */
    private function getStructuredOutputFunctions(string $contentType): array {
        $functions = [];

        switch ($contentType) {
            case 'quiz_questions':
                $functions = [
                    [
                        'name' => 'generate_quiz',
                        'description' => 'Generate structured quiz questions',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'questions' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'question' => ['type' => 'string'],
                                            'type' => ['type' => 'string'],
                                            'options' => ['type' => 'array'],
                                            'correct_answer' => ['type' => 'string'],
                                            'explanation' => ['type' => 'string']
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ];
                break;

            case 'course_metadata':
                $functions = [
                    [
                        'name' => 'generate_course_metadata',
                        'description' => 'Generate structured course metadata',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'title' => ['type' => 'string'],
                                'description' => ['type' => 'string'],
                                'level' => ['type' => 'string'],
                                'duration' => ['type' => 'string'],
                                'prerequisites' => ['type' => 'array'],
                                'learning_objectives' => ['type' => 'array'],
                                'tags' => ['type' => 'array']
                            ]
                        ]
                    ]
                ];
                break;
        }

        return $functions;
    }

    /**
     * Get provider capabilities
     *
     * @param string $provider Provider name
     * @return array Provider capabilities
     */
    public function getProviderCapabilities(string $provider): array {
        return $this->providerCapabilities[$provider] ?? [];
    }

    /**
     * Get all content type mappings
     *
     * @return array Content type mappings
     */
    public function getContentTypeMappings(): array {
        return $this->contentTypeMapping;
    }

    /**
     * Update content type mapping
     *
     * @param string $contentType Content type
     * @param array $config Provider configuration
     */
    public function updateContentTypeMapping(string $contentType, array $config): void {
        $this->contentTypeMapping[$contentType] = $config;
        
        $this->logger->info('CourseContentRouter: Updated content type mapping', [
            'content_type' => $contentType,
            'provider' => $config['provider'],
            'model' => $config['model']
        ]);
    }

    /**
     * Get routing statistics
     *
     * @return array Routing statistics
     */
    public function getRoutingStats(): array {
        return [
            'content_types' => array_keys($this->contentTypeMapping),
            'providers' => array_unique(array_column($this->contentTypeMapping, 'provider')),
            'fallback_chains' => $this->fallbackChain,
            'provider_capabilities' => array_keys($this->providerCapabilities)
        ];
    }
}