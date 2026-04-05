<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

$booking_id = (int)($_GET['id'] ?? 0);
if ($booking_id <= 0) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$db = get_db();
$stmt = $db->prepare(
    'SELECT b.*, s.name AS service_name, s.color, s.duration_minutes, s.price
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     WHERE b.id = ?'
);
$stmt->execute([$booking_id]);
$booking = $stmt->fetch();

if (!$booking) {
    header('Location: ' . APP_URL . '/');
    exit;
}

// ICS download
if (isset($_GET['ics'])) {
    $service = ['name' => $booking['service_name']];
    $ics = generate_ics($booking, $service);
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="booking-' . $booking_id . '.ics"');
    echo $ics;
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Confirmed — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= APP_URL ?>/">
            <i class="bi bi-calendar3 me-2"></i><?= h(APP_NAME) ?>
        </a>
    </div>
</nav>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card shadow-sm text-center">
                <div class="card-body py-5">
                    <?php if ($booking['status'] === 'cancelled'): ?>
                        <i class="bi bi-x-circle-fill text-danger mb-3" style="font-size:3.5rem"></i>
                        <h2 class="text-danger">Booking Cancelled</h2>
                        <p class="text-muted">This booking has been cancelled.</p>
                    <?php else: ?>
                        <i class="bi bi-check-circle-fill text-success mb-3" style="font-size:3.5rem"></i>
                        <h2 class="text-success">Booking <?= $booking['status'] === 'confirmed' ? 'Confirmed' : 'Received' ?>!</h2>
                        <p class="text-muted">
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                Your appointment is confirmed. See you soon!
                            <?php else: ?>
                                We've received your booking request and will confirm it shortly.
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="card-body border-top text-start">
                    <dl class="row mb-0">
                        <dt class="col-5 text-muted">Service</dt>
                        <dd class="col-7">
                            <span class="badge" style="background-color:<?= h($booking['color']) ?>"><?= h($booking['service_name']) ?></span>
                        </dd>

                        <dt class="col-5 text-muted">Date</dt>
                        <dd class="col-7"><?= date('l, d F Y', strtotime($booking['start_datetime'])) ?></dd>

                        <dt class="col-5 text-muted">Time</dt>
                        <dd class="col-7"><?= date('g:i A', strtotime($booking['start_datetime'])) ?> – <?= date('g:i A', strtotime($booking['end_datetime'])) ?></dd>

                        <dt class="col-5 text-muted">Duration</dt>
                        <dd class="col-7"><?= (int)$booking['duration_minutes'] ?> minutes</dd>

                        <dt class="col-5 text-muted">Name</dt>
                        <dd class="col-7"><?= h($booking['client_name']) ?></dd>

                        <dt class="col-5 text-muted">Email</dt>
                        <dd class="col-7"><?= h($booking['client_email']) ?></dd>

                        <?php if ($booking['price'] > 0): ?>
                        <dt class="col-5 text-muted">Price</dt>
                        <dd class="col-7 fw-bold">£<?= number_format((float)$booking['price'], 2) ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
                <?php if ($booking['status'] !== 'cancelled'): ?>
                <div class="card-footer d-flex flex-column flex-sm-row gap-2 justify-content-center">
                    <a href="<?= APP_URL ?>/confirm.php?id=<?= (int)$booking_id ?>&ics=1" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-plus me-1"></i>Add to Calendar (.ics)
                    </a>
                    <a href="<?= APP_URL ?>/" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-1"></i>Back to Home
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
