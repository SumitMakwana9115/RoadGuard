<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'complainant') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "My History";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get all complaints for user
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name,
           w.name as ward, a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    WHERE c.complainant_id = ?
    ORDER BY c.complaint_date DESC
");
$stmt->execute([$user_id]);
$complaints = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-title">All Registered Complaints</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Location Details</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($complaints)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No complaints found in history.</td></tr>
                <?php else: ?>
                    <?php foreach($complaints as $c): ?>
                        <tr>
                            <td><strong><?= $c['complaint_uid'] ?></strong></td>
                            <td><?= date('d M Y <br> h:i A', strtotime($c['complaint_date'])) ?></td>
                            <td><?= htmlspecialchars($c['category_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($c['ward']) ?><br>
                                <strong><?= htmlspecialchars($c['area']) ?></strong><br>
                                <small><?= htmlspecialchars($c['spot']) ?></small>
                            </td>
                            <td><span class="badge" style="background: <?= $c['priority'] == 'critical' ? 'var(--danger-color)' : ($c['priority'] == 'high' ? 'var(--warning-color)' : 'var(--primary-color)') ?>; color: white;"><?= ucfirst($c['priority']) ?></span></td>
                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $c['status_name'])) ?>"><?= $c['status_name'] ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $c['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--hover-bg); border: 1px solid var(--border-color); color: var(--text-color);">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
