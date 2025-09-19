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

if (!in_array('settings', $rights)) {
    echo "Access denied.";
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

<?php
$pageTitle = 'Ticket Type Management • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Catalog studio</span>
            <h1 class="hero-title">Design ticket products that keep the turnstiles spinning</h1>
            <p class="hero-lead">
                Launch fresh passes, tweak pricing, and manage inventory for every park you can reach.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Create new ticket type</h2>
            <p class="module-card__subtitle">Set the basics for a new pass, from price to available inventory.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="create_ticket_type" value="1">
                <input class="input-field" type="text" name="name" placeholder="Ticket name" required>
                <input class="input-field" type="number" step="0.01" name="price" placeholder="Price" required>
                <input class="input-field" type="number" name="available_quantity" placeholder="Available quantity" required>
                <button class="btn btn-primary" type="submit">Create ticket</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Existing ticket types</h2>
            <p class="module-card__subtitle">Edit live products or purge them completely.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Price</th>
                        <th>Available</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tickets)): ?>
                        <tr>
                            <td colspan="5">No ticket types yet. Add one above to start selling.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tickets as $ticket): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($ticket['name']); ?></td>
                                <td>€<?php echo number_format($ticket['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars((string) $ticket['available_quantity']); ?></td>
                                <td><?php echo htmlspecialchars($ticket['created_at']); ?></td>
                                <td>
                                    <form class="module-form module-form--inline" method="POST">
                                        <input type="hidden" name="edit_ticket_type_id" value="<?php echo $ticket['id']; ?>">
                                        <input class="input-field" type="text" name="edit_name" value="<?php echo htmlspecialchars($ticket['name']); ?>">
                                        <input class="input-field" type="number" step="0.01" name="edit_price" value="<?php echo $ticket['price']; ?>">
                                        <input class="input-field" type="number" name="edit_quantity" value="<?php echo $ticket['available_quantity']; ?>">
                                        <button class="btn btn-outline" type="submit">Update</button>
                                        <a class="module-link" href="?delete=<?php echo $ticket['id']; ?>" onclick="return confirm('Delete this ticket type?');">Delete</a>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>