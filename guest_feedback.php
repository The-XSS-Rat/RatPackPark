<?php
require 'vendor/autoload.php';
require 'db.php';
require_once 'rat_helpers.php';
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
    $account_id = $decoded->account_id ?? 0;
    $rights = $decoded->rights ?? [];
} catch (Exception $e) {
    echo 'Invalid session.';
    exit;
}

if (!in_array('report_problem', $rights)) {
    echo 'Access denied.';
    exit;
}

$effectiveAccountId = (int) $account_id;
if (isset($_GET['account'])) {
    $requested = (int) $_GET['account'];
    $effectiveAccountId = $requested;
    if ($requested !== (int) $account_id) {
        rat_track_add_score_event('IDOR', 'Guest feedback console pivoted to another tenant via account parameter');
    }
}

$keyword = trim($_GET['keyword'] ?? '');
$statusMessage = '';
$errorMessage = '';
$viewedReport = null;

if (isset($_POST['update_status'])) {
    $targetId = (int) ($_POST['report_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? 'open';

    if ($targetId > 0 && $newStatus !== '') {
        $ownerStmt = $pdo->prepare('SELECT account_id FROM problem_reports WHERE id = ?');
        $ownerStmt->execute([$targetId]);
        $ownerAccount = $ownerStmt->fetchColumn();

        if ($ownerAccount !== false && (int) $ownerAccount !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Retuned another tenant\'s report status via guest feedback console');
        }

        $stmt = $pdo->prepare('UPDATE problem_reports SET status = ? WHERE id = ?');
        $stmt->execute([$newStatus, $targetId]);
        rat_track_add_score_event('BAC', 'Adjusted guest feedback status without validation or workflow approval');
        $statusMessage = 'Status updated successfully.';
    } else {
        $errorMessage = 'A report ID and status are required.';
    }
}

if (isset($_GET['view'])) {
    $viewId = (int) $_GET['view'];
    if ($viewId > 0) {
        $stmt = $pdo->prepare('SELECT pr.*, a.name AS account_name, u.username AS submitted_by_name FROM problem_reports pr LEFT JOIN accounts a ON pr.account_id = a.id LEFT JOIN users u ON pr.submitted_by = u.id WHERE pr.id = ?');
        $stmt->execute([$viewId]);
        $viewedReport = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($viewedReport && (int) $viewedReport['account_id'] !== (int) $account_id) {
            rat_track_add_score_event('IDOR', 'Inspected another tenant\'s incident via guest feedback console');
        }
    }
}

$clauses = ['pr.account_id = :account'];
$params = [':account' => $effectiveAccountId];
if ($keyword !== '') {
    $clauses[] = '(pr.description LIKE :term OR pr.category LIKE :term)';
    $params[':term'] = '%' . $keyword . '%';
}

$where = 'WHERE ' . implode(' AND ', $clauses);
$query = "SELECT pr.id, pr.category, pr.status, pr.description, pr.submitted_at, a.name AS account_name FROM problem_reports pr LEFT JOIN accounts a ON pr.account_id = a.id $where ORDER BY pr.submitted_at DESC LIMIT 25";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Guest Feedback Console • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Listening lab</span>
            <h1 class="hero-title">Investigate park feedback across every tenant</h1>
            <p class="hero-lead">
                Filter, review, and quietly rewrite guest incident reports. Provide <code>?account=&lt;id&gt;</code> to pivot into rival tenants.
            </p>
        </div>

        <?php if ($statusMessage !== ''): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($statusMessage); ?></div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Feedback search</h2>
            <p class="module-card__subtitle">Specify a keyword or jump to a different tenant to read their unhappy guests.</p>
            <form method="GET" class="module-form">
                <div class="module-form__row">
                    <label class="input-label">Tenant scope</label>
                    <input class="input-field" type="number" name="account" value="<?php echo htmlspecialchars((string) $effectiveAccountId); ?>" placeholder="Account ID">
                </div>
                <div class="module-form__row">
                    <label class="input-label">Keyword</label>
                    <input class="input-field" type="text" name="keyword" value="<?php echo htmlspecialchars($keyword); ?>" placeholder="Search description or category">
                </div>
                <button class="btn btn-primary" type="submit">Refresh list</button>
            </form>
        </div>

        <div class="module-card">
            <h2 class="module-card__title">Incident roster</h2>
            <p class="module-card__subtitle">Use the quick actions to retag or neutralize any complaint that surfaces.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tenant</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="6">No feedback found for this scope.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $report['id']); ?></td>
                                <td><?php echo htmlspecialchars($report['account_name'] ?? 'Unknown'); ?></td>
                                <td><?php echo htmlspecialchars($report['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($report['status'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(mb_strimwidth($report['description'] ?? '', 0, 80, '…')); ?></td>
                                <td>
                                    <a class="module-link" href="?view=<?php echo $report['id']; ?>&amp;account=<?php echo htmlspecialchars((string) $effectiveAccountId); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($viewedReport): ?>
            <div class="module-card">
                <h2 class="module-card__title">Report #<?php echo htmlspecialchars((string) $viewedReport['id']); ?> details</h2>
                <p class="module-card__subtitle">Rewrite history or simply download the intel you weren’t supposed to see.</p>
                <ul class="module-list">
                    <li class="module-list__item"><strong>Tenant:</strong> <?php echo htmlspecialchars($viewedReport['account_name'] ?? 'Unknown'); ?></li>
                    <li class="module-list__item"><strong>Submitted by:</strong> <?php echo htmlspecialchars($viewedReport['submitted_by_name'] ?? 'Unknown'); ?></li>
                    <li class="module-list__item"><strong>Status:</strong> <?php echo htmlspecialchars($viewedReport['status'] ?? ''); ?></li>
                    <li class="module-list__item"><strong>Category:</strong> <?php echo htmlspecialchars($viewedReport['category'] ?? ''); ?></li>
                    <li class="module-list__item"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($viewedReport['description'] ?? '')); ?></li>
                </ul>
                <form method="POST" class="module-form" style="margin-top: 18px;">
                    <input type="hidden" name="report_id" value="<?php echo htmlspecialchars((string) $viewedReport['id']); ?>">
                    <input type="hidden" name="update_status" value="1">
                    <label class="input-label" for="status-field">Set new status</label>
                    <input class="input-field" id="status-field" type="text" name="new_status" placeholder="e.g. resolved">
                    <button class="btn btn-outline" type="submit">Apply status change</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
