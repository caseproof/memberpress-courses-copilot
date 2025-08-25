<?php
/**
 * Admin Cleanup Sessions Tool
 * Run this from the browser while logged in as admin
 */

// Load WordPress
require_once('../../../../wp-load.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('You must be logged in as an administrator to run this cleanup.');
}

// Load plugin autoload
require_once(__DIR__ . '/vendor/autoload.php');

?>
<!DOCTYPE html>
<html>
<head>
    <title>MemberPress Courses Copilot - Session Cleanup</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .success {
            color: green;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .info {
            color: #1976d2;
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .warning {
            color: #f57c00;
            background: #fff3e0;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        button {
            background: #2271b1;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #135e96;
        }
        .session-list {
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .session-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        .session-item:last-child {
            border-bottom: none;
        }
        .empty-session {
            background: #ffebee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>MemberPress Courses Copilot - Session Cleanup</h1>
        
        <?php
        $sessionService = new \MemberPressCoursesCopilot\Services\SessionService();
        
        // Get all sessions before cleanup
        global $wpdb;
        $prefix = 'mpcc_session_';
        $all_sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
                $wpdb->esc_like($prefix) . '%'
            )
        );
        
        $empty_count = 0;
        $sessions_info = [];
        
        foreach ($all_sessions as $session) {
            $data = maybe_unserialize($session->option_value);
            if (!is_array($data)) continue;
            
            // Check if session has user messages
            $hasUserMessages = false;
            $message_count = 0;
            if (isset($data['conversation_history']) && is_array($data['conversation_history'])) {
                foreach ($data['conversation_history'] as $msg) {
                    if (isset($msg['role']) && $msg['role'] === 'user') {
                        $hasUserMessages = true;
                        $message_count++;
                    }
                }
            }
            
            $hasCourseStructure = (isset($data['conversation_state']['course_structure']['title']) && 
                                 !empty($data['conversation_state']['course_structure']['title'])) ||
                                (isset($data['conversation_state']['course_data']['title']) && 
                                 !empty($data['conversation_state']['course_data']['title']));
            
            $title = $data['title'] ?? 'Untitled Course';
            $is_empty = !$hasUserMessages && !$hasCourseStructure;
            
            if ($is_empty) {
                $empty_count++;
            }
            
            $sessions_info[] = [
                'name' => $session->option_name,
                'title' => $title,
                'is_empty' => $is_empty,
                'user_messages' => $message_count,
                'has_structure' => $hasCourseStructure,
                'last_updated' => $data['last_updated'] ?? 'Unknown'
            ];
        }
        
        if (isset($_POST['cleanup'])) {
            // Run cleanup
            $deleted = $sessionService->cleanupEmptySessions();
            echo '<div class="success">Successfully cleaned up ' . $deleted . ' empty session(s)!</div>';
            
            // Refresh the page after cleanup
            echo '<script>setTimeout(function() { window.location.reload(); }, 2000);</script>';
        } else {
            ?>
            <div class="info">
                <strong>Total sessions found:</strong> <?php echo count($all_sessions); ?><br>
                <strong>Empty sessions to be deleted:</strong> <?php echo $empty_count; ?>
            </div>
            
            <?php if ($empty_count > 0): ?>
                <div class="warning">
                    <strong>Warning:</strong> Clicking the cleanup button below will permanently delete <?php echo $empty_count; ?> empty session(s).
                    These are sessions with no user messages and no course structure.
                </div>
                
                <form method="post" action="">
                    <button type="submit" name="cleanup" value="1" onclick="return confirm('Are you sure you want to delete <?php echo $empty_count; ?> empty sessions? This cannot be undone.');">
                        Clean Up Empty Sessions
                    </button>
                </form>
            <?php else: ?>
                <div class="success">
                    No empty sessions found. Your database is clean!
                </div>
            <?php endif; ?>
            
            <h2>Session Details</h2>
            <div class="session-list">
                <?php foreach ($sessions_info as $session): ?>
                    <div class="session-item <?php echo $session['is_empty'] ? 'empty-session' : ''; ?>">
                        <strong><?php echo esc_html($session['title']); ?></strong><br>
                        <small>
                            Last updated: <?php echo esc_html($session['last_updated']); ?><br>
                            User messages: <?php echo $session['user_messages']; ?> | 
                            Has structure: <?php echo $session['has_structure'] ? 'Yes' : 'No'; ?> |
                            <?php echo $session['is_empty'] ? '<strong style="color: red;">WILL BE DELETED</strong>' : '<span style="color: green;">Will be kept</span>'; ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
        }
        ?>
        
        <p style="margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=mpcc-course-editor'); ?>">← Back to Course Editor</a>
        </p>
    </div>
</body>
</html>