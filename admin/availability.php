<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request.');
        header('Location: ' . APP_URL . '/admin/availability.php');
        exit;
    }

    for ($day = 0; $day <= 6; $day++) {
        $is_available = isset($_POST['available'][$day]) ? 1 : 0;
        $start_time   = $_POST['start'][$day] ?? '09:00';
        $end_time     = $_POST['end'][$day]   ?? '17:00';

        // Validate times
        if (!preg_match('/^\d{2}:\d{2}$/', $start_time)) $start_time = '09:00';
        if (!preg_match('/^\d{2}:\d{2}$/', $end_time))   $end_time   = '17:00';

        $stmt = $db->prepare('UPDATE availability SET start_time=?, end_time=?, is_available=? WHERE day_of_week=?');
        $stmt->execute([$start_time . ':00', $end_time . ':00', $is_available, $day]);
    }

    set_flash('success', 'Availability saved.');
    header('Location: ' . APP_URL . '/admin/availability.php');
    exit;
}

$rows = $db->query('SELECT * FROM availability ORDER BY day_of_week ASC')->fetchAll();
$avail = [];
foreach ($rows as $r) {
    $avail[$r['day_of_week']] = $r;
}

$day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$csrf      = generate_csrf();
$flash     = get_flash();

$page_title = 'Availability';
$active_nav = 'availability';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-clock me-2"></i>Weekly Availability</h4>
</div>

<div class="card">
    <div class="card-body">
        <p class="text-muted">Set the days and hours when clients can book appointments.</p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Day</th>
                            <th>Available</th>
                            <th>Start Time</th>
                            <th>End Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($day = 0; $day <= 6; $day++): ?>
                        <?php $a = $avail[$day] ?? ['is_available' => 0, 'start_time' => '09:00:00', 'end_time' => '17:00:00']; ?>
                        <tr>
                            <td class="fw-semibold"><?= $day_names[$day] ?></td>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input availability-toggle" type="checkbox"
                                           name="available[<?= $day ?>]" value="1"
                                           id="avail_<?= $day ?>"
                                           <?= $a['is_available'] ? 'checked' : '' ?>
                                           data-day="<?= $day ?>">
                                </div>
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" style="width:130px"
                                       name="start[<?= $day ?>]"
                                       value="<?= substr(h($a['start_time']), 0, 5) ?>"
                                       <?= !$a['is_available'] ? 'disabled' : '' ?>
                                       id="start_<?= $day ?>">
                            </td>
                            <td>
                                <input type="time" class="form-control form-control-sm" style="width:130px"
                                       name="end[<?= $day ?>]"
                                       value="<?= substr(h($a['end_time']), 0, 5) ?>"
                                       <?= !$a['is_available'] ? 'disabled' : '' ?>
                                       id="end_<?= $day ?>">
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Availability</button>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.availability-toggle').forEach(function(toggle) {
    toggle.addEventListener('change', function() {
        var day = this.dataset.day;
        var disabled = !this.checked;
        document.getElementById('start_' + day).disabled = disabled;
        document.getElementById('end_' + day).disabled = disabled;
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
