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

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admin Problem Panel | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 900px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        h2 { color: #6a1b9a; text-align: center; }
        .message { color: green; text-align: center; }
        .error { color: #d32f2f; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.05); margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: left; vertical-align: top; }
        th { background: #6a1b9a; color: white; }
        form.inline-form { display: flex; gap: 6px; align-items: center; }
        select, button { padding: 6px; border-radius: 4px; border: 1px solid #999; }
        .notes-card { margin-top: 20px; background: #e8eaf6; padding: 18px; border-radius: 12px; }
        .notes-card h3 { margin-top: 0; color: #303f9f; }
        .notes-card ul { list-style: disc; padding-left: 20px; color: #1a237e; }
        .notes-card li { margin: 6px 0; }
        .notes-empty { color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 Admin Problem Management</h2>
        <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>
        <?php if ($notes_problem_meta): ?>
            <div class="notes-card">
                <h3>🗒️ Notes for Incident #<?= htmlspecialchars($notes_problem_meta['id']) ?> (Tenant <?= htmlspecialchars((string)$notes_problem_meta['account_id']); ?>)</h3>
                <p><strong>Category:</strong> <?= htmlspecialchars($notes_problem_meta['category']); ?> | <strong>Status:</strong> <?= htmlspecialchars($notes_problem_meta['status']); ?></p>
                <ul>
                    <?php if (!empty($notes_for_problem)): ?>
                        <?php foreach ($notes_for_problem as $note): ?>
                            <li><strong><?= htmlspecialchars($note['username'] ?? 'Unknown') ?>:</strong> <?= htmlspecialchars($note['note']); ?> <em>(<?= htmlspecialchars($note['created_at']); ?>)</em></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li class="notes-empty">No notes recorded for this problem.</li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php elseif ($notes_missing): ?>
            <p class="error">No problem record exists for that ID.</p>
        <?php endif; ?>
        <table>
            <thead>
                <tr>
                    <th>Submitted By</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['username']) ?></td>
                        <td><?= htmlspecialchars($report['category']) ?></td>
                        <td><?= htmlspecialchars($report['description']) ?></td>
                        <td><?= htmlspecialchars($report['status']) ?></td>
                        <td><?= htmlspecialchars($report['submitted_at']) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="report_id" value="<?= $report['id'] ?>">
                                <select name="new_status">
                                    <option value="open" <?= $report['status'] == 'open' ? 'selected' : '' ?>>Open</option>
                                    <option value="in_progress" <?= $report['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="resolved" <?= $report['status'] == 'resolved' ? 'selected' : '' ?>>Resolved</option>
                                    <option value="wont_resolve" <?= $report['status'] == 'wont_resolve' ? 'selected' : '' ?>>Won't Resolve</option>
                                </select>
                                <button type="submit">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="rat_scoreboard.js"></script>
    <?php include 'partials/score_event.php'; ?>
</body>
</html>
