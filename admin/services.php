<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();
$db = get_db();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request.');
        header('Location: ' . APP_URL . '/admin/services.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $duration    = max(5, (int)($_POST['duration_minutes'] ?? 60));
        $price       = max(0, (float)($_POST['price'] ?? 0));
        $color       = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#3788d8';
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if ($name === '') {
            set_flash('danger', 'Service name is required.');
        } elseif ($id > 0) {
            $stmt = $db->prepare('UPDATE services SET name=?, description=?, duration_minutes=?, price=?, color=?, is_active=? WHERE id=?');
            $stmt->execute([$name, $description, $duration, $price, $color, $is_active, $id]);
            set_flash('success', 'Service updated.');
        } else {
            $stmt = $db->prepare('INSERT INTO services (name, description, duration_minutes, price, color, is_active) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$name, $description, $duration, $price, $color, $is_active]);
            set_flash('success', 'Service created.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare('DELETE FROM services WHERE id=?')->execute([$id]);
            set_flash('success', 'Service deleted.');
        }
    }

    header('Location: ' . APP_URL . '/admin/services.php');
    exit;
}

$services = $db->query('SELECT * FROM services ORDER BY created_at DESC')->fetchAll();
$csrf     = generate_csrf();
$flash    = get_flash();

$page_title = 'Services';
$active_nav = 'services';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-briefcase me-2"></i>Services</h4>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#serviceModal" onclick="openServiceModal()">
        <i class="bi bi-plus-lg"></i> New Service
    </button>
</div>

<div class="card">
    <div class="card-body p-0">
        <?php if (empty($services)): ?>
            <p class="text-muted text-center py-4">No services yet. Create your first service.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th>Duration</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $s): ?>
                    <tr>
                        <td>
                            <span class="badge me-2" style="background-color:<?= h($s['color']) ?>">&nbsp;</span>
                            <strong><?= h($s['name']) ?></strong>
                            <?php if ($s['description']): ?>
                                <br><small class="text-muted"><?= h(mb_strimwidth($s['description'], 0, 80, '…')) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= (int)$s['duration_minutes'] ?> min</td>
                        <td><?= $s['price'] > 0 ? '£' . number_format($s['price'], 2) : '<span class="text-muted">Free</span>' ?></td>
                        <td>
                            <?php if ($s['is_active']): ?>
                                <span class="badge text-bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge text-bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary"
                                data-bs-toggle="modal" data-bs-target="#serviceModal"
                                onclick="openServiceModal(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this service?')">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Service Modal -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" id="serviceForm">
                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="id" id="service_id" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceModalTitle">New Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Service Name *</label>
                        <input type="text" class="form-control" name="name" id="s_name" required maxlength="100">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="s_desc" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Duration (minutes) *</label>
                            <input type="number" class="form-control" name="duration_minutes" id="s_duration" value="60" min="5" step="5" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Price (£)</label>
                            <input type="number" class="form-control" name="price" id="s_price" value="0" min="0" step="0.01">
                        </div>
                    </div>
                    <div class="row g-3 mt-1">
                        <div class="col-6">
                            <label class="form-label">Colour</label>
                            <input type="color" class="form-control form-control-color w-100" name="color" id="s_color" value="#3788d8">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="is_active" id="s_active" value="1" checked>
                                <label class="form-check-label" for="s_active">Active (bookable)</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Service</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openServiceModal(s) {
    if (s) {
        document.getElementById('serviceModalTitle').textContent = 'Edit Service';
        document.getElementById('service_id').value = s.id;
        document.getElementById('s_name').value = s.name;
        document.getElementById('s_desc').value = s.description || '';
        document.getElementById('s_duration').value = s.duration_minutes;
        document.getElementById('s_price').value = s.price;
        document.getElementById('s_color').value = s.color;
        document.getElementById('s_active').checked = s.is_active == 1;
    } else {
        document.getElementById('serviceModalTitle').textContent = 'New Service';
        document.getElementById('serviceForm').reset();
        document.getElementById('service_id').value = '0';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
