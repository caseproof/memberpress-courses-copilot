<?php

declare(strict_types=1);

namespace MemberPressCoursesCopilot\Services;

/**
 * Session Service
 * 
 * Handles conversation session storage using WordPress options
 */
class SessionService
{
    private const OPTION_PREFIX = 'mpcc_session_';
    private const SESSION_EXPIRY = 30 * DAY_IN_SECONDS; // 30 days
    
    /**
     * Get session data
     * 
     * @param string $sessionId Session ID
     * @return array|null Session data or null if not found
     */
    public function getSession(string $sessionId): ?array
    {
        // If session ID already includes the prefix, use it as is
        if (strpos($sessionId, self::OPTION_PREFIX) === 0) {
            $optionName = $sessionId;
        } else {
            $optionName = self::OPTION_PREFIX . $sessionId;
        }
        $data = get_option($optionName);
        
        if ($data === false) {
            return null;
        }
        
        // Check if session has expired
        if (isset($data['expires']) && $data['expires'] < time()) {
            delete_option($optionName);
            return null;
        }
        
        return $data;
    }
    
    /**
     * Save session data
     * 
     * @param string $sessionId Session ID
     * @param array $data Session data
     * @return bool Success
     */
    public function saveSession(string $sessionId, array $data): bool
    {
        // If session ID already includes the prefix, use it as is
        if (strpos($sessionId, self::OPTION_PREFIX) === 0) {
            $optionName = $sessionId;
        } else {
            $optionName = self::OPTION_PREFIX . $sessionId;
        }
        
        // Add expiry timestamp
        $data['expires'] = time() + self::SESSION_EXPIRY;
        
        return update_option($optionName, $data, false);
    }
    
    /**
     * Delete session
     * 
     * @param string $sessionId Session ID
     * @return bool Success
     */
    public function deleteSession(string $sessionId): bool
    {
        $optionName = self::OPTION_PREFIX . $sessionId;
        return delete_option($optionName);
    }
    
    /**
     * Clean up empty sessions (no messages, no course structure)
     * 
     * @return int Number of sessions deleted
     */
    public function cleanupEmptySessions(): int
    {
        global $wpdb;
        
        $deleted = 0;
        $prefix = self::OPTION_PREFIX;
        
        // Get all session options
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
        
        foreach ($results as $result) {
            $data = maybe_unserialize($result->option_value);
            
            if (!is_array($data)) {
                continue;
            }
            
            // Check if session has meaningful content
            $hasMessages = isset($data['conversation_history']) && 
                         is_array($data['conversation_history']) && 
                         count($data['conversation_history']) > 0;
                         
            $hasCourseStructure = (isset($data['conversation_state']['course_structure']['title']) && 
                                 !empty($data['conversation_state']['course_structure']['title'])) ||
                                (isset($data['conversation_state']['course_data']['title']) && 
                                 !empty($data['conversation_state']['course_data']['title'])) ||
                                (isset($data['title']) && 
                                 !empty($data['title']) && 
                                 $data['title'] !== 'Untitled Course');
            
            // Delete if empty
            if (!$hasMessages && !$hasCourseStructure) {
                delete_option($result->option_name);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Clean up expired sessions
     * 
     * @return int Number of sessions cleaned
     */
    public function cleanupExpiredSessions(): int
    {
        global $wpdb;
        
        $count = 0;
        $prefix = self::OPTION_PREFIX;
        
        // Get all session options
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
        
        foreach ($sessions as $session) {
            $data = maybe_unserialize($session->option_value);
            
            // Delete if expired
            if (is_array($data) && isset($data['expires']) && $data['expires'] < time()) {
                delete_option($session->option_name);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Get all sessions for the current user
     * 
     * @return array List of sessions
     */
    public function getAllSessions(): array
    {
        global $wpdb;
        
        $sessions = [];
        $prefix = self::OPTION_PREFIX;
        
        // Get all session options
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like($prefix) . '%'
            )
        );
        
        foreach ($results as $result) {
            $data = maybe_unserialize($result->option_value);
            
            // Skip expired sessions
            if (!is_array($data) || (isset($data['expires']) && $data['expires'] < time())) {
                continue;
            }
            
            // Keep the full session ID including prefix
            $sessionId = $result->option_name;
            
            // Get course title - check both direct title and conversation state
            $title = 'Untitled Course';
            if (isset($data['title'])) {
                $title = $data['title'];
            } elseif (isset($data['conversation_state']['course_data']['title'])) {
                $title = 'Course: ' . $data['conversation_state']['course_data']['title'];
            } elseif (isset($data['conversation_state']['course_structure']['title'])) {
                $title = 'Course: ' . $data['conversation_state']['course_structure']['title'];
            }
            
            $sessions[] = [
                'id' => $sessionId,
                'title' => $title,
                'last_updated' => $data['last_updated'] ?? date('Y-m-d H:i:s'),
                'message_count' => count($data['conversation_history'] ?? [])
            ];
        }
        
        // Sort by last updated, newest first
        usort($sessions, function($a, $b) {
            return strtotime($b['last_updated']) - strtotime($a['last_updated']);
        });
        
        return $sessions;
    }
}