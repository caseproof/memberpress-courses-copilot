<?php
namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Database\LessonDraftTable;

class LessonDraftService {
    private $table;
    
    public function __construct() {
        $this->table = new LessonDraftTable();
        
        // Ensure table exists (temporary fix)
        $this->ensureTableExists();
    }
    
    /**
     * Ensure the lesson drafts table exists
     */
    private function ensureTableExists() {
        global $wpdb;
        $table_name = $this->table->getTableName();
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if (!$table_exists) {
            $this->table->create();
            error_log('MPCC: Created missing lesson drafts table');
        }
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
            error_log('MPCC: Failed to save lesson draft - Session: ' . $session_id . ', Lesson: ' . $lesson_id . ', Error: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('MPCC: Lesson draft saved - Session: ' . $session_id . ', Lesson: ' . $lesson_id . ', Content length: ' . strlen($content));
        
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
            error_log('MPCC: Failed to delete lesson draft - Session: ' . $session_id . ', Lesson: ' . $lesson_id . ', Error: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('MPCC: Lesson draft deleted - Session: ' . $session_id . ', Lesson: ' . $lesson_id);
        
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
            error_log('MPCC: Failed to delete session drafts - Session: ' . $session_id . ', Error: ' . $wpdb->last_error);
            return false;
        }
        
        error_log('MPCC: Session drafts deleted - Session: ' . $session_id . ', Count: ' . $result);
        
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
                error_log('MPCC: Failed to update lesson order - Lesson: ' . $lesson_id . ', Error: ' . $wpdb->last_error);
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
                            error_log('MPCC: Applied draft content to lesson - Section: ' . $section['title'] . ', Lesson: ' . $lesson['title']);
                        }
                    }
                }
            }
        }
        
        return $course_structure;
    }
}