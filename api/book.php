<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// CSRF check
if (session_status() === PHP_SESSION_NONE) session_start();
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid request token.']);
    exit;
}

// Input validation
$service_id   = (int)($_POST['service_id'] ?? 0);
$date         = trim($_POST['date'] ?? '');
$time         = trim($_POST['time'] ?? '');
$client_name  = trim($_POST['client_name'] ?? '');
$client_email = trim($_POST['client_email'] ?? '');
$client_notes = trim($_POST['client_notes'] ?? '');

$errors = [];

if ($service_id <= 0) $errors[] = 'Invalid service.';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $errors[] = 'Invalid date.';
} elseif ($date < date('Y-m-d')) {
    $errors[] = 'Cannot book in the past.';
}

if (!preg_match('/^\d{2}:\d{2}$/', $time)) $errors[] = 'Invalid time.';
if ($client_name === '')                     $errors[] = 'Name is required.';
if (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';

if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => implode(' ', $errors)]);
    exit;
}

$db = get_db();

// Load service
$stmt = $db->prepare('SELECT * FROM services WHERE id = ? AND is_active = 1');
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    echo json_encode(['error' => 'Service not found.']);
    exit;
}

$start_dt = $date . ' ' . $time . ':00';
$start_ts = strtotime($start_dt);
$end_ts   = $start_ts + ((int)$service['duration_minutes'] * 60);
$end_dt   = date('Y-m-d H:i:s', $end_ts);

// Re-check availability to guard against race conditions
$available_slots = get_available_slots($service_id, $date);
$available_times = array_column($available_slots, 'time');

if (!in_array($time, $available_times, true)) {
    http_response_code(409);
    echo json_encode(['error' => 'This slot is no longer available. Please choose another time.']);
    exit;
}

// Insert booking
$stmt = $db->prepare(
    'INSERT INTO bookings (service_id, client_name, client_email, client_notes, start_datetime, end_datetime, status)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $service_id,
    $client_name,
    $client_email,
    $client_notes,
    $start_dt,
    $end_dt,
    'pending',
]);
$booking_id = (int)$db->lastInsertId();

// Try to sync with Google Calendar
$gc              = new GoogleCalendar();
$google_event_id = null;
$status          = 'pending';

if ($gc->is_connected()) {
    try {
        $booking = [
            'client_name'   => $client_name,
            'client_email'  => $client_email,
            'client_notes'  => $client_notes,
            'start_datetime' => $start_dt,
            'end_datetime'   => $end_dt,
        ];
        $google_event_id = $gc->create_event($booking, $service);
        $status          = 'confirmed';
    } catch (Exception $e) {
        // Google sync failed — booking stays pending, admin can confirm manually
    }
}

// Update booking with Google event ID and status
$db->prepare('UPDATE bookings SET google_event_id=?, status=? WHERE id=?')
   ->execute([$google_event_id, $status, $booking_id]);

echo json_encode([
    'success'    => true,
    'booking_id' => $booking_id,
    'status'     => $status,
]);
