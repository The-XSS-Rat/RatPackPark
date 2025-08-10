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
</body>
</html>
