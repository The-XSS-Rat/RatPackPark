<?php
require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('tickets', $rights)) {
    echo "Access denied.";
    exit;
}

if (isset($_POST['buy_ticket'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $quantity = (int)$_POST['quantity'];

    // Reduce available_quantity
    $stmt = $pdo->prepare("UPDATE tickets SET available_quantity = available_quantity - ? WHERE id = ? AND available_quantity >= ?");
    $stmt->execute([$quantity, $ticket_id, $quantity]);

    if ($stmt->rowCount()) {
        // Record the sale
        $stmt = $pdo->prepare("INSERT INTO sales (user_id, ticket_id, quantity, sale_date) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $ticket_id, $quantity]);
        $success = "Ticket(s) sold successfully.";
    } else {
        $error = "Not enough tickets available.";
    }
}

$stmt = $pdo->prepare("SELECT * FROM tickets WHERE available_quantity > 0 AND account_id = ? ORDER BY created_at DESC");
$stmt->execute([$account_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Available Tickets | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        form { display: flex; gap: 6px; align-items: center; }
        input[type=number] { width: 60px; padding: 6px; }
        button { padding: 6px 10px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .message { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>🎟️ Available Tickets</h2>
    <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <table>
        <thead>
            <tr><th>Name</th><th>Price</th><th>Available</th><th>Buy</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['name']) ?></td>
                    <td>&euro;<?= number_format($ticket['price'], 2) ?></td>
                    <td><?= $ticket['available_quantity'] ?></td>
                    <td>
                        <form method="POST">
                            <input type="hidden" name="buy_ticket" value="1">
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                            <input type="number" name="quantity" min="1" max="<?= $ticket['available_quantity'] ?>" required>
                            <button type="submit">Buy</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>