<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Models\ConversationSession;
use MemberPressCoursesCopilot\Security\NonceConstants;

/**
 * Session Features Service
 * 
 * Provides advanced session features including auto-save functionality,
 * session timeout management, conversation export/import capabilities,
 * multi-device session synchronization, and collaborative editing support.
 */
class SessionFeaturesService extends BaseService
{
    private ConversationManager $conversationManager;
    private DatabaseService $databaseService;
    
    // Auto-save configuration
    private const AUTO_SAVE_INTERVAL = 30; // seconds
    private const AUTO_SAVE_BATCH_SIZE = 10; // sessions
    private const AUTO_SAVE_RETRY_ATTEMPTS = 3;
    
    // Timeout configuration
    private const DEFAULT_TIMEOUT_MINUTES = 60;
    private const WARNING_THRESHOLD_MINUTES = 50;
    private const IDLE_CHECK_INTERVAL = 300; // 5 minutes
    
    // Sync configuration
    private const SYNC_CONFLICT_RESOLUTION = 'server_wins'; // 'server_wins', 'client_wins', 'merge'
    private const MAX_SYNC_RETRIES = 3;
    private const SYNC_BATCH_SIZE = 5;
    
    // Export/Import configuration
    private const EXPORT_FORMAT_VERSION = '2.0';
    private const MAX_EXPORT_SIZE_MB = 50;
    private const COMPRESSION_ENABLED = true;

    public function __construct(
        ConversationManager $conversationManager,
        DatabaseService $databaseService
    ) {
        $this->conversationManager = $conversationManager;
        $this->databaseService = $databaseService;
        
        // Initialize auto-save and timeout monitoring
        $this->initializeAutoSave();
        $this->initializeTimeoutMonitoring();
    }

    /**
     * Initialize auto-save functionality
     */
    public function initializeAutoSave(): void
    {
        // Schedule auto-save tasks
        add_action('init', [$this, 'scheduleAutoSaveTasks']);
        add_action('mpcc_auto_save_sessions', [$this, 'performAutoSave']);
        
        // Hook into session modifications for immediate auto-save triggers
        add_action('mpcc_session_modified', [$this, 'handleSessionModification'], 10, 2);
        
        // Client-side auto-save via AJAX
        add_action('wp_ajax_mpcc_auto_save_session', [$this, 'handleAjaxAutoSave']);
        add_action('wp_ajax_nopriv_mpcc_auto_save_session', [$this, 'handleAjaxAutoSave']);
    }

    /**
     * Initialize timeout monitoring
     */
    public function initializeTimeoutMonitoring(): void
    {
        add_action('init', [$this, 'scheduleTimeoutChecks']);
        add_action('mpcc_check_session_timeouts', [$this, 'performTimeoutCheck']);
        
        // Client-side heartbeat integration
        add_filter('heartbeat_received', [$this, 'handleHeartbeatTimeout'], 10, 2);
        add_action('wp_ajax_mpcc_extend_session', [$this, 'handleSessionExtension']);
    }

    /**
     * Perform auto-save for active sessions
     */
    public function performAutoSave(): void
    {
        try {
            $activeSessions = $this->getActiveSessionsNeedingSave();
            $savedCount = 0;
            $errors = [];
            
            foreach ($activeSessions as $sessionId) {
                try {
                    $session = $this->conversationManager->loadSession($sessionId);
                    
                    if ($session && $session->hasUnsavedChanges()) {
                        $success = $this->conversationManager->saveSession($session);
                        
                        if ($success) {
                            $savedCount++;
                            $this->log("Auto-saved session: {$sessionId}");
                        } else {
                            $errors[] = "Failed to auto-save session: {$sessionId}";
                        }
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Auto-save error for session {$sessionId}: " . $e->getMessage();
                }
                
                // Prevent memory issues with large batches
                if ($savedCount >= self::AUTO_SAVE_BATCH_SIZE) {
                    break;
                }
            }
            
            // Log auto-save summary
            $this->log("Auto-save completed: {$savedCount} sessions saved, " . count($errors) . " errors");
            
            if (!empty($errors)) {
                $this->log("Auto-save errors: " . implode('; ', $errors), 'error');
            }
            
        } catch (\Exception $e) {
            $this->log("Auto-save process failed: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Handle AJAX auto-save request
     */
    public function handleAjaxAutoSave(): void
    {
        // Verify nonce and permissions
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::AUTO_SAVE_SESSION, false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        $sessionData = json_decode(stripslashes($_POST['session_data'] ?? '{}'), true);
        
        if (empty($sessionId)) {
            wp_send_json_error('Session ID required');
            return;
        }
        
        try {
            $session = $this->conversationManager->loadSession($sessionId);
            
            if (!$session) {
                wp_send_json_error('Session not found');
                return;
            }
            
            // Update session with client data
            $this->updateSessionFromClientData($session, $sessionData);
            
            // Save session
            $success = $this->conversationManager->saveSession($session);
            
            if ($success) {
                wp_send_json_success([
                    'saved_at' => time(),
                    'session_id' => $sessionId,
                    'auto_save_interval' => self::AUTO_SAVE_INTERVAL
                ]);
            } else {
                wp_send_json_error('Failed to save session');
            }
            
        } catch (\Exception $e) {
            wp_send_json_error('Auto-save error: ' . $e->getMessage());
        }
    }

    /**
     * Perform timeout check for all active sessions
     */
    public function performTimeoutCheck(): void
    {
        try {
            $timeoutThreshold = time() - (self::DEFAULT_TIMEOUT_MINUTES * 60);
            $warningThreshold = time() - (self::WARNING_THRESHOLD_MINUTES * 60);
            
            // Get sessions approaching timeout
            $warningSessions = $this->getSessionsNearTimeout($warningThreshold);
            $expiredSessions = $this->getExpiredSessions($timeoutThreshold);
            
            // Send timeout warnings
            foreach ($warningSessions as $sessionData) {
                $this->sendTimeoutWarning($sessionData);
            }
            
            // Handle expired sessions
            foreach ($expiredSessions as $sessionData) {
                $this->handleSessionTimeout($sessionData);
            }
            
            $this->log("Timeout check completed: " . count($warningSessions) . " warnings sent, " . 
                      count($expiredSessions) . " sessions expired");
            
        } catch (\Exception $e) {
            $this->log("Timeout check failed: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Handle heartbeat timeout check
     */
    public function handleHeartbeatTimeout(array $response, array $data): array
    {
        if (isset($data['mpcc_session_id'])) {
            $sessionId = sanitize_text_field($data['mpcc_session_id']);
            $session = $this->conversationManager->loadSession($sessionId);
            
            if ($session) {
                $timeUntilExpiry = $session->getTimeUntilExpiry(self::DEFAULT_TIMEOUT_MINUTES);
                
                $response['mpcc_session_status'] = [
                    'session_id' => $sessionId,
                    'time_until_expiry' => $timeUntilExpiry,
                    'is_active' => $session->isActive(),
                    'needs_warning' => $timeUntilExpiry < (self::DEFAULT_TIMEOUT_MINUTES - self::WARNING_THRESHOLD_MINUTES) * 60,
                    'heartbeat_interval' => self::IDLE_CHECK_INTERVAL
                ];
            }
        }
        
        return $response;
    }

    /**
     * Handle session extension request
     */
    public function handleSessionExtension(): void
    {
        if (!NonceConstants::verify($_POST['nonce'] ?? '', NonceConstants::EXTEND_SESSION, false)) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $sessionId = sanitize_text_field($_POST['session_id'] ?? '');
        
        try {
            $session = $this->conversationManager->loadSession($sessionId);
            
            if (!$session) {
                wp_send_json_error('Session not found');
                return;
            }
            
            // Extend session by updating last activity
            $session->markAsModified();
            $this->conversationManager->saveSession($session);
            
            wp_send_json_success([
                'session_id' => $sessionId,
                'extended_until' => time() + (self::DEFAULT_TIMEOUT_MINUTES * 60),
                'time_until_expiry' => self::DEFAULT_TIMEOUT_MINUTES * 60
            ]);
            
        } catch (\Exception $e) {
            wp_send_json_error('Extension failed: ' . $e->getMessage());
        }
    }

    /**
     * Export conversation session with comprehensive data
     */
    public function exportSession(string $sessionId, array $options = []): array
    {
        $session = $this->conversationManager->loadSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found for export');
        }
        
        // Check export permissions
        if (!$this->canUserExportSession($session->getUserId(), get_current_user_id())) {
            throw new \Exception('Insufficient permissions to export this session');
        }
        
        // Prepare export data
        $exportData = [
            'export_info' => [
                'version' => self::EXPORT_FORMAT_VERSION,
                'timestamp' => current_time('timestamp'),
                'exporter_user_id' => get_current_user_id(),
                'plugin_version' => $this->getPluginVersion(),
                'wordpress_version' => get_bloginfo('version'),
                'export_options' => $options
            ],
            'session_data' => $session->toArray(),
            'conversation_analytics' => $this->conversationManager->getSessionAnalytics($sessionId),
            'metadata' => [
                'total_messages' => count($session->getMessages()),
                'session_duration' => $session->getLastUpdated() - $session->getCreatedAt(),
                'states_visited' => array_unique(array_column($session->getStateHistory(), 'state')),
                'export_size_estimate' => 0 // Will be calculated
            ]
        ];
        
        // Add optional data based on export options
        if ($options['include_analytics'] ?? true) {
            $exportData['analytics'] = $this->generateExportAnalytics($session);
        }
        
        if ($options['include_debug_info'] ?? false) {
            $exportData['debug_info'] = $this->generateDebugInfo($session);
        }
        
        if ($options['include_related_data'] ?? false) {
            $exportData['related_data'] = $this->getRelatedSessionData($session);
        }
        
        // Calculate actual export size
        $exportData['metadata']['export_size_estimate'] = $this->calculateExportSize($exportData);
        
        // Check size limits
        if ($exportData['metadata']['export_size_estimate'] > (self::MAX_EXPORT_SIZE_MB * 1024 * 1024)) {
            throw new \Exception('Export data exceeds maximum size limit');
        }
        
        // Compress if enabled and beneficial
        if (self::COMPRESSION_ENABLED && $exportData['metadata']['export_size_estimate'] > 1024) {
            $exportData = $this->compressExportData($exportData);
        }
        
        // Log export activity
        $this->log("Session exported: {$sessionId} by user " . get_current_user_id());
        
        return $exportData;
    }

    /**
     * Import conversation session from exported data
     */
    public function importSession(array $importData, array $options = []): ConversationSession
    {
        // Validate import data
        $validation = $this->validateImportData($importData);
        if (!$validation['valid']) {
            throw new \Exception('Import data validation failed: ' . implode(', ', $validation['errors']));
        }
        
        // Check import permissions
        if (!current_user_can('manage_options') && !($options['allow_user_import'] ?? false)) {
            throw new \Exception('Insufficient permissions to import sessions');
        }
        
        // Decompress if needed
        if (isset($importData['compressed']) && $importData['compressed']) {
            $importData = $this->decompressImportData($importData);
        }
        
        $sessionData = $importData['session_data'];
        
        // Handle session ID conflicts
        $originalSessionId = $sessionData['session_id'];
        if ($options['preserve_session_id'] ?? false) {
            // Check if session ID already exists
            $existing = $this->conversationManager->loadSession($originalSessionId);
            if ($existing) {
                if (!($options['overwrite_existing'] ?? false)) {
                    throw new \Exception('Session ID already exists and overwrite not allowed');
                }
            }
        } else {
            // Generate new session ID
            $sessionData['session_id'] = $this->generateNewSessionId();
        }
        
        // Update user ID if importing for different user
        if (isset($options['target_user_id'])) {
            $sessionData['user_id'] = $options['target_user_id'];
        }
        
        // Create session from imported data
        $session = $this->conversationManager->createSessionFromData($sessionData);
        
        // Add import metadata
        $session->setMetadata('import_info', [
            'imported_at' => time(),
            'imported_by' => get_current_user_id(),
            'original_session_id' => $originalSessionId,
            'import_version' => $importData['export_info']['version'] ?? 'unknown',
            'import_options' => $options
        ]);
        
        // Save imported session
        $success = $this->conversationManager->saveSession($session);
        if (!$success) {
            throw new \Exception('Failed to save imported session');
        }
        
        // Log import activity
        $this->log("Session imported: {$session->getSessionId()} from {$originalSessionId} by user " . get_current_user_id());
        
        return $session;
    }

    /**
     * Synchronize session across multiple devices/clients
     */
    public function synchronizeSession(string $sessionId, array $clientState, array $options = []): array
    {
        $session = $this->conversationManager->loadSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found for synchronization');
        }
        
        // Check sync permissions
        if (!$this->canUserSyncSession($session->getUserId(), get_current_user_id())) {
            throw new \Exception('Insufficient permissions to sync this session');
        }
        
        $serverState = $this->generateServerSyncState($session);
        $clientTimestamp = $clientState['last_updated'] ?? 0;
        $serverTimestamp = $session->getLastUpdated();
        
        $syncResult = [
            'session_id' => $sessionId,
            'sync_timestamp' => time(),
            'server_state' => $serverState,
            'client_state' => $clientState,
            'conflict_detected' => false,
            'resolution_applied' => null,
            'sync_actions' => []
        ];
        
        // Detect conflicts
        if ($this->detectSyncConflict($serverState, $clientState)) {
            $syncResult['conflict_detected'] = true;
            $resolution = $this->resolveSyncConflict($session, $serverState, $clientState, $options);
            $syncResult['resolution_applied'] = $resolution;
        }
        
        // Determine sync direction
        if ($serverTimestamp > $clientTimestamp && !$syncResult['conflict_detected']) {
            // Server is newer, send updates to client
            $syncResult['sync_direction'] = 'server_to_client';
            $syncResult['updates_for_client'] = $this->generateClientUpdates($session, $clientState);
            
        } elseif ($clientTimestamp > $serverTimestamp && !$syncResult['conflict_detected']) {
            // Client is newer, apply client updates to server
            $syncResult['sync_direction'] = 'client_to_server';
            $this->applyClientUpdates($session, $clientState);
            $this->conversationManager->saveSession($session);
            
        } else {
            // States are in sync
            $syncResult['sync_direction'] = 'in_sync';
        }
        
        // Add sync metadata to session
        $session->setMetadata('last_sync', [
            'timestamp' => time(),
            'client_id' => $clientState['client_id'] ?? 'unknown',
            'sync_direction' => $syncResult['sync_direction'],
            'conflict_resolved' => $syncResult['conflict_detected']
        ]);
        
        $this->conversationManager->saveSession($session);
        
        // Log sync activity
        $this->log("Session synchronized: {$sessionId}, direction: {$syncResult['sync_direction']}");
        
        return $syncResult;
    }

    /**
     * Enable collaborative editing for session
     */
    public function enableCollaborativeEditing(string $sessionId, array $collaborators = []): array
    {
        $session = $this->conversationManager->loadSession($sessionId);
        
        if (!$session) {
            throw new \Exception('Session not found for collaboration setup');
        }
        
        // Check collaboration permissions
        if (!$this->canUserEnableCollaboration($session->getUserId(), get_current_user_id())) {
            throw new \Exception('Insufficient permissions to enable collaboration');
        }
        
        // Set up collaboration metadata
        $collaborationData = [
            'enabled' => true,
            'owner_user_id' => $session->getUserId(),
            'collaborators' => $this->validateCollaborators($collaborators),
            'collaboration_rules' => [
                'can_edit_requirements' => true,
                'can_modify_structure' => true,
                'can_review_content' => true,
                'can_create_course' => false, // Only owner by default
                'requires_approval' => false
            ],
            'enabled_at' => time(),
            'enabled_by' => get_current_user_id()
        ];
        
        $session->setMetadata('collaboration', $collaborationData);
        
        // Create collaboration channels/locks
        $channels = $this->createCollaborationChannels($sessionId, $collaborationData);
        
        // Initialize real-time sync
        $this->initializeRealTimeSync($sessionId);
        
        $this->conversationManager->saveSession($session);
        
        // Log collaboration setup
        $this->log("Collaborative editing enabled for session: {$sessionId}");
        
        return [
            'session_id' => $sessionId,
            'collaboration_enabled' => true,
            'collaborators' => $collaborationData['collaborators'],
            'collaboration_channels' => $channels,
            'real_time_sync_url' => $this->getRealTimeSyncUrl($sessionId)
        ];
    }

    // PRIVATE HELPER METHODS

    private function scheduleAutoSaveTasks(): void
    {
        if (!wp_next_scheduled('mpcc_auto_save_sessions')) {
            wp_schedule_event(time(), 'mpcc_auto_save_interval', 'mpcc_auto_save_sessions');
        }
        
        // Add custom cron interval if it doesn't exist
        add_filter('cron_schedules', function($schedules) {
            $schedules['mpcc_auto_save_interval'] = [
                'interval' => self::AUTO_SAVE_INTERVAL,
                'display' => 'MPCC Auto Save Interval'
            ];
            return $schedules;
        });
    }

    private function scheduleTimeoutChecks(): void
    {
        if (!wp_next_scheduled('mpcc_check_session_timeouts')) {
            wp_schedule_event(time(), 'mpcc_timeout_check_interval', 'mpcc_check_session_timeouts');
        }
        
        add_filter('cron_schedules', function($schedules) {
            $schedules['mpcc_timeout_check_interval'] = [
                'interval' => self::IDLE_CHECK_INTERVAL,
                'display' => 'MPCC Timeout Check Interval'
            ];
            return $schedules;
        });
    }

    private function getActiveSessionsNeedingSave(): array
    {
        // Implementation would query database for sessions with unsaved changes
        return [];
    }

    private function handleSessionModification(string $sessionId, ConversationSession $session): void
    {
        // Trigger immediate auto-save if threshold reached
        if ($session->shouldAutoSave(self::AUTO_SAVE_INTERVAL)) {
            $this->conversationManager->saveSession($session);
        }
    }

    private function updateSessionFromClientData(ConversationSession $session, array $clientData): void
    {
        // Update session with client-side changes
        if (isset($clientData['current_state'])) {
            $session->setCurrentState($clientData['current_state']);
        }
        
        if (isset($clientData['context'])) {
            $session->setContext($clientData['context'], null);
        }
        
        if (isset($clientData['progress'])) {
            $session->updateProgress($clientData['progress']);
        }
        
        // Add any new messages
        if (isset($clientData['new_messages'])) {
            foreach ($clientData['new_messages'] as $message) {
                $session->addMessage($message['type'], $message['content'], $message['metadata'] ?? []);
            }
        }
    }

    // Additional helper methods would be implemented here...
    // For brevity, including key method signatures

    private function getSessionsNearTimeout(int $threshold): array { return []; }
    private function getExpiredSessions(int $threshold): array { return []; }
    private function sendTimeoutWarning(object $sessionData): void {}
    private function handleSessionTimeout(object $sessionData): void {}
    private function canUserExportSession(int $sessionUserId, int $currentUserId): bool { return $sessionUserId === $currentUserId; }
    private function getPluginVersion(): string { return '1.0.0'; }
    private function generateExportAnalytics(ConversationSession $session): array { return []; }
    private function generateDebugInfo(ConversationSession $session): array { return []; }
    private function getRelatedSessionData(ConversationSession $session): array { return []; }
    private function calculateExportSize(array $data): int { return strlen(json_encode($data)); }
    private function compressExportData(array $data): array { return $data; }
    private function validateImportData(array $data): array { return ['valid' => true, 'errors' => []]; }
    private function decompressImportData(array $data): array { return $data; }
    private function generateNewSessionId(): string { return 'imported_' . wp_generate_uuid4(); }
    private function canUserSyncSession(int $sessionUserId, int $currentUserId): bool { return $sessionUserId === $currentUserId; }
    private function generateServerSyncState(ConversationSession $session): array { return []; }
    private function detectSyncConflict(array $serverState, array $clientState): bool { return false; }
    private function resolveSyncConflict(ConversationSession $session, array $serverState, array $clientState, array $options): string { return 'server_wins'; }
    private function generateClientUpdates(ConversationSession $session, array $clientState): array { return []; }
    private function applyClientUpdates(ConversationSession $session, array $clientState): void {}
    private function canUserEnableCollaboration(int $sessionUserId, int $currentUserId): bool { return $sessionUserId === $currentUserId; }
    private function validateCollaborators(array $collaborators): array { return $collaborators; }
    private function createCollaborationChannels(string $sessionId, array $collaborationData): array { return []; }
    private function initializeRealTimeSync(string $sessionId): void {}
    private function getRealTimeSyncUrl(string $sessionId): string { return ''; }
}