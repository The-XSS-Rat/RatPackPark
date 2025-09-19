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
];
?>
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
                Every issue we’ve shipped for the lab—complete with exploitation notes—lives here for quick reference.
            </p>
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
                                <div><?php echo $entry['details']; ?></div>
                                <div style="margin-top: 6px;"><strong>How to exploit:</strong> <?php echo $entry['exploit']; ?></div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
