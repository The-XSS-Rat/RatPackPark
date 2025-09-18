<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'temporary_tenants.php';

session_start();

ensureTemporaryTenantSchema($pdo);
cleanupExpiredTemporaryTenants($pdo);

$sessionId = session_id();
$activeTenant = getActiveTemporaryTenant($pdo, $sessionId);
if ($activeTenant !== null) {
    $_SESSION['temporary_tenant_id'] = $activeTenant['id'];
}

$expiresAtDisplay = null;
$timeRemaining = null;
if ($activeTenant !== null) {
    $expiresAt = new DateTime($activeTenant['expires_at'], new DateTimeZone('UTC'));
    $localTz = new DateTimeZone(date_default_timezone_get());
    $expiresAt->setTimezone($localTz);
    $expiresAtDisplay = $expiresAt->format('M j, Y g:i A T');

    $nowLocal = new DateTime('now', $localTz);
    if ($expiresAt > $nowLocal) {
        $diff = $nowLocal->diff($expiresAt);
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
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>RatPack Park Management</title>
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
        }
        .aurora {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 20% 20%, rgba(255, 111, 162, 0.35), transparent 55%),
                        radial-gradient(circle at 80% 15%, rgba(39, 216, 213, 0.35), transparent 50%),
                        radial-gradient(circle at 50% 80%, rgba(255, 204, 41, 0.25), transparent 60%);
            z-index: 0;
            pointer-events: none;
        }
        .landing {
            position: relative;
            z-index: 1;
            max-width: 1100px;
            margin: 0 auto;
            padding: 80px 24px 120px;
        }
        .hero {
            background: rgba(255, 247, 234, 0.92);
            border-radius: 28px;
            padding: 56px 60px;
            box-shadow: 0 30px 80px rgba(29, 17, 85, 0.15);
            position: relative;
            overflow: hidden;
        }
        .hero::before,
        .hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.35;
            filter: blur(0.5px);
        }
        .hero::before {
            width: 260px;
            height: 260px;
            background: var(--park-magenta);
            top: -120px;
            right: -80px;
        }
        .hero::after {
            width: 180px;
            height: 180px;
            background: var(--park-teal);
            bottom: -80px;
            left: -60px;
        }
        .hero__badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(93, 44, 168, 0.12);
            color: var(--park-purple);
            padding: 8px 18px;
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        h1 {
            font-size: clamp(2.4rem, 5vw, 3.4rem);
            margin: 20px 0 12px;
            line-height: 1.1;
        }
        .hero__lead {
            font-size: 1.1rem;
            max-width: 640px;
            line-height: 1.7;
            color: rgba(27, 17, 85, 0.75);
        }
        .hero__actions {
            margin: 36px 0 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 26px;
            border-radius: 999px;
            font-weight: 600;
            text-decoration: none;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.3s ease;
        }
        .btn:hover:not([disabled]) {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(93, 44, 168, 0.2);
        }
        .btn:disabled {
            cursor: not-allowed;
            opacity: 0.6;
            box-shadow: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--park-purple), var(--park-magenta));
            color: white;
        }
        .btn-outline {
            border-color: rgba(93, 44, 168, 0.35);
            color: var(--park-purple);
            background: white;
        }
        .btn-outline:hover:not([disabled]) {
            background: rgba(93, 44, 168, 0.08);
        }
        .btn-accent {
            background: linear-gradient(135deg, var(--park-gold), var(--park-magenta));
            color: var(--park-navy);
        }
        .tenant-form {
            margin-top: 10px;
        }
        .tenant-card {
            margin-top: 28px;
            padding: 22px 24px;
            border-radius: 18px;
            background: rgba(39, 216, 213, 0.12);
            border: 1px solid rgba(39, 216, 213, 0.35);
        }
        .tenant-card h3 {
            margin-top: 0;
            margin-bottom: 8px;
        }
        .tenant-hint {
            margin-top: 16px;
            color: rgba(27, 17, 85, 0.65);
            font-size: 0.95rem;
        }
        .feature-grid {
            margin-top: 70px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 28px;
        }
        .feature-card {
            background: rgba(255, 247, 234, 0.88);
            border-radius: 20px;
            padding: 28px;
            border: 1px solid rgba(93, 44, 168, 0.12);
            box-shadow: 0 20px 40px rgba(27, 17, 85, 0.08);
        }
        .feature-card h3 {
            margin-top: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .guide {
            margin-top: 80px;
            background: white;
            padding: 40px 44px;
            border-radius: 24px;
            box-shadow: 0 24px 60px rgba(27, 17, 85, 0.12);
            border: 1px solid rgba(93, 44, 168, 0.1);
        }
        .guide h2 {
            margin-top: 0;
        }
        .guide ol {
            margin: 18px 0 0;
            padding-left: 22px;
            line-height: 1.8;
        }
        @media (max-width: 768px) {
            .landing {
                padding: 60px 16px 100px;
            }
            .hero {
                padding: 46px 32px;
            }
            .hero__actions {
                flex-direction: column;
                align-items: stretch;
            }
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="aurora"></div>
    <main class="landing">
        <section class="hero">
            <span class="hero__badge">RatPack Park Platform</span>
            <h1>Design, staff, and thrill your park from a single control center.</h1>
            <p class="hero__lead">Our whimsical dashboards keep your crews choreographed, your rides humming, and your guests talking about the magic long after the gates close.</p>
            <div class="hero__actions">
                <a class="btn btn-primary" href="register.php">Start Free Trial</a>
                <a class="btn btn-outline" href="login.php">Already a Member? Log In</a>
            </div>
            <form class="tenant-form" method="POST" action="generate_tenant.php" target="_blank">
                <button type="submit" class="btn btn-accent" <?php echo $activeTenant ? 'disabled' : ''; ?>>Generate a temporary tenant</button>
            </form>
            <?php if ($activeTenant): ?>
                <div class="tenant-card">
                    <h3>🎟️ You're already running a temporary tenant!</h3>
                    <p>Your playground <strong><?php echo htmlspecialchars($activeTenant['account_name']); ?></strong> will pack up on <strong><?php echo htmlspecialchars($expiresAtDisplay ?? ''); ?></strong><?php echo $timeRemaining ? ' (in ~' . htmlspecialchars($timeRemaining) . ')' : ''; ?>.</p>
                    <a class="btn btn-outline" href="generate_tenant.php" target="_blank" rel="noopener">Open your credential guide</a>
                </div>
            <?php else: ?>
                <p class="tenant-hint">Need a zero-setup playground? Spin up a temporary tenant and receive four themed logins to explore every dashboard. It self-destructs after 12 hours, leaving no cleanup behind.</p>
            <?php endif; ?>
        </section>

        <section class="feature-grid">
            <article class="feature-card">
                <h3>🎢 Ride-Ready Scheduling</h3>
                <p>Coordinate attractions, vendors, and maintenance crews with colorful roster tools built for high-energy parks.</p>
            </article>
            <article class="feature-card">
                <h3>💡 Live Operations Pulse</h3>
                <p>Track ticket sales, revenue surges, and surprise downtime so you can dispatch teams before the queue gets restless.</p>
            </article>
            <article class="feature-card">
                <h3>🛠️ Rapid Incident Response</h3>
                <p>From sticky turnstiles to stargazing events, capture and triage every report with an audit trail your managers will love.</p>
            </article>
            <article class="feature-card">
                <h3>🎯 Role-Based Control</h3>
                <p>Admins, managers, sellers, and operators each get curated dashboards that make their responsibilities shine.</p>
            </article>
        </section>

        <section class="guide">
            <h2>Map Out Your Park Adventure</h2>
            <ol>
                <li><strong>Launch your tenant:</strong> Register or generate a temporary playground to receive admin credentials instantly.</li>
                <li><strong>Cast your crew:</strong> Invite staff in User Management, assign roles, and place them on rosters that match ride hours.</li>
                <li><strong>Keep the magic alive:</strong> Sell tickets, monitor daily operations, and escalate issues before guests notice.</li>
                <li><strong>Study the thrill factor:</strong> Dive into Analytics and Rat Track to pinpoint bottlenecks and celebrate wins.</li>
            </ol>
        </section>
    </main>
    <?php include 'partials/footer.php'; ?>
