<?php

namespace MemberPressCoursesCopilot\Services;

use MemberPressCoursesCopilot\Database\LessonDraftTable;
use MemberPressCoursesCopilot\Interfaces\IDatabaseService;

class LessonDraftService
{
    private $table;
    private ?IDatabaseService $databaseService;

    /**
     * Constructor with dependency injection
     *
     * @param IDatabaseService|null $databaseService
     */
    public function __construct(?IDatabaseService $databaseService = null)
    {
        // Store database service (optional for backward compatibility)
        $this->databaseService = $databaseService;

        $this->table = new LessonDraftTable();

        // Ensure table exists (temporary fix)
        $this->ensureTableExists();
    }

    /**
     * Ensure the lesson drafts table exists
     */
    private function ensureTableExists()
    {
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
    public function saveDraft($session_id, $section_id, $lesson_id, $content, $order_index = 0)
    {
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
            'session_id'  => $session_id,
            'section_id'  => $section_id,
            'lesson_id'   => $lesson_id,
            'content'     => $content, // Content is already sanitized in the AJAX handler
            'order_index' => intval($order_index),
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
    public function getDraft($session_id, $section_id, $lesson_id)
    {
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
    public function getSessionDrafts($session_id)
    {
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
    public function deleteDraft($session_id, $section_id, $lesson_id)
    {
        global $wpdb;

        $table_name = $this->table->getTableName();

        $result = $wpdb->delete(
            $table_name,
            [
                'session_id' => $session_id,
                'section_id' => $section_id,
                'lesson_id'  => $lesson_id,
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
    public function deleteSessionDrafts($session_id)
    {
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
    public function updateOrder($session_id, $section_id, $lesson_orders)
    {
        global $wpdb;

        $table_name = $this->table->getTableName();
        $success    = true;

        foreach ($lesson_orders as $lesson_id => $order) {
            $result = $wpdb->update(
                $table_name,
                ['order_index' => intval($order)],
                [
                    'session_id' => $session_id,
                    'section_id' => $section_id,
                    'lesson_id'  => $lesson_id,
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
    public function mapDraftsToStructure($session_id, $course_structure)
    {
        $drafts = $this->getSessionDrafts($session_id);

        error_log('MPCC: mapDraftsToStructure called - Session: ' . $session_id . ', Drafts found: ' . count($drafts));

        if (empty($drafts)) {
            error_log('MPCC: No drafts found for session: ' . $session_id);
            return $course_structure;
        }

        // Create a map for quick lookup
        $draft_map = [];
        foreach ($drafts as $draft) {
            $key             = $draft->section_id . '::' . $draft->lesson_id;
            $draft_map[$key] = $draft->content;
            error_log('MPCC: Draft found - Key: ' . $key . ', Content length: ' . strlen($draft->content));
        }

        // Apply drafts to structure
        if (isset($course_structure['sections']) && is_array($course_structure['sections'])) {
            foreach ($course_structure['sections'] as $section_index => &$section) {
                if (isset($section['lessons']) && is_array($section['lessons'])) {
                    foreach ($section['lessons'] as $lesson_index => &$lesson) {
                        // Try both numeric indices and formatted IDs
                        $keys_to_try = [
                            // Numeric indices (what JS sends)
                            $section_index . '::' . $lesson_index,
                            // Formatted IDs
                            'section_' . ($section_index + 1) . '::' . 'lesson_' . ($section_index + 1) . '_' . ($lesson_index + 1),
                            // String indices
                            strval($section_index) . '::' . strval($lesson_index),
                        ];

                        foreach ($keys_to_try as $key) {
                            error_log('MPCC: Looking for draft with key: ' . $key);

                            if (isset($draft_map[$key])) {
                                $lesson['content'] = $draft_map[$key];
                                error_log('MPCC: Applied draft content to lesson - Section: ' . $section['title'] . ', Lesson: ' . $lesson['title'] . ', Content length: ' . strlen($draft_map[$key]));
                                break; // Found a match, stop trying other keys
                            }
                        }

                        if (!isset($lesson['content'])) {
                            error_log('MPCC: No draft found for lesson - Section: ' . $section['title'] . ', Lesson: ' . $lesson['title']);
                        }
                    }
                }
            }
        } else {
            error_log('MPCC: Course structure has no sections or sections is not an array');
        }

        return $course_structure;
    }

    /**
     * Copy all lesson drafts from one session to another
     */
    public function copySessionDrafts($sourceSessionId, $targetSessionId)
    {
        global $wpdb;

        $table_name = $this->table->getTableName();

        // Get all drafts from the source session
        $sourceDrafts = $this->getSessionDrafts($sourceSessionId);

        if (empty($sourceDrafts)) {
            error_log('MPCC: No drafts found to copy from session: ' . $sourceSessionId);
            return 0;
        }

        $copiedCount = 0;

        // Copy each draft to the target session
        foreach ($sourceDrafts as $draft) {
            $result = $this->saveDraft(
                $targetSessionId,
                $draft->section_id,
                $draft->lesson_id,
                $draft->content,
                $draft->order_index
            );

            if ($result) {
                ++$copiedCount;
            }
        }

        error_log('MPCC: Copied ' . $copiedCount . ' lesson drafts from session ' . $sourceSessionId . ' to session ' . $targetSessionId);

        return $copiedCount;
    }
}
