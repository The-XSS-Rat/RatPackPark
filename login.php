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
            $payload = [
                'sub' => $user['id'],
                'username' => $user['username'],
                'role_id' => $user['role_id'],
                'account_id' => $user['account_id'],
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
?>
<?php include 'partials/header.php'; ?>
<div class="form-container">
    <h2>🎟️ Login to RatPack Park</h2>
    <?php if (!empty($error)): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Log In</button>
    </form>
</div>
<?php include 'partials/footer.php'; ?>