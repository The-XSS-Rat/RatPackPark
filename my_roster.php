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
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array($role_id, [1, 2, 4])) {
    echo "Access denied. This page is for admins, managers, and operators only.";
    exit;
}

$stmt = $pdo->prepare("SELECT shift_date, start_time, end_time FROM shifts WHERE user_id = ? ORDER BY shift_date ASC, start_time ASC");
$stmt->execute([$user_id]);
$shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>My Roster | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
    </style>
</head>
<body>
    <div class="container">
        <h2>📅 My Roster</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shifts)): ?>
                    <tr><td colspan="3">No shifts scheduled.</td></tr>
                <?php else: ?>
                    <?php foreach ($shifts as $shift): ?>
                        <tr>
                            <td><?= htmlspecialchars($shift['shift_date']) ?></td>
                            <td><?= htmlspecialchars($shift['start_time']) ?></td>
                            <td><?= htmlspecialchars($shift['end_time']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>