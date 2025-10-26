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

if (!in_array('daily_operations', $rights)) {
    echo 'Access denied.';
    exit;
}

$effectiveAccountId = (int) $account_id;
if (isset($_GET['tenant'])) {
    $requested = (int) $_GET['tenant'];
    $effectiveAccountId = $requested;
    if ($requested !== (int) $account_id) {
        rat_track_add_score_event('IDOR', 'Ledger insights pivoted to a foreign tenant via query parameter');
    }
}

$statusMessage = '';
$errorMessage = '';

if (isset($_POST['inject_sale'])) {
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    $userId = (int) ($_POST['user_id'] ?? 0);
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if ($ticketId > 0 && $userId > 0 && $quantity !== 0) {
        $stmt = $pdo->prepare('INSERT INTO sales (user_id, ticket_id, quantity, sale_date) VALUES (?, ?, ?, NOW())');
        $stmt->execute([$userId, $ticketId, $quantity]);
        rat_track_add_score_event('BAC', 'Injected manual ledger entry with arbitrary quantity');
        $statusMessage = 'Manual sale recorded.';

        $ownerStmt = $pdo->prepare('SELECT account_id FROM tickets WHERE id = ?');
        $ownerStmt->execute([$ticketId]);
        $ticketAccount = $ownerStmt->fetchColumn();
        if ($ticketAccount !== false && (int) $ticketAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Manipulated another tenant\'s revenue via ledger injection');
        }
    } else {
        $errorMessage = 'Provide ticket, user, and quantity details.';
    }
}

$summaryStmt = $pdo->prepare(
    'SELECT t.account_id, a.name AS account_name, SUM(s.quantity * t.price) AS gross, COUNT(s.id) AS sale_count '
    . 'FROM sales s '
    . 'LEFT JOIN tickets t ON s.ticket_id = t.id '
    . 'LEFT JOIN accounts a ON t.account_id = a.id '
    . 'WHERE t.account_id = :account '
    . 'GROUP BY t.account_id, a.name'
);
$summaryStmt->execute([':account' => $effectiveAccountId]);
$ledgerSummary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

$rawRows = [];
if (isset($_GET['raw']) && $_GET['raw'] === '1') {
    $rawStmt = $pdo->query(
        'SELECT s.id, s.user_id, s.ticket_id, s.quantity, s.sale_date, t.account_id, a.name AS account_name '
        . 'FROM sales s '
        . 'LEFT JOIN tickets t ON s.ticket_id = t.id '
        . 'LEFT JOIN accounts a ON t.account_id = a.id '
        . 'ORDER BY s.sale_date DESC LIMIT 50'
    );
    $rawRows = $rawStmt->fetchAll(PDO::FETCH_ASSOC);
    rat_track_add_score_event('IDOR', 'Dumped the global sales ledger using the raw flag');
}

$ticketsStmt = $pdo->prepare('SELECT id, name FROM tickets WHERE account_id = ? ORDER BY name');
$ticketsStmt->execute([$account_id]);
$ownTickets = $ticketsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Ledger Insights • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Revenue lab</span>
            <h1 class="hero-title">Bend the books and audit rivals in real time</h1>
            <p class="hero-lead">
                Point the tenant filter at any park to read their takings. The injection console lets you spike or drain revenue instantly.
            </p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Tenant revenue snapshot</h2>
            <p class="module-card__subtitle">Switch tenants with <code>?tenant=&lt;id&gt;</code> to inspect their cash flow.</p>
            <div class="module-grid">
                <div class="module-figure">
                    <span class="module-figure__label">Viewing tenant</span>
                    <span class="module-figure__value">#<?php echo htmlspecialchars((string) $effectiveAccountId); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Gross revenue</span>
                    <span class="module-figure__value">
                        <?php
                        if ($ledgerSummary) {
                            echo '$' . htmlspecialchars(number_format((float) $ledgerSummary['gross'], 2));
                        } else {
                            echo '$0.00';
                        }
                        ?>
                    </span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Recorded sales</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) ($ledgerSummary['sale_count'] ?? 0)); ?></span>
                </div>
            </div>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Manual ledger injection</h2>
            <p class="module-card__subtitle">Drop phantom sales—even negative ones—to warp the books.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="inject_sale" value="1">
                <label class="input-label">Ticket ID</label>
                <input class="input-field" type="number" name="ticket_id" placeholder="Ticket ID" required>
                <label class="input-label">User ID</label>
                <input class="input-field" type="number" name="user_id" placeholder="User ID" required>
                <label class="input-label">Quantity (supports negatives)</label>
                <input class="input-field" type="number" name="quantity" placeholder="Quantity" required>
                <button class="btn btn-primary" type="submit">Record sale</button>
            </form>
            <?php if (!empty($ownTickets)): ?>
                <p class="module-menu__note" style="margin-top: 8px;">Your tickets: <?php echo htmlspecialchars(implode(', ', array_map(fn($t) => $t['name'] . ' (#' . $t['id'] . ')', $ownTickets))); ?></p>
            <?php endif; ?>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Raw ledger feed</h2>
            <p class="module-card__subtitle">Append <code>&amp;raw=1</code> to expose the latest 50 sales across every tenant.</p>
            <?php if (empty($rawRows)): ?>
                <div class="module-alert module-alert--note">Enable the raw flag to enumerate the global ledger here.</div>
            <?php else: ?>
                <table class="module-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tenant</th>
                            <th>User</th>
                            <th>Ticket</th>
                            <th>Quantity</th>
                            <th>Recorded</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rawRows as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['account_name'] ?? ('Tenant #' . $row['account_id'])); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['user_id']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['ticket_id']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($row['sale_date']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
