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
    $role_id = $decoded->role_id;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('maintenance', $rights)) {
    echo "Access denied.";
    exit;
}
?>
<?php
$pageTitle = 'Maintenance • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Ride readiness</span>
            <h1 class="hero-title">Keep attractions safe and spectacular</h1>
            <p class="hero-lead">
                Work through the essentials before gates open—these checks keep thrill-seekers smiling.
            </p>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Today’s checklist</h2>
            <ul class="module-list">
                <li class="module-list__item">Check roller coaster brakes</li>
                <li class="module-list__item">Inspect water slides</li>
                <li class="module-list__item">Test safety harnesses</li>
            </ul>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>

