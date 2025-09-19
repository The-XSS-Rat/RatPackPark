<?php
// dashboard.php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
session_start();

$jwt_secret = 'your-secret-key';

if (!isset($_SESSION['jwt'])) {
    header('Location: login.php');
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $username = $decoded->username;
    $role_id = $decoded->role_id;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$pageTitle = 'Operations Dashboard • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Operations cockpit</span>
            <h1 class="hero-title">Command every corner of RatPack Park</h1>
            <p class="hero-lead">
                Launch scheduling, ticketing, analytics, and maintenance tools without leaving your control center. Everything you
                need to keep the park humming lives here.
            </p>
            <div class="module-meta">
                <span>Signed in as <strong><?php echo htmlspecialchars($username); ?></strong></span>
                <span>Role ID <?php echo htmlspecialchars((string) $role_id); ?></span>
            </div>
            <div class="module-actions">
                <a class="btn btn-primary" href="settings.php" target="mainframe">Open settings</a>
                <a class="btn btn-outline" href="tickets.php" target="mainframe">Manage tickets</a>
                <a class="btn btn-accent" href="logout.php">Sign out</a>
            </div>
        </div>
        <div class="module-layout">
            <aside class="module-menu">
                <h2 class="module-menu__title">Control modules</h2>
                <?php include 'menu.php'; ?>
                <p class="module-menu__note">
                    Pick a destination to load it in the operations stage. Modules inherit your current tenant context so you can
                    switch between tasks without breaking flow.
                </p>
            </aside>
            <div class="module-stage">
                <iframe class="module-stage__frame" name="mainframe" src="welcome.php" title="Operations module workspace"></iframe>
            </div>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
