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
    $account_id = $decoded->account_id ?? 1;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('user_management', $rights)) {
    echo "Access denied.";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role_id = (int)($_POST['role_id'] ?? 0);
    if ($user_id && $role_id) {
        $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ? AND account_id = ?");
        $stmt->execute([$role_id, $user_id, $account_id]);
        $success = 'Role assigned.';
    } else {
        $error = 'User and role are required.';
    }
}

$stmt = $pdo->prepare("SELECT id, username, role_id FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$role_map = [];
foreach ($roles as $r) {
    $role_map[$r['id']] = $r['name'];
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Assign Roles | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f5f5fc; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .form-section { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        select, button { padding: 10px; margin: 5px 0; border-radius: 5px; border: 1px solid #ccc; }
        button { background: #6a1b9a; color: white; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>🎭 Assign Roles</h2>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>

    <div class="form-section">
        <form method="POST">
            <select name="user_id" required>
                <option value="" disabled selected>Select user</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="role_id" required>
                <option value="" disabled selected>Select role</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Assign</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>User</th><th>Current Role</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($role_map[$u['role_id']] ?? 'Unknown') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
