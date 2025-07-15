<?php
require 'vendor/autoload.php';
require 'db.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RatPack Park Management</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #f3e5f5, #ede7f6);
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 80px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            color: #6a1b9a;
        }
        p {
            font-size: 18px;
            color: #555;
        }
        a.button {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px;
            background: #6a1b9a;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        a.button:hover {
            background: #4a148c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🎢 Welcome to RatPack Park Management</h1>
        <p>
            Manage your entire theme park effortlessly. From shift rosters to ticket sales, we've got it covered.<br>
            Try it now and see how it transforms your operations.
        </p>
        <a class="button" href="register.php">Start Free Trial</a>
        <a class="button" href="login.php">Already a Member? Log In</a>
    </div>
</body>
</html>