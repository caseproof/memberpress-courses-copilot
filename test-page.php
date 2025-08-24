<?php
// Simple test to check if the page loads
add_action('admin_menu', function() {
    add_menu_page(
        'Test Page',
        'Test Page',
        'edit_posts',
        'test-page',
        function() {
            echo '<div class="wrap"><h1>Test Page Works!</h1></div>';
        },
        '',
        99
    );
}, 50);