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

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
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

    $stmt = $pdo->prepare("UPDATE shifts SET user_id = ?, shift_date = ?, start_time = ?, end_time = ? WHERE id = ?");
    $stmt->execute([$user_id, $shift_date, $start_time, $end_time, $shift_id]);
}

if (isset($_POST['create_shift'])) {
    $user_id = (int)$_POST['user_id'];
    $shift_date = $_POST['shift_date'];
    $start_time = $_POST['start_time'];
    $end_time = $_POST['end_time'];

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

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Rosters | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .shift-table-container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input, select { padding: 8px; border: 1px solid #ccc; border-radius: 5px; }
        button { background: #6a1b9a; color: white; padding: 8px 12px; border: none; border-radius: 5px; cursor: pointer; }
        form.inline-form { display: flex; gap: 6px; align-items: center; margin-bottom: 8px; }
    </style>
</head>
<body>
    <div class="shift-table-container">
        <h2>👥 Staff Rosters</h2>

        <h3>Create New Shift</h3>
        <form method="POST">
            <input type="hidden" name="create_shift" value="1">
            <select name="user_id" required>
                <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="shift_date" required>
            <input type="time" name="start_time" required>
            <input type="time" name="end_time" required>
            <button type="submit">Create</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Shift Date</th>
                    <th>Start Time</th>
                    <th>End Time</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shifts as $shift): ?>
                    <tr>
                        <td><?= htmlspecialchars($shift['username']) ?></td>
                        <td><?= htmlspecialchars($shift['shift_date']) ?></td>
                        <td><?= htmlspecialchars($shift['start_time']) ?></td>
                        <td><?= htmlspecialchars($shift['end_time']) ?></td>
                        <td>
                            <form method="POST" class="inline-form">
                                <input type="hidden" name="edit_shift_id" value="<?= $shift['id'] ?>">
                                <select name="edit_user_id">
                                    <?php foreach ($users as $u): ?>
                                        <option value="<?= $u['id'] ?>" <?= $u['id'] == $shift['user_id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['username']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="date" name="edit_shift_date" value="<?= $shift['shift_date'] ?>">
                                <input type="time" name="edit_start_time" value="<?= $shift['start_time'] ?>">
                                <input type="time" name="edit_end_time" value="<?= $shift['end_time'] ?>">
                                <button type="submit">Update</button>
                                <a href="?delete=<?= $shift['id'] ?>" onclick="return confirm('Delete this shift?')">🗑️</a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>