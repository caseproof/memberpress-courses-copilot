# AI Model Configuration Documentation

## Overview

This document details the AI model configuration and usage patterns in the MemberPress Courses Copilot plugin. The plugin uses a content-aware routing system to select the most appropriate AI provider and model for each task.

## Model Configuration

### Primary Models

| Provider | Model | Status | Usage |
|----------|-------|--------|-------|
| Anthropic | claude-3-5-sonnet-20241022 | ✅ Working | Primary content generation |
| OpenAI | gpt-4 | ⚠️ Quota Limited | Structured analysis |
| OpenAI | gpt-3.5-turbo | ⚠️ Quota Limited | General tasks fallback |

## Content Type Routing

The plugin intelligently routes requests to different AI providers based on the content type:

### Anthropic (Claude) - Content Generation
Used for creative and educational content generation:
- `content_analysis` - Analyzing course requirements
- `lesson_content` - Generating lesson content
- `course_outline` - Creating course structures
- `advanced_content` - Complex educational material
- `case_studies` - Real-world examples
- `project_content` - Hands-on project descriptions
- `content_optimization` - Improving existing content
- `personalized_content` - Tailored learning paths

### OpenAI (GPT) - Structured Tasks
Used for tasks requiring structured output:
- `structured_analysis` - JSON/structured data extraction
- `quiz_questions` - Multiple choice and quiz generation
- `interactive_exercises` - Practice problems
- `assessment_rubric` - Grading criteria
- `learning_activities` - Interactive learning tasks

## Implementation Details

### Provider Selection Logic
```php
private function getProviderForContentType(string $contentType): string
{
    switch ($contentType) {
        // Content generation tasks → Anthropic
        case 'content_analysis':
        case 'lesson_content':
        case 'course_outline':
            return 'anthropic';
            
        // Structured tasks → OpenAI
        case 'structured_analysis':
        case 'quiz_questions':
            return 'openai';
            
        default:
            return 'anthropic'; // Safe default
    }
}
```

### Model Selection Logic
```php
private function getModelForProvider(string $provider, string $contentType): string
{
    switch ($provider) {
        case 'anthropic':
            return 'claude-3-5-sonnet-20241022';
            
        case 'openai':
            if (in_array($contentType, ['structured_analysis', 'assessment_rubric'])) {
                return 'gpt-4';
            } else {
                return 'gpt-3.5-turbo';
            }
    }
}
```

## Usage Examples

### Course Outline Generation (Claude)
```php
$llm = new LLMService();
$response = $llm->generateContent(
    "Create a course outline for 'Introduction to Web Development'",
    'course_outline',  // Routes to Claude
    ['temperature' => 0.7, 'max_tokens' => 2000]
);
```

### Quiz Generation (GPT)
```php
$llm = new LLMService();
$response = $llm->generateContent(
    "Create 5 multiple choice questions about HTML basics",
    'quiz_questions',  // Routes to GPT
    ['temperature' => 0.3, 'max_tokens' => 1500]
);
```

## Current Status (August 2025)

### Working Features ✅
All Anthropic/Claude-based features are fully operational:
- Course creation wizard
- Lesson content generation
- Course outline creation
- Content optimization
- Case study generation

### Limited Features ⚠️
OpenAI/GPT features have quota limitations on the LiteLLM proxy:
- Quiz generation
- Interactive exercises
- Structured data extraction
- Assessment rubrics

## Fallback Strategy

When a provider fails, the system currently returns an error. Future implementations could include:

1. **Cross-Provider Fallback**
   - If OpenAI fails, retry with Anthropic
   - Adjust prompts for provider differences

2. **Graceful Degradation**
   - Provide simpler alternatives
   - Cache successful responses

3. **User Notification**
   - Inform users of temporary limitations
   - Suggest alternative actions

## Configuration Constants

The models are configured as constants in LLMService.php and can be updated if models change:

```php
// Update these if model names change
const CLAUDE_MODEL = 'claude-3-5-sonnet-20241022';
const GPT4_MODEL = 'gpt-4';
const GPT35_MODEL = 'gpt-3.5-turbo';
```

## Performance Considerations

### Token Usage
- Claude 3.5 Sonnet: ~$3/1M input tokens, ~$15/1M output tokens
- GPT-4: ~$30/1M input tokens, ~$60/1M output tokens
- GPT-3.5 Turbo: ~$0.50/1M input tokens, ~$1.50/1M output tokens

### Response Times
- Claude: 2-5 seconds for typical requests
- GPT-4: 3-8 seconds for typical requests
- GPT-3.5: 1-3 seconds for typical requests

## Recommendations

1. **Primary Usage**: Continue using Claude for most content generation
2. **Quota Management**: Monitor OpenAI usage and consider upgrading proxy limits
3. **Future Updates**: Consider adding more Claude models (Haiku for speed, Opus for quality)
4. **Cost Optimization**: Use appropriate max_tokens limits for each content type

## Testing Commands

Test specific models through the auth gateway:

```bash
# Test Claude (working)
curl -X POST http://localhost:3001/v1/chat/completions \
  -H "Authorization: Bearer dev-license-key-001" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "claude-3-5-sonnet-20241022",
    "messages": [{"role": "user", "content": "Create a course outline"}],
    "max_tokens": 500
  }'

# Test GPT (quota limited)
curl -X POST http://localhost:3001/v1/chat/completions \
  -H "Authorization: Bearer dev-license-key-001" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "gpt-3.5-turbo",
    "messages": [{"role": "user", "content": "Create quiz questions"}],
    "max_tokens": 500
  }'
```

## Conclusion

The plugin's AI model configuration is designed for flexibility and optimal performance. While OpenAI features are currently limited by quota, the primary course creation functionality using Claude remains fully operational. The content-aware routing ensures each task uses the most appropriate AI model.