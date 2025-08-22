<?php

namespace MemberPressCoursesCopilot\Models;

/**
 * Conversation Session Model
 * 
 * Represents a conversation session with comprehensive state management,
 * context tracking, message history, progress calculation, and recovery mechanisms.
 * Supports auto-save functionality, session timeout management, and collaborative editing.
 */
class ConversationSession
{
    private string $sessionId;
    private int $userId;
    private string $contextType;
    private ?int $databaseId = null;
    private string $title = '';
    
    // Session state management
    private string $currentState = 'initial';
    private array $stateHistory = [];
    private ?string $pausedFromState = null;
    private bool $isActive = true;
    
    // Context and data
    private array $context = [];
    private array $metadata = [];
    private array $messages = [];
    
    // Progress tracking
    private float $progress = 0.0;
    private float $confidenceScore = 0.0;
    
    // Timing and persistence
    private int $createdAt;
    private int $lastUpdated;
    private int $lastSaved = 0;
    private int $lastAutoSave = 0;
    private bool $hasUnsavedChanges = false;
    
    // Resource tracking
    private int $totalTokens = 0;
    private float $totalCost = 0.0;
    
    // Session configuration
    private const MAX_MESSAGE_HISTORY = 1000;
    private const AUTO_SAVE_INTERVAL = 30; // seconds
    private const MAX_STATE_HISTORY = 50;

    public function __construct(string $sessionId, int $userId, string $contextType = 'course_creation')
    {
        $this->sessionId = $sessionId;
        $this->userId = $userId;
        $this->contextType = $contextType;
        $this->createdAt = time();
        $this->lastUpdated = time();
        $this->title = 'Course Creation Session - ' . date('Y-m-d H:i:s');
    }

    /**
     * Add a message to the conversation
     */
    public function addMessage(string $type, string $content, array $metadata = []): void
    {
        $message = [
            'id' => uniqid('msg_'),
            'type' => $type, // 'user', 'assistant', 'system'
            'content' => $content,
            'metadata' => $metadata,
            'timestamp' => time(),
            'state' => $this->currentState
        ];
        
        $this->messages[] = $message;
        
        // Trim message history if it gets too long
        if (count($this->messages) > self::MAX_MESSAGE_HISTORY) {
            $this->messages = array_slice($this->messages, -self::MAX_MESSAGE_HISTORY);
        }
        
        $this->markAsModified();
        
        // Track tokens if provided in metadata
        if (isset($metadata['tokens_used'])) {
            $this->totalTokens += $metadata['tokens_used'];
        }
        
        if (isset($metadata['cost'])) {
            $this->totalCost += $metadata['cost'];
        }
    }

    /**
     * Clear all messages from the conversation
     */
    public function clearMessages(): void
    {
        $this->messages = [];
        $this->markAsModified();
    }

    /**
     * Get recent messages
     */
    public function getRecentMessages(int $count = 10): array
    {
        return array_slice($this->messages, -$count);
    }

    /**
     * Get all messages
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Get messages by type
     */
    public function getMessagesByType(string $type): array
    {
        return array_filter($this->messages, fn($msg) => $msg['type'] === $type);
    }

    /**
     * Set current conversation state
     */
    public function setCurrentState(string $state): void
    {
        if ($state !== $this->currentState) {
            $this->currentState = $state;
            $this->markAsModified();
            
            // Auto-update progress based on state
            $this->updateProgressFromState($state);
        }
    }

    /**
     * Get current conversation state
     */
    public function getCurrentState(): string
    {
        return $this->currentState;
    }

    /**
     * Save current state to history for backtracking
     */
    public function saveStateToHistory(string $state, array $transitionData = []): void
    {
        $historyItem = [
            'state' => $state,
            'timestamp' => time(),
            'context' => $this->context,
            'transition_data' => $transitionData,
            'progress' => $this->progress
        ];
        
        $this->stateHistory[] = $historyItem;
        
        // Trim state history if it gets too long
        if (count($this->stateHistory) > self::MAX_STATE_HISTORY) {
            $this->stateHistory = array_slice($this->stateHistory, -self::MAX_STATE_HISTORY);
        }
        
        $this->markAsModified();
    }

    /**
     * Get state history for backtracking
     */
    public function getStateHistory(): array
    {
        return $this->stateHistory;
    }

    /**
     * Set state history (for restoration)
     */
    public function setStateHistory(array $history): void
    {
        $this->stateHistory = $history;
        $this->markAsModified();
    }

    /**
     * Set conversation context data
     */
    public function setContext(string|array $key, mixed $value = null): void
    {
        if (is_array($key) && $value === null) {
            // Setting entire context array
            $this->context = $key;
        } else {
            // Setting individual key-value pair
            $this->context[$key] = $value;
        }
        
        $this->markAsModified();
    }

    /**
     * Get conversation context data
     */
    public function getContext(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->context;
        }
        
        return $this->context[$key] ?? $default;
    }

    /**
     * Restore context from history item
     */
    public function restoreContext(array $context): void
    {
        $this->context = $context;
        $this->markAsModified();
    }

    /**
     * Set progress percentage
     */
    public function updateProgress(float $progress): void
    {
        $this->progress = max(0.0, min(100.0, $progress));
        $this->markAsModified();
    }

    /**
     * Get progress percentage
     */
    public function getProgress(): float
    {
        return $this->progress;
    }

    /**
     * Set progress value directly
     */
    public function setProgress(float $progress): void
    {
        $this->progress = $progress;
        $this->markAsModified();
    }

    /**
     * Set confidence score
     */
    public function setConfidenceScore(float $score): void
    {
        $this->confidenceScore = max(0.0, min(1.0, $score));
        $this->markAsModified();
    }

    /**
     * Get confidence score
     */
    public function getConfidenceScore(): float
    {
        return $this->confidenceScore;
    }

    /**
     * Pause the conversation session
     */
    public function pause(string $reason = ''): void
    {
        $this->pausedFromState = $this->currentState;
        $this->isActive = false;
        
        $this->addMessage('system', 'Conversation paused', [
            'reason' => $reason,
            'paused_from_state' => $this->pausedFromState
        ]);
    }

    /**
     * Resume the conversation session
     */
    public function resume(): void
    {
        $this->isActive = true;
        
        $this->addMessage('system', 'Conversation resumed', [
            'resumed_to_state' => $this->pausedFromState ?? $this->currentState
        ]);
        
        if ($this->pausedFromState) {
            $this->currentState = $this->pausedFromState;
            $this->pausedFromState = null;
        }
    }

    /**
     * Complete the conversation session
     */
    public function complete(array $completionData = []): void
    {
        $this->isActive = false;
        $this->progress = 100.0;
        
        $this->addMessage('system', 'Conversation completed', $completionData);
        $this->setMetadata('completed_at', time());
        $this->setMetadata('completion_data', $completionData);
    }

    /**
     * Abandon the conversation session
     */
    public function abandon(string $reason = ''): void
    {
        $this->isActive = false;
        
        $this->addMessage('system', 'Conversation abandoned', ['reason' => $reason]);
        $this->setMetadata('abandoned_at', time());
        $this->setMetadata('abandon_reason', $reason);
    }

    /**
     * Check if session is active
     */
    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * Check if session is expired
     */
    public function isExpired(int $timeoutMinutes = 60): bool
    {
        return (time() - $this->lastUpdated) > ($timeoutMinutes * 60);
    }

    /**
     * Get time until expiry
     */
    public function getTimeUntilExpiry(int $timeoutMinutes = 60): int
    {
        $expiryTime = $this->lastUpdated + ($timeoutMinutes * 60);
        return max(0, $expiryTime - time());
    }

    /**
     * Get pause duration if paused
     */
    public function getPauseDuration(): int
    {
        if ($this->isActive) {
            return 0;
        }
        
        // Find the last pause message
        $pauseMessage = null;
        foreach (array_reverse($this->messages) as $message) {
            if ($message['type'] === 'system' && strpos($message['content'], 'paused') !== false) {
                $pauseMessage = $message;
                break;
            }
        }
        
        return $pauseMessage ? (time() - $pauseMessage['timestamp']) : 0;
    }

    /**
     * Set paused from state
     */
    public function setPausedFromState(string $state): void
    {
        $this->pausedFromState = $state;
        $this->markAsModified();
    }

    /**
     * Get paused from state
     */
    public function getPausedFromState(): ?string
    {
        return $this->pausedFromState;
    }

    /**
     * Set session metadata
     */
    public function setMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->markAsModified();
    }

    /**
     * Set entire metadata array
     */
    public function setMetadataArray(array $metadata): void
    {
        $this->metadata = $metadata;
        $this->markAsModified();
    }

    /**
     * Get session metadata
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }
        
        return $this->metadata[$key] ?? null;
    }

    /**
     * Check if auto-save is needed
     */
    public function shouldAutoSave(int $intervalSeconds = null): bool
    {
        $interval = $intervalSeconds ?? self::AUTO_SAVE_INTERVAL;
        return $this->hasUnsavedChanges && (time() - $this->lastAutoSave) >= $interval;
    }

    /**
     * Mark session as saved
     */
    public function markAsSaved(): void
    {
        $this->hasUnsavedChanges = false;
        $this->lastSaved = time();
        $this->lastAutoSave = time();
    }

    /**
     * Mark session as modified
     */
    public function markAsModified(): void
    {
        $this->hasUnsavedChanges = true;
        $this->lastUpdated = time();
    }

    /**
     * Check if session has unsaved changes
     */
    public function hasUnsavedChanges(): bool
    {
        return $this->hasUnsavedChanges;
    }

    /**
     * Calculate session statistics
     */
    public function getStatistics(): array
    {
        $userMessages = $this->getMessagesByType('user');
        $assistantMessages = $this->getMessagesByType('assistant');
        $systemMessages = $this->getMessagesByType('system');
        
        $duration = $this->lastUpdated - $this->createdAt;
        $avgResponseTime = count($assistantMessages) > 0 ? $duration / count($assistantMessages) : 0;
        
        return [
            'total_messages' => count($this->messages),
            'user_messages' => count($userMessages),
            'assistant_messages' => count($assistantMessages),
            'system_messages' => count($systemMessages),
            'duration_seconds' => $duration,
            'average_response_time' => $avgResponseTime,
            'state_transitions' => count($this->stateHistory),
            'progress_percentage' => $this->progress,
            'confidence_score' => $this->confidenceScore,
            'total_tokens' => $this->totalTokens,
            'total_cost' => $this->totalCost,
            'is_active' => $this->isActive,
            'has_unsaved_changes' => $this->hasUnsavedChanges
        ];
    }

    /**
     * Export session data
     */
    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'user_id' => $this->userId,
            'context_type' => $this->contextType,
            'database_id' => $this->databaseId,
            'title' => $this->title,
            'current_state' => $this->currentState,
            'state_history' => $this->stateHistory,
            'paused_from_state' => $this->pausedFromState,
            'is_active' => $this->isActive,
            'context' => $this->context,
            'metadata' => $this->metadata,
            'messages' => $this->messages,
            'progress' => $this->progress,
            'confidence_score' => $this->confidenceScore,
            'created_at' => $this->createdAt,
            'last_updated' => $this->lastUpdated,
            'last_saved' => $this->lastSaved,
            'has_unsaved_changes' => $this->hasUnsavedChanges,
            'total_tokens' => $this->totalTokens,
            'total_cost' => $this->totalCost,
            'statistics' => $this->getStatistics()
        ];
    }

    /**
     * Create session from array data
     */
    public static function fromArray(array $data): self
    {
        $session = new self($data['session_id'], $data['user_id'], $data['context_type'] ?? 'course_creation');
        
        $session->databaseId = $data['database_id'] ?? null;
        $session->title = $data['title'] ?? '';
        $session->currentState = $data['current_state'] ?? 'initial';
        $session->stateHistory = $data['state_history'] ?? [];
        $session->pausedFromState = $data['paused_from_state'] ?? null;
        $session->isActive = $data['is_active'] ?? true;
        $session->context = $data['context'] ?? [];
        $session->metadata = $data['metadata'] ?? [];
        $session->progress = $data['progress'] ?? 0.0;
        $session->confidenceScore = $data['confidence_score'] ?? 0.0;
        $session->createdAt = $data['created_at'] ?? time();
        $session->lastUpdated = $data['last_updated'] ?? time();
        $session->lastSaved = $data['last_saved'] ?? 0;
        $session->hasUnsavedChanges = $data['has_unsaved_changes'] ?? false;
        $session->totalTokens = $data['total_tokens'] ?? 0;
        $session->totalCost = $data['total_cost'] ?? 0.0;
        
        // Restore messages
        foreach ($data['messages'] ?? [] as $message) {
            $session->messages[] = $message;
        }
        
        return $session;
    }

    // GETTERS AND SETTERS

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getContextType(): string
    {
        return $this->contextType;
    }

    public function getDatabaseId(): ?int
    {
        return $this->databaseId;
    }

    public function setDatabaseId(int $id): void
    {
        $this->databaseId = $id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
        $this->markAsModified();
    }

    public function getCreatedAt(): int
    {
        return $this->createdAt;
    }

    public function setCreatedAt(int $timestamp): void
    {
        $this->createdAt = $timestamp;
    }

    public function getLastUpdated(): int
    {
        return $this->lastUpdated;
    }

    public function setLastUpdated(int $timestamp): void
    {
        $this->lastUpdated = $timestamp;
    }

    public function getLastSaved(): int
    {
        return $this->lastSaved;
    }

    public function getTotalTokens(): int
    {
        return $this->totalTokens;
    }

    public function setTotalTokens(int $tokens): void
    {
        $this->totalTokens = $tokens;
        $this->markAsModified();
    }

    public function addTokens(int $tokens): void
    {
        $this->totalTokens += $tokens;
        $this->markAsModified();
    }

    public function getTotalCost(): float
    {
        return $this->totalCost;
    }

    public function setTotalCost(float $cost): void
    {
        $this->totalCost = $cost;
        $this->markAsModified();
    }

    public function addCost(float $cost): void
    {
        $this->totalCost += $cost;
        $this->markAsModified();
    }

    /**
     * Create a recovery checkpoint
     */
    public function createCheckpoint(string $name = ''): array
    {
        $checkpointName = $name ?: 'checkpoint_' . time();
        
        $checkpoint = [
            'name' => $checkpointName,
            'timestamp' => time(),
            'state' => $this->currentState,
            'context' => $this->context,
            'progress' => $this->progress,
            'message_count' => count($this->messages),
            'confidence_score' => $this->confidenceScore
        ];
        
        $this->setMetadata('checkpoints', array_merge(
            $this->getMetadata('checkpoints') ?: [],
            [$checkpointName => $checkpoint]
        ));
        
        return $checkpoint;
    }

    /**
     * Restore from a checkpoint
     */
    public function restoreFromCheckpoint(string $checkpointName): bool
    {
        $checkpoints = $this->getMetadata('checkpoints') ?: [];
        
        if (!isset($checkpoints[$checkpointName])) {
            return false;
        }
        
        $checkpoint = $checkpoints[$checkpointName];
        
        $this->currentState = $checkpoint['state'];
        $this->context = $checkpoint['context'];
        $this->progress = $checkpoint['progress'];
        $this->confidenceScore = $checkpoint['confidence_score'];
        
        // Trim messages to checkpoint count
        if (isset($checkpoint['message_count']) && count($this->messages) > $checkpoint['message_count']) {
            $this->messages = array_slice($this->messages, 0, $checkpoint['message_count']);
        }
        
        $this->markAsModified();
        
        return true;
    }

    /**
     * Get available checkpoints
     */
    public function getCheckpoints(): array
    {
        return $this->getMetadata('checkpoints') ?: [];
    }
    
    /**
     * Update progress based on current state
     */
    private function updateProgressFromState(string $state): void
    {
        // Define progress percentages for each state
        $stateProgress = [
            'initial' => 0,
            'welcome' => 0,
            'template_selection' => 10,
            'requirements_gathering' => 20,
            'structure_generation' => 35,
            'structure_review' => 45,
            'content_generation' => 60,
            'content_review' => 75,
            'final_review' => 90,
            'wordpress_creation' => 95,
            'completed' => 100,
            'complete' => 100
        ];
        
        // Update progress if state is recognized
        if (isset($stateProgress[$state])) {
            $this->updateProgress($stateProgress[$state]);
        }
    }
}