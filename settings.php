<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings | RatPack Park</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3e5f5;
            padding: 20px;
        }
        .settings-container {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            color: #6a1b9a;
        }
        .settings-option {
            display: block;
            background: #6a1b9a;
            color: white;
            text-decoration: none;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            text-align: center;
            transition: background 0.3s;
        }
        .settings-option:hover {
            background: #4a148c;
        }
    </style>
</head>
<body>
    <div class="settings-container">
        <h2>⚙️ Settings</h2>
        <a href="user_management.php" class="settings-option" target="mainframe">👥 User Management</a>
        <a href="ticket_types.php" class="settings-option" target="mainframe">🎟️ Ticket Types</a>
    </div>
</body>
</html>
