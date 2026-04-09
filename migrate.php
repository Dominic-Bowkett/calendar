<?php
/**
 * Database Migration Runner
 * -------------------------
 * Visit this page in your browser after deploying to apply pending SQL migrations.
 * Protected by a secret key defined in config.php (MIGRATE_SECRET).
 *
 * Usage: https://yourdomain.com/migrate.php?secret=YOUR_SECRET
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

// ── Security: require secret key ─────────────────────────────────────────────
if (!defined('MIGRATE_SECRET') || MIGRATE_SECRET === '') {
    die('MIGRATE_SECRET is not set in config.php. Add: define(\'MIGRATE_SECRET\', \'some-long-random-string\');');
}
if (($_GET['secret'] ?? '') !== MIGRATE_SECRET) {
    http_response_code(403);
    die('Access denied. Provide ?secret=YOUR_SECRET in the URL.');
}

// ── Ensure migrations tracking table exists ───────────────────────────────────
$db = get_db();
$db->exec("
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL UNIQUE,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// ── Load applied migrations ───────────────────────────────────────────────────
$applied = $db->query("SELECT filename FROM schema_migrations ORDER BY filename ASC")
               ->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied); // for fast isset() checks

// ── Find all migration files ──────────────────────────────────────────────────
$files = glob(__DIR__ . '/migrations/*.sql');
natsort($files);

// ── Handle "run pending" POST request ────────────────────────────────────────
$run_results = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run'])) {
    foreach ($files as $filepath) {
        $filename = basename($filepath);
        if (isset($applied[$filename])) continue; // already applied

        $sql = file_get_contents($filepath);
        try {
            // Split on semicolons to run multiple statements
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($s) => $s !== ''
            );
            foreach ($statements as $statement) {
                $db->exec($statement);
            }
            $db->prepare("INSERT INTO schema_migrations (filename) VALUES (?)")->execute([$filename]);
            $run_results[$filename] = ['status' => 'ok', 'message' => 'Applied successfully.'];
            $applied[$filename] = true;
        } catch (PDOException $e) {
            $run_results[$filename] = ['status' => 'error', 'message' => $e->getMessage()];
            break; // stop on first error
        }
    }
}

// ── Build status for display ──────────────────────────────────────────────────
$migrations = [];
foreach ($files as $filepath) {
    $filename = basename($filepath);
    $migrations[] = [
        'filename' => $filename,
        'applied'  => isset($applied[$filename]),
        'result'   => $run_results[$filename] ?? null,
    ];
}
$pending_count = count(array_filter($migrations, fn($m) => !$m['applied']));

$secret = htmlspecialchars($_GET['secret'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Migrations — <?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:700px">

    <div class="d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-database-gear fs-2 text-primary"></i>
        <div>
            <h4 class="mb-0">Database Migrations</h4>
            <small class="text-muted"><?= htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') ?></small>
        </div>
    </div>

    <?php if ($pending_count === 0 && empty($run_results)): ?>
        <div class="alert alert-success d-flex gap-2">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><strong>All up to date.</strong> No pending migrations.</div>
        </div>
    <?php elseif ($pending_count > 0 && empty($run_results)): ?>
        <div class="alert alert-warning d-flex gap-2">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i>
            <div><strong><?= $pending_count ?> pending migration<?= $pending_count > 1 ? 's' : '' ?>.</strong> Run them below.</div>
        </div>
    <?php endif; ?>

    <?php foreach ($run_results as $filename => $result): ?>
        <?php if ($result['status'] === 'ok'): ?>
            <div class="alert alert-success py-2"><i class="bi bi-check-lg me-2"></i><strong><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?></strong> — Applied successfully.</div>
        <?php else: ?>
            <div class="alert alert-danger py-2"><i class="bi bi-x-lg me-2"></i><strong><?= htmlspecialchars($filename, ENT_QUOTES, 'UTF-8') ?></strong> — Error: <?= htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Migration list -->
    <div class="card mb-4">
        <div class="card-header fw-semibold">Migration Files</div>
        <ul class="list-group list-group-flush">
            <?php foreach ($migrations as $m): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <span>
                    <i class="bi bi-file-earmark-code me-2 text-muted"></i>
                    <?= htmlspecialchars($m['filename'], ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($m['applied']): ?>
                    <span class="badge text-bg-success"><i class="bi bi-check me-1"></i>Applied</span>
                <?php else: ?>
                    <span class="badge text-bg-warning">Pending</span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if (empty($migrations)): ?>
            <li class="list-group-item text-muted text-center py-3">No migration files found in /migrations/</li>
            <?php endif; ?>
        </ul>
    </div>

    <?php if ($pending_count > 0): ?>
    <form method="post">
        <input type="hidden" name="run" value="1">
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Run <?= $pending_count ?> pending migration<?= $pending_count > 1 ? 's' : '' ?>?')">
            <i class="bi bi-play-fill me-1"></i>Run <?= $pending_count ?> Pending Migration<?= $pending_count > 1 ? 's' : '' ?>
        </button>
    </form>
    <?php endif; ?>

    <div class="mt-4 text-muted small">
        <i class="bi bi-info-circle me-1"></i>
        To add a schema change, create a new file in <code>/migrations/</code> named
        <code>002_description.sql</code>, <code>003_description.sql</code>, etc.
        Then deploy and revisit this page.
    </div>

</div>
</body>
</html>
