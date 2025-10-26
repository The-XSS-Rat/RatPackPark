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

if (!in_array('user_management', $rights)) {
    echo 'Access denied.';
    exit;
}

$effectiveAccountId = (int) $account_id;
$showAll = isset($_GET['all']) && $_GET['all'] === '1';
if (isset($_GET['account'])) {
    $requested = (int) $_GET['account'];
    $effectiveAccountId = $requested;
    if ($requested !== (int) $account_id) {
        rat_track_add_score_event('IDOR', 'Tenant directory hijacked to enumerate foreign users');
    }
}
if ($showAll) {
    rat_track_add_score_event('IDOR', 'Tenant directory dumped every user by using the all switch');
}

$statusMessage = '';
$errorMessage = '';

if (isset($_POST['set_role'])) {
    $targetUser = (int) ($_POST['user_id'] ?? 0);
    $roleId = (int) ($_POST['role_id'] ?? 0);

    if ($targetUser > 0 && $roleId > 0) {
        $ownerStmt = $pdo->prepare('SELECT account_id FROM users WHERE id = ?');
        $ownerStmt->execute([$targetUser]);
        $ownerAccount = $ownerStmt->fetchColumn();
        if ($ownerAccount !== false && (int) $ownerAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Reassigned staff who belong to another tenant through directory console');
        }

        $stmt = $pdo->prepare('UPDATE users SET role_id = ? WHERE id = ?');
        $stmt->execute([$roleId, $targetUser]);
        rat_track_add_score_event('BAC', 'Updated staff permissions with arbitrary role IDs');
        $statusMessage = 'Role updated successfully.';
    } else {
        $errorMessage = 'Provide both a user ID and a role ID.';
    }
}

$query = 'SELECT u.id, u.username, u.email, u.account_id, u.role_id, a.name AS account_name, r.name AS role_name FROM users u '
    . 'LEFT JOIN accounts a ON u.account_id = a.id '
    . 'LEFT JOIN roles r ON u.role_id = r.id';
$params = [];
if (!$showAll) {
    $query .= ' WHERE u.account_id = :account';
    $params[':account'] = $effectiveAccountId;
}
$query .= ' ORDER BY u.account_id, u.username';
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$rolesStmt = $pdo->query('SELECT id, name FROM roles ORDER BY id');
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Tenant Directory • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Identity radar</span>
            <h1 class="hero-title">Cross-tenant staff oversight in one screen</h1>
            <p class="hero-lead">
                Reassign roles, explore rival staff rosters, or pull every user at once. The directory trusts whatever IDs you feed it.
            </p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Directory filters</h2>
            <p class="module-card__subtitle">Swap account scopes or flip on the all switch to see everyone in the platform.</p>
            <form method="GET" class="module-form">
                <div class="module-form__row">
                    <label class="input-label">Tenant</label>
                    <input class="input-field" type="number" name="account" value="<?php echo htmlspecialchars((string) $effectiveAccountId); ?>" placeholder="Account ID">
                </div>
                <label class="input-checkbox">
                    <input type="checkbox" name="all" value="1" <?php echo $showAll ? 'checked' : ''; ?>> Show every tenant
                </label>
                <button class="btn btn-primary" type="submit">Update view</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Staff roster</h2>
            <p class="module-card__subtitle">Select any entry and assign whatever role ID suits your mission.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="6">No users found for this scope.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['account_name'] ?? 'Tenant #' . $user['account_id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['role_name'] ?? ('Role #' . $user['role_id'])); ?></td>
                                <td>
                                    <form method="POST" class="module-form module-form--inline">
                                        <input type="hidden" name="set_role" value="1">
                                        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars((string) $user['id']); ?>">
                                        <select class="input-field" name="role_id">
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?php echo $role['id']; ?>" <?php echo (int) $role['id'] === (int) $user['role_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($role['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline" type="submit">Apply</button>
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
