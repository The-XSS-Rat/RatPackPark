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
} catch (Exception $e) {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | RatPack Park</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            width: 200px;
            background: #6a1b9a;
            color: white;
            padding: 20px;
        }
        .sidebar h3 {
            margin-top: 0;
        }
        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            margin: 10px 0;
        }
        .main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        .topbar {
            background: #4a148c;
            color: white;
            padding: 10px 20px;
        }
        iframe {
            flex: 1;
            border: none;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <h3>Menu</h3>
        <?php include 'menu.php'; ?>
    </div>
    <div class="main">
        <div class="topbar">
            Logged in as <strong><?php echo htmlspecialchars($username); ?></strong> | Role ID: <?php echo $role_id; ?>
        </div>
        <iframe name="mainframe" src="welcome.php"></iframe>
    </div>
</body>
</html>