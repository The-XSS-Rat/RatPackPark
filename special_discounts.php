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
    $account_id = $decoded->account_id ?? 1;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('tickets', $rights)) {
    echo "Access denied.";
    exit;
}

if (isset($_POST['create_discount'])) {
    $ticket_id = (int)$_POST['ticket_id'];
    $start = $_POST['start_datetime'] ?? '';
    $end = $_POST['end_datetime'] ?? '';
    $percent = (float)($_POST['discount_percent'] ?? 0);

    if ($ticket_id && $start && $end && $percent) {
        $ticketOwnerStmt = $pdo->prepare("SELECT account_id FROM tickets WHERE id = ?");
        $ticketOwnerStmt->execute([$ticket_id]);
        $ticketOwner = $ticketOwnerStmt->fetchColumn();
        if ($ticketOwner !== false && (int)$ticketOwner !== (int)$account_id) {
            rat_track_add_score_event('IDOR', 'Created a discount for another tenant’s ticket');
        }

        $stmt = $pdo->prepare("INSERT INTO ticket_discounts (ticket_id, start_datetime, end_datetime, discount_percent) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ticket_id, $start, $end, $percent]);
    } else {
        $error = "All fields are required.";
    }
}

if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $discountOwnerStmt = $pdo->prepare("SELECT t.account_id FROM ticket_discounts td JOIN tickets t ON td.ticket_id = t.id WHERE td.id = ?");
    $discountOwnerStmt->execute([$delete_id]);
    $discountOwner = $discountOwnerStmt->fetchColumn();
    if ($discountOwner !== false && (int)$discountOwner !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Deleted another tenant’s discount');
    }

    $stmt = $pdo->prepare("DELETE FROM ticket_discounts WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: special_discounts.php");
    exit;
}

$stmt = $pdo->prepare("SELECT td.id, t.name AS ticket_name, td.start_datetime, td.end_datetime, td.discount_percent FROM ticket_discounts td JOIN tickets t ON td.ticket_id = t.id WHERE t.account_id = ? ORDER BY td.start_datetime DESC");
$stmt->execute([$account_id]);
$discounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, name FROM tickets WHERE account_id = ?");
$stmt->execute([$account_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Special Discounts</title>
    <style>
        body { font-family: Arial; background: #f5f5fc; padding: 20px; }
        h2 { color: #6a1b9a; text-align: center; }
        table { width: 100%; background: white; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-top: 20px; }
        th, td { padding: 12px; border-bottom: 1px solid #ccc; text-align: left; }
        th { background: #6a1b9a; color: white; }
        .form-section { background: white; padding: 20px; margin-top: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        input, select { width: 100%; padding: 10px; margin: 5px 0 10px; border-radius: 5px; border: 1px solid #ccc; }
        button { padding: 10px 20px; background: #6a1b9a; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .error { color: red; }
    </style>
</head>
<body>
    <h2>🎉 Special Discounts</h2>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <div class="form-section">
        <h3>Create Discount Period</h3>
        <form method="POST">
            <input type="hidden" name="create_discount" value="1">
            <select name="ticket_id" required>
                <option value="">Select Ticket</option>
                <?php foreach ($tickets as $ticket): ?>
                    <option value="<?= $ticket['id'] ?>"><?= htmlspecialchars($ticket['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="datetime-local" name="start_datetime" required>
            <input type="datetime-local" name="end_datetime" required>
            <input type="number" step="0.01" name="discount_percent" placeholder="Discount %" required>
            <button type="submit">Create Discount</button>
        </form>
    </div>

    <table>
        <thead>
            <tr><th>Ticket</th><th>Start</th><th>End</th><th>Discount %</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($discounts as $disc): ?>
                <tr>
                    <td><?= htmlspecialchars($disc['ticket_name']) ?></td>
                    <td><?= $disc['start_datetime'] ?></td>
                    <td><?= $disc['end_datetime'] ?></td>
                    <td><?= $disc['discount_percent'] ?></td>
                    <td><a href="?delete=<?= $disc['id'] ?>" onclick="return confirm('Delete this discount?')">🗑️</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script src="rat_scoreboard.js"></script>
    <?php include 'partials/score_event.php'; ?>
</body>
</html>
