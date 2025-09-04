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
     * @param  array  $options Question generation options
     * @return array Generated multiple choice questions
     */
    public function generateMultipleChoiceQuestions(string $content, array $options = []): array;

    /**
     * Generate true/false questions
     *
     * @param  string $content The content to generate questions from
     * @param  array  $options Question generation options
     * @return array Generated true/false questions
     */
    public function generateTrueFalseQuestions(string $content, array $options = []): array;

    /**
     * Generate text answer questions
     *
     * @param  string $content The content to generate questions from
     * @param  array  $options Question generation options
     * @return array Generated text answer questions
     */
    public function generateTextAnswerQuestions(string $content, array $options = []): array;

    /**
     * Generate multiple select questions
     *
     * @param  string $content The content to generate questions from
     * @param  array  $options Question generation options
     * @return array Generated multiple select questions
     */
    public function generateMultipleSelectQuestions(string $content, array $options = []): array;

    /**
     * Get supported question types
     *
     * @return array List of supported question types
     */
    public function getSupportedQuestionTypes(): array;
}
