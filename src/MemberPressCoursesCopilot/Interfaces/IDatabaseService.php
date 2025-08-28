<?php

namespace MemberPressCoursesCopilot\Interfaces;

/**
 * Interface for database operations
 */
interface IDatabaseService {
    /**
     * Save a conversation to the database
     *
     * @param int $userId User ID
     * @param string $message User message
     * @param string $response AI response
     * @return int|false Conversation ID on success, false on failure
     */
    public function saveConversation(int $userId, string $message, string $response);

    /**
     * Get conversation history for a user
     *
     * @param int $userId User ID
     * @param int $limit Number of messages to retrieve
     * @return array Conversation history
     */
    public function getConversationHistory(int $userId, int $limit = 50): array;

    /**
     * Delete a conversation
     *
     * @param int $conversationId Conversation ID
     * @return bool Success status
     */
    public function deleteConversation(int $conversationId): bool;
}