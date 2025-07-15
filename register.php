<?php
// register.php (admin-only registration + intro explanation)
require 'vendor/autoload.php';
require 'db.php';

// Force all registrations to be admin-level (role_id = 1)
$role_id = 1;
// Create a new account for this admin
$stmt = $pdo->prepare("INSERT INTO accounts (name, contact_email) VALUES (?, ?)");
$accountName = $username . "'s Theme Park";
$stmt->execute([$accountName, $email]);
$account_id = $pdo->lastInsertId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$username || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } else {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("INSERT INTO users (account_id, role_id, username, email, password_hash) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$account_id, $role_id, $username, $email, $password_hash]);
            $success = 'Welcome to RatPack Park! Your admin account has been created. You can now log in and begin managing your park!';
        } catch (PDOException $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<?php include 'partials/header.php'; ?>
<div class="form-container">
    <h2>🎢 Start Your Trial - RatPack Park</h2>
    <p style="font-size: 0.9em; color: #333;">Sign up to create your own theme park management environment. This trial grants you full access as an <strong>Admin</strong> so you can explore features like shift scheduling, ticket sales, reporting problems, and inviting your team.</p>
    <?php if (!empty($error)): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
    <?php if (!empty($success)): ?><div class="message"><?php echo $success; ?></div><?php endif; ?>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required>
        <input type="email" name="email" placeholder="Email" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Register Your Trial</button>
    </form>
</div>
<?php include 'partials/footer.php'; ?>
