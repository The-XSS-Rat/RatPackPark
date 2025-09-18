<?php
if (!isset($pageTitle)) {
    $pageTitle = 'RatPack Park';
}
if (!isset($activePage)) {
    $activePage = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<div class="aurora"></div>
<div class="app-shell">
    <header class="top-nav">
        <a href="index.php" class="brand">
            <span class="brand__badge">RatPack</span>
            <span>Park OS</span>
        </a>
        <nav class="nav-links">
            <a class="nav-link<?php echo $activePage === 'home' ? ' active' : ''; ?>" href="index.php">Home</a>
            <a class="nav-link<?php echo $activePage === 'dashboard' ? ' active' : ''; ?>" href="dashboard.php">Dashboard</a>
            <a class="nav-link<?php echo $activePage === 'login' ? ' active' : ''; ?>" href="login.php">Login</a>
            <a class="nav-link nav-link--primary<?php echo $activePage === 'register' ? ' active' : ''; ?>" href="register.php">Start Trial</a>
        </nav>
    </header>
    <main class="main-content">
