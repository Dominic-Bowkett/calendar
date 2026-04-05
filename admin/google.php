<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google.php';

require_login();

$gc = new GoogleCalendar();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid request.');
        header('Location: ' . APP_URL . '/admin/google.php');
        exit;
    }

    if ($_POST['action'] === 'disconnect') {
        $gc->disconnect();
        set_flash('success', 'Google Calendar disconnected.');
        header('Location: ' . APP_URL . '/admin/google.php');
        exit;
    }
}

$is_connected  = $gc->is_connected();
$auth_url      = null;
$config_missing = !defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '';

if (!$config_missing && !$is_connected) {
    try {
        $auth_url = $gc->get_auth_url();
    } catch (Exception $e) {
        $auth_url = null;
    }
}

$csrf      = generate_csrf();
$flash     = get_flash();
$page_title = 'Google Calendar';
$active_nav = 'google';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<?php if ($flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= h($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-google me-2"></i>Google Calendar Integration</h4>
</div>

<div class="row justify-content-center">
    <div class="col-lg-6">

        <?php if ($config_missing): ?>
        <div class="card border-warning">
            <div class="card-body">
                <h5 class="card-title text-warning"><i class="bi bi-exclamation-triangle me-2"></i>Configuration Required</h5>
                <p>To enable Google Calendar sync, you need to configure your Google OAuth2 credentials in <code>config.php</code>.</p>
                <ol>
                    <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
                    <li>Create a new project (or select an existing one)</li>
                    <li>Enable the <strong>Google Calendar API</strong></li>
                    <li>Go to <strong>Credentials</strong> → <strong>Create Credentials</strong> → <strong>OAuth 2.0 Client IDs</strong></li>
                    <li>Set the application type to <strong>Web application</strong></li>
                    <li>Add your redirect URI: <code><?= h(GOOGLE_REDIRECT_URI) ?></code></li>
                    <li>Copy the <strong>Client ID</strong> and <strong>Client Secret</strong> into <code>config.php</code></li>
                </ol>
            </div>
        </div>

        <?php elseif ($is_connected): ?>
        <div class="card border-success">
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                </div>
                <h5 class="text-success">Google Calendar Connected</h5>
                <p class="text-muted mb-4">
                    Your Google Calendar is synced. Available slots will exclude your existing calendar events,
                    and new bookings will automatically appear in your Google Calendar.
                </p>
                <form method="post" onsubmit="return confirm('Disconnect Google Calendar? Existing bookings will not be removed from your calendar.')">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="action" value="disconnect">
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i>Disconnect Google Calendar
                    </button>
                </form>
            </div>
        </div>

        <?php else: ?>
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <i class="bi bi-google text-primary" style="font-size: 3rem;"></i>
                </div>
                <h5>Connect Google Calendar</h5>
                <p class="text-muted mb-4">
                    Connect your Google Calendar to automatically block out busy times and add new bookings to your calendar.
                </p>
                <?php if ($auth_url): ?>
                <a href="<?= h($auth_url) ?>" class="btn btn-primary btn-lg">
                    <i class="bi bi-google me-2"></i>Connect with Google
                </a>
                <?php else: ?>
                <div class="alert alert-warning">Unable to generate authorisation URL. Please check your Google credentials in <code>config.php</code>.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
