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
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-TMQL73DM4R"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());

        gtag('config', 'G-TMQL73DM4R');
    </script>
    <link rel="stylesheet" href="assets/styles.css">
</head>
<body>
<script>
    (function () {
        try {
            if (window.self !== window.top) {
                document.documentElement.classList.add('is-embedded');
                document.body.classList.add('is-embedded');
            }
        } catch (err) {
            document.documentElement.classList.add('is-embedded');
            document.body.classList.add('is-embedded');
        }
    })();
</script>
<?php
$tenantMeta = [
    'tenantId' => null,
    'startedAt' => null,
    'speedrun' => new stdClass(),
    'history' => [],
];

if (session_status() === PHP_SESSION_ACTIVE) {
    if (isset($_SESSION['temporary_tenant_id'])) {
        $tenantMeta['tenantId'] = (int)$_SESSION['temporary_tenant_id'];
    }
    if (isset($_SESSION['temporary_tenant_started_at'])) {
        $tenantMeta['startedAt'] = (int)$_SESSION['temporary_tenant_started_at'];
    }

    if (
        $tenantMeta['tenantId'] !== null &&
        isset($_SESSION['rat_speedrun']) &&
        is_array($_SESSION['rat_speedrun']) &&
        isset($_SESSION['rat_speedrun'][$tenantMeta['tenantId']]) &&
        is_array($_SESSION['rat_speedrun'][$tenantMeta['tenantId']])
    ) {
        $speedrunRecord = $_SESSION['rat_speedrun'][$tenantMeta['tenantId']];
        if (!empty($speedrunRecord['categories']) && is_array($speedrunRecord['categories'])) {
            $tenantMeta['speedrun'] = $speedrunRecord['categories'];
        }
        if (!empty($speedrunRecord['history']) && is_array($speedrunRecord['history'])) {
            $tenantMeta['history'] = array_values(array_slice($speedrunRecord['history'], -10));
        }
    }
}

$tenantMetaJson = json_encode($tenantMeta, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
if ($tenantMetaJson === false) {
    $tenantMetaJson = '{}';
}
?>
<script>
    window.__ratTenantMeta = <?php echo $tenantMetaJson; ?>;
</script>
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
