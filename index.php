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

$pageTitle = 'RatPack Park • Theme Park Ops Platform';
$activePage = 'home';
include 'partials/header.php';
?>
<section class="section section--hero">
    <div class="section__inner">
        <div class="hero-card">
            <span class="hero-badge">The park ops control center</span>
            <h1 class="hero-title">Delight guests. Empower staff. Keep every ride humming.</h1>
            <p class="hero-lead">
                RatPack Park is your command deck for running a modern theme park &mdash; orchestrate staffing, ticketing, maintenance,
                and guest experience from one vibrant, connected platform.
            </p>
            <div class="hero-actions">
                <a class="btn btn-primary" href="register.php">Start free trial</a>
                <a class="btn btn-outline" href="login.php">Sign in</a>
                <a class="btn btn-accent" href="dashboard.php">View dashboard</a>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">4.9/5</div>
                    <div class="stat-label">Admin satisfaction</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">12k+</div>
                    <div class="stat-label">Daily tickets processed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">24/7</div>
                    <div class="stat-label">Maintenance monitoring</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="section">
    <div class="section__inner">
        <h2 class="section-title">Why teams choose RatPack Park</h2>
        <div class="feature-grid">
            <article class="feature-card">
                <h3>Unified operations view</h3>
                <p>From ride uptime to staff check-ins, visualize every moving part of your park with live dashboards and automated alerts.</p>
            </article>
            <article class="feature-card">
                <h3>Staffing that scales</h3>
                <p>Coordinate shifts, broadcast roster updates, and empower crews with mobile-friendly workflows that keep everyone in sync.</p>
            </article>
            <article class="feature-card">
                <h3>Guest-first ticketing</h3>
                <p>Launch seasonal passes, flash promos, and VIP experiences while tracking revenue in real time from the same command center.</p>
            </article>
            <article class="feature-card">
                <h3>Fast issue resolution</h3>
                <p>Flag maintenance concerns, escalate incidents, and keep attractions open with collaborative tools your teams actually enjoy using.</p>
            </article>
        </div>
    </div>
</section>

<section class="section">
    <div class="section__inner">
        <h2 class="section-title">Try RatPack Park in your browser</h2>
        <p class="hero-lead" style="margin-bottom: 24px;">
            Launch a temporary sandbox tenant in seconds. Experiment with real workflows before inviting the rest of your crew.
        </p>
        <form class="hero-actions" method="POST" action="temporary_tenants.php">
            <button class="btn btn-primary" type="submit" name="create_tenant">Create trial tenant</button>
            <a class="btn btn-outline" href="login.php">Use existing account</a>
        </form>
        <?php if ($activeTenant !== null): ?>
            <div class="tenant-card">
                <h3>Temporary tenant active</h3>
                <p><strong>Tenant ID:</strong> <?php echo htmlspecialchars($activeTenant['id']); ?></p>
                <?php if ($expiresAtDisplay !== null): ?>
                    <p><strong>Expires:</strong> <?php echo htmlspecialchars($expiresAtDisplay); ?></p>
                <?php endif; ?>
                <?php if ($timeRemaining !== null): ?>
                    <p><strong>Time remaining:</strong> <?php echo htmlspecialchars($timeRemaining); ?></p>
                <?php endif; ?>
                <p class="tenant-hint">Need more time? Spin up a fresh tenant any time &mdash; your current session will automatically update.</p>
            </div>
        <?php else: ?>
            <p class="tenant-hint">We’ll provision a new tenant database that expires after one hour. Perfect for demos and experimentation.</p>
        <?php endif; ?>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
