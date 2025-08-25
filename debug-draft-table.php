<?php
/**
 * Debug script to check lesson drafts table status
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    // If not in WordPress context, load WordPress
    $wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found');
    }
}

global $wpdb;

echo "<h2>Lesson Drafts Table Debug Information</h2>";

// Check if table exists
$table_name = $wpdb->prefix . 'mpcc_lesson_drafts';
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;

echo "<h3>Table Status:</h3>";
echo "<p>Table name: <code>{$table_name}</code></p>";
echo "<p>Exists: " . ($table_exists ? "✅ Yes" : "❌ No") . "</p>";

if (!$table_exists) {
    echo "<h3>Attempting to create table...</h3>";
    
    // Try to create the table
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(255) NOT NULL,
        section_id VARCHAR(255) NOT NULL,
        lesson_id VARCHAR(255) NOT NULL,
        content LONGTEXT,
        order_index INT(11) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_session (session_id),
        INDEX idx_section_lesson (section_id, lesson_id),
        UNIQUE KEY unique_lesson (session_id, section_id, lesson_id)
    ) {$charset_collate};";
    
    dbDelta($sql);
    
    // Check again
    $table_exists_after = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
    echo "<p>Table created: " . ($table_exists_after ? "✅ Success" : "❌ Failed") . "</p>";
    
    if ($wpdb->last_error) {
        echo "<p>Error: <code>{$wpdb->last_error}</code></p>";
    }
} else {
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>{$column->Field}</td>";
        echo "<td>{$column->Type}</td>";
        echo "<td>{$column->Null}</td>";
        echo "<td>{$column->Key}</td>";
        echo "<td>{$column->Default}</td>";
        echo "<td>{$column->Extra}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show draft count
    echo "<h3>Draft Statistics:</h3>";
    $draft_count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
    echo "<p>Total drafts in database: <strong>{$draft_count}</strong></p>";
    
    // Show recent drafts
    if ($draft_count > 0) {
        echo "<h3>Recent Drafts (last 5):</h3>";
        $recent_drafts = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 5");
        echo "<table border='1' cellpadding='5' style='width: 100%;'>";
        echo "<tr><th>ID</th><th>Session ID</th><th>Section ID</th><th>Lesson ID</th><th>Content Length</th><th>Created</th><th>Updated</th></tr>";
        foreach ($recent_drafts as $draft) {
            echo "<tr>";
            echo "<td>{$draft->id}</td>";
            echo "<td>" . substr($draft->session_id, 0, 30) . "...</td>";
            echo "<td>{$draft->section_id}</td>";
            echo "<td>{$draft->lesson_id}</td>";
            echo "<td>" . strlen($draft->content) . " chars</td>";
            echo "<td>{$draft->created_at}</td>";
            echo "<td>{$draft->updated_at}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test insert
    echo "<h3>Testing Insert Operation:</h3>";
    $test_session_id = 'test_' . time();
    $test_data = [
        'session_id' => $test_session_id,
        'section_id' => 'test_section_1',
        'lesson_id' => 'test_lesson_1',
        'content' => 'This is a test draft content created at ' . current_time('mysql'),
        'order_index' => 0
    ];
    
    $insert_result = $wpdb->insert($table_name, $test_data);
    
    if ($insert_result !== false) {
        echo "<p>✅ Test insert successful (ID: {$wpdb->insert_id})</p>";
        
        // Clean up test data
        $wpdb->delete($table_name, ['session_id' => $test_session_id]);
        echo "<p>Test data cleaned up</p>";
    } else {
        echo "<p>❌ Test insert failed</p>";
        if ($wpdb->last_error) {
            echo "<p>Error: <code>{$wpdb->last_error}</code></p>";
        }
    }
}

// Check for any database errors
echo "<h3>Database Information:</h3>";
echo "<p>WordPress table prefix: <code>{$wpdb->prefix}</code></p>";
echo "<p>Database character set: <code>{$wpdb->charset}</code></p>";
echo "<p>Database collation: <code>{$wpdb->collate}</code></p>";

if ($wpdb->last_error) {
    echo "<p>Last database error: <code>{$wpdb->last_error}</code></p>";
}

// Check if plugin activation hook was run
echo "<h3>Plugin Status:</h3>";
$db_version = get_option('mpcc_db_version', 'not set');
echo "<p>Database version in options: <code>{$db_version}</code></p>";

// Check if DatabaseService can be loaded
echo "<h3>Class Loading Test:</h3>";
if (class_exists('MemberPressCoursesCopilot\\Database\\LessonDraftTable')) {
    echo "<p>✅ LessonDraftTable class can be loaded</p>";
    
    $draftTable = new \MemberPressCoursesCopilot\Database\LessonDraftTable();
    echo "<p>Table name from class: <code>" . $draftTable->getTableName() . "</code></p>";
} else {
    echo "<p>❌ LessonDraftTable class cannot be loaded</p>";
}

if (class_exists('MemberPressCoursesCopilot\\Services\\LessonDraftService')) {
    echo "<p>✅ LessonDraftService class can be loaded</p>";
} else {
    echo "<p>❌ LessonDraftService class cannot be loaded</p>";
}

echo "<hr>";
echo "<p><em>Debug script completed at " . current_time('mysql') . "</em></p>";