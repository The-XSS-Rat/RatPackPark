<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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
    $account_id = $decoded->account_id;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('analytics', $rights)) {
    echo "Access denied.";
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_id = ?");
$stmt->execute([$account_id]);
$total_users = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE account_id = ?");
$stmt->execute([$account_id]);
$total_tickets = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Analytics | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f3e5f5; padding: 20px; }
        h2 { text-align: center; color: #6a1b9a; }
        .card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin: 10px 0;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }
        .card h3 { color: #4a148c; margin: 0 0 10px; }
        .card p { font-size: 20px; margin: 0; color: #333; }
    </style>
</head>
<body>
    <h2>📊 Analytics Dashboard</h2>
    <div class="card">
        <h3>Total Users</h3>
        <p><?= $total_users ?></p>
    </div>
    <div class="card">
        <h3>Total tickets</h3>
        <p><?= $total_tickets ?></p>
    </div>

</body>
</html>