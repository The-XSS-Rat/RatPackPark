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

if (!in_array('user_management', $rights)) {
    echo "Access denied.";
    exit;
}

// Handle user delete
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND account_id = ?");
    $stmt->execute([$delete_id, $account_id]);
    $success = "User deleted.";
    header("Location: user_management.php");
    exit;
}

// Handle user edit submission
if (isset($_POST['edit_user_id'])) {
    $edit_id = (int)$_POST['edit_user_id'];
    $edit_role = (int)$_POST['edit_role_id'];
    $stmt = $pdo->prepare("UPDATE users SET role_id = ? WHERE id = ? AND account_id = ?");
    $stmt->execute([$edit_role, $edit_id, $account_id]);
    $success = "User updated.";
}

// Handle create form
if (isset($_POST['create_user'])) {
    $new_username = $_POST['username'] ?? '';
    $new_email = $_POST['email'] ?? '';
    $new_password = $_POST['password'] ?? '';
    $new_role = $_POST['role_id'] ?? 3;

    if ($new_username && $new_email && $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (account_id, role_id, username, email, password_hash) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$account_id, $new_role, $new_username, $new_email, $password_hash]);
        $success = "User created successfully!";
    } else {
        $error = "All fields are required.";
    }
}

// Fetch users
$stmt = $pdo->prepare("SELECT id, username, email, role_id, created_at FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role map
$stmt = $pdo->query("SELECT id, name FROM roles");
$roles = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $roles[$row['id']] = $row['name'];
}
?>

$pageTitle = 'User Management • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Crew directory</span>
            <h1 class="hero-title">Invite, elevate, and prune your park staff</h1>
            <p class="hero-lead">
                Control access across attractions and departments by creating new users and shifting their roles on the fly.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Create new user</h2>
            <p class="module-card__subtitle">Spin up a fresh account and grant the right level of control.</p>
            <form method="POST" class="module-form">
                <input type="hidden" name="create_user" value="1">
                <input class="input-field" type="text" name="username" placeholder="Username" required>
                <input class="input-field" type="email" name="email" placeholder="Email" required>
                <input class="input-field" type="password" name="password" placeholder="Password" required>
                <select class="input-field" name="role_id" required>
                    <?php foreach ($roles as $id => $label): ?>
                        <?php if ($id !== 1): ?>
                            <option value="<?php echo $id; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Create user</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Existing staff</h2>
            <p class="module-card__subtitle">Adjust roles or remove accounts that no longer need access.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="5">No users found for this tenant.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo htmlspecialchars($roles[$user['role_id']] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                                <td>
                                    <form class="module-form module-form--inline" method="POST">
                                        <input type="hidden" name="edit_user_id" value="<?php echo $user['id']; ?>">
                                        <select class="input-field" name="edit_role_id">
                                            <?php foreach ($roles as $id => $label): ?>
                                                <option value="<?php echo $id; ?>" <?php echo $id == $user['role_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline" type="submit">Update</button>
                                        <a class="module-link" href="?delete=<?php echo $user['id']; ?>" onclick="return confirm('Are you sure you want to delete this user?');">Delete</a>
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
