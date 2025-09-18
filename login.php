<?php
// login.php (redirects to dashboard.php on success)
require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;

session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = 'Missing username or password';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Create JWT payload
            $stmt = $pdo->prepare("SELECT right_name FROM role_rights WHERE role_id = ?");
            $stmt->execute([$user['role_id']]);
            $rights = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $payload = [
                'sub' => $user['id'],
                'username' => $user['username'],
                'role_id' => $user['role_id'],
                'account_id' => $user['account_id'],
                'rights' => $rights,
                'iat' => time(),
                'exp' => time() + (60 * 60) // 1 hour
            ];

            $jwt = JWT::encode($payload, 'your-secret-key', 'HS256');
            $_SESSION['jwt'] = $jwt;

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid credentials';
        }
    }
}

$pageTitle = 'Log In • RatPack Park';
$activePage = 'login';
include 'partials/header.php';
?>
<section class="form-section">
    <div class="form-shell">
        <div class="form-card">
            <h2>Welcome back, Ringmaster!</h2>
            <p>Log in with your RatPack Park credentials to get back to scheduling rides, wrangling staff, and delighting guests.</p>
            <?php if (!empty($error)): ?>
                <div class="alert alert--error"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input class="input-field" type="text" name="username" placeholder="Username" required>
                <input class="input-field" type="password" name="password" placeholder="Password" required>
                <button class="btn btn-primary" type="submit">Log In</button>
            </form>
            <p style="margin-top: 20px; font-size: 0.95rem; color: var(--text-muted);">
                New here? <a href="register.php" class="nav-link" style="padding: 0; border-radius: 0;">Start a free trial</a> to spin up your park in seconds.
            </p>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
