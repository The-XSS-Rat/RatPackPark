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
        .guide {
            margin: 30px 0;
            text-align: left;
            background: #f8f3ff;
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: inset 0 0 0 1px rgba(106, 27, 154, 0.12);
        }
        .guide h2 {
            color: #4a148c;
            margin-top: 0;
        }
        .guide ol {
            margin: 0;
            padding-left: 20px;
            color: #444;
            line-height: 1.6;
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
        <div class="guide">
            <h2>How the Platform Fits Together</h2>
            <ol>
                <li><strong>Create your organisation:</strong> Register a trial account to generate a brand new tenant with an administrator user.</li>
                <li><strong>Invite and schedule your crew:</strong> Configure ticket types and discounts, create staff through User Management, and arrange their shifts within Rosters.</li>
                <li><strong>Operate the park:</strong> Track daily metrics in Daily Operations, sell tickets, and watch revenue update automatically.</li>
                <li><strong>Stay on top of issues:</strong> Log incidents from the Report Problem area and triage them through the Admin Problem view.</li>
                <li><strong>Review performance:</strong> Visit Analytics for tenant-wide KPIs and Rat Track to study known weaknesses and challenges.</li>
            </ol>
        </div>
        <a class="button" href="register.php">Start Free Trial</a>
        <a class="button" href="login.php">Already a Member? Log In</a>
    </div>
    <?php include 'partials/footer.php'; ?>
