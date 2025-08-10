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
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array($role_id, [1, 2])) {
    echo "Access denied. Admins and Managers only.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Maintenance | RatPack Park</title>
    <style>
        body { font-family: Arial; background: #f5f5fc; padding: 20px; }
        h2 { color: #6a1b9a; }
        ul { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.05); }
        li { margin: 8px 0; }
    </style>
    </head>
<body>
    <h2>🛠️ Maintenance Tasks</h2>
    <ul>
        <li>Check roller coaster brakes</li>
        <li>Inspect water slides</li>
        <li>Test safety harnesses</li>
    </ul>
</body>
</html>

