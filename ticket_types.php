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
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if ($role_id != 1) {
    echo "Access denied. Admins only.";
    exit;
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM tickets WHERE id = ? AND account_id = ?");
    $stmt->execute([$delete_id, $account_id]);
    header("Location: ticket_types.php");
    exit;
}

if (isset($_POST['edit_ticket_type_id'])) {
    $edit_id = (int)$_POST['edit_ticket_type_id'];
    $name = $_POST['edit_name'];
    $price = (float)$_POST['edit_price'];
    $qty = (int)$_POST['edit_quantity'];

    $stmt = $pdo->prepare("UPDATE tickets SET name = ?, price = ?, available_quantity = ? WHERE id = ? AND account_id = ?");
    $stmt->execute([$name, $price, $qty, $edit_id, $account_id]);
}

if (isset($_POST['create_ticket_type'])) {
    $name = $_POST['name'] ?? '';
    $price = (float)($_POST['price'] ?? 0);
    $qty = (int)($_POST['available_quantity'] ?? 0);

    if ($name && $price && $qty) {
        $stmt = $pdo->prepare("INSERT INTO tickets (account_id, name, price, available_quantity) VALUES (?, ?, ?, ?)");
        $stmt->execute([$account_id, $name, $price, $qty]);
    } else {
        $error = "All fields are required.";
    }
}

$stmt = $pdo->prepare("SELECT id, name, price, available_quantity, created_at FROM tickets WHERE account_id = ?");
$stmt->execute([$account_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Ticket Type Management</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5fc; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .form-section { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input { width: 100%; padding: 10px; margin: 5px 0 10px; border-radius: 5px; border: 1px solid #ccc; }
        button { padding: 10px 20px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
        .inline-form { display: flex; gap: 8px; align-items: center; }
        .inline-form input { flex: 1; }
    </style>
</head>
<body>
    <h2>🎟 Ticket Type Management</h2>

    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <div class="form-section">
        <h3>Create New Ticket Type</h3>
        <form method="POST">
            <input type="hidden" name="create_ticket_type" value="1">
            <input type="text" name="name" placeholder="Ticket Name" required>
            <input type="number" step="0.01" name="price" placeholder="Price" required>
            <input type="number" name="available_quantity" placeholder="Available Quantity" required>
            <button type="submit">Create Ticket</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>Name</th><th>Price</th><th>Available Quantity</th><th>Created At</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['name']) ?></td>
                    <td><?= number_format($ticket['price'], 2) ?></td>
                    <td><?= $ticket['available_quantity'] ?></td>
                    <td><?= $ticket['created_at'] ?></td>
                    <td>
                        <form class="inline-form" method="POST">
                            <input type="hidden" name="edit_ticket_type_id" value="<?= $ticket['id'] ?>">
                            <input type="text" name="edit_name" value="<?= htmlspecialchars($ticket['name']) ?>">
                            <input type="number" step="0.01" name="edit_price" value="<?= $ticket['price'] ?>">
                            <input type="number" name="edit_quantity" value="<?= $ticket['available_quantity'] ?>">
                            <button type="submit">Update</button>
                        </form>
                        <a href="?delete=<?= $ticket['id'] ?>" onclick="return confirm('Delete this ticket type?')">🗑️</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>