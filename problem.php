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
    echo "Not authenticated";
    exit;
}

try {
    $decoded = JWT::decode($_SESSION['jwt'], new Key($jwt_secret, 'HS256'));
    $role_id = $decoded->role_id;
    $rights = $decoded->rights ?? [];
    $account_id = $decoded->account_id;
    $submitted_by = $decoded->sub;
} catch (Exception $e) {
    echo "Invalid session.";
    exit;
}

if (!in_array('report_problem', $rights)) {
    echo "Access denied.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = $_POST['category'] ?? '';
    $description = $_POST['description'] ?? '';
    $attachment_url = $_POST['attachment_url'] ?? null;

    if ($category && $description) {
        $stmt = $pdo->prepare("INSERT INTO problem_reports (account_id, submitted_by, category, description, attachment_url, status, submitted_at, updated_at) VALUES (?, ?, ?, ?, ?, 'open', NOW(), NOW())");
        $stmt->execute([$account_id, $submitted_by, $category, $description, $attachment_url]);
        $success = "Problem reported successfully.";
    } else {
        $error = "All required fields must be filled.";
    }
}

$stmt = $pdo->prepare("SELECT id, category, description, status, submitted_at FROM problem_reports WHERE submitted_by = ? ORDER BY submitted_at DESC");
$stmt->execute([$submitted_by]);
$reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$viewed_report = null;
$view_notes = [];
$view_missing = false;
if (isset($_GET['view'])) {
    $view_id = (int)$_GET['view'];
    if ($view_id > 0) {
        $viewStmt = $pdo->prepare("SELECT pr.*, u.username FROM problem_reports pr LEFT JOIN users u ON pr.submitted_by = u.id WHERE pr.id = ?");
        $viewStmt->execute([$view_id]);
        $viewed_report = $viewStmt->fetch(PDO::FETCH_ASSOC);
        if ($viewed_report) {
            if ((int)$viewed_report['account_id'] !== (int)$account_id) {
                rat_track_add_score_event('IDOR', 'Peeked at another tenant’s incident report');
            }
            $notesStmt = $pdo->prepare("SELECT pn.note, pn.created_at, au.username FROM problem_notes pn LEFT JOIN users au ON pn.author_id = au.id WHERE pn.problem_id = ? ORDER BY pn.created_at ASC");
            $notesStmt->execute([$view_id]);
            $view_notes = $notesStmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $view_missing = true;
        }
    }
}
?>

$pageTitle = 'Report a Problem • RatPack Park';
$activePage = 'dashboard';
include 'partials/header.php';
?>
<section class="section section--module">
    <div class="section__inner module-shell">
        <div class="hero-card module-hero">
            <span class="hero-badge">Guest &amp; ride ops</span>
            <h1 class="hero-title">Flag incidents before they derail the show</h1>
            <p class="hero-lead">
                Submit detailed reports, attach evidence, and revisit maintenance chatter—including for parks you probably shouldn’t see.
            </p>
        </div>

        <?php if (!empty($success)): ?>
            <div class="module-alert module-alert--success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="module-alert module-alert--error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">Report an incident</h2>
            <p class="module-card__subtitle">Give maintenance the context they need to swoop in fast.</p>
            <form method="POST" class="module-form">
                <select class="input-field" name="category" id="category" required>
                    <option value="">-- Select Category --</option>
                    <option value="Ride Malfunction">Ride Malfunction</option>
                    <option value="Trash Overflow">Trash Overflow</option>
                    <option value="Guest Complaint">Guest Complaint</option>
                    <option value="Safety Concern">Safety Concern</option>
                    <option value="Other">Other</option>
                </select>
                <textarea class="input-field" name="description" id="description" rows="4" placeholder="Describe what happened" required></textarea>
                <input class="input-field" type="url" name="attachment_url" id="attachment_url" placeholder="Attachment URL (optional)">
                <button class="btn btn-primary" type="submit">Submit report</button>
            </form>
        </div>

        <?php if ($viewed_report): ?>
            <div class="module-card">
                <h2 class="module-card__title">Incident intelligence</h2>
                <p class="module-card__subtitle">Viewing ID #<?php echo htmlspecialchars($viewed_report['id']); ?> · Tenant #<?php echo htmlspecialchars((string) $viewed_report['account_id']); ?></p>
                <ul class="module-list">
                    <li class="module-list__item"><strong>Category:</strong> <?php echo htmlspecialchars($viewed_report['category']); ?></li>
                    <li class="module-list__item"><strong>Status:</strong> <?php echo htmlspecialchars($viewed_report['status']); ?></li>
                    <li class="module-list__item"><strong>Submitted by:</strong> <?php echo htmlspecialchars($viewed_report['username'] ?? 'Unknown'); ?></li>
                    <?php if (!empty($viewed_report['attachment_url'])): ?>
                        <li class="module-list__item"><strong>Attachment:</strong> <a class="module-link" href="<?php echo htmlspecialchars($viewed_report['attachment_url']); ?>" target="_blank" rel="noopener noreferrer">Open evidence</a></li>
                    <?php endif; ?>
                </ul>
                <p><?php echo nl2br(htmlspecialchars($viewed_report['description'])); ?></p>
                <?php if (!empty($view_notes)): ?>
                    <h3 class="module-card__title" style="margin-top: 32px; font-size: 1.1rem;">Maintenance notes</h3>
                    <ul class="module-list">
                        <?php foreach ($view_notes as $note): ?>
                            <li class="module-list__item">
                                <strong><?php echo htmlspecialchars($note['username'] ?? 'Unknown'); ?>:</strong>
                                <?php echo htmlspecialchars($note['note']); ?>
                                <em> · <?php echo htmlspecialchars($note['created_at']); ?></em>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="module-alert module-alert--note">No remediation notes recorded for this issue.</div>
                <?php endif; ?>
            </div>
        <?php elseif ($view_missing): ?>
            <div class="module-alert module-alert--error">That incident could not be found.</div>
        <?php endif; ?>

        <div class="module-card">
            <h2 class="module-card__title">My submitted reports</h2>
            <p class="module-card__subtitle">Every problem you’ve escalated, ready for follow-up.</p>
            <table class="module-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                        <tr>
                            <td colspan="4">You have not submitted any reports yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($reports as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['category']); ?></td>
                                <td><?php echo htmlspecialchars($r['description']); ?></td>
                                <td><?php echo htmlspecialchars($r['status']); ?></td>
                                <td><?php echo htmlspecialchars($r['submitted_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php include 'partials/footer.php'; ?>
