<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Utilities\Logger;

/**
 * Prompt Template Service
 * 
 * Provides specialized, optimized prompts for different types of course generation tasks.
 * Each template is designed to work with specific AI providers and models for optimal results.
 */
class PromptTemplateService
{
    private Logger $logger;
    private array $templateCache = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get course outline generation prompt
     */
    public function getCourseOutlinePrompt(array $requirements): string
    {
        $template = $this->getTemplate('course_outline');
        
        return $this->interpolateTemplate($template, [
            'title' => $requirements['title'] ?? 'New Course',
            'description' => $requirements['description'] ?? '',
            'difficulty' => $requirements['difficulty_level'] ?? 'intermediate',
            'objectives' => $this->formatObjectives($requirements['learning_objectives'] ?? []),
            'duration' => $requirements['estimated_duration'] ?? '4-6 weeks',
            'prerequisites' => $this->formatPrerequisites($requirements['prerequisites'] ?? []),
            'target_audience' => $requirements['target_audience'] ?? 'general learners'
        ]);
    }

    /**
     * Get lesson content generation prompt
     */
    public function getLessonContentPrompt(array $context): string
    {
        $template = $this->getTemplate('lesson_content');
        
        return $this->interpolateTemplate($template, [
            'course_title' => $context['course_title'] ?? 'Course',
            'section_title' => $context['section_title'] ?? 'Section',
            'lesson_title' => $context['lesson_title'] ?? 'Lesson',
            'lesson_number' => $context['lesson_number'] ?? 1,
            'difficulty' => $context['difficulty_level'] ?? 'intermediate',
            'audience' => $context['target_audience'] ?? 'general learners',
            'learning_objectives' => $this->formatLessonObjectives($context['learning_objectives'] ?? []),
            'previous_context' => $context['previous_lesson_summary'] ?? '',
            'key_concepts' => $this->formatKeyConcepts($context['key_concepts'] ?? [])
        ]);
    }

    /**
     * Get quiz generation prompt
     */
    public function getQuizGenerationPrompt(string $content, array $options = []): string
    {
        $template = $this->getTemplate('quiz_generation');
        
        return $this->interpolateTemplate($template, [
            'lesson_content' => $this->truncateContent($content, 3000),
            'question_count' => $options['question_count'] ?? 5,
            'question_types' => $this->formatQuestionTypes($options['question_types'] ?? ['multiple_choice', 'true_false']),
            'difficulty' => $options['difficulty'] ?? 'intermediate',
            'focus_areas' => $this->formatFocusAreas($options['focus_areas'] ?? [])
        ]);
    }

    /**
     * Get assignment creation prompt
     */
    public function getAssignmentPrompt(array $context): string
    {
        $template = $this->getTemplate('assignment_creation');
        
        return $this->interpolateTemplate($template, [
            'course_title' => $context['course_title'] ?? 'Course',
            'lesson_title' => $context['lesson_title'] ?? 'Lesson',
            'assignment_type' => $context['assignment_type'] ?? 'practical',
            'difficulty' => $context['difficulty_level'] ?? 'intermediate',
            'time_estimate' => $context['time_estimate'] ?? '1-2 hours',
            'learning_objectives' => $this->formatObjectives($context['learning_objectives'] ?? []),
            'skills_to_assess' => $this->formatSkills($context['skills_to_assess'] ?? [])
        ]);
    }

    /**
     * Get learning objectives generation prompt
     */
    public function getLearningObjectivesPrompt(array $context): string
    {
        $template = $this->getTemplate('learning_objectives');
        
        return $this->interpolateTemplate($template, [
            'course_title' => $context['course_title'] ?? 'Course',
            'section_title' => $context['section_title'] ?? 'Section',
            'content_summary' => $context['content_summary'] ?? '',
            'difficulty' => $context['difficulty_level'] ?? 'intermediate',
            'bloom_levels' => $this->formatBloomLevels($context['bloom_levels'] ?? ['understand', 'apply', 'analyze']),
            'objective_count' => $context['objective_count'] ?? 3
        ]);
    }

    /**
     * Get assessment rubric generation prompt
     */
    public function getAssessmentRubricPrompt(array $context): string
    {
        $template = $this->getTemplate('assessment_rubric');
        
        return $this->interpolateTemplate($template, [
            'assignment_description' => $context['assignment_description'] ?? '',
            'learning_objectives' => $this->formatObjectives($context['learning_objectives'] ?? []),
            'assessment_criteria' => $this->formatCriteria($context['assessment_criteria'] ?? []),
            'point_scale' => $context['point_scale'] ?? '0-4',
            'rubric_type' => $context['rubric_type'] ?? 'holistic'
        ]);
    }

    /**
     * Get content analysis prompt
     */
    public function getContentAnalysisPrompt(string $content, string $analysisType): string
    {
        $template = $this->getTemplate('content_analysis');
        
        return $this->interpolateTemplate($template, [
            'content' => $this->truncateContent($content, 4000),
            'analysis_type' => $analysisType,
            'focus_areas' => $this->getAnalysisFocusAreas($analysisType)
        ]);
    }

    /**
     * Get template by name with caching
     */
    private function getTemplate(string $templateName): string
    {
        if (isset($this->templateCache[$templateName])) {
            return $this->templateCache[$templateName];
        }

        $template = $this->loadTemplate($templateName);
        $this->templateCache[$templateName] = $template;
        
        return $template;
    }

    /**
     * Load template content
     */
    private function loadTemplate(string $templateName): string
    {
        $templates = [
            'course_outline' => '
You are an expert instructional designer creating a comprehensive course outline. Create a detailed, pedagogically sound course structure.

**Course Details:**
- Title: {{title}}
- Description: {{description}}
- Difficulty Level: {{difficulty}}
- Target Audience: {{target_audience}}
- Duration: {{duration}}
- Learning Objectives: {{objectives}}
- Prerequisites: {{prerequisites}}

**Instructions:**
1. Create 5-8 main sections that follow a logical learning progression
2. Each section should have 3-6 lessons that build upon each other
3. Ensure proper scaffolding from basic to advanced concepts
4. Include practical applications and real-world examples
5. Consider different learning styles and accessibility

**Output Format (JSON):**
```json
{
  "course_title": "{{title}}",
  "total_estimated_hours": "X hours",
  "sections": [
    {
      "section_number": 1,
      "title": "Section Title",
      "description": "What this section covers",
      "estimated_hours": "X hours",
      "learning_outcomes": ["outcome1", "outcome2"],
      "lessons": [
        {
          "lesson_number": 1,
          "title": "Lesson Title",
          "description": "Lesson description",
          "estimated_duration": "30-45 minutes",
          "lesson_type": "lecture|practical|discussion|assessment",
          "key_concepts": ["concept1", "concept2"],
          "activities": ["activity1", "activity2"]
        }
      ]
    }
  ],
  "final_project": {
    "title": "Capstone Project Title",
    "description": "Project description",
    "deliverables": ["deliverable1", "deliverable2"]
  }
}
```

Create an engaging, comprehensive course outline that provides clear learning pathways and practical value.',

            'lesson_content' => '
You are an expert educator creating engaging lesson content. Generate comprehensive, interactive lesson material that promotes active learning.

**Lesson Context:**
- Course: {{course_title}}
- Section: {{section_title}}
- Lesson: {{lesson_title}} (Lesson {{lesson_number}})
- Difficulty: {{difficulty}}
- Target Audience: {{audience}}
- Learning Objectives: {{learning_objectives}}
- Key Concepts: {{key_concepts}}
- Previous Context: {{previous_context}}

**Content Requirements:**
1. Start with clear learning objectives (3-5 objectives)
2. Provide engaging introduction that connects to previous learning
3. Present content in digestible chunks with examples
4. Include interactive elements and practical applications
5. Use various content types (text, examples, case studies, exercises)
6. End with summary and bridge to next lesson

**Content Structure:**
# {{lesson_title}}

## Learning Objectives
By the end of this lesson, you will be able to:
- [Specific, measurable objectives using action verbs]

## Introduction
[Engaging hook that connects to prior knowledge and motivates learning]

## Main Content
### Key Concept 1: [Title]
[Explanation with examples, analogies, and real-world applications]

**Example:** [Concrete example]

**Practice Activity:** [Quick exercise or reflection]

### Key Concept 2: [Title]
[Continue with logical progression]

## Practical Application
[Real-world scenario or case study]

## Interactive Exercise
[Hands-on activity or problem-solving exercise]

## Summary and Key Takeaways
- [Bullet points of main concepts]
- [Connections to broader course themes]

## Looking Ahead
[Brief preview of next lesson and how it builds on this one]

## Additional Resources
- [Optional supplementary materials]

Create engaging, educational content that is appropriate for {{difficulty}} level learners.',

            'quiz_generation' => '
You are an expert assessment designer creating fair, comprehensive quiz questions that test understanding rather than memorization.

**Content to Assess:**
{{lesson_content}}

**Quiz Parameters:**
- Number of Questions: {{question_count}}
- Question Types: {{question_types}}
- Difficulty Level: {{difficulty}}
- Focus Areas: {{focus_areas}}

**Assessment Principles:**
1. Test understanding and application, not just recall
2. Use clear, unambiguous language
3. Avoid trick questions or irrelevant details
4. Ensure cultural sensitivity and accessibility
5. Provide meaningful distractors for multiple choice
6. Include explanations for correct answers

**Output Format (JSON):**
```json
{
  "quiz_title": "Quiz: [Lesson Topic]",
  "instructions": "Clear instructions for taking the quiz",
  "time_limit": "X minutes",
  "questions": [
    {
      "question_number": 1,
      "question_text": "Clear, specific question",
      "question_type": "multiple_choice|true_false|short_answer|essay",
      "points": 1,
      "options": ["Option A", "Option B", "Option C", "Option D"],
      "correct_answer": "Option A",
      "explanation": "Why this answer is correct and others are wrong",
      "bloom_level": "remember|understand|apply|analyze|evaluate|create",
      "difficulty": "easy|medium|hard"
    }
  ],
  "answer_key": {
    "total_points": X,
    "passing_score": "70%",
    "grading_rubric": "Brief grading guidelines"
  }
}
```

Create challenging but fair questions that accurately assess student learning.',

            'assignment_creation' => '
You are an expert instructional designer creating meaningful assignments that assess learning objectives through practical application.

**Assignment Context:**
- Course: {{course_title}}
- Lesson: {{lesson_title}}
- Assignment Type: {{assignment_type}}
- Difficulty Level: {{difficulty}}
- Time Estimate: {{time_estimate}}
- Learning Objectives: {{learning_objectives}}
- Skills to Assess: {{skills_to_assess}}

**Assignment Design Principles:**
1. Align directly with learning objectives
2. Provide opportunities for creativity and critical thinking
3. Include clear success criteria and rubrics
4. Offer scaffolding and support resources
5. Consider diverse learning preferences
6. Enable meaningful feedback

**Assignment Structure:**

# Assignment: [Title]

## Overview
[Brief description of the assignment and its purpose]

## Learning Objectives
This assignment will help you:
- [Specific objectives that align with course goals]

## Instructions
### Part 1: [Component Name]
[Detailed instructions with examples]

### Part 2: [Component Name]
[Continue with logical progression]

## Requirements
- [Specific deliverables and format requirements]
- [Length, format, submission guidelines]
- [Any tools or resources needed]

## Evaluation Criteria
You will be assessed on:
- [Criterion 1] (X points)
- [Criterion 2] (X points)
- [Overall quality and presentation] (X points)

## Resources and Support
- [Relevant course materials]
- [Additional resources]
- [Where to get help]

## Submission Guidelines
- **Due Date:** [Date and time]
- **Format:** [File format and naming conventions]
- **Submission Method:** [How to submit]

## Grading Rubric
[Detailed rubric with performance levels]

Create an engaging assignment that provides meaningful learning opportunities.',

            'learning_objectives' => '
You are an expert instructional designer creating specific, measurable learning objectives using Bloom\'s Taxonomy.

**Context:**
- Course: {{course_title}}
- Section: {{section_title}}
- Content Summary: {{content_summary}}
- Difficulty Level: {{difficulty}}
- Target Bloom Levels: {{bloom_levels}}
- Number of Objectives: {{objective_count}}

**Objective Writing Guidelines:**
1. Use specific action verbs aligned with Bloom\'s levels
2. Make objectives measurable and observable
3. Ensure objectives are achievable for the target audience
4. Focus on what students will DO, not what they will learn about
5. Progress from lower to higher-order thinking skills

**Bloom\'s Taxonomy Action Verbs:**
- **Remember:** define, list, recall, recognize, retrieve
- **Understand:** explain, interpret, summarize, compare, classify
- **Apply:** execute, implement, solve, use, demonstrate
- **Analyze:** differentiate, examine, compare, contrast, organize
- **Evaluate:** critique, judge, assess, evaluate, justify
- **Create:** design, construct, produce, create, develop

**Output Format:**
```json
{
  "section_title": "{{section_title}}",
  "learning_objectives": [
    {
      "objective_number": 1,
      "objective_text": "Students will be able to [action verb] [content] [condition/criterion]",
      "bloom_level": "understand",
      "assessment_method": "How this will be assessed",
      "difficulty": "beginner|intermediate|advanced"
    }
  ],
  "overall_outcome": "Big picture goal for this section"
}
```

Create clear, measurable objectives that guide effective learning and assessment.',

            'assessment_rubric' => '
You are an expert in educational assessment creating a detailed rubric for fair and consistent evaluation.

**Assignment Context:**
- Assignment Description: {{assignment_description}}
- Learning Objectives: {{learning_objectives}}
- Assessment Criteria: {{assessment_criteria}}
- Point Scale: {{point_scale}}
- Rubric Type: {{rubric_type}}

**Rubric Design Principles:**
1. Align with learning objectives and assignment requirements
2. Use clear, specific performance descriptions
3. Provide distinct performance levels
4. Focus on observable behaviors and outcomes
5. Enable consistent scoring across evaluators
6. Support student learning and improvement

**Output Format (JSON):**
```json
{
  "rubric_title": "Assessment Rubric for [Assignment Name]",
  "rubric_type": "{{rubric_type}}",
  "point_scale": "{{point_scale}}",
  "criteria": [
    {
      "criterion_name": "Content Knowledge",
      "weight": "25%",
      "performance_levels": {
        "excellent": {
          "points": 4,
          "description": "Specific description of excellent performance"
        },
        "proficient": {
          "points": 3,
          "description": "Specific description of proficient performance"
        },
        "developing": {
          "points": 2,
          "description": "Specific description of developing performance"
        },
        "beginning": {
          "points": 1,
          "description": "Specific description of beginning performance"
        }
      }
    }
  ],
  "total_points": "X points",
  "grading_scale": {
    "A": "90-100%",
    "B": "80-89%",
    "C": "70-79%",
    "D": "60-69%",
    "F": "Below 60%"
  },
  "feedback_guidelines": "Instructions for providing constructive feedback"
}
```

Create a comprehensive rubric that promotes fair assessment and student growth.',

            'content_analysis' => '
You are an expert content analyst providing detailed analysis of educational materials.

**Content to Analyze:**
{{content}}

**Analysis Type:** {{analysis_type}}

**Focus Areas:** {{focus_areas}}

**Analysis Framework:**
1. Content Quality and Accuracy
2. Pedagogical Effectiveness
3. Engagement and Accessibility
4. Alignment with Learning Objectives
5. Areas for Improvement

**Output Format (JSON):**
```json
{
  "analysis_summary": "Brief overall assessment",
  "content_quality": {
    "accuracy": "Assessment of factual accuracy",
    "completeness": "Assessment of content completeness",
    "currency": "Assessment of content currency",
    "score": "1-10"
  },
  "pedagogical_effectiveness": {
    "learning_progression": "How well content builds understanding",
    "instructional_methods": "Effectiveness of teaching approaches",
    "scaffolding": "Quality of support provided",
    "score": "1-10"
  },
  "engagement_accessibility": {
    "engagement_level": "How engaging the content is",
    "accessibility": "How accessible to diverse learners",
    "multimedia_use": "Effectiveness of multimedia elements",
    "score": "1-10"
  },
  "strengths": ["List of content strengths"],
  "areas_for_improvement": ["List of improvement suggestions"],
  "recommendations": ["Specific actionable recommendations"],
  "overall_score": "1-10"
}
```

Provide thorough, constructive analysis that supports content improvement.'
        ];

        return $templates[$templateName] ?? '';
    }

    /**
     * Interpolate template with variables
     */
    private function interpolateTemplate(string $template, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }

    /**
     * Format learning objectives for templates
     */
    private function formatObjectives(array $objectives): string
    {
        if (empty($objectives)) {
            return 'No specific objectives provided';
        }
        
        return "- " . implode("\n- ", $objectives);
    }

    /**
     * Format prerequisites for templates
     */
    private function formatPrerequisites(array $prerequisites): string
    {
        if (empty($prerequisites)) {
            return 'No specific prerequisites';
        }
        
        return "- " . implode("\n- ", $prerequisites);
    }

    /**
     * Format lesson objectives
     */
    private function formatLessonObjectives(array $objectives): string
    {
        if (empty($objectives)) {
            return 'Understand key concepts and apply them effectively';
        }
        
        return implode(', ', $objectives);
    }

    /**
     * Format key concepts
     */
    private function formatKeyConcepts(array $concepts): string
    {
        if (empty($concepts)) {
            return 'Core concepts related to the lesson topic';
        }
        
        return implode(', ', $concepts);
    }

    /**
     * Format question types
     */
    private function formatQuestionTypes(array $types): string
    {
        return implode(', ', $types);
    }

    /**
     * Format focus areas
     */
    private function formatFocusAreas(array $areas): string
    {
        if (empty($areas)) {
            return 'All major concepts covered in the lesson';
        }
        
        return implode(', ', $areas);
    }

    /**
     * Format skills to assess
     */
    private function formatSkills(array $skills): string
    {
        if (empty($skills)) {
            return 'Core skills related to lesson objectives';
        }
        
        return "- " . implode("\n- ", $skills);
    }

    /**
     * Format Bloom's taxonomy levels
     */
    private function formatBloomLevels(array $levels): string
    {
        return implode(', ', $levels);
    }

    /**
     * Format assessment criteria
     */
    private function formatCriteria(array $criteria): string
    {
        if (empty($criteria)) {
            return 'Standard assessment criteria for the assignment type';
        }
        
        return "- " . implode("\n- ", $criteria);
    }

    /**
     * Get analysis focus areas based on type
     */
    private function getAnalysisFocusAreas(string $analysisType): string
    {
        $focusAreas = [
            'quality' => 'Content accuracy, completeness, and currency',
            'pedagogy' => 'Teaching effectiveness and learning progression',
            'engagement' => 'Student engagement and motivation factors',
            'accessibility' => 'Accessibility and inclusive design',
            'assessment' => 'Assessment alignment and effectiveness'
        ];
        
        return $focusAreas[$analysisType] ?? 'General content analysis';
    }

    /**
     * Truncate content to specified length
     */
    private function truncateContent(string $content, int $maxLength): string
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        $truncated = substr($content, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    /**
     * Clear template cache
     */
    public function clearCache(): void
    {
        $this->templateCache = [];
        $this->logger->info('PromptTemplateService: Template cache cleared');
    }

    /**
     * Get available template names
     */
    public function getAvailableTemplates(): array
    {
        return [
            'course_outline',
            'lesson_content',
            'quiz_generation',
            'assignment_creation',
            'learning_objectives',
            'assessment_rubric',
            'content_analysis'
        ];
    }
}