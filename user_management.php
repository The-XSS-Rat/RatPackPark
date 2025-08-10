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

// Handle user delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND account_id = ?");
    $stmt->execute([$delete_id, $account_id]);
    $success = "User deleted.";
    header("Location: user_management.php");
    exit;
}

// Handle user edit submission
if (isset($_POST['edit_user_id'])) {
    $edit_id = (int)$_POST['edit_user_id'];
    $edit_role = (int)$_POST['edit_role_id'];
    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ? AND account_id = ?");
    $stmt->execute([$edit_role, $edit_id, $account_id]);
    $success = "User updated.";
}

// Handle create form
if (isset($_POST['create_user'])) {
    $new_username = $_POST['username'] ?? '';
    $new_email = $_POST['email'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $new_role = $_POST['role_id'] ?? 3;

    if ($new_username && $new_email && $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (account_id, role_id, username, email, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$account_id, $new_role, $new_username, $new_email, $password_hash]);
        $success = "User created successfully!";
    } else {
        $error = "All fields are required.";
    }
}

// Fetch users
$stmt = $pdo->prepare("SELECT id, username, email, role_id, created_at FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role map
$stmt = $pdo->query("SELECT id, name FROM roles");
$roles = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $roles[$row['id']] = $row['name'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5fc; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .form-section { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input, select { width: 100%; padding: 10px; margin: 5px 0 10px; border-radius: 5px; border: 1px solid #ccc; }
        button { padding: 10px 20px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
        .inline-form { display: flex; gap: 8px; align-items: center; }
        .inline-form select { flex: 1; }
    </style>
</head>
<body>
    <h2>👥 User Management</h2>

    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>
    <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>

    <div class="form-section">
        <h3>Create New User</h3>
        <form method="POST">
            <input type="hidden" name="create_user" value="1">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <select name="role_id" required>
                <?php foreach ($roles as $id => $label): ?>
                    <?php if ($id !== 1): ?>
                        <option value="<?= $id ?>"><?= $label ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit">Create User</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>Username</th><th>Email</th><th>Role</th><th>Created At</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= $roles[$user['role_id']] ?? 'Unknown' ?></td>
                    <td><?= htmlspecialchars($user['created_at']) ?></td>
                    <td>
                        <form class="inline-form" method="POST" style="display:inline-block;">
                            <input type="hidden" name="edit_user_id" value="<?= $user['id'] ?>">
                            <select name="edit_role_id">
                                <?php foreach ($roles as $id => $label): ?>
                                    <option value="<?= $id ?>" <?= $id == $user['role_id'] ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit">Update</button>
                        </form>
                        <a href="?delete=<?= $user['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
