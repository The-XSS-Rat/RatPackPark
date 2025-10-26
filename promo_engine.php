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
    echo 'Not authenticated';
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $account_id = $decoded->account_id ?? 0;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo 'Invalid session.';
    exit;
}

if (!in_array('tickets', $rights)) {
    echo 'Access denied.';
    exit;
}

$effectiveAccountId = (int) $account_id;
if (isset($_GET['account'])) {
    $requested = (int) $_GET['account'];
    $effectiveAccountId = $requested;
    if ($requested !== (int) $account_id) {
        rat_track_add_score_event('IDOR', 'Promo engine retargeted another tenant via account override');
    }
}

$statusMessage = '';
$errorMessage = '';

if (isset($_POST['flash_sale'])) {
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $newPrice = isset($_POST['new_price']) ? (float) $_POST['new_price'] : null;
    $inventoryDelta = (int) ($_POST['inventory_delta'] ?? 0);

    if ($ticketId > 0 && $newPrice !== null) {
        $stmt = $pdo->prepare('UPDATE tickets SET price = ?, available_quantity = available_quantity + ? WHERE id = ?');
        $stmt->execute([$newPrice, $inventoryDelta, $ticketId]);
        rat_track_add_score_event('BAC', 'Applied flash sale adjustments without guardrails');

        $ownerStmt = $pdo->prepare('SELECT account_id FROM tickets WHERE id = ?');
        $ownerStmt->execute([$ticketId]);
        $ticketAccount = $ownerStmt->fetchColumn();
        if ($ticketAccount !== false && (int) $ticketAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Manipulated a rival tenant ticket via promo engine');
        }

        $statusMessage = 'Flash sale values applied.';
    } else {
        $errorMessage = 'Ticket ID and price are required.';
    }
}

if (isset($_POST['seed_discount'])) {
    $ticketId = (int) ($_POST['discount_ticket_id'] ?? 0);
    $percent = isset($_POST['percent_off']) ? (float) $_POST['percent_off'] : null;

    if ($ticketId > 0 && $percent !== null) {
        $stmt = $pdo->prepare('INSERT INTO ticket_discounts (ticket_id, start_datetime, end_datetime, discount_percent) VALUES (?, NOW(), DATE_ADD(NOW(), INTERVAL 2 DAY), ?)');
        $stmt->execute([$ticketId, $percent]);
        rat_track_add_score_event('BAC', 'Seeded an unrestricted discount window via promo engine');

        $ownerStmt = $pdo->prepare('SELECT account_id FROM tickets WHERE id = ?');
        $ownerStmt->execute([$ticketId]);
        $ticketAccount = $ownerStmt->fetchColumn();
        if ($ticketAccount !== false && (int) $ticketAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Planted a discount on someone else\'s catalog from promo engine');
        }

        $statusMessage = 'Discount scheduled successfully.';
    } else {
        $errorMessage = 'Provide a ticket ID and percent.';
    }
}

$ticketsStmt = $pdo->prepare('SELECT t.id, t.name, t.price, t.available_quantity, a.name AS account_name FROM tickets t LEFT JOIN accounts a ON t.account_id = a.id WHERE t.account_id = ? ORDER BY t.name');
$ticketsStmt->execute([$effectiveAccountId]);
$tickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Promo Engine • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Pricing war room</span>
            <h1 class="hero-title">Hijack ticket pricing and inventory in seconds</h1>
            <p class="hero-lead">
                Provide <code>?account=&lt;id&gt;</code> to meddle with someone else’s tickets. Every adjustment applies immediately across the park.
            </p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Ticket catalog</h2>
            <p class="module-card__subtitle">Point the account override at a rival to rewrite their pricing.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Inventory</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="5">No tickets found for this tenant.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $ticket['id']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['account_name'] ?? 'Tenant #' . $effectiveAccountId); ?></td>
                                <td><?php echo htmlspecialchars($ticket['name']); ?></td>
                                <td><?php echo htmlspecialchars('$' . number_format((float) $ticket['price'], 2)); ?></td>
                                <td><?php echo htmlspecialchars((string) $ticket['available_quantity']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Flash sale overrides</h2>
            <p class="module-card__subtitle">Set prices to zero or feed negative inventory to restock instantly.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="flash_sale" value="1">
                <label class="input-label">Ticket ID</label>
                <input class="input-field" type="number" name="ticket_id" placeholder="Ticket ID" required>
                <label class="input-label">New price</label>
                <input class="input-field" type="number" step="0.01" name="new_price" placeholder="0.00" required>
                <label class="input-label">Inventory delta</label>
                <input class="input-field" type="number" name="inventory_delta" value="0">
                <button class="btn btn-primary" type="submit">Apply override</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Rapid discount seeder</h2>
            <p class="module-card__subtitle">Drop a 99% sale on any ticket by supplying its ID and a percentage.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="seed_discount" value="1">
                <label class="input-label">Ticket ID</label>
                <input class="input-field" type="number" name="discount_ticket_id" placeholder="Ticket ID" required>
                <label class="input-label">Percent off</label>
                <input class="input-field" type="number" step="0.01" name="percent_off" placeholder="50" required>
                <button class="btn btn-outline" type="submit">Schedule discount</button>
            </form>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
