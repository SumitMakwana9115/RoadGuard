<?php
require_once __DIR__ . '/../config/config.php';

// Check role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'complainant') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$page_title = "My Dashboard";
require_once __DIR__ . '/../includes/header.php';

$pdo = getDBConnection();
$user_id = $_SESSION['user_id'];

// Get stats
$stats = [
    'total' => $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE complainant_id = ?"),
    'active' => $pdo->prepare("SELECT COUNT(*) FROM complaints WHERE complainant_id = ? AND status_id NOT IN (5, 6, 9)"),
];

$stat_values = [];
foreach($stats as $key => $stmt) {
    $stmt->execute([$user_id]);
    $stat_values[$key] = $stmt->fetchColumn();
}

// Get recent complaints
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
    LIMIT 5
");
$stmt->execute([$user_id]);
$recent = $stmt->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon icon-blue"><i class='bx bx-list-ul'></i></div>
        <div class="stat-details">
            <h3><?= $stat_values['total'] ?></h3>
            <p>Total Registered</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon icon-orange"><i class='bx bx-loader-circle'></i></div>
        <div class="stat-details">
            <h3><?= $stat_values['active'] ?></h3>
            <p>Active</p>
        </div>
    </div>
    <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='<?= BASE_URL ?>/complaints/register.php'">
        <div class="stat-icon icon-cyan" style="background: var(--primary-color); color: white;"><i class='bx bx-plus'></i></div>
        <div class="stat-details">
            <h3 style="font-size: 18px; margin-top: 5px;">Register</h3>
            <p>New Complaint</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-title">My Recent Complaints</div>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>UID</th>
                    <th>Date</th>
                    <th>Category</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($recent)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 20px;">You haven't registered any complaints yet.</td></tr>
                <?php else: ?>
                    <?php foreach($recent as $c): ?>
                        <tr>
                            <td><strong><?= $c['complaint_uid'] ?></strong></td>
                            <td><?= date('d M Y', strtotime($c['complaint_date'])) ?></td>
                            <td><?= htmlspecialchars($c['category_name']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($c['area']) ?></strong><br>
                                <small><?= htmlspecialchars($c['spot']) ?></small>
                            </td>
                            <td><span class="badge badge-<?= strtolower(str_replace(' ', '', $c['status_name'])) ?>"><?= $c['status_name'] ?></span></td>
                            <td>
                                <a href="<?= BASE_URL ?>/complaints/view.php?id=<?= $c['id'] ?>" class="btn" style="padding: 6px 12px; font-size: 13px; background: var(--hover-bg); border: 1px solid var(--border-color); color: var(--text-color);">Track</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
