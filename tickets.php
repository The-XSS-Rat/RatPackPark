<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'rat_helpers.php';
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

    if ($quantity < 0) {
        rat_track_add_score_event('BAC', 'Used negative ticket quantity to restock inventory');
    }

    $ticketOwnerStmt = $pdo->prepare("SELECT account_id FROM tickets WHERE id = ?");
    $ticketOwnerStmt->execute([$ticket_id]);
    $ticketAccount = $ticketOwnerStmt->fetchColumn();
    if ($ticketAccount !== false && (int)$ticketAccount !== (int)$account_id) {
        rat_track_add_score_event('IDOR', 'Manipulated another tenant’s ticket inventory');
    }

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

$stmt = $pdo->prepare("SELECT t.*, MAX(td.discount_percent) as discount_percent FROM tickets t LEFT JOIN ticket_discounts td ON t.id = td.ticket_id AND NOW() BETWEEN td.start_datetime AND td.end_datetime WHERE t.available_quantity > 0 AND t.account_id = ? GROUP BY t.id ORDER BY t.created_at DESC");
$stmt->execute([$account_id]);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($tickets as &$ticket) {
    if (!empty($ticket['discount_percent'])) {
        $ticket['discounted_price'] = $ticket['price'] * (1 - $ticket['discount_percent'] / 100);
    }
}
unset($ticket);

$inspected_ticket = null;
$inspection_missing = false;
if (isset($_GET['inspect'])) {
    $inspect_id = (int)$_GET['inspect'];
    if ($inspect_id > 0) {
        $inspectStmt = $pdo->prepare("SELECT t.*, a.name AS account_name FROM tickets t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.id = ?");
        $inspectStmt->execute([$inspect_id]);
        $inspected_ticket = $inspectStmt->fetch(PDO::FETCH_ASSOC);
        if ($inspected_ticket && (int)$inspected_ticket['account_id'] !== (int)$account_id) {
            rat_track_add_score_event('IDOR', 'Inspected another tenant’s ticket catalog');
        }
        if (!$inspected_ticket) {
            $inspection_missing = true;
        }
    }
}

$sales_audit_rows = [];
if (isset($_GET['sales_audit']) && (int)$_GET['sales_audit'] === 1) {
    $auditStmt = $pdo->prepare("SELECT s.id, s.sale_date, s.quantity, u.username, t.name AS ticket_name, t.account_id FROM sales s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN tickets t ON s.ticket_id = t.id ORDER BY s.sale_date DESC");
    $auditStmt->execute();
    $sales_audit_rows = $auditStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($sales_audit_rows)) {
        rat_track_add_score_event('IDOR', 'Dumped every tenant’s ticket sales history');
    }
}
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
        .inspect-card {
            margin-top: 20px;
            padding: 18px 20px;
            background: #fff8e1;
            border-radius: 10px;
            box-shadow: 0 6px 20px rgba(255, 152, 0, 0.15);
        }
        .inspect-card h3 {
            margin: 0 0 10px;
            color: #ff6f00;
        }
        .inspect-card p {
            margin: 4px 0;
        }
        .hint {
            margin-top: 12px;
            font-size: 12px;
            color: #444;
        }
        .audit-heading {
            margin-top: 40px;
            color: #4a148c;
            text-align: left;
        }
        .audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background: white;
            box-shadow: 0 0 12px rgba(0,0,0,0.08);
        }
        .audit-table th, .audit-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 14px;
        }
        .audit-table th {
            background: #4a148c;
            color: #fff;
        }
        .audit-table tr:nth-child(even) {
            background: #f7f1ff;
        }
        .audit-table tr.cross-tenant {
            background: rgba(244, 67, 54, 0.12);
        }
    </style>
</head>
<body>
    <h2>🎟️ Available Tickets</h2>
    <?php if (!empty($success)): ?><p class="message"><?= $success ?></p><?php endif; ?>
    <?php if (!empty($error)): ?><p class="error"><?= $error ?></p><?php endif; ?>

    <?php if ($inspected_ticket): ?>
        <div class="inspect-card">
            <h3>🔍 Ticket Insight</h3>
            <p><strong><?= htmlspecialchars($inspected_ticket['name']) ?></strong> (Ticket ID #<?= htmlspecialchars($inspected_ticket['id']) ?>)</p>
            <p>Account: <?= htmlspecialchars($inspected_ticket['account_name'] ?? 'Unknown') ?> (ID #<?= htmlspecialchars($inspected_ticket['account_id'] ?? 'N/A') ?>)</p>
            <p>Face value: &euro;<?= number_format((float)$inspected_ticket['price'], 2) ?></p>
            <p>Inventory: <?= htmlspecialchars((string)$inspected_ticket['available_quantity']) ?></p>
        </div>
    <?php elseif ($inspection_missing): ?>
        <p class="error">No ticket was found for that ID.</p>
    <?php endif; ?>

    <table>
        <thead>
            <tr><th>Name</th><th>Price</th><th>Available</th><th>Buy</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $ticket): ?>
                <tr>
                    <td><?= htmlspecialchars($ticket['name']) ?></td>
                    <td>
                        <?php if (!empty($ticket['discounted_price'])): ?>
                            <s>&euro;<?= number_format($ticket['price'], 2) ?></s> &euro;<?= number_format($ticket['discounted_price'], 2) ?>
                        <?php else: ?>
                            &euro;<?= number_format($ticket['price'], 2) ?>
                        <?php endif; ?>
                    </td>
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
    <p class="hint">Tip: append <code>?inspect=&lt;ticket_id&gt;</code> or <code>?sales_audit=1</code> to this page to explore deeper data.</p>

    <?php if (!empty($sales_audit_rows)): ?>
        <h3 class="audit-heading">📈 Global Sales Audit</h3>
        <table class="audit-table">
            <thead>
                <tr><th>Sale #</th><th>Ticket</th><th>Quantity</th><th>Seller</th><th>Sold At</th><th>Account</th></tr>
            </thead>
            <tbody>
                <?php foreach ($sales_audit_rows as $sale): ?>
                    <?php $foreign = isset($sale['account_id']) && (int)$sale['account_id'] !== (int)$account_id; ?>
                    <tr class="<?= $foreign ? 'cross-tenant' : '' ?>">
                        <td>#<?= htmlspecialchars($sale['id']) ?></td>
                        <td><?= htmlspecialchars($sale['ticket_name'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars((string)$sale['quantity']) ?></td>
                        <td><?= htmlspecialchars($sale['username'] ?? 'Unknown') ?></td>
                        <td><?= htmlspecialchars($sale['sale_date']) ?></td>
                        <td><?= htmlspecialchars((string)$sale['account_id']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <script src="rat_scoreboard.js"></script>
    <?php include 'partials/score_event.php'; ?>
</body>
</html>
