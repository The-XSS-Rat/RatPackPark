<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'temporary_tenants.php';

session_start();

ensureTemporaryTenantSchema($pdo);
cleanupExpiredTemporaryTenants($pdo);

$sessionId = session_id();
$activeTenant = getActiveTemporaryTenant($pdo, $sessionId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTenant === null) {
    $activeTenant = createTemporaryTenant($pdo, $sessionId);
}

if ($activeTenant === null) {
    header('Location: index.php');
    exit;
}

$_SESSION['temporary_tenant_id'] = $activeTenant['id'];

$expiresAtUtc = new DateTime($activeTenant['expires_at'], new DateTimeZone('UTC'));
$localTz = new DateTimeZone(date_default_timezone_get());
$expiresAtLocal = clone $expiresAtUtc;
$expiresAtLocal->setTimezone($localTz);
$expiresAtDisplay = $expiresAtLocal->format('M j, Y g:i A T');
$nowLocal = new DateTime('now', $localTz);
$timeRemaining = '';
if ($expiresAtLocal > $nowLocal) {
    $diff = $nowLocal->diff($expiresAtLocal);
    $totalHours = ($diff->days * 24) + $diff->h;
    if ($totalHours > 0) {
        $timeRemaining = $totalHours . ' hour' . ($totalHours === 1 ? '' : 's');
        if ($diff->i > 0) {
            $timeRemaining .= ' ' . $diff->i . ' minute' . ($diff->i === 1 ? '' : 's');
        }
    } else {
        $timeRemaining = $diff->i . ' minute' . ($diff->i === 1 ? '' : 's');
    }
}

$credentialRows = [
    [
        'role' => 'Admin Ringmaster',
        'username' => $activeTenant['admin_username'],
        'password' => $activeTenant['admin_password'],
        'mission' => 'Configure settings, invite staff, and explore every dashboard to understand how the park runs end-to-end.',
    ],
    [
        'role' => 'Operations Manager',
        'username' => $activeTenant['manager_username'],
        'password' => $activeTenant['manager_password'],
        'mission' => 'Build rosters, review incident queues, and make sure each attraction is fully staffed.',
    ],
    [
        'role' => 'Ticket Seller',
        'username' => $activeTenant['seller_username'],
        'password' => $activeTenant['seller_password'],
        'mission' => 'Sell tickets, manage discounts, and keep an eye on sales analytics.',
    ],
    [
        'role' => 'Ride Operator',
        'username' => $activeTenant['operator_username'],
        'password' => $activeTenant['operator_password'],
        'mission' => 'Check personal rosters, report ride hiccups, and collaborate with maintenance.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your RatPack Park Playground</title>
    <style>
        :root {
            --park-purple: #5d2ca8;
            --park-magenta: #ff5fa2;
            --park-gold: #ffcc29;
            --park-teal: #27d8d5;
            --park-navy: #1b1155;
            --park-cream: #fff7ea;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            font-family: 'Poppins', 'Segoe UI', Tahoma, sans-serif;
            color: var(--park-navy);
            background: radial-gradient(circle at top, rgba(255, 245, 230, 0.85), rgba(241, 223, 255, 0.95)),
                linear-gradient(135deg, #1a2a6c 0%, #fdbb2d 100%);
            min-height: 100vh;
            padding: 70px 20px 120px;
        }
        .wrap {
            max-width: 960px;
            margin: 0 auto;
            background: rgba(255, 247, 234, 0.95);
            border-radius: 30px;
            padding: 56px 60px;
            box-shadow: 0 40px 90px rgba(27, 17, 85, 0.15);
            position: relative;
            overflow: hidden;
        }
        .wrap::before,
        .wrap::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.25;
        }
        .wrap::before {
            width: 320px;
            height: 320px;
            background: var(--park-magenta);
            top: -140px;
            right: -120px;
            filter: blur(1px);
        }
        .wrap::after {
            width: 220px;
            height: 220px;
            background: var(--park-teal);
            bottom: -120px;
            left: -90px;
            filter: blur(1px);
        }
        h1 {
            font-size: clamp(2.2rem, 4vw, 3rem);
            margin: 0 0 12px;
        }
        .subtitle {
            font-size: 1.05rem;
            color: rgba(27, 17, 85, 0.7);
            margin-bottom: 32px;
        }
        .highlight-box {
            background: rgba(39, 216, 213, 0.12);
            border: 1px solid rgba(39, 216, 213, 0.4);
            border-radius: 18px;
            padding: 22px 26px;
            margin-bottom: 36px;
        }
        .highlight-box strong {
            color: var(--park-purple);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            position: relative;
            z-index: 1;
        }
        thead {
            background: linear-gradient(135deg, rgba(93, 44, 168, 0.85), rgba(255, 95, 162, 0.85));
            color: white;
        }
        th, td {
            padding: 16px 18px;
            text-align: left;
        }
        tbody tr:nth-child(even) {
            background: rgba(255, 247, 234, 0.65);
        }
        tbody tr:nth-child(odd) {
            background: rgba(255, 255, 255, 0.8);
        }
        code {
            font-family: 'Fira Code', 'Courier New', monospace;
            font-size: 0.95rem;
            background: rgba(27, 17, 85, 0.08);
            padding: 2px 6px;
            border-radius: 6px;
        }
        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 22px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            border: 2px solid transparent;
            background: linear-gradient(135deg, var(--park-purple), var(--park-magenta));
            color: white;
            box-shadow: 0 16px 32px rgba(93, 44, 168, 0.22);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(93, 44, 168, 0.28);
        }
        .notes {
            margin-top: 40px;
            line-height: 1.8;
            color: rgba(27, 17, 85, 0.75);
            position: relative;
            z-index: 1;
        }
        .notes h2 {
            margin-top: 0;
        }
        ul.checklist {
            list-style: none;
            padding: 0;
            margin: 16px 0 0;
        }
        ul.checklist li {
            padding-left: 28px;
            position: relative;
            margin-bottom: 12px;
        }
        ul.checklist li::before {
            content: '✔';
            position: absolute;
            left: 0;
            color: var(--park-teal);
            font-weight: 700;
        }
        @media (max-width: 768px) {
            body {
                padding: 32px 12px 80px;
            }
            .wrap {
                padding: 42px 24px;
            }
            th, td {
                padding: 12px 14px;
                font-size: 0.95rem;
            }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>🎡 Temporary Tenant Ready!</h1>
        <p class="subtitle">Share these credentials with your testers and explore every inch of RatPack Park's control center.</p>
        <div class="highlight-box">
            <p><strong>Tenant name:</strong> <?php echo htmlspecialchars($activeTenant['account_name']); ?> (Account ID #<?php echo htmlspecialchars((string)$activeTenant['account_id']); ?>)</p>
            <p><strong>Expires:</strong> <?php echo htmlspecialchars($expiresAtDisplay); ?><?php echo $timeRemaining ? ' — about ' . htmlspecialchars($timeRemaining) . ' remaining' : ''; ?></p>
            <p>When the clock strikes zero, we automatically retire this tenant and all related data. No cleanup required.</p>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Username</th>
                    <th>Password</th>
                    <th>Mission Brief</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($credentialRows as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['role']); ?></td>
                        <td><code><?php echo htmlspecialchars($row['username']); ?></code></td>
                        <td><code><?php echo htmlspecialchars($row['password']); ?></code></td>
                        <td><?php echo htmlspecialchars($row['mission']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="notes">
            <h2>Next Steps</h2>
            <ul class="checklist">
                <li>Visit <code><?php echo htmlspecialchars((string)($_SERVER['HTTP_HOST'] ?? 'your-lab-host')); ?>/login.php</code> and sign in as the Admin Ringmaster first.</li>
                <li>Invite additional teammates or rotate through each persona using the credentials above.</li>
                <li>Experiment with rosters, ticket types, maintenance logs, and analytics dashboards to experience the full park lifecycle.</li>
                <li>Need another sandbox after this one? Return to the home page once the 12-hour timer expires and generate a fresh tenant.</li>
            </ul>
        </div>

        <div class="actions">
            <a class="btn" href="index.php" target="_self">Back to RatPack Park Home</a>
        </div>
    </div>
</body>
</html>
