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
    $account_id = $decoded->account_id ?? 1;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('user_management', $rights)) {
    echo "Access denied.";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $role_id = (int)($_POST['role_id'] ?? 0);
    if ($user_id && $role_id) {
        $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ? AND account_id = ?");
        $stmt->execute([$role_id, $user_id, $account_id]);
        $success = 'Role assigned.';
    } else {
        $error = 'User and role are required.';
    }
}

$stmt = $pdo->prepare("SELECT id, username, role_id FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT id, name FROM roles ORDER BY name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
$role_map = [];
foreach ($roles as $r) {
    $role_map[$r['id']] = $r['name'];
}
?>
<?php
$pageTitle = 'Assign Roles • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Access control</span>
            <h1 class="hero-title">Match teammates to the powers they need</h1>
            <p class="hero-lead">
                Promote a superstar, demote a troublemaker, or quietly align yourself with admin rights across any tenant.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Assign a new role</h2>
            <p class="module-card__subtitle">Pick a user and align them with the right level of access.</p>
            <form method="POST" class="module-form">
                <select class="input-field" name="user_id" required>
                    <option value="" disabled selected>Select user</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['username']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="input-field" name="role_id" required>
                    <option value="" disabled selected>Select role</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Assign role</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Current roster</h2>
            <p class="module-card__subtitle">Review the roles everyone already holds.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Current role</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="2">No users found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($u['username']); ?></td>
                                <td><?php echo htmlspecialchars($role_map[$u['role_id']] ?? 'Unknown'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
