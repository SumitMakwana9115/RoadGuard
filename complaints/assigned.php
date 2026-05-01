<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "My Assigned Tasks";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$staff_id = $_SESSION['user_id'];

// Get assigned complaints
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name,
           w.name as ward, a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN ward_master w ON c.ward_id = w.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    WHERE c.assigned_to = ? AND c.status_id IN (3, 4, 5, 7, 8)
    ORDER BY CASE WHEN c.status_id IN (3, 4, 7, 8) THEN 0 ELSE 1 END, c.resolution_deadline ASC
");
$stmt->execute([$staff_id]);
$tasks = $stmt->fetchAll();
?>

<div class="card">
    <div class="card-title">My Tasks List</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Priority</th>
                    <th>SLA Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($tasks)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 20px;">No tasks assigned currently.</td></tr>
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
                                    if ($t['status_id'] == 5) {
                                        echo "<span style='color: var(--success-color)'>Resolved</span>";
                                    } else {
                                        $deadline = strtotime($t['resolution_deadline']);
                                        $is_overdue = time() > $deadline;
                                        echo "<span style='color: " . ($is_overdue ? 'var(--danger-color)' : 'inherit') . "'>" . date('d M Y, H:i', $deadline) . "</span>";
                                    }
                                ?>
                            </td>
                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $t['status_name'])) ?>"><?= $t['status_name'] ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/task.php?id=<?= $t['id'] ?>" class="btn <?= in_array($t['status_id'], [3,4]) ? 'btn-primary' : '' ?>" style="padding: 6px 12px; font-size: 13px; <?= $t['status_id']==5 ? 'background: var(--hover-bg); border: 1px solid var(--border-color); color: var(--text-color);' : '' ?>">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
