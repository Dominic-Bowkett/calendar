<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$service_id = (int)($_GET['service'] ?? 0);
if ($service_id <= 0) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$db = get_db();
$stmt = $db->prepare('SELECT * FROM services WHERE id = ? AND is_active = 1');
$stmt->execute([$service_id]);
$service = $stmt->fetch();

if (!$service) {
    header('Location: ' . APP_URL . '/');
    exit;
}

$csrf     = generate_csrf();
$min_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+60 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Book <?= h($service['name']) ?> — <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
        <div class="col-lg-8">

            <!-- Service info -->
            <div class="card mb-4 shadow-sm">
                <div class="card-header text-white fw-semibold" style="background-color: <?= h($service['color']) ?>">
                    <i class="bi bi-briefcase me-2"></i><?= h($service['name']) ?>
                </div>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <?php if ($service['description']): ?>
                            <p class="mb-1 text-muted"><?= nl2br(h($service['description'])) ?></p>
                        <?php endif; ?>
                        <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= (int)$service['duration_minutes'] ?> minutes</span>
                    </div>
                    <div class="fw-bold fs-4">
                        <?= $service['price'] > 0 ? '£' . number_format((float)$service['price'], 2) : '<span class="text-success">Free</span>' ?>
                    </div>
                </div>
            </div>

            <!-- Step 1: Date & Time -->
            <div class="card mb-4 shadow-sm" id="step-datetime">
                <div class="card-header fw-semibold">
                    <i class="bi bi-calendar2 me-2"></i>Step 1: Choose a Date &amp; Time
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="booking_date" class="form-label">Select Date</label>
                        <input type="text" class="form-control" id="booking_date" placeholder="Choose a date…" autocomplete="off" readonly>
                    </div>

                    <div id="slots-container" class="d-none">
                        <label class="form-label">Available Times</label>
                        <div id="slots-grid" class="d-flex flex-wrap gap-2"></div>
                        <input type="hidden" id="selected_time" value="">
                    </div>

                    <div id="slots-loading" class="d-none text-center py-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading…</span>
                        </div>
                        <p class="text-muted mt-2 small">Checking availability…</p>
                    </div>

                    <div id="slots-empty" class="d-none">
                        <p class="text-muted">No available slots on this date. Please choose another day.</p>
                    </div>
                </div>
            </div>

            <!-- Step 2: Your Details -->
            <div class="card shadow-sm" id="step-details" style="display: none !important;">
                <div class="card-header fw-semibold">
                    <i class="bi bi-person me-2"></i>Step 2: Your Details
                </div>
                <div class="card-body">
                    <form id="booking-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                        <input type="hidden" name="service_id" value="<?= (int)$service['id'] ?>">
                        <input type="hidden" name="date" id="form_date" value="">
                        <input type="hidden" name="time" id="form_time" value="">

                        <div class="mb-3">
                            <label class="form-label">Selected Time</label>
                            <div id="selected-time-display" class="form-control bg-light fw-semibold"></div>
                        </div>

                        <div class="mb-3">
                            <label for="client_name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="client_name" name="client_name" required maxlength="100">
                        </div>
                        <div class="mb-3">
                            <label for="client_email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="client_email" name="client_email" required maxlength="150">
                        </div>
                        <div class="mb-3">
                            <label for="client_notes" class="form-label">Notes <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control" id="client_notes" name="client_notes" rows="3" maxlength="1000" placeholder="Anything you'd like us to know…"></textarea>
                        </div>

                        <div id="booking-error" class="alert alert-danger d-none"></div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="btn-back">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </button>
                            <button type="submit" class="btn btn-primary flex-grow-1" id="btn-submit">
                                <i class="bi bi-check-lg me-1"></i>Confirm Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<footer class="text-center text-muted py-4 mt-5 border-top">
    <small>Powered by <?= h(APP_NAME) ?></small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
var SERVICE_ID = <?= (int)$service['id'] ?>;
var MIN_DATE   = '<?= $min_date ?>';
var MAX_DATE   = '<?= $max_date ?>';
var API_URL    = '<?= APP_URL ?>/api/slots.php';
var BOOK_URL   = '<?= APP_URL ?>/api/book.php';
var CONFIRM_URL = '<?= APP_URL ?>/confirm.php';
</script>
<script src="<?= APP_URL ?>/assets/js/booking.js"></script>
</body>
</html>
