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

if (!in_array('admin_problem', $rights)) {
    echo "Access denied.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['new_status'])) {
    $report_id = (int)$_POST['report_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE problem_reports SET status = ?, updated_at = NOW() WHERE id = ? AND account_id = ?");
    $stmt->execute([$new_status, $report_id, $account_id]);
    $success = "Status updated successfully.";
}

$stmt = $pdo->prepare("SELECT pr.id, u.username, pr.category, pr.description, pr.status, pr.submitted_at FROM problem_reports pr JOIN users u ON pr.submitted_by = u.id WHERE pr.account_id = ? ORDER BY pr.submitted_at DESC");
$stmt->execute([$account_id]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$notes_problem_meta = null;
$notes_for_problem = [];
$notes_missing = false;
if (isset($_GET['notes'])) {
    $notes_id = (int)$_GET['notes'];
    if ($notes_id > 0) {
        $metaStmt = $pdo->prepare("SELECT pr.*, u.username FROM problem_reports pr LEFT JOIN users u ON pr.submitted_by = u.id WHERE pr.id = ?");
        $metaStmt->execute([$notes_id]);
        $notes_problem_meta = $metaStmt->fetch(PDO::FETCH_ASSOC);
        if ($notes_problem_meta) {
            if ((int)$notes_problem_meta['account_id'] !== (int)$account_id) {
                rat_track_add_score_event('IDOR', 'Viewed maintenance notes for another tenant');
            }
            $notesStmt = $pdo->prepare("SELECT pn.note, pn.created_at, au.username FROM problem_notes pn LEFT JOIN users au ON pn.author_id = au.id WHERE pn.problem_id = ? ORDER BY pn.created_at ASC");
            $notesStmt->execute([$notes_id]);
            $notes_for_problem = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $notes_missing = true;
        }
    }
}
?>
$pageTitle = 'Incident Command • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Operations response</span>
            <h1 class="hero-title">Triage issues and steer the cleanup</h1>
            <p class="hero-lead">
                Review every report filed by crews, pivot into maintenance notes, and update statuses as remediation moves forward.
            </p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($notes_problem_meta): ?>
            <div class="module-card">
                <h2 class="module-card__title">Maintenance notebook</h2>
                <p class="module-card__subtitle">Incident #<?php echo htmlspecialchars($notes_problem_meta['id']); ?> · Tenant #<?php echo htmlspecialchars((string) $notes_problem_meta['account_id']); ?></p>
                <div class="module-meta">
                    <span>Category: <strong><?php echo htmlspecialchars($notes_problem_meta['category']); ?></strong></span>
                    <span>Status: <strong><?php echo htmlspecialchars($notes_problem_meta['status']); ?></strong></span>
                </div>
                <ul class="module-list">
                    <?php if (!empty($notes_for_problem)): ?>
                        <?php foreach ($notes_for_problem as $note): ?>
                            <li class="module-list__item">
                                <strong><?php echo htmlspecialchars($note['username'] ?? 'Unknown'); ?>:</strong>
                                <?php echo htmlspecialchars($note['note']); ?>
                                <em> · <?php echo htmlspecialchars($note['created_at']); ?></em>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="module-list__item">No notes recorded for this incident yet.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php elseif ($notes_missing): ?>
            <div class="module-alert module-alert--error">No problem record exists for that ID.</div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Problem queue</h2>
            <p class="module-card__subtitle">Advance cases, peek at foreign tenants, and keep the park spotless.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Submitted by</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6">No incidents logged. Encourage teams to report issues from the field.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['username']); ?></td>
                                <td><?php echo htmlspecialchars($report['category']); ?></td>
                                <td><?php echo htmlspecialchars($report['description']); ?></td>
                                <td><?php echo htmlspecialchars($report['status']); ?></td>
                                <td><?php echo htmlspecialchars($report['submitted_at']); ?></td>
                                <td>
                                    <form method="POST" class="module-form module-form--inline">
                                        <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                        <select class="input-field" name="new_status">
                                            <option value="open" <?php echo $report['status'] == 'open' ? 'selected' : ''; ?>>Open</option>
                                            <option value="in_progress" <?php echo $report['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="resolved" <?php echo $report['status'] == 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                            <option value="wont_resolve" <?php echo $report['status'] == 'wont_resolve' ? 'selected' : ''; ?>>Won't Resolve</option>
                                        </select>
                                        <button class="btn btn-outline" type="submit">Update</button>
                                        <a class="module-link" href="?notes=<?php echo $report['id']; ?>">Notes</a>
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
