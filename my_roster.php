<?php
require 'vendor/autoload.php';
require 'db.php';
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
    $user_id = $decoded->sub;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('my_roster', $rights)) {
    echo "Access denied.";
    exit;
}

$stmt = $pdo->prepare("SELECT shift_date, start_time, end_time FROM shifts WHERE user_id = ? ORDER BY shift_date ASC, start_time ASC");
$stmt->execute([$user_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'My Roster • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Shift overview</span>
            <h1 class="hero-title">See exactly when you’re on duty</h1>
            <p class="hero-lead">
                Glance at your upcoming schedule and prep for call times without pinging your manager.
            </p>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Scheduled shifts</h2>
            <p class="module-card__subtitle">Your roster updates live as supervisors adjust coverage.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr>
                            <td colspan="3">No shifts scheduled. Check back once you’ve been assigned.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['shift_date']); ?></td>
                                <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>