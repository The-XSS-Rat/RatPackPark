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
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('roles_management', $rights)) {
    echo "Access denied.";
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $rights_input = $_POST['rights'] ?? '';
    if ($name) {
        $stmt = $pdo->prepare("INSERT INTO roles (name) VALUES (?)");
        $stmt->execute([$name]);
        $role_id = $pdo->lastInsertId();
        $rights_list = array_filter(array_map('trim', explode(',', $rights_input)));
        foreach ($rights_list as $r) {
            $stmt = $pdo->prepare("INSERT INTO role_rights (role_id, right_name) VALUES (?, ?)");
            $stmt->execute([$role_id, $r]);
        }
        $success = 'Role created.';
    } else {
        $error = 'Name is required.';
    }
}

$stmt = $pdo->query("SELECT r.id, r.name, GROUP_CONCAT(rr.right_name ORDER BY rr.right_name SEPARATOR ', ') AS rights FROM roles r LEFT JOIN role_rights rr ON r.id = rr.role_id GROUP BY r.id, r.name ORDER BY r.id");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
$pageTitle = 'Role Management • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Permission architecture</span>
            <h1 class="hero-title">Craft bespoke access stacks</h1>
            <p class="hero-lead">
                Define new roles and wire them up with rights that unlock every hidden corner of the platform.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Create new role</h2>
            <p class="module-card__subtitle">Name your role and list its rights as a comma-separated string.</p>
            <form method="POST" class="module-form">
                <input class="input-field" type="text" name="name" placeholder="Role name" required>
                <textarea class="input-field" name="rights" placeholder="Comma-separated rights"></textarea>
                <button class="btn btn-primary" type="submit">Create role</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Defined roles</h2>
            <p class="module-card__subtitle">Inspect every role and the rights it grants.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Rights</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($roles)): ?>
                        <tr>
                            <td colspan="3">No roles have been defined yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($roles as $role): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($role['id']); ?></td>
                                <td><?php echo htmlspecialchars($role['name']); ?></td>
                                <td><?php echo htmlspecialchars($role['rights'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
