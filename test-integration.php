<?php
/**
 * AI Chat Integration Test Page
 * 
 * Access via: yoursite.com/wp-content/plugins/memberpress-courses-copilot/test-integration.php
 */

// Security check - only allow if WordPress is loaded and user is admin
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        __DIR__ . '/../../../../wp-load.php',
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../wp-load.php'
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $wp_load_path) {
        if (file_exists($wp_load_path)) {
            require_once $wp_load_path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('WordPress not found. Access this file through WordPress admin.');
    }
}

// Check if user is admin
if (!current_user_can('manage_options')) {
    wp_die('Access denied. You need administrator privileges.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MemberPress AI Chat Integration Test</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }
        .test-section {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 5px;
            border-left: 4px solid #0073aa;
        }
        .success { border-left-color: #46b450; }
        .warning { border-left-color: #ffb900; }
        .error { border-left-color: #dc3232; }
        .status-good { color: #46b450; }
        .status-warning { color: #ffb900; }
        .status-error { color: #dc3232; }
        pre { 
            background: #fff;
            padding: 15px;
            border-radius: 3px;
            overflow-x: auto;
        }
        .test-result {
            padding: 10px;
            margin: 10px 0;
            border-radius: 3px;
        }
        .test-pass {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .test-fail {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .test-actions {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>MemberPress AI Chat Integration Test</h1>
    
    <?php
    
    echo '<div class="test-section">';
    echo '<h2>Environment Check</h2>';
    echo '<p><strong>WordPress Version:</strong> ' . get_bloginfo('version') . '</p>';
    echo '<p><strong>PHP Version:</strong> ' . PHP_VERSION . '</p>';
    echo '<p><strong>Current User:</strong> ' . wp_get_current_user()->user_login . '</p>';
    echo '<p><strong>User Capabilities:</strong> ' . (current_user_can('edit_posts') ? '✅ edit_posts' : '❌ edit_posts') . '</p>';
    echo '</div>';
    
    // Test 1: Plugin Activation
    echo '<div class="test-section">';
    echo '<h2>Test 1: Plugin Status</h2>';
    
    $plugin_file = 'memberpress-courses-copilot/memberpress-courses-copilot.php';
    $is_active = is_plugin_active($plugin_file);
    
    if ($is_active) {
        echo '<div class="test-result test-pass">✅ Plugin is active</div>';
    } else {
        echo '<div class="test-result test-fail">❌ Plugin is not active</div>';
        echo '<div class="test-actions">Please activate the MemberPress Courses Copilot plugin</div>';
    }
    echo '</div>';
    
    // Test 2: Class Loading
    echo '<div class="test-section">';
    echo '<h2>Test 2: Class Loading</h2>';
    
    $required_classes = [
        'MemberPressCoursesCopilot\\Plugin',
        'MemberPressCoursesCopilot\\Services\\NewCourseIntegration',
        'MemberPressCoursesCopilot\\Services\\CourseAjaxService'
    ];
    
    foreach ($required_classes as $class) {
        if (class_exists($class)) {
            echo '<div class="test-result test-pass">✅ ' . $class . ' loaded</div>';
        } else {
            echo '<div class="test-result test-fail">❌ ' . $class . ' not found</div>';
        }
    }
    echo '</div>';
    
    // Test 3: Hook Registration
    echo '<div class="test-section">';
    echo '<h2>Test 3: WordPress Hooks</h2>';
    
    global $wp_filter;
    
    // Check for metabox hook
    $metabox_hooked = false;
    if (isset($wp_filter['add_meta_boxes'])) {
        foreach ($wp_filter['add_meta_boxes']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && 
                    is_object($callback['function'][0]) && 
                    get_class($callback['function'][0]) === 'MemberPressCoursesCopilot\\Services\\NewCourseIntegration') {
                    $metabox_hooked = true;
                    break 2;
                }
            }
        }
    }
    
    if ($metabox_hooked) {
        echo '<div class="test-result test-pass">✅ Metabox hook registered</div>';
    } else {
        echo '<div class="test-result test-fail">❌ Metabox hook not found</div>';
    }
    
    // Check for AJAX hooks
    $ajax_hooks = ['wp_ajax_mpcc_new_ai_chat'];
    
    foreach ($ajax_hooks as $hook) {
        $ajax_hooked = false;
        if (isset($wp_filter[$hook])) {
            foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && 
                        is_object($callback['function'][0]) && 
                        get_class($callback['function'][0]) === 'MemberPressCoursesCopilot\\Services\\CourseAjaxService') {
                        $ajax_hooked = true;
                        break 2;
                    }
                }
            }
        }
        
        if ($ajax_hooked) {
            echo '<div class="test-result test-pass">✅ AJAX hook ' . $hook . ' registered</div>';
        } else {
            echo '<div class="test-result test-fail">❌ AJAX hook ' . $hook . ' not found</div>';
        }
    }
    echo '</div>';
    
    // Test 4: MemberPress Courses Integration
    echo '<div class="test-section">';
    echo '<h2>Test 4: MemberPress Courses Integration</h2>';
    
    // Check if MemberPress is active
    $memberpress_active = is_plugin_active('memberpress/memberpress.php');
    if ($memberpress_active) {
        echo '<div class="test-result test-pass">✅ MemberPress plugin active</div>';
    } else {
        echo '<div class="test-result test-fail">❌ MemberPress plugin not active</div>';
    }
    
    // Check if MemberPress Courses is active
    $courses_active = is_plugin_active('memberpress-courses/main.php');
    if ($courses_active) {
        echo '<div class="test-result test-pass">✅ MemberPress Courses plugin active</div>';
    } else {
        echo '<div class="test-result test-fail">❌ MemberPress Courses plugin not active</div>';
    }
    
    // Check for course post type
    $post_types = get_post_types();
    if (isset($post_types['mpcs-course'])) {
        echo '<div class="test-result test-pass">✅ Course post type (mpcs-course) registered</div>';
    } else {
        echo '<div class="test-result test-fail">❌ Course post type (mpcs-course) not found</div>';
    }
    echo '</div>';
    
    // Test 5: Database Tables
    echo '<div class="test-section">';
    echo '<h2>Test 5: Database Tables</h2>';
    
    global $wpdb;
    
    $required_tables = [
        $wpdb->prefix . 'mpcc_conversations',
        $wpdb->prefix . 'mpcc_conversation_sessions',
        $wpdb->prefix . 'mpcc_lesson_drafts'
    ];
    
    foreach ($required_tables as $table) {
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
        if ($table_exists) {
            echo '<div class="test-result test-pass">✅ Table ' . $table . ' exists</div>';
        } else {
            echo '<div class="test-result test-fail">❌ Table ' . $table . ' missing</div>';
        }
    }
    echo '</div>';
    
    // Test 6: AJAX Endpoint Test
    echo '<div class="test-section">';
    echo '<h2>Test 6: AJAX Endpoint Test</h2>';
    
    // Create a nonce for testing
    $nonce = wp_create_nonce(\MemberPressCoursesCopilot\Security\NonceConstants::AI_ASSISTANT);
    
    echo '<p>You can test the AJAX endpoint using browser developer tools:</p>';
    echo '<pre>';
    echo "// Open browser developer console and run this:\n";
    echo "fetch('" . admin_url('admin-ajax.php') . "', {\n";
    echo "  method: 'POST',\n";
    echo "  headers: {\n";
    echo "    'Content-Type': 'application/x-www-form-urlencoded',\n";
    echo "  },\n";
    echo "  body: new URLSearchParams({\n";
    echo "    action: 'mpcc_new_ai_chat',\n";
    echo "    nonce: '" . $nonce . "',\n";
    echo "    message: 'Hello, this is a test',\n";
    echo "    post_id: 1\n";
    echo "  })\n";
    echo "})\n";
    echo ".then(response => response.json())\n";
    echo ".then(data => console.log('AI Chat Response:', data))\n";
    echo ".catch(error => console.error('Error:', error));";
    echo '</pre>';
    echo '</div>';
    
    // Test 7: Manual Testing Instructions
    echo '<div class="test-section">';
    echo '<h2>Test 7: Manual Testing Steps</h2>';
    echo '<ol>';
    echo '<li>Go to <a href="' . admin_url('edit.php?post_type=mpcs-course') . '" target="_blank">Courses list</a></li>';
    echo '<li>Edit an existing course or <a href="' . admin_url('post-new.php?post_type=mpcs-course') . '" target="_blank">create a new course</a></li>';
    echo '<li>Look for "AI Course Assistant" metabox in the right sidebar</li>';
    echo '<li>Click "Open AI Chat" button</li>';
    echo '<li>Type a message and click "Send Message"</li>';
    echo '<li>Check browser console for errors (F12 → Console)</li>';
    echo '</ol>';
    echo '</div>';
    
    // Summary
    echo '<div class="test-section">';
    echo '<h2>Test Summary</h2>';
    
    if ($is_active && class_exists('MemberPressCoursesCopilot\\Plugin')) {
        echo '<div class="test-result test-pass">';
        echo '<strong>✅ Integration appears to be working!</strong><br>';
        echo 'The plugin is loaded and classes are available. ';
        echo 'Try the manual testing steps above to verify the AI chat interface.';
        echo '</div>';
    } else {
        echo '<div class="test-result test-fail">';
        echo '<strong>❌ Integration has issues</strong><br>';
        echo 'Please check the failed tests above and resolve them first.';
        echo '</div>';
    }
    
    echo '</div>';
    ?>
    
    <div class="test-actions">
        <strong>Need Help?</strong><br>
        • Check WordPress error logs: <code><?php echo WP_CONTENT_DIR; ?>/debug.log</code><br>
        • Verify plugin activation in <a href="<?php echo admin_url('plugins.php'); ?>" target="_blank">Plugins page</a><br>
        • Ensure you have admin privileges<br>
        • Check browser console for JavaScript errors
    </div>
</body>
</html>