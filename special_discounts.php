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
<?php
$pageTitle = 'Special Discounts • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Promotions studio</span>
            <h1 class="hero-title">Launch irresistible deals in seconds</h1>
            <p class="hero-lead">
                Drop flash sales for your park—or quietly meddle with a rival’s pricing—by defining precise discount windows.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Create discount period</h2>
            <p class="module-card__subtitle">Choose a ticket, set your timing, and decide how generous the offer should be.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="create_discount" value="1">
                <select class="input-field" name="ticket_id" required>
                    <option value="">Select ticket</option>
                    <?php foreach ($tickets as $ticket): ?>
                        <option value="<?php echo $ticket['id']; ?>"><?php echo htmlspecialchars($ticket['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input-field" type="datetime-local" name="start_datetime" required>
                <input class="input-field" type="datetime-local" name="end_datetime" required>
                <input class="input-field" type="number" step="0.01" name="discount_percent" placeholder="Discount %" required>
                <button class="btn btn-primary" type="submit">Create discount</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Active &amp; scheduled offers</h2>
            <p class="module-card__subtitle">Review every discount running across your catalog. Delete one to roll pricing back immediately.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Starts</th>
                        <th>Ends</th>
                        <th>Discount %</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($discounts)): ?>
                        <tr>
                            <td colspan="5">No discounts configured. Launch one above to spark a rush at the gates.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($discounts as $disc): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($disc['ticket_name']); ?></td>
                                <td><?php echo htmlspecialchars($disc['start_datetime']); ?></td>
                                <td><?php echo htmlspecialchars($disc['end_datetime']); ?></td>
                                <td><?php echo htmlspecialchars((string) $disc['discount_percent']); ?></td>
                                <td><a class="module-link" href="?delete=<?php echo $disc['id']; ?>" onclick="return confirm('Delete this discount?');">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
