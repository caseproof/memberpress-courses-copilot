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
        $tableName = $this->table->getTableName();

        // Check if table exists
        $tableExists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tableName)) === $tableName;

        if (!$tableExists) {
            $this->table->create();
            error_log('MPCC: Created missing lesson drafts table');
        }
    }

    /**
     * Save or update a lesson draft
     */
    public function saveDraft($sessionId, $sectionId, $lessonId, $content, $orderIndex = 0)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

        // Check if draft exists
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id FROM {$tableName} WHERE session_id = %s AND section_id = %s AND lesson_id = %s",
                $sessionId,
                $sectionId,
                $lessonId
            )
        );

        $data = [
            'session_id'  => $sessionId,
            'section_id'  => $sectionId,
            'lesson_id'   => $lessonId,
            'content'     => $content, // Content is already sanitized in the AJAX handler
            'order_index' => intval($orderIndex),
        ];

        if ($existing) {
            // Update existing draft
            $result = $wpdb->update(
                $tableName,
                $data,
                ['id' => $existing->id],
                ['%s', '%s', '%s', '%s', '%d'],
                ['%d']
            );
        } else {
            // Insert new draft
            $result = $wpdb->insert(
                $tableName,
                $data,
                ['%s', '%s', '%s', '%s', '%d']
            );
        }

        if ($result === false) {
            error_log('MPCC: Failed to save lesson draft - Session: ' . $sessionId . ', Lesson: ' . $lessonId . ', Error: ' . $wpdb->last_error);
            return false;
        }

        error_log('MPCC: Lesson draft saved - Session: ' . $sessionId . ', Lesson: ' . $lessonId . ', Content length: ' . strlen($content));

        return true;
    }

    /**
     * Get a specific lesson draft
     */
    public function getDraft($sessionId, $sectionId, $lessonId)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

        $draft = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE session_id = %s AND section_id = %s AND lesson_id = %s",
                $sessionId,
                $sectionId,
                $lessonId
            )
        );

        return $draft;
    }

    /**
     * Get all drafts for a session
     */
    public function getSessionDrafts($sessionId)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

        $drafts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tableName} WHERE session_id = %s ORDER BY section_id, order_index",
                $sessionId
            )
        );

        return $drafts;
    }

    /**
     * Delete a lesson draft
     */
    public function deleteDraft($sessionId, $sectionId, $lessonId)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

        $result = $wpdb->delete(
            $tableName,
            [
                'session_id' => $sessionId,
                'section_id' => $sectionId,
                'lesson_id'  => $lessonId,
            ],
            ['%s', '%s', '%s']
        );

        if ($result === false) {
            error_log('MPCC: Failed to delete lesson draft - Session: ' . $sessionId . ', Lesson: ' . $lessonId . ', Error: ' . $wpdb->last_error);
            return false;
        }

        error_log('MPCC: Lesson draft deleted - Session: ' . $sessionId . ', Lesson: ' . $lessonId);

        return true;
    }

    /**
     * Delete all drafts for a session
     */
    public function deleteSessionDrafts($sessionId)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

        $result = $wpdb->delete(
            $tableName,
            ['session_id' => $sessionId],
            ['%s']
        );

        if ($result === false) {
            error_log('MPCC: Failed to delete session drafts - Session: ' . $sessionId . ', Error: ' . $wpdb->last_error);
            return false;
        }

        error_log('MPCC: Session drafts deleted - Session: ' . $sessionId . ', Count: ' . $result);

        return $result;
    }

    /**
     * Update lesson order
     */
    public function updateOrder($sessionId, $sectionId, $lessonOrders)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();
        $success   = true;

        foreach ($lessonOrders as $lessonId => $order) {
            $result = $wpdb->update(
                $tableName,
                ['order_index' => intval($order)],
                [
                    'session_id' => $sessionId,
                    'section_id' => $sectionId,
                    'lesson_id'  => $lessonId,
                ],
                ['%d'],
                ['%s', '%s', '%s']
            );

            if ($result === false) {
                $success = false;
                error_log('MPCC: Failed to update lesson order - Lesson: ' . $lessonId . ', Error: ' . $wpdb->last_error);
            }
        }

        return $success;
    }

    /**
     * Map drafts to course structure for creation
     */
    public function mapDraftsToStructure($sessionId, $courseStructure)
    {
        $drafts = $this->getSessionDrafts($sessionId);

        error_log('MPCC: mapDraftsToStructure called - Session: ' . $sessionId . ', Drafts found: ' . count($drafts));

        if (empty($drafts)) {
            error_log('MPCC: No drafts found for session: ' . $sessionId);
            return $courseStructure;
        }

        // Create a map for quick lookup
        $draftMap = [];
        foreach ($drafts as $draft) {
            $key             = $draft->section_id . '::' . $draft->lesson_id;
            $draftMap[$key] = $draft->content;
            error_log('MPCC: Draft found - Key: ' . $key . ', Content length: ' . strlen($draft->content));
        }

        // Apply drafts to structure
        if (isset($courseStructure['sections']) && is_array($courseStructure['sections'])) {
            foreach ($courseStructure['sections'] as $sectionIndex => &$section) {
                if (isset($section['lessons']) && is_array($section['lessons'])) {
                    foreach ($section['lessons'] as $lessonIndex => &$lesson) {
                        // Try both numeric indices and formatted IDs
                        $keysToTry = [
                            // Numeric indices (what JS sends)
                            $sectionIndex . '::' . $lessonIndex,
                            // Formatted IDs
                            'section_' . ($sectionIndex + 1) . '::' . 'lesson_' . ($sectionIndex + 1) . '_' . ($lessonIndex + 1),
                            // String indices
                            strval($sectionIndex) . '::' . strval($lessonIndex),
                        ];

                        foreach ($keysToTry as $key) {
                            error_log('MPCC: Looking for draft with key: ' . $key);

                            if (isset($draftMap[$key])) {
                                $lesson['content'] = $draftMap[$key];
                                error_log('MPCC: Applied draft content to lesson - Section: ' . $section['title'] . ', Lesson: ' . $lesson['title'] . ', Content length: ' . strlen($draftMap[$key]));
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

        return $courseStructure;
    }

    /**
     * Copy all lesson drafts from one session to another
     */
    public function copySessionDrafts($sourceSessionId, $targetSessionId)
    {
        global $wpdb;

        $tableName = $this->table->getTableName();

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
