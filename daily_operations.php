<?php
require 'vendor/autoload.php';
require 'db.php';
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

function getSalesData($pdo, $date) {
    $stmt = $pdo->prepare("SELECT SUM(s.quantity) AS guests, SUM(s.quantity * t.price) AS revenue FROM sales s JOIN tickets t ON s.ticket_id = t.id WHERE DATE(s.sale_date) = ?");
    $stmt->execute([$date]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return ['guests' => 0, 'revenue' => 0];
    }
    return ['guests' => $row['guests'] ?? 0, 'revenue' => $row['revenue'] ?? 0];
}

$sales = getSalesData($pdo, $operation_date);
$guest_count = $sales['guests'];
$base_revenue = $sales['revenue'];

$stmt = $pdo->prepare("SELECT * FROM daily_operations WHERE user_id = ? AND operation_date = ?");
$stmt->execute([$user_id, $operation_date]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    $stmt = $pdo->prepare("SELECT stock, land, attractions, stalls FROM daily_operations WHERE user_id = ? ORDER BY operation_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $prev = $stmt->fetch(PDO::FETCH_ASSOC);
    $prev_stock = $prev['stock'] ?? 100;
    $land = $prev['land'] ?? 0;
    $attractions = $prev['attractions'] ?? 0;
    $stalls = $prev['stalls'] ?? 0;

    $weather_options = ['Sunny','Cloudy','Rainy','Stormy','Windy','Snowy'];
    $weather = $weather_options[array_rand($weather_options)];
    $stock_change = random_int(-10, 10);
    $stock = max(0, $prev_stock + $stock_change);
    $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);
    $outgoing_money = random_int(100, 500) + ($land * 20) + ($attractions * 15) + ($stalls * 10);

    $stmt = $pdo->prepare("INSERT INTO daily_operations (user_id, operation_date, guest_count, weather, incoming_money, outgoing_money, stock, land, attractions, stalls) VALUES (?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([$user_id, $operation_date, $guest_count, $weather, $incoming_money, $outgoing_money, $stock, $land, $attractions, $stalls]);

    $data = [
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
    $land = $data['land'];
    $attractions = $data['attractions'];
    $stalls = $data['stalls'];
    $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);
    $stmt = $pdo->prepare("UPDATE daily_operations SET guest_count=?, incoming_money=? WHERE id=?");
    $stmt->execute([$guest_count, $incoming_money, $data['id']]);
    $data['guest_count'] = $guest_count;
    $data['incoming_money'] = $incoming_money;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buy'], $_POST['type'])) {
    $costs = ['land' => 1000, 'attraction' => 500, 'stall' => 200];
    $type = $_POST['type'];
    if (isset($costs[$type])) {
        if ($type === 'land') { $land++; }
        if ($type === 'attraction') { $attractions++; }
        if ($type === 'stall') { $stalls++; }
        $outgoing_money = $data['outgoing_money'] + $costs[$type];
        $incoming_money = $base_revenue + ($land * 100) + ($attractions * 75) + ($stalls * 50);
        $stmt = $pdo->prepare("UPDATE daily_operations SET land=?, attractions=?, stalls=?, outgoing_money=?, incoming_money=? WHERE id=?");
        $stmt->execute([$land, $attractions, $stalls, $outgoing_money, $incoming_money, $data['id']]);
        header("Location: daily_operations.php?date=" . urlencode($operation_date));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Operations</title>
</head>
<body>
<h2>Daily Operations for <?php echo htmlspecialchars($operation_date); ?></h2>
<ul>
    <li>Guest count: <?php echo htmlspecialchars($data['guest_count']); ?></li>
    <li>Weather: <?php echo htmlspecialchars($data['weather']); ?></li>
    <li>Incoming money: $<?php echo htmlspecialchars(number_format($data['incoming_money'], 2)); ?></li>
    <li>Outgoing money: $<?php echo htmlspecialchars(number_format($data['outgoing_money'], 2)); ?></li>
    <li>Stock: <?php echo htmlspecialchars($data['stock']); ?></li>
    <li>Land: <?php echo htmlspecialchars($data['land']); ?></li>
    <li>Attractions: <?php echo htmlspecialchars($data['attractions']); ?></li>
    <li>Food/Drink Stalls: <?php echo htmlspecialchars($data['stalls']); ?></li>
</ul>
<form method="post">
    <button type="submit" name="buy" value="1" onclick="this.form.type.value='land'">Buy Land ($1000)</button>
    <button type="submit" name="buy" value="1" onclick="this.form.type.value='attraction'">Buy Attraction ($500)</button>
    <button type="submit" name="buy" value="1" onclick="this.form.type.value='stall'">Buy Food/Drink Stall ($200)</button>
    <input type="hidden" name="type" value="">
</form>
</body>
</html>
