<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'rat_helpers.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

session_start();
$jwt_secret = 'your-secret-key';

if (!isset($_SESSION['jwt'])) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $account_id = $decoded->account_id ?? 0;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo 'Invalid session.';
    exit;
}

if (!in_array('rosters', $rights)) {
    echo 'Access denied.';
    exit;
}

$statusMessage = '';
$errorMessage = '';
$inspectedShift = null;

if (isset($_POST['reschedule_shift'])) {
    $shiftId = (int) ($_POST['shift_id'] ?? 0);
    $shiftDate = $_POST['shift_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime = $_POST['end_time'] ?? '';

    if ($shiftId > 0 && $shiftDate !== '' && $startTime !== '' && $endTime !== '') {
        $ownerStmt = $pdo->prepare('SELECT u.account_id FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?');
        $ownerStmt->execute([$shiftId]);
        $ownerAccount = $ownerStmt->fetchColumn();
        if ($ownerAccount !== false && (int) $ownerAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Hijacked another tenant\'s schedule via ride scheduler reschedule');
        }

        $stmt = $pdo->prepare('UPDATE shifts SET shift_date = ?, start_time = ?, end_time = ? WHERE id = ?');
        $stmt->execute([$shiftDate, $startTime, $endTime, $shiftId]);
        rat_track_add_score_event('BAC', 'Rescheduled a shift with unvalidated timing overrides');
        $statusMessage = 'Shift updated successfully.';
    } else {
        $errorMessage = 'All timing fields are required.';
    }
}

if (isset($_GET['shift'])) {
    $shiftId = (int) $_GET['shift'];
    if ($shiftId > 0) {
        $stmt = $pdo->prepare('SELECT s.*, u.username, u.account_id, a.name AS account_name FROM shifts s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN accounts a ON u.account_id = a.id WHERE s.id = ?');
        $stmt->execute([$shiftId]);
        $inspectedShift = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($inspectedShift && (int) $inspectedShift['account_id'] !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Inspected another tenant\'s shift assignment via ride scheduler');
        }
    }
}

$listingStmt = $pdo->prepare(
    'SELECT s.id, s.shift_date, s.start_time, s.end_time, u.username, a.name AS account_name '
    . 'FROM shifts s '
    . 'LEFT JOIN users u ON s.user_id = u.id '
    . 'LEFT JOIN accounts a ON u.account_id = a.id '
    . 'ORDER BY s.shift_date ASC, s.start_time ASC LIMIT 25'
);
$listingStmt->execute();
$shifts = $listingStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Ride Scheduler • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Roster overdrive</span>
            <h1 class="hero-title">Rewrite shift timing for any attraction crew</h1>
            <p class="hero-lead">
                Review coverage across the entire platform and nudge start times without tenant boundaries getting in the way.
            </p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Upcoming shifts</h2>
            <p class="module-card__subtitle">The scheduler exposes every tenant’s coverage in a single stream.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Operator</th>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr>
                            <td colspan="7">No shifts scheduled.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $shift['id']); ?></td>
                                <td><?php echo htmlspecialchars($shift['account_name'] ?? 'Unknown tenant'); ?></td>
                                <td><?php echo htmlspecialchars($shift['username'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($shift['shift_date']); ?></td>
                                <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                                <td><a class="module-link" href="?shift=<?php echo $shift['id']; ?>">Inspect</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($inspectedShift): ?>
            <div class="module-card">
                <h2 class="module-card__title">Shift #<?php echo htmlspecialchars((string) $inspectedShift['id']); ?> control panel</h2>
                <p class="module-card__subtitle">Adjust dates and times directly—no conflict detection or tenant scoping enforced.</p>
                <ul class="module-list">
                    <li class="module-list__item"><strong>Tenant:</strong> <?php echo htmlspecialchars($inspectedShift['account_name'] ?? 'Unknown'); ?></li>
                    <li class="module-list__item"><strong>Operator:</strong> <?php echo htmlspecialchars($inspectedShift['username'] ?? ''); ?></li>
                    <li class="module-list__item"><strong>Current window:</strong> <?php echo htmlspecialchars($inspectedShift['shift_date']); ?> <?php echo htmlspecialchars($inspectedShift['start_time']); ?> - <?php echo htmlspecialchars($inspectedShift['end_time']); ?></li>
                </ul>
                <form method="POST" class="module-form" style="margin-top: 18px;">
                    <input type="hidden" name="reschedule_shift" value="1">
                    <input type="hidden" name="shift_id" value="<?php echo htmlspecialchars((string) $inspectedShift['id']); ?>">
                    <label class="input-label">New date</label>
                    <input class="input-field" type="date" name="shift_date" value="<?php echo htmlspecialchars($inspectedShift['shift_date']); ?>" required>
                    <label class="input-label">New start time</label>
                    <input class="input-field" type="time" name="start_time" value="<?php echo htmlspecialchars($inspectedShift['start_time']); ?>" required>
                    <label class="input-label">New end time</label>
                    <input class="input-field" type="time" name="end_time" value="<?php echo htmlspecialchars($inspectedShift['end_time']); ?>" required>
                    <button class="btn btn-outline" type="submit">Reschedule shift</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
