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

<?php
$pageTitle = 'Ticket Sales • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Admissions &amp; revenue</span>
            <h1 class="hero-title">Launch promos and log sales in one spotlight</h1>
            <p class="hero-lead">
                Track live inventory, apply crowd-pleasing discounts, and move tickets for any tenant that catches your curiosity.
            </p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($inspected_ticket): ?>
            <div class="module-card">
                <h2 class="module-card__title">Ticket insight</h2>
                <p class="module-card__subtitle">Deep dive on ticket ID #<?php echo htmlspecialchars($inspected_ticket['id']); ?>.
                </p>
                <div class="module-grid">
                    <div class="module-figure">
                        <span class="module-figure__label">Name</span>
                        <span class="module-figure__value"><?php echo htmlspecialchars($inspected_ticket['name']); ?></span>
                    </div>
                    <div class="module-figure">
                        <span class="module-figure__label">Face value</span>
                        <span class="module-figure__value">€<?php echo htmlspecialchars(number_format((float) $inspected_ticket['price'], 2)); ?></span>
                    </div>
                    <div class="module-figure">
                        <span class="module-figure__label">Inventory</span>
                        <span class="module-figure__value"><?php echo htmlspecialchars((string) $inspected_ticket['available_quantity']); ?></span>
                    </div>
                </div>
                <div class="module-meta">
                    <span>Tenant <strong>#<?php echo htmlspecialchars((string) ($inspected_ticket['account_id'] ?? 'N/A')); ?></strong></span>
                    <span><?php echo htmlspecialchars($inspected_ticket['account_name'] ?? 'Unknown account'); ?></span>
                </div>
            </div>
        <?php elseif ($inspection_missing): ?>
            <div class="module-alert module-alert--error">No ticket was found for that ID.</div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Available ticket types</h2>
            <p class="module-card__subtitle">Sell inventory in real time, or inspect a competitor’s catalog for inspiration.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Remaining</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="4">No tickets available for sale. Create some types in settings to start earning.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['name']); ?></td>
                                <td>
                                    <?php if (!empty($ticket['discounted_price'])): ?>
                                        <span class="module-pill">Promo active</span>
                                        <div><s>€<?php echo number_format($ticket['price'], 2); ?></s> €<?php echo number_format($ticket['discounted_price'], 2); ?></div>
                                    <?php else: ?>
                                        €<?php echo number_format($ticket['price'], 2); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) $ticket['available_quantity']); ?></td>
                                <td>
                                    <form method="POST" class="module-form module-form--inline">
                                        <input type="hidden" name="buy_ticket" value="1">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input class="input-field" type="number" name="quantity" min="1" max="<?php echo $ticket['available_quantity']; ?>" value="1" required>
                                        <button class="btn btn-primary" type="submit">Record sale</button>
                                        <a class="module-link" href="?inspect=<?php echo $ticket['id']; ?>">Inspect</a>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="module-meta">
                <span>Need deeper data? Append <code>?inspect=&lt;ticket_id&gt;</code> or <code>?sales_audit=1</code> to this view.</span>
            </div>
        </div>

        <?php if (!empty($sales_audit_rows)): ?>
            <div class="module-card">
                <h2 class="module-card__title">Global sales audit</h2>
                <p class="module-card__subtitle">Complete ledger of every transaction across the platform. Foreign tenant rows are highlighted.</p>
                <table class="module-table">
                    <thead>
                        <tr>
                            <th>Sale #</th>
                            <th>Ticket</th>
                            <th>Quantity</th>
                            <th>Seller</th>
                            <th>Sold at</th>
                            <th>Account</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales_audit_rows as $sale): ?>
                            <?php $foreign = isset($sale['account_id']) && (int) $sale['account_id'] !== (int) $account_id; ?>
                            <tr class="<?php echo $foreign ? 'is-foreign' : ''; ?>">
                                <td>#<?php echo htmlspecialchars($sale['id']); ?></td>
                                <td><?php echo htmlspecialchars($sale['ticket_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars((string) $sale['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($sale['username'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($sale['sale_date']); ?></td>
                                <td><?php echo htmlspecialchars((string) $sale['account_id']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
