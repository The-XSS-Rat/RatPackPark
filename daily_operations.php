<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'rat_helpers.php';
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
    $user_id = $decoded->sub;
    $rights = $decoded->rights ?? [];
    $account_id = $decoded->account_id ?? null;
} catch (Exception $e) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if (!in_array('daily_operations', $rights)) {
    echo 'Access denied';
    exit;
}

if (!isset($_GET['date'])) {
    echo '<!DOCTYPE html><html><head><title>Daily Operations</title></head><body>';
    echo '<script>const d=new Date();const date=d.toLocaleDateString("en-CA");location.href="daily_operations.php?date="+date;</script>';
    echo '</body></html>';
    exit;
}

$operation_date = $_GET['date'];
$requestedAccountId = isset($_GET['account']) ? (int)$_GET['account'] : null;
if ($requestedAccountId !== null && $account_id !== null && $requestedAccountId !== (int)$account_id) {
    rat_track_add_score_event('IDOR', 'Queried daily operations for another tenant');
}

function getSalesData($pdo, $date, $accountScope = null) {
    if ($accountScope !== null) {
        $stmt = $pdo->prepare("SELECT SUM(s.quantity) AS guests, SUM(s.quantity * t.price) AS revenue FROM sales s JOIN tickets t ON s.ticket_id = t.id WHERE DATE(s.sale_date) = ? AND t.account_id = ?");
        $stmt->execute([$date, $accountScope]);
    } else {
        $stmt = $pdo->prepare("SELECT SUM(s.quantity) AS guests, SUM(s.quantity * t.price) AS revenue FROM sales s JOIN tickets t ON s.ticket_id = t.id WHERE DATE(s.sale_date) = ?");
        $stmt->execute([$date]);
    }
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['guests' => 0, 'revenue' => 0];
    }
    return ['guests' => ($row['guests'] ?? 0) ?: 0, 'revenue' => ($row['revenue'] ?? 0) ?: 0];
}

$sales = getSalesData($pdo, $operation_date, $requestedAccountId);
$guest_count = (int)$sales['guests'];
$base_revenue = (float)$sales['revenue'];

$stmt = $pdo->prepare("SELECT * FROM daily_operations WHERE user_id = ? AND operation_date = ?");
$stmt->execute([$user_id, $operation_date]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    // Pull previous state to seed today's defaults
    $stmt = $pdo->prepare("SELECT stock, land, attractions, stalls FROM daily_operations WHERE user_id = ? ORDER BY operation_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);

    $prev_stock = isset($prev['stock']) ? (int)$prev['stock'] : 100;
    $land = isset($prev['land']) ? (int)$prev['land'] : 0;
    $attractions = isset($prev['attractions']) ? (int)$prev['attractions'] : 0;
    $stalls = isset($prev['stalls']) ? (int)$prev['stalls'] : 0;

    // Generate today's values
    $weather_options = ['Sunny','Cloudy','Rainy','Stormy','Windy','Snowy'];
    $weather = $weather_options[array_rand($weather_options)];

    $stock_change = random_int(-10, 10);
    $stock = max(0, $prev_stock + $stock_change);

    $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);
    $outgoing_money = random_int(100, 500) + ($land * 20) + ($attractions * 15) + ($stalls * 10);

    // Single INSERT only (fixes duplicate key issue)
    $stmt = $pdo->prepare("INSERT INTO daily_operations (user_id, operation_date, guest_count, weather, incoming_money, outgoing_money, stock, land, attractions, stalls) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$user_id, $operation_date, $guest_count, $weather, $incoming_money, $outgoing_money, $stock, $land, $attractions, $stalls]);

    // Build $data including its new ID for later updates
    $insert_id = $pdo->lastInsertId();
    $data = [
        'id' => $insert_id,
        'guest_count' => $guest_count,
        'weather' => $weather,
        'incoming_money' => $incoming_money,
        'outgoing_money' => $outgoing_money,
        'stock' => $stock,
        'land' => $land,
        'attractions' => $attractions,
        'stalls' => $stalls
    ];
} else {
    // Recalculate income from sales + assets and update existing row
    $land = (int)$data['land'];
    $attractions = (int)$data['attractions'];
    $stalls = (int)$data['stalls'];

    $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);

    $stmt = $pdo->prepare("UPDATE daily_operations SET guest_count=?, incoming_money=? WHERE id=?");
    $stmt->execute([$guest_count, $incoming_money, $data['id']]);

    $data['guest_count'] = $guest_count;
    $data['incoming_money'] = $incoming_money;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['override_income_value']) && isset($data['id'])) {
    $overrideAmount = (float)$_POST['override_income_value'];
    $stmt = $pdo->prepare("UPDATE daily_operations SET incoming_money=? WHERE id=?");
    $stmt->execute([$overrideAmount, $data['id']]);
    $data['incoming_money'] = $overrideAmount;
    rat_track_add_score_event('BAC', 'Overrode daily operations revenue using insecure override');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy'], $_POST['type'])) {
    $costs = ['land' => 1000, 'attraction' => 500, 'stall' => 200];
    $type = $_POST['type'];

    if (isset($costs[$type])) {
        $land = (int)$data['land'];
        $attractions = (int)$data['attractions'];
        $stalls = (int)$data['stalls'];
        $current_outgoing = (float)$data['outgoing_money'];

        if ($type === 'land') { $land++; }
        if ($type === 'attraction') { $attractions++; }
        if ($type === 'stall') { $stalls++; }

        $outgoing_money = $current_outgoing + $costs[$type];
        $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);

        $stmt = $pdo->prepare("UPDATE daily_operations SET land=?, attractions=?, stalls=?, outgoing_money=?, incoming_money=? WHERE id=?");
        $stmt->execute([$land, $attractions, $stalls, $outgoing_money, $incoming_money, $data['id']]);

        header("Location: daily_operations.php?date=" . urlencode($operation_date));
        exit;
    }
}
?>
$pageTitle = 'Daily Operations • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Revenue orchestration</span>
            <h1 class="hero-title">Simulate the day’s performance from one console</h1>
            <p class="hero-lead">
                Track guest throughput, adjust asset investments, and even fudge the numbers when no one’s watching.
            </p>
            <div class="module-meta">
                <span>Date: <strong><?php echo htmlspecialchars($operation_date); ?></strong></span>
                <?php if ($requestedAccountId !== null): ?>
                    <span>Override tenant: <strong>#<?php echo htmlspecialchars((string) $requestedAccountId); ?></strong></span>
                <?php elseif ($account_id !== null): ?>
                    <span>Tenant scope: <strong>#<?php echo htmlspecialchars((string) $account_id); ?></strong></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($requestedAccountId !== null): ?>
            <div class="module-alert module-alert--note">
                You’re modeling operations for tenant #<?php echo htmlspecialchars((string) $requestedAccountId); ?>. Remove the
                <code>?account=</code> parameter to fall back to your own park.
            </div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Today’s snapshot</h2>
            <p class="module-card__subtitle">Auto-generated metrics that recompute whenever sales data changes.</p>
            <div class="module-grid">
                <div class="module-figure">
                    <span class="module-figure__label">Guest count</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['guest_count']); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Weather</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['weather']); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Incoming</span>
                    <span class="module-figure__value">$<?php echo htmlspecialchars(number_format((float) $data['incoming_money'], 2)); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Outgoing</span>
                    <span class="module-figure__value">$<?php echo htmlspecialchars(number_format((float) $data['outgoing_money'], 2)); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Stock</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['stock']); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Land</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['land']); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Attractions</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['attractions']); ?></span>
                </div>
                <div class="module-figure">
                    <span class="module-figure__label">Food &amp; drink stalls</span>
                    <span class="module-figure__value"><?php echo htmlspecialchars((string) $data['stalls']); ?></span>
                </div>
            </div>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Tune the park economy</h2>
            <p class="module-card__subtitle">Invest in new assets or brute-force the revenue field to manipulate the ledger.</p>
            <form method="post" class="module-actions" style="margin-top: 0;">
                <input type="hidden" name="type" value="">
                <button class="btn btn-primary" type="submit" name="buy" value="1" onclick="this.form.type.value='land'">Buy land ($1000)</button>
                <button class="btn btn-outline" type="submit" name="buy" value="1" onclick="this.form.type.value='attraction'">Buy attraction ($500)</button>
                <button class="btn btn-outline" type="submit" name="buy" value="1" onclick="this.form.type.value='stall'">Buy food stall ($200)</button>
            </form>
            <form method="post" class="module-form" style="margin-top: 24px; max-width: 360px;">
                <label for="override_income_value" style="font-weight: 600;">Force incoming revenue ($)</label>
                <input class="input-field" type="number" step="0.01" name="override_income_value" id="override_income_value" value="<?php echo htmlspecialchars(number_format((float) $data['incoming_money'], 2, '.', '')); ?>">
                <button class="btn btn-accent" type="submit">Apply override</button>
                <p style="margin-top: 12px; font-size: 0.85rem; color: var(--text-muted);">This override bypasses validation and writes directly to the database.</p>
            </form>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
