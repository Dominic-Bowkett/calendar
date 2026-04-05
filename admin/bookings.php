<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google.php';

require_login();
$db = get_db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request.');
        header('Location: ' . APP_URL . '/admin/bookings.php');
        exit;
    }

    $action     = $_POST['action'] ?? '';
    $booking_id = (int)($_POST['booking_id'] ?? 0);

    if ($booking_id > 0) {
        $stmt = $db->prepare('SELECT * FROM bookings WHERE id = ?');
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch();

        if ($booking) {
            if ($action === 'confirm' && $booking['status'] === 'pending') {
                $gc = new GoogleCalendar();
                $google_event_id = null;
                if ($gc->is_connected()) {
                    $stmt2 = $db->prepare('SELECT * FROM services WHERE id = ?');
                    $stmt2->execute([$booking['service_id']]);
                    $service = $stmt2->fetch();
                    try {
                        $google_event_id = $gc->create_event($booking, $service);
                    } catch (Exception $e) {
                        // Non-fatal
                    }
                }
                $stmt = $db->prepare('UPDATE bookings SET status=?, google_event_id=? WHERE id=?');
                $stmt->execute(['confirmed', $google_event_id, $booking_id]);
                set_flash('success', 'Booking confirmed.');

            } elseif ($action === 'cancel' && $booking['status'] !== 'cancelled') {
                if ($booking['google_event_id']) {
                    try {
                        (new GoogleCalendar())->delete_event($booking['google_event_id']);
                    } catch (Exception $e) {
                        // Non-fatal
                    }
                }
                $db->prepare('UPDATE bookings SET status=? WHERE id=?')->execute(['cancelled', $booking_id]);
                set_flash('success', 'Booking cancelled.');
            }
        }
    }

    header('Location: ' . APP_URL . '/admin/bookings.php');
    exit;
}

// Filters
$filter_status = $_GET['status'] ?? '';
$filter_search = trim($_GET['search'] ?? '');

$where  = [];
$params = [];

if (in_array($filter_status, ['pending', 'confirmed', 'cancelled'])) {
    $where[]  = 'b.status = ?';
    $params[] = $filter_status;
}
if ($filter_search !== '') {
    $where[]  = '(b.client_name LIKE ? OR b.client_email LIKE ?)';
    $params[] = '%' . $filter_search . '%';
    $params[] = '%' . $filter_search . '%';
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = $db->prepare(
    "SELECT b.*, s.name AS service_name, s.color
     FROM bookings b
     JOIN services s ON b.service_id = s.id
     $where_sql
     ORDER BY b.start_datetime DESC"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$csrf      = generate_csrf();
$flash     = get_flash();
$page_title = 'Bookings';
$active_nav = 'bookings';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Bookings</h4>
</div>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="">All statuses</option>
                    <option value="pending"   <?= $filter_status === 'pending'   ? 'selected' : '' ?>>Pending</option>
                    <option value="confirmed" <?= $filter_status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                    <option value="cancelled" <?= $filter_status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                </select>
            </div>
            <div class="col">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search name or email…" value="<?= h($filter_search) ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Search</button>
                <a href="<?= APP_URL ?>/admin/bookings.php" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($bookings)): ?>
            <p class="text-muted text-center py-4">No bookings found.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Client</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Notes</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$b['id'] ?></td>
                        <td>
                            <div class="fw-semibold"><?= h($b['client_name']) ?></div>
                            <small class="text-muted"><?= h($b['client_email']) ?></small>
                        </td>
                        <td>
                            <span class="badge" style="background-color:<?= h($b['color']) ?>"><?= h($b['service_name']) ?></span>
                        </td>
                        <td class="small"><?= date('D d M Y', strtotime($b['start_datetime'])) ?><br><?= date('g:i A', strtotime($b['start_datetime'])) ?> – <?= date('g:i A', strtotime($b['end_datetime'])) ?></td>
                        <td class="small text-muted"><?= $b['client_notes'] ? h(mb_strimwidth($b['client_notes'], 0, 60, '…')) : '—' ?></td>
                        <td>
                            <?php $badge = ['pending' => 'warning', 'confirmed' => 'success', 'cancelled' => 'danger']; ?>
                            <span class="badge text-bg-<?= $badge[$b['status']] ?>"><?= ucfirst(h($b['status'])) ?></span>
                            <?php if ($b['google_event_id']): ?>
                                <i class="bi bi-google text-primary ms-1" title="Synced to Google Calendar"></i>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($b['status'] === 'pending'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success" title="Confirm"><i class="bi bi-check-lg"></i></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($b['status'] !== 'cancelled'): ?>
                            <form method="post" class="d-inline" onsubmit="return confirm('Cancel this booking?')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="cancel">
                                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Cancel"><i class="bi bi-x-lg"></i></button>
                            </form>
                            <?php endif; ?>
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
