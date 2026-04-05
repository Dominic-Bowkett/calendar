<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google.php';

require_login();

$db = get_db();

// Stats
$total     = $db->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled'")->fetchColumn();
$upcoming  = $db->query("SELECT COUNT(*) FROM bookings WHERE status != 'cancelled' AND start_datetime > NOW()")->fetchColumn();
$pending   = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
$cancelled = $db->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

// Next 10 upcoming bookings
$stmt = $db->query(
    "SELECT b.*, s.name AS service_name, s.color
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     WHERE b.status != 'cancelled' AND b.start_datetime > NOW()
     ORDER BY b.start_datetime ASC LIMIT 10"
);
$upcoming_bookings = $stmt->fetchAll();

$gc_connected = (new GoogleCalendar())->is_connected();

$page_title = 'Dashboard';
$active_nav = 'dashboard';
$flash = get_flash();
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show mx-3" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card text-bg-primary h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-calendar-check fs-1 opacity-75"></i>
                <div>
                    <div class="fs-2 fw-bold"><?= $total ?></div>
                    <div class="small">Total Bookings</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-bg-success h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-calendar-event fs-1 opacity-75"></i>
                <div>
                    <div class="fs-2 fw-bold"><?= $upcoming ?></div>
                    <div class="small">Upcoming</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-bg-warning h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-hourglass-split fs-1 opacity-75"></i>
                <div>
                    <div class="fs-2 fw-bold"><?= $pending ?></div>
                    <div class="small">Pending</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card text-bg-secondary h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <i class="bi bi-x-circle fs-1 opacity-75"></i>
                <div>
                    <div class="fs-2 fw-bold"><?= $cancelled ?></div>
                    <div class="small">Cancelled</div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!$gc_connected): ?>
<div class="alert alert-info d-flex align-items-center gap-2">
    <i class="bi bi-google fs-4"></i>
    <div>
        Google Calendar is not connected.
        <a href="<?= APP_URL ?>/admin/google.php" class="alert-link">Connect now</a> to sync availability and bookings.
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-calendar-event me-2"></i>Upcoming Bookings</h5>
        <a href="<?= APP_URL ?>/admin/bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body p-0">
        <?php if (empty($upcoming_bookings)): ?>
            <p class="text-muted text-center py-4">No upcoming bookings.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($upcoming_bookings as $b): ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= h($b['client_name']) ?></div>
                            <small class="text-muted"><?= h($b['client_email']) ?></small>
                        </td>
                        <td>
                            <span class="badge" style="background-color:<?= h($b['color']) ?>"><?= h($b['service_name']) ?></span>
                        </td>
                        <td><?= date('D, d M Y \a\t g:i A', strtotime($b['start_datetime'])) ?></td>
                        <td>
                            <?php
                            $badge = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger'];
                            ?>
                            <span class="badge text-bg-<?= $badge[$b['status']] ?>"><?= ucfirst(h($b['status'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
