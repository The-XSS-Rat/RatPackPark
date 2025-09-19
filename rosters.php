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

if (!in_array('rosters', $rights)) {
    echo "Access denied.";
    exit;
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $ownerStmt = $pdo->prepare("SELECT u.account_id FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $ownerStmt->execute([$delete_id]);
    $ownerAccount = $ownerStmt->fetchColumn();
    if ($ownerAccount !== false && (int)$ownerAccount !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Deleted another tenant’s shift');
    }

    $stmt = $pdo->prepare("DELETE FROM shifts WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: rosters.php");
    exit;
}

if (isset($_POST['edit_shift_id'])) {
    $shift_id = (int)$_POST['edit_shift_id'];
    $user_id = (int)$_POST['edit_user_id'];
    $shift_date = $_POST['edit_shift_date'];
    $start_time = $_POST['edit_start_time'];
    $end_time = $_POST['edit_end_time'];

    $shiftOwnerStmt = $pdo->prepare("SELECT u.account_id FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $shiftOwnerStmt->execute([$shift_id]);
    $shiftOwner = $shiftOwnerStmt->fetchColumn();
    if ($shiftOwner !== false && (int)$shiftOwner !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Edited another tenant’s roster entry');
    }

    $targetUserStmt = $pdo->prepare("SELECT account_id FROM users WHERE id = ?");
    $targetUserStmt->execute([$user_id]);
    $targetAccount = $targetUserStmt->fetchColumn();
    if ($targetAccount !== false && (int)$targetAccount !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Assigned a foreign user to a shift');
    }

    $stmt = $pdo->prepare("UPDATE shifts SET user_id = ?, shift_date = ?, start_time = ?, end_time = ? WHERE id = ?");
    $stmt->execute([$user_id, $shift_date, $start_time, $end_time, $shift_id]);
}

if (isset($_POST['create_shift'])) {
    $user_id = (int)$_POST['user_id'];
    $shift_date = $_POST['shift_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

    $assignedAccountStmt = $pdo->prepare("SELECT account_id FROM users WHERE id = ?");
    $assignedAccountStmt->execute([$user_id]);
    $assignedAccount = $assignedAccountStmt->fetchColumn();
    if ($assignedAccount !== false && (int)$assignedAccount !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Created a shift for another tenant user');
    }

    $stmt = $pdo->prepare("INSERT INTO shifts (user_id, shift_date, start_time, end_time) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $shift_date, $start_time, $end_time]);
}

$stmt = $pdo->prepare("SELECT s.id, s.user_id, u.username, s.shift_date, s.start_time, s.end_time FROM shifts s JOIN users u ON s.user_id = u.id WHERE u.account_id = ? ORDER BY s.shift_date, s.start_time");
$stmt->execute([$account_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, username FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$pageTitle = 'Staff Rosters • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Crew scheduling</span>
            <h1 class="hero-title">Keep every shift covered with confidence</h1>
            <p class="hero-lead">
                Assign operators, adjust coverage, and respond to last-minute changes without leaving the dashboard. Rosters update
                instantly for your entire tenant.
            </p>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Create new shift</h2>
            <p class="module-card__subtitle">Set the crew, date, and time window to keep attractions staffed.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="create_shift" value="1">
                <select class="input-field" name="user_id" required>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input-field" type="date" name="shift_date" required>
                <input class="input-field" type="time" name="start_time" required>
                <input class="input-field" type="time" name="end_time" required>
                <button class="btn btn-primary" type="submit">Create shift</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Upcoming coverage</h2>
            <p class="module-card__subtitle">Edit on the fly or remove assignments as your staffing picture changes.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Team member</th>
                        <th>Date</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shifts)): ?>
                        <tr>
                            <td colspan="5">No shifts scheduled yet. Create one above to get today’s roster rolling.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($shifts as $shift): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($shift['username']); ?></td>
                                <td><?php echo htmlspecialchars($shift['shift_date']); ?></td>
                                <td><?php echo htmlspecialchars($shift['start_time']); ?></td>
                                <td><?php echo htmlspecialchars($shift['end_time']); ?></td>
                                <td>
                                    <form method="POST" class="module-form module-form--inline">
                                        <input type="hidden" name="edit_shift_id" value="<?php echo $shift['id']; ?>">
                                        <select class="input-field" name="edit_user_id">
                                            <?php foreach ($users as $u): ?>
                                                <option value="<?php echo $u['id']; ?>" <?php echo $u['id'] == $shift['user_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['username']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input class="input-field" type="date" name="edit_shift_date" value="<?php echo $shift['shift_date']; ?>">
                                        <input class="input-field" type="time" name="edit_start_time" value="<?php echo $shift['start_time']; ?>">
                                        <input class="input-field" type="time" name="edit_end_time" value="<?php echo $shift['end_time']; ?>">
                                        <button class="btn btn-outline" type="submit">Update</button>
                                        <a class="module-link" href="?delete=<?php echo $shift['id']; ?>" onclick="return confirm('Delete this shift?');">Delete</a>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>