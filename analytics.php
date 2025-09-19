<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'vendor/autoload.php';
require 'db.php';
require_once 'rat_helpers.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;



session_start();
$jwt_secret = 'your-secret-key';

if (!isset($_SESSION['jwt'])) {
    http_response_code(401);
    echo "Not authenticated";
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $role_id = $decoded->role_id;
    $account_id = $decoded->account_id;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('analytics', $rights)) {
    echo "Access denied.";
    exit;
}

$effectiveAccountId = $account_id;
if (isset($_GET['account'])) {
    $requestedAccount = (int)$_GET['account'];
    $effectiveAccountId = $requestedAccount;
    if ($requestedAccount !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Pulled analytics for another tenant via account override');
    }
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_id = ?");
$stmt->execute([$effectiveAccountId]);
$total_users = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE account_id = ?");
$stmt->execute([$effectiveAccountId]);
$total_tickets = $stmt->fetchColumn();

$pageTitle = 'Analytics • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Insights &amp; metrics</span>
            <h1 class="hero-title">Spot trends across your park portfolio</h1>
            <p class="hero-lead">
                Audit guest demand and staffing footprint in a single glance. Pivot the scope to peek at other tenants and uncover
                where the biggest crowds are gathering.
            </p>
            <div class="module-meta">
                <span>Default tenant ID <strong>#<?php echo htmlspecialchars((string) $account_id); ?></strong></span>
                <span>Viewing scope <strong>#<?php echo htmlspecialchars((string) $effectiveAccountId); ?></strong></span>
            </div>
        </div>

        <?php if ($effectiveAccountId !== (int) $account_id): ?>
            <div class="module-alert module-alert--note">
                Analytics override active. Provide <code>?account=<?php echo htmlspecialchars((string) $account_id); ?></code> to
                snap back to your own tenant.
            </div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Key counts</h2>
            <p class="module-card__subtitle">Live totals computed directly from operational data across the selected tenant scope.
            </p>
            <div class="module-grid">
                <div class="module-figure">
                    <span class="module-figure__label">Total users</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars(number_format((int) $total_users)); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Total tickets</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars(number_format((int) $total_tickets)); ?></span>
                </div>
            </div>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
