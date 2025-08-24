<?php
/**
 * Debug test file - add to WordPress
 */

add_action('admin_init', function() {
    if (isset($_GET['mpcc_debug'])) {
        error_log('MPCC Debug: admin_init triggered');
        error_log('MPCC Debug: Current user: ' . wp_get_current_user()->user_login);
        error_log('MPCC Debug: Can edit posts: ' . (current_user_can('edit_posts') ? 'yes' : 'no'));
        error_log('MPCC Debug: Page param: ' . ($_GET['page'] ?? 'not set'));
        
        global $submenu, $menu;
        
        // Check if our menu exists
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'mpcc-course-editor') {
                error_log('MPCC Debug: Found menu item: ' . $item[0]);
            }
        }
    }
});

// Add test endpoint
add_action('admin_menu', function() {
    add_menu_page(
        'MPCC Debug',
        'MPCC Debug',
        'read',
        'mpcc-debug-test',
        function() {
            echo '<div class="wrap">';
            echo '<h1>MPCC Debug Test</h1>';
            echo '<p>Current user: ' . wp_get_current_user()->user_login . '</p>';
            echo '<p>Can edit posts: ' . (current_user_can('edit_posts') ? 'Yes' : 'No') . '</p>';
            echo '<p><a href="' . admin_url('admin.php?page=mpcc-course-editor') . '">Go to Course Editor</a></p>';
            echo '</div>';
        }
    );
}, 100);