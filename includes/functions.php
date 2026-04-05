<?php
require_once __DIR__ . '/google.php';

/**
 * Get available time slots for a given service and date.
 * Filters out: past slots, booked slots, Google Calendar busy times.
 */
function get_available_slots(int $service_id, string $date): array {
    $db = get_db();

    // Load service
    $stmt = $db->prepare('SELECT duration_minutes FROM services WHERE id = ? AND is_active = 1');
    $stmt->execute([$service_id]);
    $service = $stmt->fetch();
    if (!$service) {
        return [];
    }
    $duration = (int)$service['duration_minutes'];

    // Load availability for this day of week
    $day_of_week = (int)date('w', strtotime($date)); // 0=Sun, 6=Sat
    $stmt = $db->prepare('SELECT start_time, end_time, is_available FROM availability WHERE day_of_week = ?');
    $stmt->execute([$day_of_week]);
    $avail = $stmt->fetch();
    if (!$avail || !$avail['is_available']) {
        return [];
    }

    // Generate all possible slots
    $slots = [];
    $start = strtotime($date . ' ' . $avail['start_time']);
    $end   = strtotime($date . ' ' . $avail['end_time']);

    for ($t = $start; $t + ($duration * 60) <= $end; $t += $duration * 60) {
        $slots[] = $t;
    }

    if (empty($slots)) {
        return [];
    }

    // Remove slots in the past
    $now = time();
    $slots = array_filter($slots, fn($t) => $t > $now);

    if (empty($slots)) {
        return [];
    }

    // Remove slots that conflict with existing bookings
    $day_start = strtotime($date . ' 00:00:00');
    $day_end   = strtotime($date . ' 23:59:59');
    $stmt = $db->prepare(
        "SELECT start_datetime, end_datetime FROM bookings
         WHERE service_id = ? AND status != 'cancelled'
         AND start_datetime BETWEEN ? AND ?"
    );
    $stmt->execute([$service_id, date('Y-m-d H:i:s', $day_start), date('Y-m-d H:i:s', $day_end)]);
    $existing = $stmt->fetchAll();

    $booked_intervals = [];
    foreach ($existing as $b) {
        $booked_intervals[] = [
            strtotime($b['start_datetime']),
            strtotime($b['end_datetime']),
        ];
    }

    // Remove slots that overlap with Google Calendar busy times
    $busy_intervals = [];
    $gc = new GoogleCalendar();
    if ($gc->is_connected()) {
        try {
            $busy_intervals = $gc->get_busy_times($date);
        } catch (Exception $e) {
            // If Google fails, continue without it
        }
    }

    $all_busy = array_merge($booked_intervals, $busy_intervals);

    $available = [];
    foreach ($slots as $slot_start) {
        $slot_end = $slot_start + ($duration * 60);
        $conflict = false;
        foreach ($all_busy as [$busy_start, $busy_end]) {
            // Overlap: slot starts before busy ends AND slot ends after busy starts
            if ($slot_start < $busy_end && $slot_end > $busy_start) {
                $conflict = true;
                break;
            }
        }
        if (!$conflict) {
            $available[] = [
                'time'      => date('H:i', $slot_start),
                'label'     => date('g:i A', $slot_start),
                'timestamp' => $slot_start,
            ];
        }
    }

    return $available;
}

/**
 * Generate an ICS calendar file content for a booking.
 */
function generate_ics(array $booking, array $service): string {
    $dtstart = gmdate('Ymd\THis\Z', strtotime($booking['start_datetime']));
    $dtend   = gmdate('Ymd\THis\Z', strtotime($booking['end_datetime']));
    $dtstamp = gmdate('Ymd\THis\Z');
    $uid     = uniqid('booking-', true) . '@bookingapp';
    $summary = h($service['name']) . ' - ' . h($booking['client_name']);
    $desc    = 'Booking confirmed.';

    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//BookingApp//EN\r\n"
         . "BEGIN:VEVENT\r\nUID:{$uid}\r\nDTSTAMP:{$dtstamp}\r\n"
         . "DTSTART:{$dtstart}\r\nDTEND:{$dtend}\r\n"
         . "SUMMARY:{$summary}\r\nDESCRIPTION:{$desc}\r\n"
         . "END:VEVENT\r\nEND:VCALENDAR\r\n";
}
