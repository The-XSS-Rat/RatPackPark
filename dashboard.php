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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .topbar a {
            color: #ffeb3b;
            text-decoration: none;
            font-weight: 600;
        }
        .topbar a:hover {
            text-decoration: underline;
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
            <span>Logged in as <strong><?php echo htmlspecialchars($username); ?></strong> | Role ID: <?php echo $role_id; ?></span>
            <a href="https://www.youtube.com/playlist?list=PLd92v1QxPOprxnqslA9ho9egWvs4_3gDQ" target="_blank" rel="noopener noreferrer">Solutions</a>
        </div>
        <iframe name="mainframe" src="welcome.php"></iframe>
    </div>
    <script src="rat_scoreboard.js"></script>
    <?php include 'partials/score_event.php'; ?>
</body>
</html>
