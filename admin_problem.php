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
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if ($role_id != 1) {
    echo "Access denied. Admins only.";
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
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.05); margin-top: 20px; }
        th, td { padding: 10px; border-bottom: 1px solid #ccc; text-align: left; vertical-align: top; }
        th { background: #6a1b9a; color: white; }
        form.inline-form { display: flex; gap: 6px; align-items: center; }
        select, button { padding: 6px; border-radius: 4px; border: 1px solid #999; }
    </style>
</head>
<body>
    <div class="container">
        <h2>📊 Admin Problem Management</h2>
        <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>
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
</body>
</html>