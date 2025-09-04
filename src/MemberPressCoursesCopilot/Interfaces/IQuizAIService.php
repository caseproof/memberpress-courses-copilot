<?php

/**
 * Quiz AI Service Interface - Streamlined
 *
 * @package MemberPressCoursesCopilot
 */

namespace MemberPressCoursesCopilot\Interfaces;

interface IQuizAIService
{
    /**
     * Generate quiz questions based on provided content
     *
     * @param  string $content The content to generate questions from
     * @param  array  $options Question generation options
     * @return array Generated questions
     */
    public function generateQuestions(string $content, array $options = []): array;

    /**
     * Generate multiple choice questions
     *
     * @param  string $content The content to generate questions from
     * @param  int    $count   Number of questions to generate
     * @return array Generated multiple choice questions
     */
    public function generateMultipleChoiceQuestions(string $content, int $count = 5): array;

    /**
     * Generate true/false questions
     *
     * @param  string $content The content to generate questions from
     * @param  int    $count   Number of questions to generate
     * @return array Generated true/false questions
     */
    public function generateTrueFalseQuestions(string $content, int $count = 5): array;

    /**
     * Generate text answer questions
     *
     * @param  string $content The content to generate questions from
     * @param  int    $count   Number of questions to generate
     * @return array Generated text answer questions
     */
    public function generateTextAnswerQuestions(string $content, int $count = 5): array;

    /**
     * Generate multiple select questions
     *
     * @param  string $content The content to generate questions from
     * @param  int    $count   Number of questions to generate
     * @return array Generated multiple select questions
     */
    public function generateMultipleSelectQuestions(string $content, int $count = 5): array;

    /**
     * Get supported question types
     *
     * @return array List of supported question types
     */
    public function getSupportedQuestionTypes(): array;
}
