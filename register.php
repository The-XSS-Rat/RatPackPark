<?php
// register.php (admin-only registration + intro explanation)
require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;

session_start();

// Force all registrations to be admin-level (role_id = 1)
$role_id = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } else {
        // Create a new account for this admin
        $stmt = $pdo->prepare("INSERT INTO accounts (name, contact_email) VALUES (?, ?)");
        $accountName = $username . "'s Theme Park";
        $stmt->execute([$accountName, $email]);
        $account_id = $pdo->lastInsertId();

        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (account_id, role_id, username, email, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$account_id, $role_id, $username, $email, $password_hash]);

            $user_id = $pdo->lastInsertId();
            $stmt = $pdo->prepare("SELECT right_name FROM role_rights WHERE role_id = ?");
            $stmt->execute([$role_id]);
            $rights = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $payload = [
                'sub' => $user_id,
                'username' => $username,
                'role_id' => $role_id,
                'account_id' => $account_id,
                'rights' => $rights,
                'iat' => time(),
                'exp' => time() + (60 * 60)
            ];
            $jwt = JWT::encode($payload, 'your-secret-key', 'HS256');
            $_SESSION['jwt'] = $jwt;

            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Start Your Trial • RatPack Park';
$activePage = 'register';
include 'partials/header.php';
?>
<section class="form-section">
    <div class="form-shell">
        <div class="form-card">
            <h2>Spin Up Your Park in Minutes</h2>
            <p>Launch a fully-featured RatPack Park environment as an Admin. Try staffing dashboards, ticketing tools, and maintenance workflows with your own crew.</p>
            <?php if (!empty($error)): ?>
                <div class="alert alert--error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert--success"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST">
                <input class="input-field" type="text" name="username" placeholder="Username" required>
                <input class="input-field" type="email" name="email" placeholder="Work email" required>
                <input class="input-field" type="password" name="password" placeholder="Create a password" required>
                <button class="btn btn-accent" type="submit">Create My Trial Park</button>
            </form>
            <p style="margin-top: 20px; font-size: 0.95rem; color: var(--text-muted);">
                Already managing a park? <a href="login.php" class="nav-link" style="padding: 0; border-radius: 0;">Log in</a> to continue the show.
            </p>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
