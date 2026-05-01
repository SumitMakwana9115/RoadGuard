<?php
require_once __DIR__ . '/../config/config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Staff Dashboard";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$staff_id = $_SESSION['user_id'];

// Get stats
$stats = [
    'assigned' => $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status_id = 3"),
    'in_progress' => $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status_id = 4"),
    'overdue' => $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE assigned_to = ? AND status_id IN (3,4) AND resolution_deadline < NOW()"),
];

$stat_values = [];
foreach($stats as $key => $stmt) {
    $stmt->execute([$staff_id]);
    $stat_values[$key] = $stmt->fetchColumn();
}

// Get assigned active tasks
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name,
           w.name as ward, a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    WHERE c.assigned_to = ? AND c.status_id IN (3, 4, 7, 8)
    ORDER BY c.resolution_deadline ASC
    LIMIT 10
");
$stmt->execute([$staff_id]);
$tasks = $stmt->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class='bx bx-task'></i></div>
        <div class="stat-details">
            <h3><?= $stat_values['assigned'] ?></h3>
            <p>New Assigned</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-orange"><i class='bx bx-loader'></i></div>
        <div class="stat-details">
            <h3><?= $stat_values['in_progress'] ?></h3>
            <p>In Progress</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-red"><i class='bx bx-time'></i></div>
        <div class="stat-details">
            <h3><?= $stat_values['overdue'] ?></h3>
            <p>SLA Overdue</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">My Active Tasks (SLA: <?= RESOLUTION_SLA_HOURS ?>h)</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Priority</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tasks)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No active tasks assigned.</td></tr>
                <?php else: ?>
                    <?php foreach($tasks as $t): ?>
                        <tr>
                            <td><strong><?= $t['complaint_uid'] ?></strong></td>
                            <td><?= htmlspecialchars($t['category_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($t['area']) ?></strong><br>
                                <small><?= htmlspecialchars($t['spot']) ?></small>
                            </td>
                            <td><span class="badge" style="background: <?= $t['priority'] == 'critical' ? 'var(--danger-color)' : ($t['priority'] == 'high' ? 'var(--warning-color)' : 'var(--primary-color)') ?>; color: white;"><?= ucfirst($t['priority']) ?></span></td>
                            <td>
                                <?php 
                                    $deadline = strtotime($t['resolution_deadline']);
                                    $is_overdue = time() > $deadline;
                                ?>
                                <span style="color: <?= $is_overdue ? 'var(--danger-color)' : 'inherit' ?>">
                                    <?= date('d M H:i', $deadline) ?>
                                    <?php if($is_overdue) echo " (Overdue)"; ?>
                                </span>
                            </td>
                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $t['status_name'])) ?>"><?= $t['status_name'] ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/task.php?id=<?= $t['id'] ?>" class="btn btn-primary" style="padding: 6px 12px; font-size: 13px;">View Task</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
