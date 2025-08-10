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
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('roles_management', $rights)) {
    echo "Access denied.";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $rights_input = $_POST['rights'] ?? '';
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
        $stmt->execute([$name]);
        $role_id = $pdo->lastInsertId();
        $rights_list = array_filter(array_map('trim', explode(',', $rights_input)));
        foreach ($rights_list as $r) {
            $stmt = $pdo->prepare("INSERT INTO role_rights (role_id, right_name) VALUES (?, ?)");
            $stmt->execute([$role_id, $r]);
        }
        $success = 'Role created.';
    } else {
        $error = 'Name is required.';
    }
}

$stmt = $pdo->query("SELECT r.id, r.name, GROUP_CONCAT(rr.right_name ORDER BY rr.right_name SEPARATOR ', ') AS rights FROM roles r LEFT JOIN role_rights rr ON r.id = rr.role_id GROUP BY r.id, r.name ORDER BY r.id");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Role Management | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .form-section { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input, textarea { width: 100%; padding: 10px; margin: 5px 0 10px; border-radius: 5px; border: 1px solid #ccc; }
        button { padding: 10px 20px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>🧩 Role Management</h2>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>

    <div class="form-section">
        <h3>Create New Role</h3>
        <form method="POST">
            <input type="text" name="name" placeholder="Role name" required>
            <textarea name="rights" placeholder="Comma-separated rights"></textarea>
            <button type="submit">Create Role</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>ID</th><th>Name</th><th>Rights</th></tr>
        </thead>
        <tbody>
            <?php foreach ($roles as $role): ?>
                <tr>
                    <td><?= htmlspecialchars($role['id']) ?></td>
                    <td><?= htmlspecialchars($role['name']) ?></td>
                    <td><?= htmlspecialchars($role['rights'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
