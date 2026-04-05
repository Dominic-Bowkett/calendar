<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$db = get_db();
$services = $db->query('SELECT * FROM services WHERE is_active = 1 ORDER BY created_at ASC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(APP_NAME) ?> — Book an Appointment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="bg-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
    <div class="container">
        <span class="navbar-brand fw-bold">
            <i class="bi bi-calendar3 me-2"></i><?= h(APP_NAME) ?>
        </span>
    </div>
</nav>

<div class="container py-5">
    <div class="text-center mb-5">
        <h1 class="fw-bold">Book an Appointment</h1>
        <p class="text-muted lead">Choose a service below to get started.</p>
    </div>

    <?php if (empty($services)): ?>
        <div class="alert alert-info text-center">No services are currently available. Please check back later.</div>
    <?php else: ?>
    <div class="row g-4 justify-content-center">
        <?php foreach ($services as $s): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100 shadow-sm service-card">
                <div class="card-header text-white fw-semibold" style="background-color: <?= h($s['color']) ?>">
                    <i class="bi bi-briefcase me-2"></i><?= h($s['name']) ?>
                </div>
                <div class="card-body d-flex flex-column">
                    <?php if ($s['description']): ?>
                        <p class="card-text text-muted"><?= nl2br(h($s['description'])) ?></p>
                    <?php endif; ?>
                    <div class="mt-auto">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="text-muted small"><i class="bi bi-clock me-1"></i><?= (int)$s['duration_minutes'] ?> minutes</span>
                            <span class="fw-bold fs-5">
                                <?= $s['price'] > 0 ? '£' . number_format((float)$s['price'], 2) : '<span class="text-success">Free</span>' ?>
                            </span>
                        </div>
                        <a href="<?= APP_URL ?>/book.php?service=<?= (int)$s['id'] ?>" class="btn btn-primary w-100">
                            <i class="bi bi-calendar-plus me-2"></i>Book Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<footer class="text-center text-muted py-4 mt-5 border-top">
    <small>Powered by <?= h(APP_NAME) ?></small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
