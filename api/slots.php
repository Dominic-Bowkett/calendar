<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/google.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$service_id = (int)($_GET['service_id'] ?? 0);
$date       = $_GET['date'] ?? '';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate(
    (int)substr($date, 5, 2),
    (int)substr($date, 8, 2),
    (int)substr($date, 0, 4)
)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date.']);
    exit;
}

if ($service_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid service.']);
    exit;
}

// Don't allow dates more than 60 days in the future
if (strtotime($date) > strtotime('+60 days')) {
    echo json_encode(['slots' => []]);
    exit;
}

// Don't allow past dates
if ($date < date('Y-m-d')) {
    echo json_encode(['slots' => []]);
    exit;
}

try {
    $slots = get_available_slots($service_id, $date);
    echo json_encode(['slots' => $slots]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load slots.']);
}
