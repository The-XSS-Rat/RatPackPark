<?php
require 'vendor/autoload.php';
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

if (!in_array('settings', $rights)) {
    echo "Access denied.";
    exit;
}

$pageTitle = 'Settings • RatPack Park';
$activePage = '';
include 'partials/header.php';
?>
<section class="section section--settings">
    <div class="section__inner settings-shell">
        <div class="settings-header hero-card">
            <span class="hero-badge">Operations control</span>
            <h1 class="hero-title">Tailor RatPack Park to your team</h1>
            <p class="hero-lead">
                Manage the backstage essentials that keep your park experience seamless — from the crews powering each
                attraction to the passes you sell at the front gate.
            </p>
        </div>
        <div class="settings-grid">
            <a href="user_management.php" class="settings-card" target="mainframe">
                <div class="settings-card__icon" aria-hidden="true">👥</div>
                <h3 class="settings-card__title">User Management</h3>
                <p class="settings-card__copy">
                    Invite new employees, adjust access levels, and keep your crew’s credentials aligned with their roles.
                </p>
                <span class="settings-card__cta">Open module →</span>
            </a>
            <a href="ticket_types.php" class="settings-card" target="mainframe">
                <div class="settings-card__icon" aria-hidden="true">🎟️</div>
                <h3 class="settings-card__title">Ticket Types</h3>
                <p class="settings-card__copy">
                    Design and launch new passes, seasonal promos, and VIP experiences without leaving your control center.
                </p>
                <span class="settings-card__cta">Open module →</span>
            </a>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
