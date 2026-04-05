<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google.php';

require_login();

if (session_status() === PHP_SESSION_NONE) session_start();

// Verify state param against CSRF
$state          = $_GET['state'] ?? '';
$expected_state = $_SESSION['google_oauth_state'] ?? '';

if ($state === '' || !hash_equals($expected_state, $state)) {
    set_flash('danger', 'OAuth state mismatch. Please try connecting again.');
    header('Location: ' . APP_URL . '/admin/google.php');
    exit;
}
unset($_SESSION['google_oauth_state']);

// Exchange auth code
$code = $_GET['code'] ?? '';
if ($code === '') {
    set_flash('danger', 'No authorisation code received from Google.');
    header('Location: ' . APP_URL . '/admin/google.php');
    exit;
}

try {
    $gc = new GoogleCalendar();
    $gc->exchange_code($code);
    set_flash('success', 'Google Calendar connected successfully!');
} catch (Exception $e) {
    set_flash('danger', 'Failed to connect Google Calendar: ' . $e->getMessage());
}

header('Location: ' . APP_URL . '/admin/google.php');
exit;
