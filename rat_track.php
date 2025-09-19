<?php
require 'vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

session_start();
$jwt_secret = 'your-secret-key';

if (!isset($_SESSION['jwt'])) {
    http_response_code(401);
    echo 'Not authenticated';
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    http_response_code(401);
    echo 'Invalid session.';
    exit;
}

if (!in_array('logout', $rights)) {
    echo 'Access denied.';
    exit;
}

if (!function_exists('rat_track_format_interval')) {
    function rat_track_format_interval(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;
        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $remaining);
        }

        return sprintf('%02d:%02d', $minutes, $remaining);
    }
}

$speedrunUnlocks = [
    'BAC' => null,
    'IDOR' => null,
];

if (
    isset($_SESSION['temporary_tenant_id'], $_SESSION['rat_speedrun']) &&
    is_array($_SESSION['rat_speedrun'])
) {
    $tenantId = (int)$_SESSION['temporary_tenant_id'];
    if (
        isset($_SESSION['rat_speedrun'][$tenantId]) &&
        is_array($_SESSION['rat_speedrun'][$tenantId]) &&
        !empty($_SESSION['rat_speedrun'][$tenantId]['categories']) &&
        is_array($_SESSION['rat_speedrun'][$tenantId]['categories'])
    ) {
        foreach (['BAC', 'IDOR'] as $categoryKey) {
            if (isset($_SESSION['rat_speedrun'][$tenantId]['categories'][$categoryKey]['elapsed'])) {
                $speedrunUnlocks[$categoryKey] = (int)$_SESSION['rat_speedrun'][$tenantId]['categories'][$categoryKey]['elapsed'];
            }
        }
    }
}

$vulnerabilities = [
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'rosters.php',
        'title' => 'Shift deletion lacks tenant scoping',
        'details' => 'The delete handler issues <code>DELETE FROM shifts WHERE id = ?</code> without confirming the shift belongs to the current account.',
        'exploit' => 'Send <code>rosters.php?delete=&lt;target_shift_id&gt;</code> to remove schedules that belong to other tenants.'
    ],
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'rosters.php',
        'title' => 'Shift edits trust arbitrary IDs',
        'details' => 'Update requests run <code>UPDATE shifts SET ... WHERE id = ?</code> without validating account ownership.',
        'exploit' => 'Submit the edit form with a forged <code>edit_shift_id</code> to overwrite another tenant’s roster entry.'
    ],
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'rosters.php',
        'title' => 'Shift creation accepts foreign user IDs',
        'details' => 'The create handler inserts whatever <code>user_id</code> is supplied, even if it points to a user from another account.',
        'exploit' => 'Post a crafted <code>create_shift</code> request with a victim account user ID to assign them bogus duties.'
    ],
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'special_discounts.php',
        'title' => 'Discount creation is missing tenant checks',
        'details' => 'Discounts are inserted via <code>INSERT INTO ticket_discounts (ticket_id,...)</code> without verifying that the ticket belongs to the active tenant.',
        'exploit' => 'Craft a <code>create_discount</code> POST pointing at another tenant’s <code>ticket_id</code> to force their discounts.'
    ],
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'special_discounts.php',
        'title' => 'Discount deletion trusts the id parameter',
        'details' => 'The delete endpoint executes <code>DELETE FROM ticket_discounts WHERE id = ?</code> with no tenant scoping.',
        'exploit' => 'Calling <code>special_discounts.php?delete=&lt;victim_discount_id&gt;</code> removes offers from other tenants.'
    ],
    [
        'category' => 'IDOR, Inter-tenant IDOR',
        'endpoint' => 'tickets.php',
        'title' => 'Ticket purchases are not account scoped',
        'details' => 'Ticket sales subtract stock using <code>UPDATE tickets SET available_quantity = available_quantity - ? WHERE id = ?</code> without checking <code>account_id</code>.',
        'exploit' => 'Forge a <code>buy_ticket</code> POST that references a different tenant’s ticket ID to drain or replenish their inventory.'
    ],
    [
        'category' => 'BAC',
        'endpoint' => 'tickets.php',
        'title' => 'Negative quantities boost inventory',
        'details' => 'No server-side validation prevents negative quantities, so the subtraction query increases stock and still logs a sale.',
        'exploit' => 'Submit <code>quantity=-10</code> in the buy flow to add tickets back into inventory illegitimately.'
    ],
    [
        'category' => 'BAC',
        'endpoint' => 'daily_operations.php',
        'title' => 'Revenue totals leak multi-tenant data',
        'details' => '<code>getSalesData()</code> sums sales joined to tickets without filtering by account, so other tenants’ revenue is revealed.',
        'exploit' => 'View any day and the dashboard includes sales from every tenant in the shared database.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'tickets.php',
        'title' => 'Ticket inspection reveals competitors',
        'details' => 'The optional <code>?inspect=</code> helper pulls ticket metadata solely by primary key with no account filter.',
        'exploit' => 'Browse to <code>tickets.php?inspect=&lt;victim_ticket_id&gt;</code> to read names, prices and stock for another tenant.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'problem.php',
        'title' => 'Incident viewer discloses foreign reports',
        'details' => 'Supplying <code>?view=</code> fetches any <code>problem_reports</code> row without checking <code>account_id</code> or reporter.',
        'exploit' => 'Call <code>problem.php?view=&lt;target_report_id&gt;</code> to see descriptions and attachments from other tenants.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'admin_problem.php',
        'title' => 'Problem note audit lacks tenant scoping',
        'details' => 'The <code>?notes=</code> audit feed joins into <code>problem_notes</code> by id alone, exposing other teams’ maintenance discussion.',
        'exploit' => 'Load <code>admin_problem.php?notes=&lt;target_problem_id&gt;</code> to enumerate remediation notes outside your estate.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'analytics.php',
        'title' => 'Analytics trust caller-supplied account IDs',
        'details' => 'When <code>?account=</code> is present the query swaps in that value, returning KPI counts for whichever tenant ID you provide.',
        'exploit' => 'Abuse <code>analytics.php?account=1</code> from another tenant to read Fantasy Kingdom’s user and ticket totals.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'daily_operations.php',
        'title' => 'Daily operations expose cross-tenant revenue filters',
        'details' => 'Adding <code>?account=</code> selects a tenant id for revenue math, letting you peek at any park’s guest and income totals.',
        'exploit' => 'Request <code>daily_operations.php?date=&lt;day&gt;&amp;account=&lt;victim_id&gt;</code> to pivot the dashboard to another tenant.'
    ],
    [
        'category' => 'BAC',
        'endpoint' => 'daily_operations.php',
        'title' => 'Income override endpoint grants unlimited cash',
        'details' => 'The hidden override form updates <code>incoming_money</code> with whatever value is submitted—no role, limit, or integrity checks.',
        'exploit' => 'POST <code>override_income_value=999999</code> to <code>daily_operations.php</code> to inflate today’s revenue instantly.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'tickets.php',
        'title' => 'Raw sales audit spills global transactions',
        'details' => 'Enabling <code>?sales_audit=1</code> dumps every <code>sales</code> record and joins tickets without verifying <code>account_id</code>.',
        'exploit' => 'Use <code>tickets.php?sales_audit=1</code> to enumerate all buyer activity across tenants.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'guest_feedback.php',
        'title' => 'Account override reveals rival grievances',
        'details' => 'Setting <code>?account=</code> rewrites the feedback tenant scope but results render without checking that you belong to that account.',
        'exploit' => 'Browse to <code>guest_feedback.php?account=2</code> to read another tenant’s complaints and categories.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'guest_feedback.php',
        'title' => 'Status editor mutates foreign reports',
        'details' => 'The status form updates <code>problem_reports</code> rows solely by id, so you can invent any status string and apply it to other tenants’ incidents.',
        'exploit' => 'Open a report and submit <code>new_status=resolved</code> to silently close a rival tenant’s escalation.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'guest_feedback.php',
        'title' => 'View parameter discloses outside incidents',
        'details' => 'Supplying <code>?view=</code> pulls the requested report with no account check, echoing descriptions, submitter names, and status.',
        'exploit' => 'Call <code>guest_feedback.php?view=1</code> to inspect an issue filed by a different tenant.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'tenant_directory.php',
        'title' => 'Directory all switch dumps global staff',
        'details' => 'Passing <code>&all=1</code> strips away the tenant filter and returns every user record platform-wide.',
        'exploit' => 'Hit <code>tenant_directory.php?all=1</code> to enumerate usernames and emails for all tenants.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'tenant_directory.php',
        'title' => 'Account override enumerates rival teams',
        'details' => 'The <code>?account=</code> parameter is trusted blindly, enabling you to pivot the roster view to any tenant id.',
        'exploit' => 'Load <code>tenant_directory.php?account=3</code> to review staffing data for another park.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'tenant_directory.php',
        'title' => 'Role assignment trusts caller supplied IDs',
        'details' => 'Posting the inline form runs <code>UPDATE users SET role_id = ? WHERE id = ?</code> with whatever values you provide, even if the target belongs to another tenant.',
        'exploit' => 'Change the selector to role ID 1 and submit to grant admin rights to someone in a rival tenant.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'ledger_insights.php',
        'title' => 'Tenant filter exposes foreign revenue',
        'details' => 'When <code>?tenant=</code> is present the revenue query simply swaps account ids, displaying sales totals for whichever tenant you request.',
        'exploit' => 'Request <code>ledger_insights.php?tenant=1</code> to see Fantasy Kingdom’s revenue metrics.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'ledger_insights.php',
        'title' => 'Raw flag dumps the global sales ledger',
        'details' => 'Appending <code>&raw=1</code> executes an unrestricted query that lists the latest 50 sales for every tenant.',
        'exploit' => 'Use <code>ledger_insights.php?raw=1</code> to capture ticket, user, and quantity data across the platform.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'ledger_insights.php',
        'title' => 'Manual injector forges cross-tenant sales',
        'details' => 'The injection form inserts directly into <code>sales</code> using the ticket id and quantity you supply—negative numbers roll back stock and foreign ticket ids tamper with rival ledgers.',
        'exploit' => 'Submit <code>ticket_id=&lt;victim&gt;</code> and <code>quantity=-50</code> to rewrite another tenant’s revenue history.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'ride_scheduler.php',
        'title' => 'Shift list shows every tenant’s coverage',
        'details' => 'The scheduler query never scopes by account, so anyone with roster access sees staffing rows for all tenants.',
        'exploit' => 'Simply open <code>ride_scheduler.php</code> to review rival parks’ shift plans.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'ride_scheduler.php',
        'title' => 'Shift inspector leaks foreign schedules',
        'details' => '<code>?shift=</code> lookups fetch shift metadata regardless of tenant ownership, revealing operator names and timing.',
        'exploit' => 'Call <code>ride_scheduler.php?shift=2</code> to pull another tenant’s roster details.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'ride_scheduler.php',
        'title' => 'Reschedule form rewrites other tenants’ shifts',
        'details' => 'Submitting the reschedule form updates <code>shifts</code> by id with no validation, letting you move staff assigned to another tenant.',
        'exploit' => 'Post new dates for a rival’s <code>shift_id</code> to sabotage their coverage window.'
    ],
    [
        'category' => 'IDOR',
        'endpoint' => 'promo_engine.php',
        'title' => 'Account override hijacks competitor catalog',
        'details' => 'Passing <code>?account=</code> switches the ticket listing to whichever tenant you target and the UI happily renders their pricing.',
        'exploit' => 'Visit <code>promo_engine.php?account=1</code> to inspect another park’s ticket lineup.'
    ],
    [
        'category' => 'BAC',
        'endpoint' => 'promo_engine.php',
        'title' => 'Flash sale override allows negative stock',
        'details' => 'The flash sale handler updates price and inventory blindly, so negative deltas or zero pricing are accepted without constraint.',
        'exploit' => 'Submit <code>inventory_delta=-999</code> to restock or drain a ticket instantly.'
    ],
    [
        'category' => 'BAC, IDOR',
        'endpoint' => 'promo_engine.php',
        'title' => 'Discount seeder plants sales for any tenant',
        'details' => 'Posting <code>seed_discount</code> inserts a discount for whichever <code>ticket_id</code> you choose and accepts any percentage, regardless of ownership.',
        'exploit' => 'Send <code>percent_off=95</code> with a competitor’s <code>discount_ticket_id</code> to torpedo their prices.'
    ],
];
?>
<?php
$pageTitle = 'Rat Track • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Vulnerability radar</span>
            <h1 class="hero-title">Documented weaknesses hiding in plain sight</h1>
            <p class="hero-lead">
                Race the clock to unlock the full BAC &amp; IDOR playbook for this tenant. Your scoreboard timer started when the
                sandbox spun up.
            </p>
        </div>

        <div class="module-card module-card--speedrun">
            <h2 class="module-card__title">Speedrun progress</h2>
            <p class="module-card__subtitle">Trigger each class of bug in your tenant to unmask the redacted guidance.</p>
            <ul class="speedrun-status">
                <li class="<?php echo $speedrunUnlocks['IDOR'] !== null ? 'speedrun-status--unlocked' : 'speedrun-status--locked'; ?>">
                    <span>IDOR intel</span>
                    <span>
                        <?php if ($speedrunUnlocks['IDOR'] !== null): ?>
                            T+<?php echo htmlspecialchars(rat_track_format_interval($speedrunUnlocks['IDOR'])); ?>
                        <?php else: ?>
                            Locked
                        <?php endif; ?>
                    </span>
                </li>
                <li class="<?php echo $speedrunUnlocks['BAC'] !== null ? 'speedrun-status--unlocked' : 'speedrun-status--locked'; ?>">
                    <span>BAC intel</span>
                    <span>
                        <?php if ($speedrunUnlocks['BAC'] !== null): ?>
                            T+<?php echo htmlspecialchars(rat_track_format_interval($speedrunUnlocks['BAC'])); ?>
                        <?php else: ?>
                            Locked
                        <?php endif; ?>
                    </span>
                </li>
            </ul>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Known issues</h2>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Endpoint</th>
                        <th>Issue</th>
                        <th>Details &amp; exploitation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vulnerabilities as $entry): ?>
                        <?php
                        $categoryString = $entry['category'];
                        $requiresBac = stripos($categoryString, 'BAC') !== false;
                        $requiresIdor = stripos($categoryString, 'IDOR') !== false;
                        $locks = [];
                        if ($requiresBac && $speedrunUnlocks['BAC'] === null) {
                            $locks[] = 'Trigger a BAC exploit to reveal this walkthrough.';
                        }
                        if ($requiresIdor && $speedrunUnlocks['IDOR'] === null) {
                            $locks[] = 'Trigger an IDOR exploit to reveal this walkthrough.';
                        }
                        $isLocked = !empty($locks);
                        $unlockTimes = [];
                        if ($requiresIdor && $speedrunUnlocks['IDOR'] !== null) {
                            $unlockTimes[] = 'IDOR T+' . rat_track_format_interval($speedrunUnlocks['IDOR']);
                        }
                        if ($requiresBac && $speedrunUnlocks['BAC'] !== null) {
                            $unlockTimes[] = 'BAC T+' . rat_track_format_interval($speedrunUnlocks['BAC']);
                        }
                        ?>
                        <tr>
                            <td>
                                <?php foreach (explode(',', $entry['category']) as $tag): ?>
                                    <span class="module-pill"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td>
                                <a class="module-link" href="<?php echo htmlspecialchars($entry['endpoint']); ?>" target="mainframe"><?php echo htmlspecialchars($entry['endpoint']); ?></a>
                            </td>
                            <td><?php echo htmlspecialchars($entry['title']); ?></td>
                            <td>
                                <div class="guide-details<?php echo $isLocked ? ' guide-details--locked' : ''; ?>">
                                    <div class="guide-details__content">
                                        <div><?php echo $entry['details']; ?></div>
                                        <div style="margin-top: 6px;"><strong>How to exploit:</strong> <?php echo $entry['exploit']; ?></div>
                                        <?php if (!$isLocked && !empty($unlockTimes)): ?>
                                            <div class="guide-details__time">Unlocked at <?php echo htmlspecialchars(implode(' • ', $unlockTimes)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isLocked): ?>
                                        <div class="guide-details__mask">
                                            <strong>Intel locked</strong>
                                            <?php foreach ($locks as $lockMessage): ?>
                                                <p><?php echo htmlspecialchars($lockMessage); ?></p>
                                            <?php endforeach; ?>
                                            <p class="guide-details__mask-hint">Speedrun the vulnerability inside this tenant to lift the veil.</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
