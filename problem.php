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
    $rights = $decoded->rights ?? [];
    $account_id = $decoded->account_id;
    $submitted_by = $decoded->sub;
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('report_problem', $rights)) {
    echo "Access denied.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $attachment_url = $_POST['attachment_url'] ?? null;

    if ($category && $description) {
        $stmt = $pdo->prepare("INSERT INTO problem_reports (account_id, submitted_by, category, description, attachment_url, status, submitted_at, updated_at) VALUES (?, ?, ?, ?, ?, 'open', NOW(), NOW())");
        $stmt->execute([$account_id, $submitted_by, $category, $description, $attachment_url]);
        $success = "Problem reported successfully.";
    } else {
        $error = "All required fields must be filled.";
    }
}

$stmt = $pdo->prepare("SELECT id, category, description, status, submitted_at FROM problem_reports WHERE submitted_by = ? ORDER BY submitted_at DESC");
$stmt->execute([$submitted_by]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$viewed_report = null;
$view_notes = [];
$view_missing = false;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    if ($view_id > 0) {
        $viewStmt = $pdo->prepare("SELECT pr.*, u.username FROM problem_reports pr LEFT JOIN users u ON pr.submitted_by = u.id WHERE pr.id = ?");
        $viewStmt->execute([$view_id]);
        $viewed_report = $viewStmt->fetch(PDO::FETCH_ASSOC);
        if ($viewed_report) {
            if ((int)$viewed_report['account_id'] !== (int)$account_id) {
                rat_track_add_score_event('IDOR', 'Peeked at another tenant’s incident report');
            }
            $notesStmt = $pdo->prepare("SELECT pn.note, pn.created_at, au.username FROM problem_notes pn LEFT JOIN users au ON pn.author_id = au.id WHERE pn.problem_id = ? ORDER BY pn.created_at ASC");
            $notesStmt->execute([$view_id]);
            $view_notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $view_missing = true;
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report Problem | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 10px; max-width: 700px; margin: auto; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        h2, h3 { color: #6a1b9a; text-align: center; }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border-radius: 6px; border: 1px solid #ccc; }
        button { padding: 10px 20px; background: #6a1b9a; color: white; border: none; border-radius: 6px; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
        table { width: 100%; margin-top: 20px; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .view-card { margin-top: 20px; background: #ede7f6; padding: 18px; border-radius: 12px; }
        .view-card h3 { margin-top: 0; color: #4a148c; }
        .view-card ul { list-style: none; padding: 0; margin: 0 0 10px; }
        .view-card li { margin: 6px 0; }
        .note-list { list-style: disc; padding-left: 20px; color: #333; }
        .note-empty { color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🚧 Report a Problem</h2>
        <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>
        <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

        <form method="POST">
            <label for="category">Category</label>
            <select name="category" id="category" required>
                <option value="">-- Select Category --</option>
                <option value="Ride Malfunction">Ride Malfunction</option>
                <option value="Trash Overflow">Trash Overflow</option>
                <option value="Guest Complaint">Guest Complaint</option>
                <option value="Safety Concern">Safety Concern</option>
                <option value="Other">Other</option>
            </select>

            <label for="description">Description</label>
            <textarea name="description" id="description" rows="4" required></textarea>

            <label for="attachment_url">Attachment URL (optional)</label>
            <input type="url" name="attachment_url" id="attachment_url">

            <button type="submit">Submit Report</button>
        </form>

        <?php if ($viewed_report): ?>
            <div class="view-card">
                <h3>🔎 Incident Peek: <?= htmlspecialchars($viewed_report['category']) ?> (ID #<?= htmlspecialchars($viewed_report['id']) ?>)</h3>
                <ul>
                    <li><strong>Tenant:</strong> <?= htmlspecialchars((string)$viewed_report['account_id']) ?></li>
                    <li><strong>Submitted By:</strong> <?= htmlspecialchars($viewed_report['username'] ?? 'Unknown'); ?></li>
                    <li><strong>Status:</strong> <?= htmlspecialchars($viewed_report['status']); ?></li>
                    <?php if (!empty($viewed_report['attachment_url'])): ?>
                        <li><strong>Attachment:</strong> <a href="<?= htmlspecialchars($viewed_report['attachment_url']); ?>" target="_blank" rel="noopener noreferrer">Open</a></li>
                    <?php endif; ?>
                </ul>
                <p><?= nl2br(htmlspecialchars($viewed_report['description'])); ?></p>
                <?php if (!empty($view_notes)): ?>
                    <h4>Maintenance Notes</h4>
                    <ul class="note-list">
                        <?php foreach ($view_notes as $note): ?>
                            <li><strong><?= htmlspecialchars($note['username'] ?? 'Unknown') ?>:</strong> <?= htmlspecialchars($note['note']); ?> <em>(<?= htmlspecialchars($note['created_at']); ?>)</em></li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="note-empty">No remediation notes recorded for this issue.</p>
                <?php endif; ?>
            </div>
        <?php elseif ($view_missing): ?>
            <p class="error">That incident could not be found.</p>
        <?php endif; ?>

        <h3>📃 My Submitted Reports</h3>
        <table>
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="4">You have not submitted any reports yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['category']) ?></td>
                            <td><?= htmlspecialchars($r['description']) ?></td>
                            <td><?= htmlspecialchars($r['status']) ?></td>
                            <td><?= htmlspecialchars($r['submitted_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <script src="rat_scoreboard.js"></script>
    <?php include 'partials/score_event.php'; ?>
</body>
</html>
