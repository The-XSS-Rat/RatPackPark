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

$stmt = $pdo->prepare("SELECT * FROM daily_operations WHERE user_id = ? AND operation_date = ?");
$stmt->execute([$user_id, $operation_date]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    $stmt = $pdo->prepare("SELECT stock FROM daily_operations WHERE user_id = ? ORDER BY operation_date DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $prev_stock = $stmt->fetchColumn();
    if ($prev_stock === false) {
        $prev_stock = 100;
    }

    $guest_count = random_int(50, 200);
    $weather_options = ['Sunny','Cloudy','Rainy','Stormy','Windy','Snowy'];
    $weather = $weather_options[array_rand($weather_options)];
    $incoming_money = $guest_count * random_int(20, 50);
    $outgoing_money = random_int(100, 500);
    $stock_change = random_int(-10, 10);
    $stock = max(0, $prev_stock + $stock_change);

    $stmt = $pdo->prepare("INSERT INTO daily_operations (user_id, operation_date, guest_count, weather, incoming_money, outgoing_money, stock) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$user_id, $operation_date, $guest_count, $weather, $incoming_money, $outgoing_money, $stock]);

    $data = [
        'guest_count' => $guest_count,
        'weather' => $weather,
        'incoming_money' => $incoming_money,
        'outgoing_money' => $outgoing_money,
        'stock' => $stock
    ];
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
</ul>
</body>
</html>
