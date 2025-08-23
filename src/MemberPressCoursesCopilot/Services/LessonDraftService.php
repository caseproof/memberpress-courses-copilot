<?php
namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Database\LessonDraftTable;

class LessonDraftService {
    private $logger;
    private $table;
    
    public function __construct() {
        $this->logger = new Logger();
        $this->table = new LessonDraftTable();
    }
    
    /**
     * Save or update a lesson draft
     */
    public function saveDraft($session_id, $section_id, $lesson_id, $content, $order_index = 0) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        
        // Check if draft exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$table_name} WHERE session_id = %s AND section_id = %s AND lesson_id = %s",
                $session_id,
                $section_id,
                $lesson_id
            )
        );
        
        $data = [
            'session_id' => $session_id,
            'section_id' => $section_id,
            'lesson_id' => $lesson_id,
            'content' => wp_kses_post($content),
            'order_index' => intval($order_index)
        ];
        
        if ($existing) {
            // Update existing draft
            $result = $wpdb->update(
                $table_name,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%s', '%s', '%d'],
                ['%d']
            );
        } else {
            // Insert new draft
            $result = $wpdb->insert(
                $table_name,
                $data,
                ['%s', '%s', '%s', '%s', '%d']
            );
        }
        
        if ($result === false) {
            $this->logger->error('Failed to save lesson draft', [
                'session_id' => $session_id,
                'lesson_id' => $lesson_id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }
        
        $this->logger->info('Lesson draft saved', [
            'session_id' => $session_id,
            'lesson_id' => $lesson_id,
            'content_length' => strlen($content)
        ]);
        
        return true;
    }
    
    /**
     * Get a specific lesson draft
     */
    public function getDraft($session_id, $section_id, $lesson_id) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        
        $draft = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE session_id = %s AND section_id = %s AND lesson_id = %s",
                $session_id,
                $section_id,
                $lesson_id
            )
        );
        
        return $draft;
    }
    
    /**
     * Get all drafts for a session
     */
    public function getSessionDrafts($session_id) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        
        $drafts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE session_id = %s ORDER BY section_id, order_index",
                $session_id
            )
        );
        
        return $drafts;
    }
    
    /**
     * Delete a lesson draft
     */
    public function deleteDraft($session_id, $section_id, $lesson_id) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        
        $result = $wpdb->delete(
            $table_name,
            [
                'session_id' => $session_id,
                'section_id' => $section_id,
                'lesson_id' => $lesson_id
            ],
            ['%s', '%s', '%s']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to delete lesson draft', [
                'session_id' => $session_id,
                'lesson_id' => $lesson_id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }
        
        $this->logger->info('Lesson draft deleted', [
            'session_id' => $session_id,
            'lesson_id' => $lesson_id
        ]);
        
        return true;
    }
    
    /**
     * Delete all drafts for a session
     */
    public function deleteSessionDrafts($session_id) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        
        $result = $wpdb->delete(
            $table_name,
            ['session_id' => $session_id],
            ['%s']
        );
        
        if ($result === false) {
            $this->logger->error('Failed to delete session drafts', [
                'session_id' => $session_id,
                'error' => $wpdb->last_error
            ]);
            return false;
        }
        
        $this->logger->info('Session drafts deleted', [
            'session_id' => $session_id,
            'count' => $result
        ]);
        
        return $result;
    }
    
    /**
     * Update lesson order
     */
    public function updateOrder($session_id, $section_id, $lesson_orders) {
        global $wpdb;
        
        $table_name = $this->table->getTableName();
        $success = true;
        
        foreach ($lesson_orders as $lesson_id => $order) {
            $result = $wpdb->update(
                $table_name,
                ['order_index' => intval($order)],
                [
                    'session_id' => $session_id,
                    'section_id' => $section_id,
                    'lesson_id' => $lesson_id
                ],
                ['%d'],
                ['%s', '%s', '%s']
            );
            
            if ($result === false) {
                $success = false;
                $this->logger->error('Failed to update lesson order', [
                    'lesson_id' => $lesson_id,
                    'error' => $wpdb->last_error
                ]);
            }
        }
        
        return $success;
    }
    
    /**
     * Map drafts to course structure for creation
     */
    public function mapDraftsToStructure($session_id, $course_structure) {
        $drafts = $this->getSessionDrafts($session_id);
        
        if (empty($drafts)) {
            return $course_structure;
        }
        
        // Create a map for quick lookup
        $draft_map = [];
        foreach ($drafts as $draft) {
            $key = $draft->section_id . '::' . $draft->lesson_id;
            $draft_map[$key] = $draft->content;
        }
        
        // Apply drafts to structure
        if (isset($course_structure['sections']) && is_array($course_structure['sections'])) {
            foreach ($course_structure['sections'] as $section_index => &$section) {
                if (isset($section['lessons']) && is_array($section['lessons'])) {
                    foreach ($section['lessons'] as $lesson_index => &$lesson) {
                        $section_id = 'section_' . ($section_index + 1);
                        $lesson_id = 'lesson_' . ($section_index + 1) . '_' . ($lesson_index + 1);
                        $key = $section_id . '::' . $lesson_id;
                        
                        if (isset($draft_map[$key])) {
                            $lesson['content'] = $draft_map[$key];
                            $this->logger->info('Applied draft content to lesson', [
                                'section' => $section['title'],
                                'lesson' => $lesson['title']
                            ]);
                        }
                    }
                }
            }
        }
        
        return $course_structure;
    }
}