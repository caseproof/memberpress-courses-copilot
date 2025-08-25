<?php
// Load WordPress
require_once('../../../../wp-load.php');

// Load plugin autoload
require_once(__DIR__ . '/vendor/autoload.php');

// Check if user is logged in and has admin privileges
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    die('Unauthorized');
}

$sessionService = new \MemberPressCoursesCopilot\Services\SessionService();
$deleted = $sessionService->cleanupEmptySessions();

echo "Successfully cleaned up $deleted empty session(s).\n";