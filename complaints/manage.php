<?php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "Manage All Complaints";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

$where = "1=1";
$params = [];

if ($status_filter) {
    $where .= " AND c.status_id = ?";
    $params[] = $status_filter;
}
if ($priority_filter) {
    $where .= " AND c.priority = ?";
    $params[] = $priority_filter;
}

// Fetch SLA Breached / Escalated complaints for highlighting
// Auto-escalate overdue tasks and record history
$overdue_check = $pdo->query("SELECT id, status_id FROM complaints WHERE status_id IN (3, 4) AND resolution_deadline < NOW()");
$overdue_list = $overdue_check->fetchAll();

if (!empty($overdue_list)) {
    $pdo->beginTransaction();
    try {
        $update_status = $pdo->prepare("UPDATE complaints SET status_id = 8 WHERE id = ?");
        $insert_history = $pdo->prepare("INSERT INTO complaint_history (complaint_id, from_status_id, to_status_id, updated_by, remark) VALUES (?, ?, 8, ?, 'System Auto-Escalation: Resolution SLA breached')");
        
        foreach ($overdue_list as $od) {
            $update_status->execute([$od['id']]);
            $insert_history->execute([$od['id'], $od['status_id'], $_SESSION['user_id']]);
        }
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
    }
}

// Fetch complaints list
$stmt = $pdo->prepare("
    SELECT c.*, cat.name as category_name, s.status_name,
           u.full_name as complainant_name, assignee.full_name as assignee_name,
           a.name as area, sp.name as spot
    FROM complaints c
    JOIN complaint_categories cat ON c.category_id = cat.id
    JOIN status_master s ON c.status_id = s.id
    JOIN users u ON c.complainant_id = u.id
    JOIN area_master a ON c.area_id = a.id
    JOIN spot_master sp ON c.spot_id = sp.id
    LEFT JOIN users assignee ON c.assigned_to = assignee.id
    WHERE $where
    ORDER BY c.complaint_date DESC
");
$stmt->execute($params);
$complaints = $stmt->fetchAll();

// Get statuses for filter
$statuses = $pdo->query("SELECT id, status_name FROM status_master ORDER BY sort_order")->fetchAll();
?>

<div class="card" style="margin-bottom: 20px;">
    <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Filter by Status</label>
            <select name="status" class="form-control">
                <option value="">All Statuses</option>
                <?php foreach($statuses as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $status_filter == $s['id'] ? 'selected' : '' ?>><?= $s['status_name'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
            <label class="form-label">Filter by Priority</label>
            <select name="priority" class="form-control">
                <option value="">All Priorities</option>
                <option value="critical" <?= $priority_filter == 'critical' ? 'selected' : '' ?>>Critical</option>
                <option value="high" <?= $priority_filter == 'high' ? 'selected' : '' ?>>High</option>
                <option value="medium" <?= $priority_filter == 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="low" <?= $priority_filter == 'low' ? 'selected' : '' ?>>Low</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding: 10px 20px;">Apply Filter</button>
        <a href="manage.php" class="btn" style="padding: 10px 20px; background: transparent; border: 1px solid var(--border-color); color: var(--text-color);">Reset</a>
    </form>
</div>

<div class="card">
    <div class="card-title">All Complaints Directory</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Date</th>
                    <th>Category & Location</th>
                    <th>Assigned To</th>
                    <th>Deadline</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($complaints)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">No complaints found matching criteria.</td></tr>
                <?php else: ?>
                    <?php foreach($complaints as $c): ?>
                        <tr style="<?= $c['status_id'] == 8 ? 'background: rgba(247, 37, 133, 0.05);' : '' ?>">
                            <td>
                                <strong><?= $c['complaint_uid'] ?></strong>
                                <?php if($c['is_repeated']): ?>
                                    <br><span title="Repeated Issue" style="color: var(--danger-color);"><i class='bx bx-flag'></i> Repeated</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d M Y', strtotime($c['complaint_date'])) ?></td>
                            <td>
                                <?= htmlspecialchars($c['category_name']) ?><br>
                                <small style="color: var(--text-muted);"><?= htmlspecialchars($c['area']) ?> - <?= htmlspecialchars($c['spot']) ?></small>
                            </td>
                            <td>
                                <?= $c['assignee_name'] ? htmlspecialchars($c['assignee_name']) : '<span style="color:var(--text-muted)">Unassigned</span>' ?>
                            </td>
                            <td>
                                <?php if (in_array($c['status_id'], [1, 2, 3, 4, 7, 8])): ?>
                                    <?php 
                                        $deadline = strtotime($c['resolution_deadline']);
                                        if ($deadline > 0):
                                            $is_overdue = time() > $deadline;
                                    ?>
                                        <span style="color: <?= $is_overdue ? 'var(--danger-color)' : 'inherit' ?>">
                                            <?= date('d M, H:i', $deadline) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted)">Not Set</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:var(--text-muted)">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= strtolower(str_replace(' ', '', $c['status_name'])) ?>"><?= $c['status_name'] ?></span>
                            </td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $c['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--hover-bg); border: 1px solid var(--border-color); color: var(--text-color);">Manage</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
