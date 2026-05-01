<?php
require_once __DIR__ . '/../config/config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Supervisor Dashboard";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

// Get stats
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) FROM complaints")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id IN (1, 2, 7)")->fetchColumn(), // Submitted, Verified, Reopened
    'in_progress' => $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id IN (3, 4)")->fetchColumn(), // Assigned, In Progress
    'escalated' => $pdo->query("SELECT COUNT(*) FROM complaints WHERE status_id = 8")->fetchColumn(),
];

// Get recent complaints requiring verification
$stmt = $pdo->query("
    SELECT c.*, cat.name as category_name, s.status_name, s.color, u.full_name as complainant_name,
           w.name as ward, a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN users u ON c.complainant_id = u.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    WHERE c.status_id = 1 OR c.status_id = 7
    ORDER BY c.priority DESC, c.complaint_date ASC
    LIMIT 5
");
$recent = $stmt->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class='bx bx-list-ul'></i></div>
        <div class="stat-details">
            <h3><?= $stats['total'] ?></h3>
            <p>Total Complaints</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-orange"><i class='bx bx-time-five'></i></div>
        <div class="stat-details">
            <h3><?= $stats['pending'] ?></h3>
            <p>Pending Action</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-cyan"><i class='bx bx-loader'></i></div>
        <div class="stat-details">
            <h3><?= $stats['in_progress'] ?></h3>
            <p>In Progress</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-red"><i class='bx bx-error'></i></div>
        <div class="stat-details">
            <h3><?= $stats['escalated'] ?></h3>
            <p>Escalated (SLA Breach)</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">Action Required: Verification / Assignment</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($recent)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No complaints require immediate action.</td></tr>
                <?php else: ?>
                    <?php foreach($recent as $c): ?>
                        <tr>
                            <td><strong><?= $c['complaint_uid'] ?></strong></td>
                            <td><?= date('d M Y, H:i', strtotime($c['complaint_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($c['category_name']) ?>
                                <?php if($c['is_repeated']): ?>
                                    <div class="repeated-flag"><i class='bx bx-flag'></i> Repeated</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($c['ward']) ?> > 
                                <strong><?= htmlspecialchars($c['area']) ?></strong> > 
                                <?= htmlspecialchars($c['spot']) ?>
                            </td>
                            <td><span class="badge" style="background: <?= $c['priority'] == 'critical' ? 'var(--danger-color)' : ($c['priority'] == 'high' ? 'var(--warning-color)' : 'var(--primary-color)') ?>; color: white;"><?= ucfirst($c['priority']) ?></span></td>
                            <td>
                                <?php 
                                    $deadline = strtotime($c['resolution_deadline']);
                                    if ($deadline > 0):
                                        $is_overdue = time() > $deadline;
                                ?>
                                    <span style="color: <?= $is_overdue ? 'var(--danger-color)' : 'inherit' ?>">
                                        <?= date('d M, H:i', $deadline) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $c['status_name'])) ?>"><?= $c['status_name'] ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $c['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
